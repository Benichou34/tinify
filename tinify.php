<?php
/*
* The MIT License (MIT)
*
* Copyright (c) 2015 Benichou
*
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
*
* The above copyright notice and this permission notice shall be included in all
* copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
* SOFTWARE.
*
*  @author    Benichou <benichou.software@gmail.com>
*  @copyright 2015 Benichou
*  @license   http://opensource.org/licenses/MIT  The MIT License (MIT)
*/

if (!defined('_PS_VERSION_'))
	exit;

require_once(__DIR__.'/vendor/autoload.php');
require_once(__DIR__.'/classes/filecache.php');
require_once(__DIR__.'/classes/dirlist.php');
require_once(__DIR__.'/classes/report.php');

class Tinify extends Module
{
	public function __construct()
	{
		$this->bootstrap = true;
		$this->name = 'tinify';
		$this->tab = 'administration';
		$this->author = 'Benichou';
		$this->version = '1.1';

		parent::__construct();
		$this->displayName = $this->l('TinyPNG image compression');
		$this->description = $this->l('Make your website faster by compressing your JPEG and PNG images');
		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);

		if (Configuration::get('TINIFY_LOGS'))
		{
			$this->logger = new FileLogger();
			$this->logger->setFilename(_PS_ROOT_DIR_.'/log/'.$this->name.'.log');
		}
	}

	public function install()
	{
		if (!parent::install() || !FileCache::createDb() || !DirList::createDb())
			return false;

		return true;
	}

	public function uninstall()
	{
		Configuration::deleteByName('TINIFY_API_KEY');
		Configuration::deleteByName('TINIFY_REPORT');
		Configuration::deleteByName('TINIFY_LOGS');

		if (!FileCache::deleteDb() || !DirList::deleteDb())
			return false;

		return parent::uninstall();
	}

	public function getContent()
	{
		if (Tools::isSubmit('addtinify'))
			return $this->renderFormAdd();
		else if (Tools::isSubmit('updatetinify'))
			return $this->renderFormAdd(new DirList(Tools::getValue('id')));

		// If form has been sent
		$output = '';

		if (Tools::isSubmit('submitAddtinify'))
		{
			$dir = new DirList(Tools::getValue('id'));
			$dir->path = Tools::getValue('TINIFY_DIR_PATH');
			$dir->desc = Tools::getValue('TINIFY_DIR_DESC');
			$dir->recursive = Tools::getValue('TINIFY_DIR_RECURSIVE');
			$dir->enabled = Tools::getValue('TINIFY_DIR_ENABLED');
			if (!$dir->save())
				$output .= $this->displayError($this->l('Save of directory failed.'));
		}

		if (Tools::isSubmit('recursivetinify'))
		{
			$dir = new DirList(Tools::getValue('id'));
			if(!$dir)
				$output .= $this->displayError($this->l('Target directory not exist.'));

			$dir->recursive = $dir->recursive? false: true;
			if (!$dir->save())
				$output .= $this->displayError($this->l('Save of directory failed.'));
		}

		if (Tools::isSubmit('enabledtinify'))
		{
			$dir = new DirList(Tools::getValue('id'));
			if(!$dir)
				$output .= $this->displayError($this->l('Target directory not exist.'));

			$dir->enabled = $dir->enabled? false: true;
			if (!$dir->save())
				$output .= $this->displayError($this->l('Save of directory failed.'));
		}

		if (Tools::isSubmit('deletetinify'))
		{
			$dir = new DirList(Tools::getValue('id'));
			if (!$dir->delete())
				$output .= $this->displayError($this->l('Delete of directory failed.'));
		}

		if (Tools::isSubmit('submit'.$this->name))
		{
			Configuration::updateValue('TINIFY_API_KEY', Tools::getValue('TINIFY_API_KEY'));
			Configuration::updateValue('TINIFY_LOGS', Tools::getValue('TINIFY_LOGS'));

			try // Just validate the API key
			{
				\Tinify\setKey(Configuration::get('TINIFY_API_KEY'));
				\Tinify\validate();

				$output .= $this->displayConfirmation($this->l('Settings updated successfully'));
			}
			catch(\Tinify\Exception $e)
			{
				$output .= $this->displayError($this->l('Validation of API key failed'));
			}
		}

		if (Tools::isSubmit('submitRunNow'))
			$output .= $this->apply();

		$output .= $this->renderForm();
		$output .= $this->rendertList();
		$output .= $this->renderFormReport();

		return $output;
	}

	private function rendertList()
	{
		$this->fields_list = array(
			'id' => array(
				'title' => $this->l('ID'),
				'width' => 60
			),
			'path' => array(
				'title' => $this->l('Path'),
				'width' => 'auto',
				'type' => 'text'
			),
			'desc' => array(
				'title' => $this->l('Description'),
				'width' => 'auto',
				'type' => 'text'
			),
			'recursive' => array(
				'title' => $this->l('Recursive'),
				'active' => 'recursive',
				'type' => 'bool',
				'align' => 'center'
			),
			'enabled' => array(
				'title' => $this->l('Enabled'),
				'active' => 'enabled',
				'type' => 'bool',
				'align' => 'center'
			)
		);

		$helper = new HelperList();
		$helper->shopLinkType = '';
		$helper->simple_header = false;

		// Actions to be displayed in the "Actions" column
		$helper->actions = array('edit', 'delete');

		$helper->identifier = 'id';
		$helper->title = $this->l('Directories list');
		$helper->table = $this->name;
		$helper->show_toolbar = true;
		$helper->toolbar_btn['new'] = array(
			'href' => AdminController::$currentIndex.'&configure='.$this->name.'&add'.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'),
			'desc' => $this->l('Add new')
		);

		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');

		$listContent = DirList::getContent();

		$helper->listTotal = count($listContent);
		return $helper->generateList($listContent, $this->fields_list);
	}

	private function renderForm()
	{
		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;

		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');

		// Title and toolbar
		$helper->title = $this->displayName;
		$helper->submit_action = 'submit'.$this->name;

		$fields_forms = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('General settings'),
					'icon' => 'icon-cogs'
				),
				'input' => array(
					array(
						'type' => 'text',
						'label' => $this->l('API key'),
						'name' => 'TINIFY_API_KEY',
						'size' => 40,
						'required' => false,
						'hint' => $this->l('You can get an API key by registering in tinypng.com/developers.')
					),
					array(
						'type' => 'switch',
						'label' => $this->l('Write logs'),
						'name' => 'TINIFY_LOGS',
						'desc' => $this->l('Log to ')._PS_ROOT_DIR_.'/log/'.$this->name.'.log',
						'required' => false,
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'TINIFY_LOGS_on',
								'value' => 1,
								'label' => $this->l('Enabled')
							),
							array(
								'id' => 'TINIFY_LOGS_off',
								'value' => 0,
								'label' => $this->l('Disabled')
							)
						)
					)
				),
				'submit' => array(
					'title' => $this->l('Save'),
					'class' => 'btn btn-default pull-right fixed-width-sm'
				)
			)
		);

		// Load current value
		$helper->fields_value['TINIFY_API_KEY'] = Configuration::get('TINIFY_API_KEY');
		$helper->fields_value['TINIFY_LOGS'] = Configuration::get('TINIFY_LOGS');

		return $helper->generateForm(array($fields_forms));
	}

	private function renderFormReport()
	{
		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;

		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');

		// Title and toolbar
		$helper->title = $this->displayName;

		$this->context->smarty->assign(array(
			'tinify_report' => json_decode(Configuration::get('TINIFY_REPORT'))
		));

		$fields_refresh = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Automatic tinify'),
					'icon' => 'icon-refresh'
				),
				'input' => array(
					array(
						'type' => 'html',
						'html_content' => $this->l('For automatically compress your new images, create a "Cron task" to load the following URL at the time you would like: ').'<br/>'._PS_BASE_URL_SSL_._MODULE_DIR_.$this->name.'/cron.php'
					)
				),
				'submit' => array(
					'title' => $this->l('Run now'),
					'name' => 'submitRunNow',
					'icon' => 'process-icon-refresh',
					'class' => 'btn btn-default pull-right fixed-width-sm'
				)
			)
		);

		$fields_clear = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Last tinify report'),
					'icon' => 'icon-flag'
				),
				'input' => array(
					array(
						'type' => 'html',
						'html_content' => $this->context->smarty->fetch($this->local_path.'views/templates/admin/report.tpl')
					)
				)
			)
		);

		return $helper->generateForm(array($fields_refresh, $fields_clear));
	}

	private function renderFormAdd($dirInfo = null)
	{
		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;

		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');

		if($dirInfo && isset($dirInfo->id))
		{
			$title = $this->l('Edit directory');
			$helper->currentIndex .= '&id='.$dirInfo->id;
		}
		else
		{
			$title = $this->l('Add directory');
		}

		// Title and toolbar
		$helper->title = $this->displayName;
		$helper->submit_action = 'submitAdd'.$this->name;

		$back = Tools::safeOutput(Tools::getValue('back', ''));
		if (!isset($back) || empty($back))
			$back = AdminController::$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules');

		$fields_forms = array(
			'form' => array(
				'legend' => array(
					'title' => $title,
					'icon' => 'icon-folder-open-o'
				),
				'input' => array(
					array(
						'type' => 'text',
						'label' => $this->l('Path'),
						'name' => 'TINIFY_DIR_PATH',
						'size' => 256,
						'required' => true,
						'hint' => $this->l('Target path.')
					),
					array(
						'type' => 'text',
						'label' => $this->l('Description'),
						'name' => 'TINIFY_DIR_DESC',
						'size' => 50,
						'required' => false,
						'hint' => $this->l('Description.')
					),
					array(
						'type' => 'switch',
						'label' => $this->l('Recursive'),
						'name' => 'TINIFY_DIR_RECURSIVE',
						'required' => false,
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'TINIFY_DIR_RECURSIVE_on',
								'value' => 1,
								'label' => $this->l('Yes')
							),
							array(
								'id' => 'TINIFY_DIR_RECURSIVE_off',
								'value' => 0,
								'label' => $this->l('No')
							)
						)
					),
					array(
						'type' => 'switch',
						'label' => $this->l('Enabled'),
						'name' => 'TINIFY_DIR_ENABLED',
						'required' => false,
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'TINIFY_DIR_ENABLED_on',
								'value' => 1,
								'label' => $this->l('Enabled')
							),
							array(
								'id' => 'TINIFY_DIR_ENABLED_off',
								'value' => 0,
								'label' => $this->l('Disabled')
							)
						)
					)
				),
				'buttons' => array(
					'cancelBlock' => array(
						'title' => $this->l('Cancel'),
						'href' => $back,
						'icon' => 'process-icon-cancel'
					)
				),
				'submit' => array(
					'title' => $this->l('Save'),
					'class' => 'btn btn-default pull-right fixed-width-sm'
				)
			)
		);

		// Load current value
		if ($dirInfo)
		{
			$helper->fields_value['TINIFY_DIR_PATH'] = $dirInfo->path;
			$helper->fields_value['TINIFY_DIR_DESC'] = $dirInfo->desc;
			$helper->fields_value['TINIFY_DIR_RECURSIVE'] = $dirInfo->recursive;
			$helper->fields_value['TINIFY_DIR_ENABLED'] = $dirInfo->enabled;
		}

		return $helper->generateForm(array($fields_forms));
	}

	private function logError($msg, &$report = null)
	{
		if (isset($this->logger))
			$this->logger->logError($msg);

		if ($report)
			$report->error[] = $msg;

		return  $this->displayError($msg);
	}

	private function logWarning($msg, &$report = null)
	{
		if (isset($this->logger))
			$this->logger->logWarning($msg);

		if ($report)
			$report->warning[] = $msg;
	}

	private function tinifyDirectory($dir, $recursive, &$report)
	{
		if (!file_exists($dir))
		{
			$this->logWarning($dir.' does not exist', $report);
			return;
		}

		if ($recursive)
			$Files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
		else
			$Files = new FilesystemIterator($dir);

		$extensions = array("png", "jpg", "jpeg");
		$pictures = array();

		foreach ($Files as $fileinfo)
		{
			if ($fileinfo->isFile() && in_array(strtolower($fileinfo->getExtension()), $extensions))
				$pictures[] = $fileinfo;
		}

		$dirFileCounter = 0;
		$dirGainSize = 0;

		// Sort by size
		usort($pictures, function($a, $b)
		{
			if ($a->getSize() == $b->getSize())
				return 0;

			return ($a->getSize() > $b->getSize()) ? -1 : 1;
		});

		foreach ($pictures as $fileinfo)
		{
			$object = new FileCache($fileinfo);
			if ($object->needTinify())
			{
				set_time_limit(10);

				$filename = $object->getPathname();
				$fileSize = $object->getSize();

				$object->tinify();
				$newFileSize = $object->getSize();

				$dirFileCounter++;
				$dirGainSize += $fileSize - $newFileSize;

				$report->fileCounter++;
				$report->gainSize += $fileSize - $newFileSize;
				$report->lastUpdate = time();
				$report->compressionCount = \Tinify\getCompressionCount();

				if (isset($this->logger))
					$this->logger->logInfo('['.$filename.'] '.$fileSize.'=>'.$newFileSize.' ('.(100 - (int)($newFileSize * 100 / $fileSize)).'%)');
			}
		}

		if (isset($this->logger))
			$this->logger->logInfo('['.$dir.']: '.$dirFileCounter.' files(s), gain: '.(int)($dirGainSize / 1024).'Ko');
	}

	public function apply()
	{
		try
		{
			\Tinify\setKey(Configuration::get('TINIFY_API_KEY'));
			\Tinify\validate();
		}
		catch(\Tinify\Exception $e)
		{
			return $this->logError('Validation of API key failed');
		}

		$report = new TinifyReport();
		$output = "";

		// Tinify
		try
		{
			foreach (DirList::getDirList() as $dir)
			{
				if ($dir->enabled)
					$this->tinifyDirectory($dir->path, $dir->recursive, $report);
			}

			if (!$report->fileCounter)
			{
				$report->lastUpdate = time();
				$report->compressionCount = \Tinify\getCompressionCount();
			}

			$output .= $this->displayConfirmation($this->l('Tinify finished successfully.'));
		}
		catch(\Tinify\AccountException $e)
		{
			$output .= $this->logError('Verify your account limit: '.$e->getMessage(), $report);
		}
		catch(\Tinify\ClientException $e)
		{
			$output .= $this->logError('Check your source image and request options: '.$e->getMessage(), $report);
		}
		catch(\Tinify\ServerException $e)
		{
			$output .= $this->logError('Temporary issue with the Tinify API: '.$e->getMessage(), $report);
		}
		catch(\Tinify\ConnectionException $e)
		{
			$output .= $this->logError('A network connection error occurred: '.$e->getMessage(), $report);
		}
		catch(Exception $e)
		{
			$output .= $this->logError('Unrelated exception: '.$e->getMessage(), $report);
		}

		Configuration::updateValue('TINIFY_REPORT', json_encode($report));

		if (isset($this->logger))
			$this->logger->logInfo('Report '.Configuration::get('TINIFY_REPORT'));

		return $output;
	}
}

