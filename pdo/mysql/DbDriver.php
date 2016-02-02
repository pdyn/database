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

namespace pdyn\database\pdo\mysql;

use \pdyn\base\Exception;

/**
 * A implementation of the PDO DbDriver for MySQL-specific quirks.
 */
class DbDriver extends \pdyn\database\pdo\DbDriver {
	/**
	 * Return a StructureManager implementation for database structure changes.
	 *
	 * @return \pdyn\database\pdo\mysql\StructureManager A structure manager instance.
	 */
	public function structure() {
		return new StructureManager($this);
	}

	/**
	 * Lock a table from other writes, if possible.
	 *
	 * @param string $table Table name.
	 * @return bool Success/Failure.
	 */
	protected function lock_table($table) {
		$this->query('LOCK TABLE {'.$table.'} WRITE');
		return true;
	}

	/**
	 * Unlock all tables.
	 *
	 * @return bool Success/Failure.
	 */
	protected function unlock_tables() {
		$this->query('UNLOCK TABLES');
		return true;
	}

	/**
	 * Insert multiple records into the database.
	 *
	 * @param string $table The table to insert into (without prefix).
	 * @param array $columns An array of columns in the same order as the values from $rows.
	 * @param array $rows An array of arrays, which each array being a list of values in the same order as $columns.
	 * @param bool $ignore Whether to perform an INSERT IGNORE (if true) to fail silently if a unique key fails.
	 * @return array Array with keys:
	 *                   "affected_rows" (being number of records actually inserted)
	 *                   "last_id" (being the first auto-generated ID generated from the batch)
	 */
	public function insert_records($table, $columns, $rows, $ignore = false) {
		$result = parent::insert_records($table, $columns, $rows, $ignore);
		if ($result['last_id'] > 0 && $result['affected_rows'] > 0) {
			$result['last_id'] = $result['last_id'] + $result['affected_rows'] - 1;
		}
		return $result;
	}
}
