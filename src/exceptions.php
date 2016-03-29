<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Doctrine\Dbal\BatchImport;

interface Exception
{

}



class InvalidArgumentException extends \InvalidArgumentException implements Exception
{

}



class BatchImportException extends \RuntimeException implements Exception
{

	/**
	 * @var string
	 */
	private $sql;



	public function __construct($sql, $message, $code = 0, \Exception $previous = NULL)
	{
		parent::__construct($message, $code, $previous);
		$this->sql = $sql;
	}



	/**
	 * @return string
	 */
	public function getSql()
	{
		return $this->sql;
	}

}
