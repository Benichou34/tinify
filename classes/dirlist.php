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

class DirList extends ObjectModel
{
	public $id = 0;
	public $path = null;
	public $desc = null;
	public $recursive = false;
	public $user = true;
	public $enabled = false;

	/**
	 * @see ObjectModel::$definition
	 */
	public static $definition = array(
		'table' => 'tinify_dirs',
		'primary' => 'id',
		'fields' => array(
			'path' => array('type' => self::TYPE_STRING, 'required' => true, 'size' => 256, 'required' => true),
			'desc' => array('type' => self::TYPE_STRING, 'required' => true, 'size' => 50, 'required' => false),
			'recursive' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool', 'required' => false),
			'user' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool', 'required' => false),
			'enabled' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool', 'required' => false)
		)
	);

	public function __construct($id = null)
	{
		parent::__construct($id);
	}

	public static function createDb()
	{
		$sql = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.bqSQL(self::$definition['table']).'` (
				`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
				`path` varchar(256) NOT NULL,
				`desc` varchar(50) NOT NULL,
				`recursive` TINYINT(1) NOT NULL DEFAULT \'0\',
				`user` TINYINT(1) NOT NULL DEFAULT \'1\',
				`enabled` TINYINT(1) NOT NULL DEFAULT \'0\',
				PRIMARY KEY (`id`)
			) ENGINE='._MYSQL_ENGINE_.' default CHARSET=utf8';

		if (!Db::getInstance()->execute($sql))
			return false;

		$directories = array(
			array(
				'path' => _PS_PROD_IMG_DIR_,
				'desc' => 'Products images',
				'recursive' => true
			),
			array(
				'path' => _PS_CAT_IMG_DIR_,
				'desc' => 'Categories images',
				'recursive' => true
			),
			array(
				'path' => _PS_MANU_IMG_DIR_,
				'desc' => 'Manufacturers  images',
				'recursive' => true
			),
			array(
				'path' => _PS_SHIP_IMG_DIR_,
				'desc' => 'Carriers (shipping) images',
				'recursive' => true
			),
			array(
				'path' => _PS_SUPP_IMG_DIR_,
				'desc' => 'Suppliers  images',
				'recursive' => true
			),
			array(
				'path' => _PS_SCENE_IMG_DIR_,
				'desc' => 'Scenes  images',
				'recursive' => true
			),
			array(
				'path' => _PS_STORE_IMG_DIR_,
				'desc' => 'Stores  images',
				'recursive' => true
			),
			array(
				'path' => _PS_COL_IMG_DIR_,
				'desc' => 'Colors  images',
				'recursive' => true
			),
			array(
				'path' => _PS_LANG_IMG_DIR_,
				'desc' => 'Languages  images',
				'recursive' => true
			),
			array(
				'path' => _PS_EMPLOYEE_IMG_DIR_,
				'desc' => 'Employees  images',
				'recursive' => true
			),
			array(
				'path' => _PS_MODULE_DIR_,
				'desc' => 'Modules  images',
				'recursive' => true
			),
			array(
				'path' => _PS_ALL_THEMES_DIR_._THEME_NAME_.'/img/',
				'desc' => 'Theme specifics images',
				'recursive' => true
			),
			array(
				'path' => _PS_ALL_THEMES_DIR_._THEME_NAME_.'/mobile/img/',
				'desc' => 'Theme mobile images',
				'recursive' => true
			),
			array(
				'path' => _PS_ALL_THEMES_DIR_._THEME_NAME_.'/modules/',
				'desc' => 'Theme modules images',
				'recursive' => true
			),
			array(
				'path' => _PS_CORE_IMG_DIR_,
				'desc' => 'Prestashop core images',
				'recursive' => false
			)
		);

		foreach ($directories as $systemdir)
		{
			$dir = new self();
			if (!$dir->getByPath($systemdir['path']) && !$dir->fromDef($systemdir, true)->save())
				return false;
		}

		return true;
	}

	public static function deleteDb()
	{
		return Db::getInstance()->execute('DROP TABLE IF EXISTS '._DB_PREFIX_.bqSQL(self::$definition['table']));
	}

	private function fromDef($def = null, $enabled = false)
	{
		if ($def != null && !empty($def))
		{
			$this->path = $def['path'];
			$this->desc = $def['desc'];
			$this->recursive = $def['recursive'];
			$this->user = false;
			$this->enabled = $enabled;
		}

		return $this;
	}

	public function getByPath($path)
	{
		$result = Db::getInstance()->getRow('
			SELECT *
			FROM `'._DB_PREFIX_.bqSQL(self::$definition['table']).'`
			WHERE `path` = \''.pSQL($path).'\'');

		if (!$result)
			return false;

		$this->id = $result['id'];
		foreach ($result as $key => $value)
			if (array_key_exists($key, $this))
				$this->{$key} = $value;

		return $this;
	}

	public static function getContent()
	{
		$sql = 'SELECT * FROM '._DB_PREFIX_.bqSQL(self::$definition['table']);
		return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
	}

	public static function getDirList()
	{
		$dirList = array();
		foreach (self::getContent() as $result)
		{
			$dir = new self();
			foreach ($result as $key => $value)
			{
				if (array_key_exists($key, $dir))
					$dir->{$key} = $value;
			}
			$dirList[] = $dir;
		}

		return $dirList;
	}
}

