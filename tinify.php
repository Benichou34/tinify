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
require_once(__DIR__.'/classes/report.php');

class Tinify extends Module
{
	public function __construct()
	{
		$this->bootstrap = true;
		$this->name = 'tinify';
		$this->tab = 'administration';
		$this->author = 'Benichou';
		$this->version = '1.0';

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
		if (!parent::install() || !FileCache::createDb())
			return false;

		return true;
	}

	public function uninstall()
	{
		Configuration::deleteByName('TINIFY_API_KEY');
		Configuration::deleteByName('TINIFY_REPORT');
		Configuration::deleteByName('TINIFY_LOGS');
		Configuration::deleteByName('TINIFY_PRODUCTS');
		Configuration::deleteByName('TINIFY_CATEGORIES');
		Configuration::deleteByName('TINIFY_MANUFACTURERS');
		Configuration::deleteByName('TINIFY_CARRIERS');
		Configuration::deleteByName('TINIFY_SUPPLIERS');
		Configuration::deleteByName('TINIFY_SCENES');
		Configuration::deleteByName('TINIFY_STORES');
		Configuration::deleteByName('TINIFY_COLORS');
		Configuration::deleteByName('TINIFY_LANGUAGES');
		Configuration::deleteByName('TINIFY_EMPLOYEES');
		Configuration::deleteByName('TINIFY_MODULES');
		Configuration::deleteByName('TINIFY_THEME');
		Configuration::deleteByName('TINIFY_THEME_MODULES');
		Configuration::deleteByName('TINIFY_IMAGES');

		if (!FileCache::deleteDb())
			return false;

		return parent::uninstall();
	}

