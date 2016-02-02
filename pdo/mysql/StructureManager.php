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
 * StructureManagerInterface implementation for MySQL.
 */
class StructureManager implements \pdyn\database\StructureManagerInterface {

	/**
	 * Constructor
	 *
	 * @param \pdyn\database\DbDriver $dbdriver The requesting DbDriver.
	 */
	public function __construct(\pdyn\database\DbDriver &$dbdriver) {
		$this->dbdriver =& $dbdriver;
	}

	/**
	 * Convert one of our datatypes into a datatype that the driver can understand.
	 *
	 * @param string $datatype The name of one of our datatypes.
	 * @param bool $is_primary_key Whether the column is a primary key.
	 * @return string The driver datatype.
	 */
	protected function get_column_sql_datatype($datatype, $is_primary_key=false) {
		$internal_datatypes = $this->dbdriver->internal_datatypes();
		if (is_array($datatype)) {
			$sql_datatype = 'enum(\''.implode('\',\'', $datatype).'\') NOT NULL';
		} elseif (!empty($datatype)) {
			if ($is_primary_key === true && $datatype === 'user_id') {
				//special case for any table that uses a userid as a primary key to get defaults and auto_increment to play nicely
				$datatype = 'id';
			}
			$sql_datatype = $internal_datatypes[$datatype]['sql_datatype'];
		} else {
			$sql_datatype = 'text NOT NULL';
		}
		return $sql_datatype;
	}

	/**
	 * Create a table.
	 *
	 * @param string $table The table name, defined in the database schema (without prefix).
	 * @return bool Success/Failure.
	 */
	public function create_table($table) {
		$engine = 'InnoDB';
		$schema = $this->dbdriver->get_table_schema($table);
		$schema = $schema[$table];

		// Generate column SQL.
		$columns = array();
		if (!empty($schema['columns'])) {
			foreach ($schema['columns'] as $fieldname => $datatype) {
				$column = $this->dbdriver->quote_column($fieldname).' ';
				$is_primary_key = ($schema['keys']['PRIMARY'][0] === $fieldname) ? true : false;
				$column .= $this->get_column_sql_datatype($datatype, $is_primary_key);
				if ($is_primary_key === true && ($datatype === 'id' || $datatype === 'user_id')) {
					$column .= ' AUTO_INCREMENT';
				}
				$columns[] = $column;
			}
		}

		// Generate key SQL.
		$keys = array();
		if (!empty($schema['keys'])) {
			foreach ($schema['keys'] as $keyname => $key_info) {

				// quote columns in key definition
				$key_fields = explode(',', $key_info[0]);
				foreach ($key_fields as $i => $field) {
					$key_fields[$i] = $this->dbdriver->quote_column($field);
				}
				$key_fields = implode(',', $key_fields);

				if ($keyname === 'PRIMARY') {
					$key = 'PRIMARY KEY ('.$key_fields.')';
				} else {
					$key = ((isset($key_info[1]) && $key_info[1] === true) ? 'UNIQUE ' : '');
					if (isset($key_info[2]) && $key_info[2] === 'FULLTEXT') {
						$engine = 'MyISAM';
						$key .= 'FULLTEXT ';
					}

					$key .= 'KEY '.$this->dbdriver->quote_column($keyname).' ('.$key_fields.')';
				}
				$keys[] = $key;
			}
		}

		$table = $this->dbdriver->quote_column('{'.$table.'}');
		$sql = 'CREATE TABLE IF NOT EXISTS '.$table.' ('."\n";
		$sql .= '  '.implode(",\n  ", $columns).",\n";
		$sql .= '  '.implode(",\n  ", $keys)."\n";
		$sql .= ') ENGINE='.$engine.' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		$this->dbdriver->query($sql);
		return true;
	}

	/**
	 * Drop a table.
	 *
	 * @param string $table The table name, defined in the database schema (without prefix).
	 * @return bool Success/Failure.
	 */
	public function drop_table($table) {
		try {
			$this->dbdriver->query('DROP TABLE {'.$table.'}');
		} catch (\Exception $e) {

		}
		return true;
	}

	/**
	 * Rename a column in a table.
	 *
	 * @param string $table The name of a table (defined in schema, without prefix).
	 * @param string $oldname The current name of a column.
	 * @param string $column The desired name of the column.
	 * @return bool Success/Failure.
	 */
	public function rename_column($table, $oldname, $column) {
		$schema = $this->dbdriver->get_table_schema($table);
		$datatype = $schema[$table]['columns'][$column];
		$is_primary_key = ($schema[$table]['keys']['PRIMARY'][0] === $column) ? true : false;
		$sql_datatype = $this->get_column_sql_datatype($datatype);

		$sql = 'ALTER TABLE ';
		$sql .= $this->dbdriver->quote_column('{'.$table.'}');
		$sql .= ' CHANGE ';
		$sql .= $this->dbdriver->quote_column($oldname).' '.$this->dbdriver->quote_column($column).' '.$sql_datatype;

		if ($is_primary_key === true && ($datatype === 'id' || $datatype === 'user_id')) {
			$sql .= ' AUTO_INCREMENT';
		}

		$this->dbdriver->query($sql);
		return true;
	}

