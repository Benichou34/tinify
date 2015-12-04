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

class FileCache extends ObjectModel
{
	public $id = 0;
	public $file_hash = null;
	public $file_size = 0;
	private $file_info = null;

	/**
	 * @see ObjectModel::$definition
	 */
	public static $definition = array(
		'table' => 'tinify_cache',
		'primary' => 'id',
		'fields' => array(
			'file_hash' =>	array('type' => self::TYPE_STRING, 'required' => true, 'size' => 32),
			'file_size' =>	array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'copy_post' => false, 'required' => true)
		)
	);

	public function __construct(SplFileInfo $fileinfo = null)
	{
		parent::__construct();

		if ($fileinfo)
		{
			$this->file_hash = md5($fileinfo->getRealPath());
			$fileSize = $fileinfo->getSize();

			// Looking for existing path
			$result = Db::getInstance()->getRow('
				SELECT *
				FROM `'._DB_PREFIX_.bqSQL(self::$definition['table']).'`
				WHERE `file_hash` = \''.pSQL($this->file_hash).'\'');

			if (!$result)
			{
				$this->file_size = $fileSize;
				$this->file_info = $fileinfo;
			}
			else
			{
				$this->id = $result['id'];
				foreach ($result as $key => $value)
					if (array_key_exists($key, $this))
						$this->{$key} = $value;

				// Check file size
				if ($this->file_size != $fileSize)
				{
					$this->file_size = $fileSize;
					$this->file_info = $fileinfo;
				}
			}
		}

		return $this;
	}

	public static function createDb()
	{
		return Db::getInstance()->execute('
			CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.bqSQL(self::$definition['table']).'` (
				`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
				`file_hash` varchar(32) NOT NULL,
				`file_size` int(10) unsigned NOT NULL,
				PRIMARY KEY (`id`)
			) ENGINE='._MYSQL_ENGINE_.' default CHARSET=utf8');
	}

	public static function deleteDb()
	{
		return Db::getInstance()->execute('DROP TABLE IF EXISTS '._DB_PREFIX_.bqSQL(self::$definition['table']));
	}

	public function needTinify()
	{
		return $this->file_info != null;
	}

	public function tinify()
	{
		if (!$this->needTinify())
			return false;

		$filename = $this->file_info->getPathname();

		$source = \Tinify\fromFile($filename);
		$source->toFile($filename);

		clearstatcache(true, $filename);
		$this->file_size = $this->file_info->getSize();
		$this->file_info = null;

		return $this->save();
	}

	public function getPathname()
	{
		if ($this->file_info == null)
			return null;

		return $this->file_info->getPathname();
	}

	public function getSize()
	{
		return $this->file_size;
	}
}