	public function getContent()
	{
		// If form has been sent
		$output = '';

		if (Tools::isSubmit('submit'.$this->name))
		{
			Configuration::updateValue('TINIFY_API_KEY', Tools::getValue('TINIFY_API_KEY'));
			Configuration::updateValue('TINIFY_LOGS', Tools::getValue('TINIFY_LOGS_on'));
			Configuration::updateValue('TINIFY_PRODUCTS', Tools::getValue('TINIFY_PRODUCTS_on'));
			Configuration::updateValue('TINIFY_CATEGORIES', Tools::getValue('TINIFY_CATEGORIES_on'));
			Configuration::updateValue('TINIFY_MANUFACTURERS', Tools::getValue('TINIFY_MANUFACTURERS_on'));
			Configuration::updateValue('TINIFY_CARRIERS', Tools::getValue('TINIFY_CARRIERS_on'));
			Configuration::updateValue('TINIFY_SUPPLIERS', Tools::getValue('TINIFY_SUPPLIERS_on'));
			Configuration::updateValue('TINIFY_SCENES', Tools::getValue('TINIFY_SCENES_on'));
			Configuration::updateValue('TINIFY_STORES', Tools::getValue('TINIFY_STORES_on'));
			Configuration::updateValue('TINIFY_COLORS', Tools::getValue('TINIFY_COLORS_on'));
			Configuration::updateValue('TINIFY_LANGUAGES', Tools::getValue('TINIFY_LANGUAGES_on'));
			Configuration::updateValue('TINIFY_EMPLOYEES', Tools::getValue('TINIFY_EMPLOYEES_on'));
			Configuration::updateValue('TINIFY_MODULES', Tools::getValue('TINIFY_MODULES_on'));
			Configuration::updateValue('TINIFY_THEME', Tools::getValue('TINIFY_THEME_on'));
			Configuration::updateValue('TINIFY_THEME_MODULES', Tools::getValue('TINIFY_THEME_MODULES_on'));
			Configuration::updateValue('TINIFY_IMAGES', Tools::getValue('TINIFY_IMAGES_on'));

			if (Tools::isSubmit('submitRunNow'))
			{
				$output .= $this->apply();
			}
			else
			{
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
		}

		$output .= $this->renderForm();
		return $output;
	}

	public function renderForm()
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

		$this->context->smarty->assign(array(
			'tinify_report' => json_decode(Configuration::get('TINIFY_REPORT'))
		));

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
						'type' => 'checkbox',
						'name' => 'TINIFY_PRODUCTS',
						'desc' => _PS_PROD_IMG_DIR_,
						'values' => array(
							'query' => array(
								array(
									'id' => 'on',
									'name' => $this->l('Tinify products images'),
									'val' => '1'
								),
							),
							'id' => 'id',
							'name' => 'name'
						)
					),
					array(
						'type' => 'checkbox',
						'name' => 'TINIFY_CATEGORIES',
						'desc' => _PS_CAT_IMG_DIR_,
						'values' => array(
							'query' => array(
								array(
									'id' => 'on',
									'name' => $this->l('Tinify categories images'),
									'val' => '1'
								),
							),
							'id' => 'id',
							'name' => 'name'
						)
					),
					array(
						'type' => 'checkbox',
						'name' => 'TINIFY_MANUFACTURERS',
						'desc' => _PS_MANU_IMG_DIR_,
						'values' => array(
							'query' => array(
								array(
									'id' => 'on',
									'name' => $this->l('Tinify manufacturers images'),
									'val' => '1'
								),
							),
							'id' => 'id',
							'name' => 'name'
						)
					),
					array(
						'type' => 'checkbox',
						'name' => 'TINIFY_CARRIERS',
						'desc' => _PS_SHIP_IMG_DIR_,
						'values' => array(
							'query' => array(
								array(
									'id' => 'on',
									'name' => $this->l('Tinify carriers (shipping) images'),
									'val' => '1'
								),
							),
							'id' => 'id',
							'name' => 'name'
						)
					),
					array(
						'type' => 'checkbox',
						'name' => 'TINIFY_SUPPLIERS',
						'desc' => _PS_SUPP_IMG_DIR_,
						'values' => array(
							'query' => array(
								array(
									'id' => 'on',
									'name' => $this->l('Tinify suppliers images'),
									'val' => '1'
								),
							),
							'id' => 'id',
							'name' => 'name'
						)
					),
					array(
						'type' => 'checkbox',
						'name' => 'TINIFY_SCENES',
						'desc' => _PS_SCENE_IMG_DIR_,
						'values' => array(
							'query' => array(
								array(
									'id' => 'on',
									'name' => $this->l('Tinify scenes images'),
									'val' => '1'
								),
							),
							'id' => 'id',
							'name' => 'name'
						)
					),
					array(
						'type' => 'checkbox',
						'name' => 'TINIFY_STORES',
						'desc' => _PS_STORE_IMG_DIR_,
						'values' => array(
							'query' => array(
								array(
									'id' => 'on',
									'name' => $this->l('Tinify stores images'),
									'val' => '1'
								),
							),
							'id' => 'id',
							'name' => 'name'
						)
					),
					array(
						'type' => 'checkbox',
						'name' => 'TINIFY_COLORS',
						'desc' => _PS_COL_IMG_DIR_,
						'values' => array(
							'query' => array(
								array(
									'id' => 'on',
									'name' => $this->l('Tinify colors images'),
									'val' => '1'
								),
							),
							'id' => 'id',
							'name' => 'name'
						)
					),
					array(
						'type' => 'checkbox',
						'name' => 'TINIFY_LANGUAGES',
						'desc' => _PS_LANG_IMG_DIR_,
						'values' => array(
							'query' => array(
								array(
									'id' => 'on',
									'name' => $this->l('Tinify languages images'),
									'val' => '1'
								),
							),
							'id' => 'id',
							'name' => 'name'
						)
					),
					array(
						'type' => 'checkbox',
						'name' => 'TINIFY_EMPLOYEES',
						'desc' => _PS_EMPLOYEE_IMG_DIR_,
						'values' => array(
							'query' => array(
								array(
									'id' => 'on',
									'name' => $this->l('Tinify employees images'),
									'val' => '1'
								),
							),
							'id' => 'id',
							'name' => 'name'
						)
					),
					array(
						'type' => 'checkbox',
						'name' => 'TINIFY_MODULES',
						'desc' => _PS_MODULE_DIR_,
						'values' => array(
							'query' => array(
								array(
									'id' => 'on',
									'name' => $this->l('Tinify modules images'),
									'val' => '1'
								),
							),
							'id' => 'id',
							'name' => 'name'
						)
					),
					array(
						'type' => 'checkbox',
						'name' => 'TINIFY_THEME',
						'desc' => _PS_ALL_THEMES_DIR_._THEME_NAME_.'/img/, /mobile/img',
						'values' => array(
							'query' => array(
								array(
									'id' => 'on',
									'name' => $this->l('Tinify theme specifics images'),
									'val' => '1'
								),
							),
							'id' => 'id',
							'name' => 'name'
						)
					),
					array(
						'type' => 'checkbox',
						'name' => 'TINIFY_THEME_MODULES',
						'desc' => _PS_ALL_THEMES_DIR_._THEME_NAME_.'/modules/',
						'values' => array(
							'query' => array(
								array(
									'id' => 'on',
									'name' => $this->l('Tinify theme modules images'),
									'val' => '1'
								),
							),
							'id' => 'id',
							'name' => 'name'
						)
					),
					array(
						'type' => 'checkbox',
						'name' => 'TINIFY_IMAGES',
						'desc' => _PS_CORE_IMG_DIR_,
						'values' => array(
							'query' => array(
								array(
									'id' => 'on',
									'name' => $this->l('Tinify Prestashop core images'),
									'val' => '1'
								),
							),
							'id' => 'id',
							'name' => 'name'
						)
					),
					array(
						'type' => 'checkbox',
						'name' => 'TINIFY_LOGS',
						'desc' => $this->l('Log to ')._PS_ROOT_DIR_.'/log/'.$this->name.'.log',
						'values' => array(
							'query' => array(
								array(
									'id' => 'on',
									'name' => $this->l('Write logs.'),
									'val' => '1'
								),
							),
							'id' => 'id',
							'name' => 'name'
						)
					)
				),
				'submit' => array(
					'title' => $this->l('Save'),
					'class' => 'btn btn-default pull-right fixed-width-sm'
				)
			)
		);

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

		// Load current value
		$helper->fields_value['TINIFY_API_KEY'] = Configuration::get('TINIFY_API_KEY');
		$helper->fields_value['TINIFY_LOGS_on'] = Configuration::get('TINIFY_LOGS');
		$helper->fields_value['TINIFY_PRODUCTS_on'] = Configuration::get('TINIFY_PRODUCTS');
		$helper->fields_value['TINIFY_CATEGORIES_on'] = Configuration::get('TINIFY_CATEGORIES');
		$helper->fields_value['TINIFY_MANUFACTURERS_on'] = Configuration::get('TINIFY_MANUFACTURERS');
		$helper->fields_value['TINIFY_CARRIERS_on'] = Configuration::get('TINIFY_CARRIERS');
		$helper->fields_value['TINIFY_SUPPLIERS_on'] = Configuration::get('TINIFY_SUPPLIERS');
		$helper->fields_value['TINIFY_SCENES_on'] = Configuration::get('TINIFY_SCENES');
		$helper->fields_value['TINIFY_STORES_on'] = Configuration::get('TINIFY_STORES');
		$helper->fields_value['TINIFY_COLORS_on'] = Configuration::get('TINIFY_COLORS');
		$helper->fields_value['TINIFY_LANGUAGES_on'] = Configuration::get('TINIFY_LANGUAGES');
		$helper->fields_value['TINIFY_EMPLOYEES_on'] = Configuration::get('TINIFY_EMPLOYEES');
		$helper->fields_value['TINIFY_MODULES_on'] = Configuration::get('TINIFY_MODULES');
		$helper->fields_value['TINIFY_THEME_on'] = Configuration::get('TINIFY_THEME');
		$helper->fields_value['TINIFY_THEME_MODULES_on'] = Configuration::get('TINIFY_THEME_MODULES');
		$helper->fields_value['TINIFY_IMAGES_on'] = Configuration::get('TINIFY_IMAGES');

		return $helper->generateForm(array($fields_forms, $fields_refresh, $fields_clear));
	}

	private function logError($msg, $report = null)
	{
		if (isset($this->logger))
			$this->logger->logError($msg);

		if ($report)
			$report->error = $msg;

		return  $this->displayError($msg);
	}

	private function tinifyDirectory($dir, &$report, $recursive = true)
	{
		if (!file_exists($dir))
		{
			if (isset($this->logger))
				$this->logger->logWarning($dir." does not exist");
			return;
		}

		if ($recursive === true)
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
			return logError('Validation of API key failed: '.$e->getMessage());
		}

		$report = new TinifyReport();
		$output = "";

		// Tinify
		try
		{
			if (Configuration::get('TINIFY_PRODUCTS'))
				$this->tinifyDirectory(_PS_PROD_IMG_DIR_, $report);

			if (Configuration::get('TINIFY_CATEGORIES'))
				$this->tinifyDirectory(_PS_CAT_IMG_DIR_, $report);

			if (Configuration::get('TINIFY_MANUFACTURERS'))
				$this->tinifyDirectory(_PS_MANU_IMG_DIR_, $report);

			if (Configuration::get('TINIFY_CARRIERS'))
				$this->tinifyDirectory(_PS_SHIP_IMG_DIR_, $report);

			if (Configuration::get('TINIFY_SUPPLIERS'))
				$this->tinifyDirectory(_PS_SUPP_IMG_DIR_, $report);

			if (Configuration::get('TINIFY_SCENES'))
				$this->tinifyDirectory(_PS_SCENE_IMG_DIR_, $report);

			if (Configuration::get('TINIFY_STORES'))
				$this->tinifyDirectory(_PS_STORE_IMG_DIR_, $report);

			if (Configuration::get('TINIFY_COLORS'))
				$this->tinifyDirectory(_PS_COL_IMG_DIR_, $report);

			if (Configuration::get('TINIFY_LANGUAGES'))
				$this->tinifyDirectory(_PS_LANG_IMG_DIR_, $report);

			if (Configuration::get('TINIFY_EMPLOYEES'))
				$this->tinifyDirectory(_PS_EMPLOYEE_IMG_DIR_, $report);

			if (Configuration::get('TINIFY_MODULES'))
				$this->tinifyDirectory(_PS_MODULE_DIR_, $report);

			if (Configuration::get('TINIFY_THEME'))
			{
				$this->tinifyDirectory(_PS_ALL_THEMES_DIR_._THEME_NAME_.'/img/', $report);
				$this->tinifyDirectory(_PS_ALL_THEMES_DIR_._THEME_NAME_.'/mobile/img/', $report);
			}

			if (Configuration::get('TINIFY_THEME_MODULES'))
				$this->tinifyDirectory(_PS_ALL_THEMES_DIR_._THEME_NAME_.'/modules/', $report);

			if (Configuration::get('TINIFY_IMAGES'))
				$this->tinifyDirectory(_PS_CORE_IMG_DIR_, $report, false);

			if (!$report->fileCounter)
			{
				$report->lastUpdate = time();
				$report->compressionCount = \Tinify\getCompressionCount();
			}

			$output .= $this->displayConfirmation($this->l('Tinify finished successfully.'));
		}
		catch(\Tinify\AccountException $e)
		{
			$output .= logError('Verify your account limit: '.$e->getMessage(), $report);
		}
		catch(\Tinify\ClientException $e)
		{
			$output .= logError('Check your source image and request options: '.$e->getMessage(), $report);
		}
		catch(\Tinify\ServerException $e)
		{
			$output .= logError('Temporary issue with the Tinify API: '.$e->getMessage(), $report);
		}
		catch(\Tinify\ConnectionException $e)
		{
			$output .= logError('A network connection error occurred: '.$e->getMessage(), $report);
		}
		catch(Exception $e)
		{
			$output .= logError('Unrelated exception: '.$e->getMessage(), $report);
		}

		Configuration::updateValue('TINIFY_REPORT', json_encode($report));

		if (isset($this->logger))
			$this->logger->logInfo('Report '.Configuration::get('TINIFY_REPORT'));

		return $output;
	}
}

