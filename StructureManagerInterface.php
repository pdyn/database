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
 * Defines methods for altering the structure of a database.
 */
interface StructureManagerInterface {
	/**
	 * Constructor
	 *
	 * @param \pdyn\database\DbDriver $dbdriver The requesting DbDriver.
	 */
	public function __construct(DbDriver &$dbdriver);

	/**
	 * Create a table.
	 *
	 * @param string $table The table name, defined in the database schema (without prefix).
	 * @return bool Success/Failure.
	 */
	public function create_table($table);

	/**
	 * Drop a table.
	 *
	 * @param string $table The table name, defined in the database schema (without prefix).
	 * @return bool Success/Failure.
	 */
	public function drop_table($table);

	/**
	 * Rename a column in a table.
	 *
	 * @param string $table The name of a table (defined in schema, without prefix).
	 * @param string $oldname The current name of a column.
	 * @param string $column The desired name of the column.
	 * @return bool Success/Failure.
	 */
	public function rename_column($table, $oldname, $column);

	/**
	 * Add a column to a table.
	 *
	 * @param string $table The table name, defined in the database schema (without prefix).
	 * @param string $column The column name, defined in the database schema.
	 * @return bool Success/Failure.
	 */
	public function add_column($table, $column);

	/**
	 * Modify a column to make it match what's defined in the schema.
	 *
	 * @param string $table The table the column is in (without prefix).
	 * @param string $column The column to update.
	 * @return bool Success/Failure.
	 */
	public function update_column($table, $column);

	/**
	 * Drop a column.
	 *
	 * @param string $table The table name, defined in the database schema.
	 * @param string $column The column name, defined in the database schema.
	 * @return bool Success/Failure.
	 */
	public function drop_column($table, $column);

	/**
	 * Add an index to a column.
	 *
	 * @param string $table The table name, defined in the database schema.
	 * @param string $column The column name, defined in the database schema.
	 * @return bool Success/Failure.
	 */
	public function add_index($table, $column);
}
