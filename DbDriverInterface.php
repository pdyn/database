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
 * Interface describing the public API of a database driver.
 */
interface DbDriverInterface {
	/** Return a single row in a single array in methods that have customizable return types. */
	const DB_RETURN_ARRAY = 0;

	/** Return an array of arrays, each being a row, in methods that have customizable return types. */
	const DB_RETURN_ARRAYSET = 1;

	/** Return an iterable object for the query results, in methods that have customizable return types. */
	const DB_RETURN_RECORDSET = 2;

	/** An error code that indicates the passed parameters were invalid. */
	const ERR_DB_BAD_REQUEST = 400;

	/** An error code that indicates an internal error occurred. Probably a bug. */
	const ERR_DB_INTERNAL_ERROR = 500;

	/**
	 * Get the Singleton instance of the Driver.
	 *
	 * @return \pdyn\database\DbDriverInterface A singleton instance of the driver.
	 */
	public static function instance();

	/**
	 * Constructor.
	 *
	 * @param string $schema The fully-qualified classname of a database schema class.
	 */
	public function __construct($schema);

	/**
	 * Set the logger to be used with the driver.
	 *
	 * @param \Psr\Log\LoggerInterface $logger A logging object to log to.
	 */
	public function set_logger(\Psr\Log\LoggerInterface $logger);

	/**
	 * Connect to the database (specifics left up to implementation)
	 */
	public function connect();

	/**
	 * Disconnect from the database.
	 *
	 * @return bool Success/Failure.
	 */
	public function disconnect();

	/**
	 * Execute a raw SQL query.
	 *
	 * @param string $sql The SQL to execute.
	 * @param array $params Parameters used in the SQL.
	 */
	public function query($sql, array $params = array());

	/**
	 * Fetch the next row available from the last query.
	 *
	 * @return array|false The next row, as an array like [column] => [value], or false if no more rows available.
	 */
	public function fetch_row();

	/**
	 * Test whether the driver can connect to the database.
	 *
	 * This function uses the same parameters as the constructor.
	 *
	 * @return bool Success/Failure.
	 */
	public static function test_connect();

	/**
	 * Set database schema.
	 *
	 * @param \pdyn\database\DbSchema $schema The class name of a database schema to set.
	 * @return bool Success/Failure.
	 */
	public function set_schema($schema);

	/**
	 * Set table prefix.
	 *
	 * @param string $prefix A new prefix.
	 * @return bool Success/Failure.
	 */
	public function set_prefix($prefix);

	/**
	 * Get current table prefix.
	 *
	 * @return The current table prefix.
	 */
	public function get_prefix();

	/**
	 * Transform a value into a storage representation. Optionally based on table/column schema.
	 *
	 * @param mixed $val A value to transform.
	 * @param string $table A table to look up to transform against schema.
	 * @param string $column A column to look up to transform against schema.
	 * @return string|int|float The transformed value.
	 */
	public function cast_val($val, $table = '', $column = '');

	/**
	 * Get a list of tables in the database.
	 *
	 * @param bool $schema Whether to use the supplied schema (if true), or whether to query the database (if false)
	 * @return array Array of tables (without prefix)
	 */
	public function get_tables($schema = true);

	/**
	 * Get a table's schema.
	 *
	 * Gets information about what columns are in a table, what datatype each column is, what keys exist in the table.
	 *
	 * @param string|array $tables A single table name, an array of table names, or "*" if you want everything.
	 * @return array Array of table schema information, indexed by table name, then separated into 'columns', and 'keys' indexes.
	 */
	public function get_table_schema($tables);

	/**
	 * Determine whether a table exists in the database.
	 *
	 * @param string $table The name of the table (without prefix).
	 * @return bool Exists/Not Exists
	 */
	public function table_exists($table);

	/**
	 * Determine whether a record matching given conditions exists in the given table.
	 *
	 * @param string $table The table to look for the record.
	 * @param array $conditions Conditions to select a record. Structured like 'field' => 'value'
	 * @return bool Whether the record exists(true) or not(false).
	 */
	public function record_exists($table, array $conditions = array());