	/**
	 * Update a column data type.
	 *
	 * @param string $table The name of a table (defined in schema, without prefix).
	 * @param string $column The desired name of the column.
	 * @return bool Success/Failure.
	 */
	public function update_column_type($table, $column) {
		return $this->rename_column($table, $column, $column);
	}

	/**
	 * Add a column to a table.
	 *
	 * @param string $table The table name, defined in the database schema (without prefix).
	 * @param string $column The column name, defined in the database schema.
	 * @return bool Success/Failure.
	 */
	public function add_column($table, $column) {
		$schema = $this->dbdriver->get_table_schema($table);
		$schema = $schema[$table];
		if (!isset($schema['columns'][$column])) {
			$driverclass = get_class($this->dbdriver);
			throw new Exception('Column is not in table schema.', $driverclass::ERR_DB_BAD_REQUEST);
		}
		$after_sql = '';
		$last_column = '';
		foreach ($schema['columns'] as $fieldname => $datatype) {
			if ($fieldname === $column) {
				if (!empty($last_column)) {
					$after_sql = 'AFTER '.$this->dbdriver->quote_column($last_column);
				}
				break;
			}
			$last_column = $fieldname;
		}
		$sql_datatype = $this->get_column_sql_datatype($schema['columns'][$column]);

		$sql = 'ALTER TABLE ';
		$sql .= $this->dbdriver->quote_column('{'.$table.'}');
		$sql .= ' ADD '.$this->dbdriver->quote_column($column).' '.$sql_datatype.' '.$after_sql;
		$this->dbdriver->query($sql);
		return true;
	}

	/**
	 * Modify a column to make it match what's defined in the schema.
	 *
	 * @param string $table The table the column is in (without prefix).
	 * @param string $column The column to update.
	 * @return bool Success/Failure.
	 */
	public function update_column($table, $column) {
		$schema = $this->dbdriver->get_table_schema($table);
		if (!isset($schema[$table]['columns'][$column])) {
			$driverclass = get_class($this->dbdriver);
			throw new Exception('Column is not in table schema', $driverclass::ERR_DB_BAD_REQUEST);
		}
		$sql_datatype = $this->get_column_sql_datatype($schema[$table]['columns'][$column]);

		$sql = 'ALTER TABLE ';
		$sql .= $this->dbdriver->quote_column('{'.$table.'}');
		$sql .= ' CHANGE '.$this->dbdriver->quote_column($column).' '.$this->dbdriver->quote_column($column).' '.$sql_datatype;
		$this->dbdriver->query($sql);
		return true;
	}

	/**
	 * Drop a column.
	 *
	 * @param string $table The table name, defined in the database schema.
	 * @param string $column The column name.
	 * @return bool Success/Failure.
	 */
	public function drop_column($table, $column) {
		$schema = $this->dbdriver->get_table_schema($table);
		if (!isset($schema[$table])) {
			$driverclass = get_class($this->dbdriver);
			throw new Exception('Table not found in table schema', $driverclass::ERR_DB_BAD_REQUEST);
		}

		$sql = 'ALTER TABLE ';
		$sql .= $this->dbdriver->quote_column('{'.$table.'}');
		$sql .= 'DROP ';
		$sql .= $this->dbdriver->quote_column($column);
		$this->dbdriver->query($sql);
		return true;
	}

	/**
	 * Add an index to a column.
	 *
	 * @param string $table The table name, defined in the database schema.
	 * @param string $column The column name, defined in the database schema.
	 * @return bool Success/Failure.
	 */
	public function add_index($table, $column) {
		$schema = $this->dbdriver->get_table_schema($table);
		if (strpos($column, ',') === false) {
			if (!isset($schema[$table]['columns'][$column])) {
				$driverclass = get_class($this->dbdriver);
				throw new Exception('Column is not in table schema', $driverclass::ERR_DB_BAD_REQUEST);
			}
			$sql = 'ALTER TABLE '.$this->dbdriver->quote_column('{'.$table.'}').' ADD INDEX ( '.$this->dbdriver->quote_column($column).' )';
			$this->dbdriver->query($sql);
		} else {
			// Multicolumn index, do it raw.
			$sql = 'ALTER TABLE '.$this->dbdriver->quote_column('{'.$table.'}').' ADD INDEX ('.$column.')';
			$this->dbdriver->query($sql);
		}
		return true;
	}
}
