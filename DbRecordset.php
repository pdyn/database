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

namespace pdyn\database;

/**
 * Recordsets are record iterators that wait until a record is requested to get it from the database.
 *
 * This can save memory with large datasets as you are only ever loading one record into memory at a time.
 */
abstract class DbRecordset implements \iterator {
	/** @var bool|array The current record, or false if no more available */
	protected $currec = false;

	/** @var string|int The current key */
	protected $key;

	/**
	 * Advance to the next record.
	 */
	abstract public function next();

	/**
	 * Get the number of rows in the recordset (if possible).
	 *
	 * @return int The number of rows.
	 */
	abstract public function num_rows();

	/**
	 * Clean up and free memory.
	 */
	abstract public function __destruct();

	/**
	 * Get the current record.
	 *
	 * @return array The current record.
	 */
	public function current() {
		return $this->currec;
	}

	/**
	 * Get the key of the current record.
	 *
	 * @return string|int The key of the current record.
	 */
	public function key() {
		return $this->key;
	}

	/**
	 * Rewind to the beginning of the recordset.
	 *
	 * Note: This is almost always unsupported.
	 *
	 * @return bool Success/Failure.
	 */
	public function rewind() {
		return false;
	}

	/**
	 * Determine if there is currently a record.
	 *
	 * @return bool Valid/Not Valid.
	 */
	public function valid() {
		return (empty($this->currec)) ? false : true;
	}
}
