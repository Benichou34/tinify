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

class TinifyReport
{
	const STATUS_RUNNING = 'Running...';
	const STATUS_DONE = 'Done';

	public $status;
	public $lastUpdate = 0;
	public $fileCounter = 0;
	public $gainSize = 0;
	public $compressionCount = 0;
	public $error = array();
	public $warning = array();

	public function beginTinify()
	{
		$this->status = self::STATUS_RUNNING;
		$this->update();
	}

	public function endTinify()
	{
		$this->status = self::STATUS_DONE;
		$this->update();
	}

	public static function delete()
	{
		Configuration::deleteByName('TINIFY_REPORT');
	}

	public static function getLast($json = false)
	{
		$lastReport = Configuration::get('TINIFY_REPORT');
		if(!$json)
			return json_decode($lastReport);

		return $lastReport;
	}

	public static function isRunning()
	{
		$lastReport = self::getLast();
		return (isset($lastReport->status) && $lastReport->status == self::STATUS_RUNNING);
	}

	public function logError($msg)
	{
		$this->error[] = $msg;
	}

	public function logWarning($msg)
	{
		$this->warning[] = $msg;
	}

	public function update($gainSize = null)
	{
		if ($gainSize != null)
		{
			$this->fileCounter++;
			$this->gainSize += $gainSize;
		}

		$this->lastUpdate = time();
		$this->compressionCount = \Tinify\getCompressionCount();
		$this->save();
	}

	private function save()
	{
		Configuration::updateValue('TINIFY_REPORT', json_encode($this));
	}
}