	/**
	 * Get a single record.
	 *
	 * @param string $table The table to get the record from (without prefix).
	 * @param array $conditions Conditions to select a record. Structured like 'field' => 'value'
	 * @param array $order The order to sort records. If multiple records are found, the first will be returned.
	 *                     Structured like 'field' => 'ASC' or 'DESC'
	 * @param string $columns A comma-separated list of columns to select.
	 * @return array|false. Either the found record as an array, or false if nothing found.
	 */
	public function get_record($table, array $conditions = array(), array $order = array(), $columns = '*');

	/**
	 * Get a single record via SQL where.
	 *
	 * @param string $table The table to get the record from (without prefix).
	 * @param string $select A string SQL statement for the query's WHERE clause.
	 * @param array $selectparams An array of parameters used in the WHERE clause.
	 * @param array $order The order to sort records. If multiple records are found, the first will be returned.
	 *                     Structured like 'field' => 'ASC' or 'DESC'
	 * @param string $columns A comma-separated list of columns to select.
	 * @return array|false. Either the found record as an array, or false if nothing found.
	 */
	public function get_record_select($table, $select = '', array $selectparams = array(), array $order = array(), $columns = '*');

	/**
	 * Get a single record with a raw SQL query..
	 *
	 * @param string $table The table to get the record from (without prefix).
	 * @param array $conditions Conditions to select a record. Structured like 'field' => 'value'
	 * @param array $order The order to sort records. If multiple records are found, the first will be returned.
	 *                     Structured like 'field' => 'ASC' or 'DESC'
	 * @param string $columns A comma-separated list of columns to select.
	 * @return array|false. Either the found record as an array, or false if nothing found.
	 */
	public function get_record_sql($sql, array $params = array(), array $returnopts = array());

	/**
	 * Get multiple records.
	 *
	 * @param string $table The table to get records from (without prefix).
	 * @param array $conditions Conditions to select records. Structured like 'field' => 'value'
	 * @param array $order The order to sort records. Structured like 'field' => 'ASC' or 'DESC'
	 * @param string $columns A comma-separated list of columns to select.
	 * @param int $start The record to start at.
	 * @param int $count The number of records to return.
	 * @param array $returnopts Additional options
	 *                        idindexed: Return is usually a numeric array of records. If idindexed is specified, use the value
	 *                                   from the specified field is used as the index for each returned record in the array.
	 *                                   Ex.
	 *                                   array(
	 *                                       'val1' => array('id' => 1, 'somefield' => 'val1'),
	 *                                       'val2' => array('id' => 2, 'somefield' => 'val2'),
	 *                                   )
	 *                        idsorted: Instead of a numeric array of arrays, return an array with keys being the values of the
	 *                                  field specified, and values being the records that contain the value for that field.
	 *                                  Ex.
	 *                                  array(
	 *                                      'val1' => array(
	 *                                          array('id' => 1, 'somefield' => 'val1'),
	 *                                          array('id' => 2, 'somefield' => 'val1'),
	 *                                      ),
	 *                                      'val2' => array(
	 *                                          array('id' => 3, 'somefield' => 'val2'),
	 *                                          array('id' => 4, 'somefield' => 'val2'),
	 *                                      ),
	 *                                  )
	 * @return array An array of all found records. If none found, will be an empty array.
	 */
	public function get_records($table, array $conditions = array(), array $order = array(), $columns = '*', $start = 0, $count = null,
								array $returnopts = array());

	/**
	 * Get multiple records via SQL where.
	 *
	 * @param string $table The table to get the record from (without prefix).
	 * @param string $select A string SQL statement for the query's WHERE clause.
	 * @param array $selectparams An array of parameters used in the WHERE clause.
	 * @param array $order The order to sort records. If multiple records are found, the first will be returned.
	 *                     Structured like 'field' => 'ASC' or 'DESC'
	 * @param string $columns A comma-separated list of columns to select.
	 * @param int $start The record to start at.
	 * @param int $count The number of records to return.
	 * @param array $returnopts See $returnopts in get_records().
	 * @return array|false. Either the found record as an array, or false if nothing found.
	 */
	public function get_records_select($table, $select, array $selectparams = array(), array $order = array(), $columns = '*',
										$start = 0, $count = null, array $returnopts = array());

	/**
	 * Get records with a raw SQL query.
	 *
	 * @param string $sql A raw SELECT sql query.
	 * @param array $params Array of parameters used by $sql.
	 * @param array $returnopts See $returnopts in get_records().
	 * @return array An array of all found records. If none found, will be an empty array.
	 */
	public function get_records_sql($sql, array $params = array(), array $returnopts = array());

