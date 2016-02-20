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

use \pdyn\base\Exception;

/**
 * Base class for most drivers.
 */
abstract class DbDriver implements DbDriverInterface {
	/** @var resource A link to the database. */
	public $link = null;

	/** @var bool Whether we are connected to the database or not. */
	public $connected = false;

	/** @var array Array of database schema information. */
	protected $schema = [];

	/** @var string A prefix to add to all tables. */
	protected $prefix = 'pdyn_';

	/** @var \Psr\Log\LoggerInterface A logging object to log to (if set). */
	protected $logger = null;

	/** @var \pdyn\database\DbDriverInterface An instance of the driver. */
	public static $instance = null;

	/** @var int An ongoing count of the number of queries performed. */
	public $numqueries = 0;

	/** @var array A cache of plugin table information. */
	protected $plugintablecache = [];

	/**
	 * Get the Singleton instance of the Driver.
	 *
	 * @return \pdyn\database\DbDriverInterface A singleton instance of the driver.
	 */
	public static function instance() {
		if (empty(static::$instance)) {
			die('No DB Connection');
		}
		return static::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @param array $schema Array of fully-qualified classnames of database schema classes.
	 */
	public function __construct(array $schema = array()) {
		if (!empty($schema)) {
			$this->set_schema($schema);
		}

		static::$instance =& $this;
	}

	/**
	 * Set the logger to be used with the driver.
	 *
	 * @param \Psr\Log\LoggerInterface $logger A logging object to log to.
	 */
	public function set_logger(\Psr\Log\LoggerInterface $logger) {
		$this->logger = $logger;
	}

	/**
	 * Connect to the database (specifics left up to implementation)
	 */
	abstract public function connect();

	/**
	 * Disconnect from the database.
	 *
	 * @return bool Success/Failure.
	 */
	abstract public function disconnect();

	/**
	 * Execute a raw SQL query.
	 *
	 * @param string $sql The SQL to execute.
	 * @param array $params Parameters used in the SQL.
	 */
	abstract public function query($sql, array $params = array());

	/**
	 * Fetch the next row available from the last query.
	 *
	 * @return array|false The next row, as an array like [column] => [value], or false if no more rows available.
	 */
	abstract public function fetch_row();

	/**
	 * Transform a column name to quote it within a Query (i.e. add ` for mysql)
	 *
	 * @param string $column Column name.
	 * @return string Quoted column.
	 */
	abstract public function quote_column($column);

	/**
	 * Fetch an Iterator that returns each row when used.
	 *
	 * @return \pdyn\database\Recordset A recordset.
	 */
	abstract public function fetch_recordset();

	/**
	 * Return a StructureManager implementation for database structure changes.
	 *
	 * @return \pdyn\database\StructureManagerInterface A structure manager instance.
	 */
	abstract public function structure();

	/**
	 * Transform a value into a storage representation based on it's table and column.
	 *
	 * @param mixed $val A value to transform.
	 * @param string $table A table to look up to transform against schema.
	 * @param string $column A column to look up to transform against schema.
	 * @return string|int|float The transformed value.
	 */
	public function cast_val_for_column($value, $table, $column) {
		$datatype = $this->get_column_datatype($table, $column);
		return $this->cast_val($value, $datatype);
	}

	/**
	 * Transform a value into a storable representation.
	 *
	 * @param mixed $val A value to transform.
	 * @param string $datatype The datatype of the value.
	 * @return string|int|float The transformed value.
	 */
	public function cast_val($val, $datatype) {
		// Translate non-storable values.
		if (is_bool($val)) {
			$val = (int)$val;
		} elseif (is_array($val)) {
			$val = \pdyn\datatype\Text::utf8safe_serialize($val);
		} elseif (!is_scalar($val)) {
			$val = '';
		}

		// Cast for column type (if possible).
		switch ($datatype) {
			case 'timestamp':
			case 'int':
			case 'bigint':
			case 'id':
			case 'bool':
			case 'user_id':
				$val = (int)$val;
				break;

			case 'float':
				$val = (float)$val;
				break;

			default:
				$val = (string)$val;
		}
		return $val;
	}

	/**
	 * Get a list of datatypes supported by the driver.
	 *
	 * @return array Array of datatypes.
	 */
	public static function internal_datatypes() {
		$charactersetcollate = ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ';
		return [
			'email' => [
				'vfunc' => '\pdyn\datatype\Validator::email',
				'sql_datatype' => 'text '.$charactersetcollate.' NOT NULL'
			],
			'timestamp' => [
				'vfunc' => '\pdyn\datatype\Validator::timestamp',
				'sql_datatype' => 'int(11) NOT NULL DEFAULT \'0\''
			],
			'str' => [
				'vfunc' => '\pdyn\datatype\Validator::stringlike',
				'sql_datatype' => 'varchar(191) '.$charactersetcollate.' NOT NULL'
			],
			'smallstr' => [
				'vfunc' => '\pdyn\datatype\Validator::stringlike',
				'sql_datatype' => 'varchar(63) '.$charactersetcollate.' NOT NULL'
			],
			'text' => [
				'vfunc' => '\pdyn\datatype\Validator::stringlike',
				'sql_datatype' => 'text '.$charactersetcollate.' NOT NULL'
			],
			'longtext' => [
				'vfunc' => '\pdyn\datatype\Validator::stringlike',
				'sql_datatype' => 'text '.$charactersetcollate.' NOT NULL'
			],
			'filename' => [
				'vfunc' => '\pdyn\datatype\Validator::filename',
				'sql_datatype' => 'varchar(191) '.$charactersetcollate.' NOT NULL'
			],
			'int' => [
				'vfunc' => '\pdyn\datatype\Validator::intlike',
				'sql_datatype' => 'int(11) NOT NULL DEFAULT \'0\''
			],
			'bigint' => [
				'vfunc' => '\pdyn\datatype\Validator::intlike',
				'sql_datatype' => 'bigint(20) NOT NULL'
			],
			'float' => [
				'vfunc' => '\pdyn\datatype\Validator::float',
				'sql_datatype' => 'float NOT NULL'
			],
			'id' => [
				'vfunc' => '\pdyn\datatype\Id::validate',
				'sql_datatype' => 'int(11) NOT NULL'
			],
			'bool' => [
				'vfunc' => '\pdyn\datatype\Validator::boollike',
				'sql_datatype' => 'tinyint(1) NOT NULL DEFAULT \'0\''
			],
			'user_id' => [
				'vfunc' => '\pdyn\datatype\Id::validate',
				'sql_datatype' => 'int(11) NOT NULL DEFAULT \'0\''
			],
			'url' => [
				'vfunc' => '\pdyn\datatype\Url::validate',
				'sql_datatype' => 'text '.$charactersetcollate.' NOT NULL'
			],
			'mime' => [
				'vfunc' => '\pdyn\datatype\Validator::mime',
				'sql_datatype' => 'text '.$charactersetcollate.' NOT NULL'
			],
			'blob' => [
				'vfunc' => null,
				'sql_datatype' => 'BLOB NOT NULL',
			],
			'mediumblob' => [
				'vfunc' => null,
				'sql_datatype' => 'MEDIUMBLOB NOT NULL',
			],
		];
	}

	/**
	 * Test whether the driver can connect to the database.
	 *
	 * This function uses the same parameters as the constructor.
	 *
	 * @return bool Success/Failure.
	 */
	public static function test_connect() {
		ob_start();
		try {
			$args = func_get_args();
			$class = get_called_class();
			$DB = new $class();
			$callable = [$DB, 'connect'];
			call_user_func_array($callable, $args);
		} catch (\Exception $e) {
			return false;
		}
		ob_end_clean();
		return (!empty($DB) && !empty($DB->connected) && $DB->connected === true) ? true : false;
	}

	/**
	 * Set database schema.
	 *
	 * @param array $schema Array of classnames extending \pdyn\database\DbSchema.
	 * @return bool Success/Failure.
	 */
	public function set_schema(array $schema) {
		$this->schema = [];
		foreach ($schema as $schemaclass) {
			if (empty($schemaclass) || !class_exists($schemaclass)) {
				$errmsg = 'Schema class "'.$schemaclass.'" does not exist.';
				throw new Exception($errmsg, Exception::ERR_INTERNAL_ERROR);
			}
			if (!is_subclass_of($schemaclass, '\pdyn\database\DbSchema', true)) {
				$errmsg = 'Schema class "'.$schemaclass.'" does not extend \pdyn\database\DbSchema.';
				throw new Exception($errmsg, Exception::ERR_INTERNAL_ERROR);
			}
			$tables = $schemaclass::get_all();
			foreach ($tables as $table) {
				$this->schema[$table] = $schemaclass::$table();
			}
		}
	}

	/**
	 * Get the set schema.
	 *
	 * @return array The set schema.
	 */
	public function get_schema() {
		return $this->schema;
	}

	/**
	 * Set table prefix.
	 *
	 * @param string $prefix A new prefix.
	 * @return bool Success/Failure.
	 */
	public function set_prefix($prefix) {
		if (!empty($prefix) && (is_string($prefix) || is_numeric($prefix))) {
			$this->prefix = preg_replace('#[^A-Za-z0-9-_]#', '', $prefix);
			return true;
		} elseif (empty($prefix) && is_string($prefix)) {
			$this->prefix = '';
		} else {
			return false;
		}
	}

	/**
	 * Get current table prefix.
	 *
	 * @return The current table prefix.
	 */
	public function get_prefix() {
		return $this->prefix;
	}

	/**
	 * Get a list of tables in the database.
	 *
	 * @return array Array of tables (without prefix)
	 */
	public function get_tables() {
		$this->query('SHOW TABLES');
		$tables_raw = $this->fetch_arrayset();
		$tables = [];
		foreach ($tables_raw as $row) {
			$table = current($row);
			if (mb_strpos($table, $this->prefix) === 0) {
				$tables[] = mb_substr($table, mb_strlen($this->prefix));
			}
		}
		return $tables;
	}

	/**
	 * Transform a table name from one used in a query, to the real name in the database.
	 *
	 * Does the following:
	 *     - Prefixes table names.
	 *     - Translates plugin names (plugin:tablename) to plugin_[plugin]_[table].
	 *
	 * @param string $table Untransformed table name.
	 * @return string The transformed table name.
	 */
	protected function transform_tablename($table) {
		if (strpos($table, ':') !== false) {
			$table = str_replace(':', '_', $table);
		}
		return $this->prefix.$table;
	}

	/**
	 * Get a table's schema.
	 *
	 * Gets information about what columns are in a table, what datatype each column is, what keys exist in the table.
	 *
	 * @param string|array $tables A single table name or array of table names. Or "*" if you want everything.
	 * @return array Array of table schema information, indexed by table name, then separated into 'columns', and 'keys' indexes.
	 */
	public function get_table_schema($tables) {
		$ret = [];

		if ($tables === '*') {
			$tables = $this->get_schema();
			$tables = array_keys($tables);
		}
		if (is_string($tables)) {
			$tables = [$tables];
		}

		if (is_array($tables)) {
			foreach ($tables as $table) {
				if (strpos($table, ':') === false || strpos($table, 'core:') === 0) {
					// Core tables.
					if (isset($this->schema[$table])) {
						$ret[$table] = $this->schema[$table];
					}
				} else {
					// Plugin tables.
					$tableparts = explode(':', $table, 2);
					$pluginid = $tableparts[0];
					$table = $tableparts[1];
					if (!isset($this->plugintablecache[$pluginid])) {
						$pluginmanager = new \hydra\plugins\PluginManager($this);
						list($plugintype, $pluginsubtype) = $pluginmanager->parse_pluginid($pluginid);
						$plugindbschema = $pluginmanager->get_classname($plugintype, $pluginsubtype, '\manifest\DbSchema');
						if (class_exists($plugindbschema)) {
							$schematables = $plugindbschema::get_all();
							foreach ($schematables as $tablename) {
								$this->plugintablecache[$pluginid][$tablename] = $plugindbschema::$tablename();
							}
						}
					}
					if (!isset($this->plugintablecache[$pluginid][$table])) {
						throw new Exception('Invalid plugin table specified in db call.', static::ERR_DB_BAD_REQUEST);
					}
					$ret[$pluginid.':'.$table] = $this->plugintablecache[$pluginid][$table];
				}
			}
		}
		return $ret;
	}

	/**
	 * Determine whether a table exists in the database.
	 *
	 * @param string $table The name of the table (without prefix).
	 * @return bool Exists/Not Exists
	 */
	public function table_exists($table) {
		static $cache = [];
		if (!is_string($table)) {
			return false;
		}
		if (!isset($cache[$table])) {
			if (is_string($table) || is_numeric($table)) {
				$this->query('SHOW TABLES LIKE "{'.$table.'}"');
				$result = $this->fetch_arrayset();
				$cache[$table] = (!empty($result)) ? true : false;
			} else {
				$cache[$table] = false;
			}
		}
		return $cache[$table];
	}

	/**
	 * Determine whether a record matching given conditions exists in the given table.
	 *
	 * TODO: When we ensure every table has an 'id' field, change this to only select the id field.
	 *
	 * @param string $table The table to look for the record.
	 * @param array $conditions Conditions to select a record. Structured like 'field' => 'value'
	 * @return bool Whether the record exists(true) or not(false).
	 */
	public function record_exists($table, array $conditions = array()) {
		$rec = $this->get_record($table, $conditions);
		return (!empty($rec)) ? true : false;
	}

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
	public function get_record($table, array $conditions = array(), array $order = array(), $columns = '*') {
		list($sql, $params) = $this->generate_select_sql($table, $columns, $conditions, $order, 0, 1);
		return $this->get_records_sql($sql, $params, [], static::DB_RETURN_ARRAY);
	}

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
	public function get_record_select($table, $select = '', array $selectparams = array(), array $order = array(), $columns = '*') {
		$sql = 'SELECT '.$columns.' FROM {'.$table.'}';
		if (!empty($select)) {
			$sql .= ' WHERE '.$select;
			$params = $selectparams;
		} else {
			$params = [];
		}

		// Sorting.
		$sql .= $this->generate_order_clause($order);

		// Record limiting.
		$sql .= $this->generate_limit_clause(0, 1);

		return $this->get_records_sql($sql, $params, [], static::DB_RETURN_ARRAY);
	}

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
	public function get_record_sql($sql, array $params = array(), array $returnopts = array()) {
		return $this->get_records_sql($sql, $params, $returnopts, static::DB_RETURN_ARRAY);
	}

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
	 *                                   [
	 *                                       'val1' => ['id' => 1, 'somefield' => 'val1'],
	 *                                       'val2' => ['id' => 2, 'somefield' => 'val2'],
	 *                                   ]
	 *                        idsorted: Instead of a numeric array of arrays, return an array with keys being the values of the
	 *                                  field specified, and values being the records that contain the value for that field.
	 *                                  Ex.
	 *                                  [
	 *                                      'val1' => [
	 *                                          ['id' => 1, 'somefield' => 'val1'],
	 *                                          ['id' => 2, 'somefield' => 'val1'],
	 *                                      ],
	 *                                      'val2' => [
	 *                                          ['id' => 3, 'somefield' => 'val2'],
	 *                                          ['id' => 4, 'somefield' => 'val2'],
	 *                                      ],
	 *                                  ]
	 * @return array An array of all found records. If none found, will be an empty array.
	 */
	public function get_records($table, array $conditions = array(), array $order = array(), $columns = '*', $start = 0,
								$count = null, array $returnopts = array()) {
		list($sql, $params) = $this->generate_select_sql($table, $columns, $conditions, $order, $start, $count);
		return $this->get_records_sql($sql, $params, $returnopts, static::DB_RETURN_ARRAYSET);
	}

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
										$start = 0, $count = null, array $returnopts = array()) {
		$sql = 'SELECT '.$columns.' FROM {'.$table.'}';
		if (!empty($select)) {
			$sql .= ' WHERE '.$select;
			$params = $selectparams;
		} else {
			$params = [];
		}

		// Sorting.
		$sql .= $this->generate_order_clause($order);

		// Record limiting.
		$sql .= $this->generate_limit_clause($start, $count);

		return $this->get_records_sql($sql, $params, $returnopts, static::DB_RETURN_ARRAYSET);
	}

