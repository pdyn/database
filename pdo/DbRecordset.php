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

use \pdyn\base\Exception;

/**
 * Recordsets are record iterators that wait until a record is requested to get it from the database.
 *
 * This can save memory with large datasets as you are only ever loading one record into memory at a time.
 */
class DbRecordset extends \pdyn\database\DbRecordset {
	/** @var \PDOStatement The PDOStatement object representing the query used by the recordset. */
	protected $stmt;

	/**
	 * Constructor
	 */
	public function __construct($stmt) {
		if ($stmt instanceof \PDOStatement) {
			$this->stmt = $stmt;
		} else {
			throw new Exception('Attempt to construct recordset from invalid PDOStatement', Exception::ERR_BAD_REQUEST);
		}
		$this->next();
	}

	/**
	 * Advance to the next record.
	 */
	public function next() {
		$this->currec = $this->stmt->fetch(\PDO::FETCH_ASSOC);
		if ($this->currec === false) {
			$this->closequery();
		} else {
			$this->key++;
		}
		return $this->currec;
	}

	/**
	 * Get the number of rows in the recordset (if possible).
	 *
	 * @return int The number of rows.
	 */
	public function num_rows() {
		if (!empty($this->stmt)) {
			$count = $this->stmt->rowCount();
			return (!empty($count)) ? $count : 0;
		} else {
			return 0;
		}
	}

	/**
	 * Clean up and free memory.
	 */
	public function __destruct() {
		$this->closequery();
	}

	/**
	 * Close the statement and free up memory.
	 */
	protected function closequery() {
		if (!empty($this->stmt)) {
			$this->stmt->closeCursor();
			$this->stmt = null;
		}
	}
}