	/**
	 * Get a recordset of records.
	 *
	 * @param string $table The table to get records from (without prefix).
	 * @param array $conditions Conditions to select records. Structured like 'field' => 'value'
	 * @param array $order The order to sort records. Structured like 'field' => 'ASC' or 'DESC'
	 * @param string $columns A comma-separated list of columns to select.
	 * @param int $start The record to start at.
	 * @param int $count The number of records to return.
	 * @return array An array of all found records. If none found, will be an empty array.
	 */
	public function get_recordset($table, array $conditions = array(), array $order = array(), $columns = '*', $start = 0, $count = null);

	/**
	 * Determine the number of rows that match a list of conditions.
	 *
	 * @param string $table The table to get records from (without prefix).
	 * @param array $conditions Conditions to select records. Structured like 'field' => 'value'
	 * @return int The number of records that match the conditions.
	 */
	public function count_records($table, array $conditions = array());

	/**
	 * Determine the number of rows that match a given WHERE clause.
	 *
	 * @param string $table The table to get records from (without prefix).
	 * @param string $select A string SQL statement for the query's WHERE clause.
	 * @param array $selectparams An array of parameters used in the WHERE clause.
	 * @return int The number of records that match the conditions.
	 */
	public function count_records_select($table, $select, array $selectparams = array());

	/**
	 * Insert a record into the database.
	 *
	 * @param string $table The table to insert into (without prefix).
	 * @param array $record A record to insert in the form column => value.
	 * @param bool $ignore Whether to perform an INSERT IGNORE (if true) to fail silently if a unique key fails.
	 * @return int The auto-generated record ID.
	 */
	public function insert_record($table, array $record, $ignore = false);

	/**
	 * Insert multiple records into the database.
	 *
	 * @param string $table The table to insert into (without prefix).
	 * @param array $columns An array of columns in the same order as the values from $rows.
	 * @param array An array of arrays, which each array being a list of values in the same order as $columns.
	 * @param bool $ignore Whether to perform an INSERT IGNORE (if true) to fail silently if a unique key fails.
	 * @return array Array with keys:
	 *                   "affected_rows" (being number of records actually inserted)
	 *                   "last_id" (being the first auto-generated ID generated from the batch)
	 */
	public function insert_records($table, $columns, $rows, $ignore = false);

	/**
	 * Update database records.
	 *
	 * @param string $table Table to update (without prefix).
	 * @param array $toupdate An array of updated data in the form column => value.
	 * @param array $conditions A list of conditions to determine which records get updated (in the form column => value)
	 * @return bool Success|Failure
	 */
	public function update_records($table, array $toupdate, array $conditions = array());

	/**
	 * Update records with a raw WHERE query.
	 *
	 * @param string $table Table to update (without prefix).
	 * @param string $select A raw string of conditions, which will be the query's WHERE section.
	 * @param array $selectparams An array of parameters used in $select.
	 * @param array $updated An array of updated data in the form column => value.
	 * @return bool Success/Failure
	 */
	public function update_records_select($table, $select, $selectparams, array $updated);

	/**
	 * Delete records.
	 *
	 * @param string $table The table to delete records from.
	 * @param array $conditions Conditions to select records to delete. Structured like 'field' => 'value'
	 * @return int Number of records deleted.
	 */
	public function delete_records($table, array $conditions = array());

	/**
	 * Delete records with a raw WHERE query.
	 *
	 * @param string $table The table to delete records from.
	 * @param array $select The raw WHERE part of the SQL query.
	 * @return array $params List of parameters used in $select.
	 */
	public function delete_records_select($table, $select, array $params = array());

	/**
	 * Fetches an array representing a single row of the result.
	 *
	 * @return array|false Array if a row is available, false if not
	 */
	public function fetch_array();

	/**
	 * Fetched an array of arrays for each returned row.
	 *
	 * @param string $sortmode The mode to sort results.
	 * @param string $sortparam The parameter to sort by.
	 * @return array Arrayset of results.
	 */
	public function fetch_arrayset($sortmode = 'normal', $sortparam = '');

	/**
	 * Fetch an Iterator that returns each row when used.
	 *
	 * @return \pdyn\database\Recordset A recordset.
	 */
	public function fetch_recordset();
}