	/**
	 * Get records with a raw SQL query.
	 *
	 * @param string $sql A raw SELECT sql query.
	 * @param array $params Array of parameters used by $sql.
	 * @param array $returnopts See $returnopts in get_records().
	 * @return array An array of all found records. If none found, will be an empty array.
	 */
	public function get_records_sql($sql, array $params = array(), array $returnopts = array(), $returntype = self::DB_RETURN_ARRAYSET) {
		$this->query($sql, $params);

		// Return Opts.
		$returnmode = 'normal';
		$returnmodeval = '';
		if (!empty($returnopts['idindexed'])) {
			$returnmode = 'idindexed';
			$returnmodeval = $returnopts['idindexed'];
		}
		if (!empty($returnopts['idsorted'])) {
			$returnmode = 'idsorted';
			$returnmodeval = $returnopts['idsorted'];
		}

		switch ($returntype) {
			case self::DB_RETURN_ARRAY:
				return $this->fetch_array();

			case self::DB_RETURN_ARRAYSET:
				$data = $this->fetch_arrayset($returnmode, $returnmodeval);
				return (!empty($data)) ? $data : [];

			case self::DB_RETURN_RECORDSET:
				return $this->fetch_recordset();
		}
	}

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
	public function get_recordset($table, array $conditions = array(), array $order = array(), $columns = '*', $start = 0, $count = null) {
		list($sql, $params) = $this->generate_select_sql($table, $columns, $conditions, $order, $start, $count);
		return $this->get_records_sql($sql, $params, [], static::DB_RETURN_RECORDSET);
	}

