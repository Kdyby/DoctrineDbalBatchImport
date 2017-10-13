<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Doctrine\Dbal\BatchImport;

use Doctrine\DBAL\Connection;
use Kdyby;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Helpers
{

	/**
	 * Import taken from Adminer, slightly modified
	 * This implementation is aware of delimiters used for trigger definitions
	 *
	 * @author   Jakub Vrána, Jan Tvrdík, Michael Moravec, Filip Procházka
	 * @license  Apache License
	 */
	public static function executeBatch(Connection $connection, $query, $callback = NULL)
	{
		$db = $connection->getWrappedConnection();

		$delimiter = ';';
		$offset = 0;
		while ($query != '') {
			if (!$offset && preg_match('~^\\s*DELIMITER\\s+(.+)~i', $query, $match)) {
				$delimiter = $match[1];
				$query = substr($query, strlen($match[0]));
			} else {
				preg_match('(' . preg_quote($delimiter) . '|[\'`"]|/\\*|-- |#|$)', $query, $match, PREG_OFFSET_CAPTURE, $offset); // should always match
				$found = $match[0][0];
				$offset = $match[0][1] + strlen($found);

				if (!$found && rtrim($query) === '') {
					break;
				}

				if (!$found || $found == $delimiter) { // end of a query
					$q = substr($query, 0, $match[0][1]);

					try {
						if ($callback) {
							call_user_func($callback, $q, $db);
						}

						$db->exec($q);

					} catch (\Exception $e) {
						throw new BatchImportException($q, $e->getMessage(), 0, $e);
					}

					$query = substr($query, $offset);
					$offset = 0;
				} else { // find matching quote or comment end
					while (preg_match('~' . ($found === '/*' ? '\\*/' : (preg_match('~-- |#~', $found) ? "\n" : "$found|\\\\.")) . '|$~s', $query, $match, PREG_OFFSET_CAPTURE, $offset)) { //! respect sql_mode NO_BACKSLASH_ESCAPES
						$s = $match[0][0];
						$offset = $match[0][1] + strlen($s);
						if ($s[0] !== '\\') {
							break;
						}
					}
				}
			}
		}
	}



	/**
	 * @author David Grudl
	 * @see https://github.com/dg/dibi/blob/cde5af7cbe02d231fe2d3f904fc2c3d3eeda66f0/dibi/libs/DibiConnection.php#L630
	 */
	public static function loadFromFile(Connection $connection, $file, $callback = NULL)
	{
		@set_time_limit(0); // intentionally @

		if (!$handle = @fopen($file, 'r')) { // intentionally @
			throw new InvalidArgumentException("Cannot open file '$file'.");
		}

		$count = 0;
		$delimiter = ';';
		$sql = '';
		while (!feof($handle)) {
			$s = rtrim(fgets($handle));
			if (substr($s, 0, 10) === 'DELIMITER ') {
				$delimiter = substr($s, 10);

			} elseif (substr($s, -strlen($delimiter)) === $delimiter) {
				$sql .= substr($s, 0, -strlen($delimiter));
				if ($callback) {
					call_user_func($callback, $sql, ftell($handle));
				}
				$connection->exec($sql);
				$sql = '';
				$count++;

			} else {
				$sql .= $s . "\n";
			}
		}

		if (trim($sql) !== '') {
			if ($callback) {
				call_user_func($callback, $sql, ftell($handle));
			}
			$connection->exec($sql);
			$count++;
		}

		fclose($handle);

		return $count;
	}

}
