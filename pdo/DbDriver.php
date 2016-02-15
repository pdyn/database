<?php
/*
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * @copyright 2010 onwards James McQuillan (http://pdyn.net)
 * @author James McQuillan <james@pdyn.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pdyn\database\pdo;

/**
 * PDO Implementation of the database driver.
 */
abstract class DbDriver extends \pdyn\database\DbDriver {
	/** @var \PDOStatement The statement for the last query that was performed. */
	protected $laststmt;

	/**
	 * Connect to the database (specifics left up to implementation)
	 */
	public function connect($dsn = '', $user = '', $pass = '', $opts = array()) {
		try {
			$this->link = new \PDO($dsn, $user, $pass, $opts);
		} catch (\PDOException $e) {
			throw new \Exception($e->getMessage(), static::ERR_DB_BAD_REQUEST);
		}

		$this->connected = true;
	}

	/**
	 * Disconnect from the database.
	 *
	 * @return bool Success/Failure.
	 */
	public function disconnect() {
		$this->link = null;
		$this->connected = false;
	}

	/**
	 * Execute a raw SQL query.
	 *
	 * @param string $sql The SQL to execute.
	 * @param array $params Parameters used in the SQL.
	 */
	public function query($sql, array $params = array()) {

		$querytype = mb_strtoupper(mb_substr($sql, 0, 6));

		// Ensure UTF-8.
		foreach ($params as $k => $v) {
			$params[$k] = \pdyn\datatype\Text::force_utf8($v);
		}

		// Prefix tables.
		$sql = preg_replace_callback('#\{(.+)\}#msU', function($matches) { return $this->transform_tablename($matches[1]); }, $sql);

		// Logging.
		$this->log($sql, $params);

		if (!is_array($params)) {
			throw new \Exception('Bad params argument in $DB->query', static::ERR_DB_BAD_REQUEST);
		}
		if (empty($this->link)) {
			throw new \Exception('No database connection present.', static::ERR_DB_BAD_REQUEST);
		}

		if (empty($params)) {
			$stmt = $this->link->query($sql);
		} else {
			$stmt = $this->link->prepare($sql, [\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY]);
			if (!empty($stmt)) {
				$stmt->execute($params);

				$errinfo = $stmt->errorInfo();
				if ($errinfo[0] !== '00000') {
					throw new \Exception($errinfo[2], static::ERR_DB_BAD_REQUEST);
				}

				if (in_array($querytype, ['INSERT', 'UPDATE', 'DELETE'], true)) {
					$affected_rows = $stmt->rowCount();
				}
			}
		}

		$errinfo = $this->link->errorInfo();
		if ($errinfo[0] !== '00000') {
			throw new \Exception($errinfo[2], static::ERR_DB_BAD_REQUEST);
		}

		$this->numqueries++;
		$this->laststmt = $stmt;
		$lastid = $this->link->lastInsertId();
		$ar = [
			'affected_rows' => (isset($affected_rows)) ? $affected_rows	: -1,
			'last_id' => ($querytype === 'INSERT' && !empty($lastid)) ? $lastid : 0
		];
		return $ar;
	}

	/**
	 * Close a query after it's used.
	 *
	 * @return bool Success/Failure.
	 */
	protected function closequery() {
		if (empty($this->laststmt)) {
			return false;
		}

		if ($this->laststmt instanceof \PDOStatement) {
			$this->laststmt->closeCursor();
			$this->laststmt = null;
		}
		return true;
	}

	/**
	 * Fetch the next row available from the last query.
	 *
	 * @return array|false The next row, as an array like [column] => [value], or false if no more rows available.
	 */
	public function fetch_row() {
		$row = $this->laststmt->fetch(\PDO::FETCH_ASSOC);
		if ($row === false) {
			$this->closequery();
		}
		return $row;
	}

	/**
	 * Transform a column name to quote it within a Query (i.e. add ` for mysql)
	 *
	 * @param string $column Column name.
	 * @return string Quoted column.
	 */
	public function quote_column($column) {
		return '`'.$column.'`';
	}

	/**
	 * Fetch an Iterator that returns each row when used.
	 *
	 * @return \pdyn\database\Recordset A recordset.
	 */
	public function fetch_recordset() {
		return new \pdyn\database\pdo\DbRecordset($this->laststmt);
	}
}