	/**
	 * Get recordset via SQL where.
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
	public function get_recordset_select($table, $select, array $selectparams = array(), array $order = array(), $columns = '*',
											$start = 0, $count = null) {
		$sql = 'SELECT '.$columns.' FROM {'.$table.'}';
		if (!empty($select)) {
			if (is_array($select)) {
				$select = implode(' AND ', $select);
			}
			$sql .= ' WHERE '.$select;
			$params = $selectparams;
		} else {
			$params = [];
		}

		// Sorting.
		$sql .= $this->generate_order_clause($order);

		// Record limiting.
		$sql .= $this->generate_limit_clause($start, $count);

		return $this->get_records_sql($sql, $params, [], static::DB_RETURN_RECORDSET);
	}

	/**
	 * Get recordset with a raw SQL query.
	 *
	 * @param string $sql A raw SELECT sql query.
	 * @param array $params Array of parameters used by $sql.
	 * @return \pdyn\database\DbRecordset A recordset of returned rows.
	 */
	public function get_recordset_sql($sql, array $params = array()) {
		return $this->get_records_sql($sql, $params, [], self::DB_RETURN_RECORDSET);
	}

	/**
	 * Determine the number of rows that match a list of conditions.
	 *
	 * @param string $table The table to get records from (without prefix).
	 * @param array $conditions Conditions to select records. Structured like 'field' => 'value'
	 * @return int The number of records that match the conditions.
	 */
	public function count_records($table, array $conditions = array()) {
		$sql = 'SELECT count(1) as count FROM {'.$table.'}';
		$params = [];

		if (!empty($conditions)) {
			list($where, $params) = $this->sql_from_filters($table, $conditions);
			if (!empty($where)) {
				$sql .= ' WHERE '.implode(' AND ', $where);
			}
		}

		$result = $this->get_records_sql($sql, $params);
		return (!empty($result[0]['count'])) ? (int)$result[0]['count'] : 0;
	}

	/**
	 * Determine the number of rows that match a given WHERE clause.
	 *
	 * @param string $table The table to get records from (without prefix).
	 * @param string $select A string SQL statement for the query's WHERE clause.
	 * @param array $selectparams An array of parameters used in the WHERE clause.
	 * @return int The number of records that match the conditions.
	 */
	public function count_records_select($table, $select, array $selectparams = array()) {
		$sql = 'SELECT count(1) as count FROM {'.$table.'}';
		$params = [];

		if (!empty($select)) {
			$sql .= ' WHERE '.$select;
			$params = $selectparams;
		} else {
			$params = [];
		}

		$result = $this->get_records_sql($sql, $params);
		return (!empty($result[0]['count'])) ? (int)$result[0]['count'] : 0;
	}

	/**
	 * Insert a record into the database.
	 *
	 * @param string $table The table to insert into (without prefix).
	 * @param array $record A record to insert in the form column => value.
	 * @param bool $ignore Whether to perform an INSERT IGNORE (if true) to fail silently if a unique key fails.
	 * @return int|false The auto-generated record ID or false if failure.
	 */
	public function insert_record($table, array $record, $ignore = false, $validate = true) {
		if ($validate === true) {
			$this->validate_columns($table, $record);
		}

		$columns = [];
		$params = [];
		foreach ($record as $column => $value) {
			$columns[] = $this->quote_column($column);
			$params[] = $this->cast_val_for_column($value, $table, $column);
		}

		$ignore = ($ignore === true) ? ' IGNORE' : '';
		$columns = implode(',', $columns);
		$placeholders = implode(',', array_fill(0, count($params), '?'));

		$sql = 'INSERT'.$ignore.' INTO {'.$table.'}('.$columns.') VALUES('.$placeholders.')';

		$stmt = $this->query($sql, $params);
		return (isset($stmt['last_id'])) ? $stmt['last_id'] : false;
	}

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
	public function insert_records($table, $columns, $rows, $ignore = false) {
		$numcolumns = count($columns);

		// Quote all columns.
		array_walk($columns, function(&$var) { $var = $this->quote_column($var); });

		$rowplaceholders = '('.implode(',', array_fill(0, $numcolumns, '?')).')';

		$rowsplaceholders = [];
		$params = [];
		foreach ($rows as $i => $row) {

			// Skip over incomplete or too large rows.
			if (count($row) !== $numcolumns) {
				continue;
			}

			$rowsplaceholders[] = $rowplaceholders;

			foreach ($row as $j => $value) {
				$params[] = $this->cast_val_for_column($value, $table, $columns[$j]);
			}
		}

		$ignore = ($ignore === true) ? 'IGNORE' : '';
		$sql = 'INSERT '.$ignore.' INTO {'.$table.'}('.implode(',', $columns).') VALUES'.implode(',', $rowsplaceholders);

		$this->lock_table($table);
		$stmt = $this->query($sql, $params);
		$this->unlock_tables();

		return [
			'affected_rows' => $stmt['affected_rows'],
			'last_id' => $stmt['last_id']
		];
	}

	/**
	 * Lock a table from other writes, if possible.
	 *
	 * @param string $table Table name.
	 * @return bool Success/Failure.
	 */
	protected function lock_table($table) {
		return true;
	}

	/**
	 * Unlock all tables.
	 *
	 * @return bool Success/Failure.
	 */
	protected function unlock_tables() {
		return true;
	}

	/**
	 * Update database records.
	 *
	 * @param string $table Table to update (without prefix).
	 * @param array $toupdate An array of updated data in the form column => value.
	 * @param array $conditions A list of conditions to determine which records get updated (in the form column => value)
	 * @return bool Success|Failure
	 */
	public function update_records($table, array $toupdate, array $conditions = array()) {
		if (!empty($toupdate)) {
			$where = '';
			$params = [];

			$updatedata = [];
			$this->validate_columns($table, $toupdate);
			foreach ($toupdate as $column => $value) {
				$updatedata[] = $this->quote_column($column).' = ?';
				$params[] = $this->cast_val_for_column($value, $table, $column);
			}
			$updatedata = implode(',', $updatedata);

			if (!empty($conditions)) {
				list($wherecolumns, $whereparams) = $this->sql_from_filters($table, $conditions);
				if (!empty($wherecolumns)) {
					$where = ' WHERE '.implode(' AND ', $wherecolumns);
					$params = array_merge($params, $whereparams);
				} else {
					throw new Exception('Bad filters received in update_records()', static::ERR_DB_BAD_REQUEST);
				}
			}
			$sql = 'UPDATE {'.$table.'} SET '.$updatedata.$where;
			$this->query($sql, $params);
		}
		return true;
	}

	/**
	 * Update records with a raw WHERE query.
	 *
	 * @param string $table Table to update (without prefix).
	 * @param string $select A raw string of conditions, which will be the query's WHERE section.
	 * @param array $selectparams An array of parameters used in $select.
	 * @param array $updated An array of updated data in the form column => value.
	 * @return bool Success/Failure
	 */
	public function update_records_select($table, $select, $selectparams, array $updated) {
		if (!empty($updated)) {
			$where = '';
			$params = [];

			$updatedata = [];
			$this->validate_columns($table, $updated);
			foreach ($updated as $column => $value) {
				$updatedata[] = $this->quote_column($column).' = ?';
				$params[] = $this->cast_val_for_column($value, $table, $column);
			}
			$updatedata = implode(',', $updatedata);

			if (!empty($select)) {
				$where = ' WHERE '.$select;
				$params = array_merge($params, $selectparams);
			}

			$sql = 'UPDATE {'.$table.'} SET '.$updatedata.$where;
			$this->query($sql, $params);
		}
		return true;
	}

	/**
	 * Delete records.
	 *
	 * TODO: Change return to number of records deleted.
	 *
	 * @param string $table The table to delete records from.
	 * @param array $conditions Conditions to select records to delete. Structured like 'field' => 'value'
	 * @return int Number of records deleted.
	 */
	public function delete_records($table, array $conditions = array()) {
		if (empty($conditions)) {
			$this->query('TRUNCATE TABLE {'.$table.'}');
		} else {
			list($where, $params) = $this->sql_from_filters($table, $conditions);
			$sql = 'DELETE FROM {'.$table.'} '.((!empty($where)) ? 'WHERE '.implode(' AND ', $where) : '');
			$this->query($sql, $params);
		}
		return true;
	}

	/**
	 * Delete records with a raw WHERE query.
	 *
	 * @param string $table The table to delete records from.
	 * @param array $select The raw WHERE part of the SQL query.
	 * @param array $params List of parameters used in $select.
	 */
	public function delete_records_select($table, $select, array $params = array()) {
		if (!is_string($table) || !is_string($select)) {
			throw new Exception('Bad table or select in delete_records_select', static::ERR_DB_BAD_REQUEST);
		}
		if ($select === '') {
			return $this->delete_records($table);
		}
		$sql = 'DELETE FROM {'.$table.'} WHERE '.$select;
		return $this->query($sql, $params);
	}

	/**
	 * Fetches an array representing a single row of the result.
	 *
	 * @return array|false Array if a row is available, false if not
	 */
	public function fetch_array() {
		return $this->fetch_row();
	}

	/**
	 * Fetched an array of arrays for each returned row.
	 *
	 * @param string $sortmode The mode to sort results.
	 * @param string $sortparam The parameter to sort by.
	 * @return array Arrayset of results.
	 */
	public function fetch_arrayset($sortmode = 'normal', $sortparam = '') {
		$arrayset = [];
		while ($row = $this->fetch_row()) {
			switch ($sortmode) {
				case 'normal':
					$arrayset[] = $row;
					break;

				case 'idindexed':
					if (isset($row[$sortparam])) {
						$arrayset[$row[$sortparam]] = $row;
					} else {
						$arrayset[] = $row;
					}
					break;

				case 'idsorted':
					if (isset($row[$sortparam])) {
						$arrayset[$row[$sortparam]][] = $row;
					} else {
						$arrayset[] = $row;
					}
					break;
			}
		}
		return $arrayset;
	}

	/**
	 * Generate a SELECT SQL query based on a number of factors.
	 *
	 * @param string $table The table to get the records from (without prefix).
	 * @param string $columns A comma-separated list of columns to select.
	 * @param array $conditions Conditions to select a record. Structured like 'field' => 'value'
	 * @param array $order The order to sort records. Structured like 'field' => 'ASC' or 'DESC'
	 * @param int $start The record to start at.
	 * @param int $count The number of records to return.
	 * @return array Array of an SQL query and array of parameters.
	 */
	protected function generate_select_sql($table, $columns, $conditions, $order, $start, $count) {
		if (!is_string($columns)) {
			$columns = '*';
		}

		$sql = 'SELECT '.$columns.' FROM {'.$table.'} '.str_replace(':', '_', $table).' ';
		$params = [];

		if (!empty($conditions)) {
			list($wheresql, $params) = $this->sql_from_filters($table, $conditions);

			if (!empty($wheresql)) {
				$sql .= ' WHERE '.implode(' AND ', $wheresql);
			}
		}

		// Sorting.
		$sql .= $this->generate_order_clause($order);

		// Record limiting.
		$sql .= $this->generate_limit_clause($start, $count);

		return [$sql, $params];
	}

	/**
	 * Generate the ORDER BY clause of the query using an array of fields and directions.
	 *
	 * @param array $order The order to sort records. Structured like 'field' => 'ASC' or 'DESC'
	 * @return string The generated ORDER BY clause.
	 */
	public function generate_order_clause(array $order) {
		// Sorting.
		if (!empty($order) && is_array($order)) {
			$orderby = [];
			foreach ($order as $field => $dir) {
				if (is_numeric($field)) {
					// If we have an auto-assigned it, it's a manual sort entry. Add the value.
					$orderby[] = $dir;
				} else {
					if (in_array(mb_strtolower($dir), ['asc', 'desc'])) {
						$orderby[] = $this->quote_column($field).' '.strtoupper($dir);
					}
				}
			}
			return ' ORDER BY '.implode(', ', $orderby);
		} else {
			return '';
		}
	}

	/**
	 * Generate a LIMIT SQL fragment based on start and count values.
	 *
	 * @param int $start The record to start at.
	 * @param int $count The number of records to return.
	 * @return string A SQL fragment representing the passed $start and $count parameters.
	 */
	protected function generate_limit_clause($start, $count) {
		$sql = '';
		if (!empty($start) || !empty($count)) {
			$sql .= ' LIMIT ';
			if (!empty($start) && !empty($count)) {
				$sql .= $start.','.$count;
			} elseif (!empty($start) && empty($count)) {
				$sql .= $start.',18446744073709551615';
			} elseif (empty($start) && !empty($count)) {
				$sql .= '0,'.$count;
			}
		}
		return $sql;
	}

	/**
	 * Determine whether a simple equals or an IN() filter is needed for a given field and value.
	 *
	 * @param string $field The field name.
	 * @param mixed $value The value.
	 * @return array List of a WHERE sql fragment and the associated parameters.
	 */
	public function in_or_equal($field, $value) {
		if (is_array($value)) {
			return [
				$field.' IN ('.implode(',', array_fill(0, count($value), '?')).')',
				array_values($value)
			];
		} else {
			return [
				$field.' = ?',
				[$value]
			];
		}
	}

	/**
	 * Generate a list of SQL conditions from a list of data filters.
	 *
	 * @param string $table The table we're going to be querying.
	 * @param array $filters A list of filters.
	 * @return array Array of SQL WHERE fragment (without WHERE string) and array of parameters.
	 */
	public function sql_from_filters($table, $filters) {
		$where = [];
		$params = [];
		$schema = $this->get_table_schema($table);

		foreach ($filters as $column => $val) {
			// Numeric keys allow us to add raw where conditions.
			if (is_numeric($column) && is_string($val)) {
				$where[] = $val;
				continue;
			}

			$columndatatype = $this->get_column_datatype($table, $column);
			$filter = ($val instanceof DataFilter) ? $val : new DataFilter($column, $columndatatype, $val);

			if (!empty($schema) && !isset($schema[$table]['columns'][$filter->field])) {
				throw new Exception('Bad filter encountered', static::ERR_DB_BAD_REQUEST);
			}

			if (is_array($filter->data)) {
				// If we have an array for value, it's transformed into a search for any of the values.

				if (empty($filter->data)) {
					throw new Exception('Empty values received for db filter', static::ERR_DB_BAD_REQUEST);
				}

				// Validate values.
				$filterparams = [];
				foreach ($filter->data as $val) {
					if ($this->validate_value($filter->datatype, $val) !== true) {
						$msg = 'Data passed to database was not valid. <br />';
						$msg .= 'Table: '.$table.'<br />';
						$msg .= 'Datatype: '. $filter->datatype.'<br />';
						$msg .= 'Val: '.$val.'<br />';
						$msg .= 'Field: '.$filter->field;
						throw new Exception($msg, static::ERR_DB_BAD_REQUEST);
					}
					$filterparams[] = $this->cast_val($val, $columndatatype);
				}

				$where[] .= $this->quote_column($filter->field).' IN ('.implode(',', array_fill(0, count($filter->data), '?')).')';
				$params = array_merge($params, $filterparams);
			} else {
				if ($this->validate_value($filter->datatype, $filter->data) !== true && $filter->data !== '""') {
					$msg = 'Data passed to database was not valid. <br />';
					$msg .= 'Table: '.$table.'<br />';
					$msg .= 'Datatype: '. $filter->datatype.'<br />';
					$msg .= 'Val: '.$filter->data.'<br />';
					$msg .= 'Field: '.$filter->field;
					throw new Exception($msg, static::ERR_DB_BAD_REQUEST);
				}

				// Assemble the SQL for this filter.
				$filtersql = $this->quote_column($filter->field).' '.$filter->operator.' ';
				if ($filter->data === '""') {
					// If the data is "", we're comparing against empty strings.
					$filtersql .= '""';
				} else {
					$filtersql .= '?';
					$params[] = $this->cast_val($filter->data, $columndatatype);
				}
				$where[] = $filtersql;
			}
		}

		return [$where, $params];
	}

	/**
	 * Cleans an array to remove keys that are not columns in a table.
	 *
	 * @param string $table The table the record belongs to.
	 * @param array $record A column => value array to clean.
	 * @return array The cleaned record.
	 */
	public function whitelist_columns($table, $record) {
		$schema = $this->get_table_schema($table);
		return array_intersect_key($record, $schema[$table]['columns']);
	}

	/**
	 * Validate that an array contains only keys that match columns from a given table.
	 *
	 * @throws Exception If $record does not validate.
	 * @param string $table The table to validate against.
	 * @param array $record The array to validate.
	 * @return bool Whether the array validates or not.
	 */
	protected function validate_columns($table, $record) {
		$schema = $this->get_table_schema($table);
		if (!isset($schema[$table])) {
			throw new Exception('Table not found in schema.', Exception::ERR_RESOURCE_NOT_FOUND);
		}
		$diff = array_diff_key($record, $schema[$table]['columns']);
		if (!empty($diff)) {
			$msg = 'Received invalid record for "'.$table.'". Invalid columns: '.implode(', ', array_keys($diff));
			throw new Exception($msg, static::ERR_DB_BAD_REQUEST);
		}
		return true;
	}

	/**
	 * Get the defined datatype for a table's column from the database schema.
	 *
	 * @param string $table The table to query (wihtout prefix).
	 * @param string $column The column to get the datatype for.
	 * @return string A datatype.
	 */
	public function get_column_datatype($table, $column) {
		if (!empty($this->schema)) {
			$schema = $this->get_table_schema($table);
			if (!isset($schema[$table])) {
				throw new Exception('Table not found in schema.', Exception::ERR_RESOURCE_NOT_FOUND);
			}
			$schema = $schema[$table];
			return (isset($schema['columns'][$column])) ? $schema['columns'][$column] : 'str';
		} else {
			return 'str';
		}
	}

	/**
	 * Validate a value against a datatype.
	 *
	 * @param string $datatype A datatype to validate against.
	 * @param string|int|float $val A value to validate.
	 * @return bool Whether the value is valid.
	 */
	public function validate_value($datatype, $val) {
		if (is_array($datatype)) {
			return (in_array($val, $datatype, true)) ? true : false;
		} elseif ($datatype === null) {
			return true;
		} else {
			$datatypes = static::internal_datatypes();
			if ($datatypes[$datatype]) {
				if (empty($datatypes[$datatype]['vfunc'])) {
					// Datatype has no validation defined.
					return true;
				} else {
					$vfunc = $datatypes[$datatype]['vfunc'];
					return call_user_func($vfunc, $val);
				}
			}
		}
		return false;
	}

	/**
	 * Log a query in multiple methods.
	 *
	 * @param string $sql The query SQL.
	 * @param array $params The query parameters.
	 */
	protected function log($sql, $params) {
		$log_explain = false;
		$log_to_file = false;
		$log_internal = false;

		if ($log_internal === true) {
			$this->log_internal($sql, $params);
		}
		if ($log_to_file === true ) {
			$this->log_errorlog($sql, $params);
		}
		if ($log_explain === true) {
			$this->log_explain($sql, $params);
		}
	}

	/**
	 * Log an EXPLAIN of the query.
	 *
	 * @param string $sql The query SQL.
	 * @param array $params The query parameters.
	 */
	protected function log_explain($sql, $params) {
		if (mb_strpos($sql, 'EXPLAIN') !== 0 && mb_strpos($sql, 'SELECT') === 0 && !empty($this->logger)) {
			$sqlexp = 'EXPLAIN '.$sql;
			$info = [];
			$this->query($sqlexp, $params);
			$info = $this->fetch_array();
			$out = htmlentities($sqlexp).'<br /><table>';
			foreach ($info as $k => $v) {
				$out .= '<tr><td style="text-align:right;"><b>'.$k.'</b></td><td>'.$v.'</td>';
			}
			$out .= '</table>';
			$this->logger->debug($out);
		}
	}

	/**
	 * Log the query within the $this->query_history property.
	 *
	 * @param string $sql The query SQL.
	 * @param array $params The query parameters.
	 */
	protected function log_internal($sql, $params) {
		if (mb_strpos($sql, '?') !== false) {
			$sqlexplode = explode('?', $sql);
			$sql = '';
			foreach ($sqlexplode as $i => $part) {
				$sql .= $part;
				if (!empty($params[$i]) && is_array($params[$i])) {
					$sql .= '"'.array_shift($params[$i]).'"';
				}
			}
		}
		$this->query_history[] = $sql;
	}
}
