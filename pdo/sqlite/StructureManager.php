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

namespace pdyn\database\pdo\sqlite;

use \pdyn\base\Exception;

/**
 * StructureManagerInterface implementation for SQLite.
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
			$sql_datatype = 'TEXT NOT NULL';
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
		$columns = [];
		if (!empty($schema['columns'])) {
			foreach ($schema['columns'] as $fieldname => $datatype) {
				$is_primary_key = ($schema['keys']['PRIMARY'][0] === $fieldname) ? true : false;

				$sqldatatype = mb_strtoupper($this->get_column_sql_datatype($datatype, $is_primary_key));

				if ($is_primary_key === true && ($datatype === 'id' || $datatype === 'user_id')) {
					$columns[] = $this->dbdriver->quote_column($fieldname).' INTEGER PRIMARY KEY AUTOINCREMENT';
				} else {
					$primarykey = ($is_primary_key === true) ? ' PRIMARY KEY ' : '';
					$columns[] = $this->dbdriver->quote_column($fieldname).' '.$sqldatatype.$primarykey;
				}
			}
		}

		$tableq = $this->dbdriver->quote_column('{'.$table.'}');

		// Create table.
		$sql = 'CREATE TABLE IF NOT EXISTS main.'.$tableq.' ('.implode(",\n  ", $columns).')';
		$this->dbdriver->query($sql);

		// Create indexes.
		$indexes = [];
		if (!empty($schema['keys'])) {
			foreach ($schema['keys'] as $keyname => $key_info) {
				if ($keyname === 'PRIMARY') {
					continue;
				}

				$columns = explode(',', $key_info[0]);
				$unique = (isset($key_info[1]) && $key_info[1] === true) ? ' UNIQUE' : '';
				$name = $table.'_'.implode('_', $columns);
				$columns = implode(',', $columns);
				$sql = 'CREATE'.$unique.' INDEX IF NOT EXISTS main.'.$name.' ON '.$tableq.' ('.$columns.')';
				$this->dbdriver->query($sql);
			}
		}

		return true;
	}

	/**
	 * Drop a table.
	 *
	 * @param string $table The table name, defined in the database schema (without prefix).
	 * @return bool Success/Failure.
	 */
	public function drop_table($table) {
		$schema = $this->dbdriver->get_table_schema($table);
		$schema = $schema[$table];

		$this->dbdriver->query('DROP TABLE {'.$table.'}');
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
		$tmptablename = $table.'_'.uniqid();
		$this->rename_table($table, $tmptablename);

		// Get current state + modify selected column.
		$curtablestate = $this->dbdriver->get_recordset_sql('PRAGMA table_info({'.$tmptablename.'})');
		$newtablecols = [];
		$newtablekeys = [];
		foreach ($curtablestate as $curcol) {
			$name = ($curcol['name'] === $oldname) ? $column : $curcol['name'];
			$curcol['name'] = $name;
			$newtablecols[$name] = $curcol;
		}

		$this->create_table_raw($table, $newtablecols, $newtablekeys);

		$recs = $this->dbdriver->get_records($tmptablename);
		foreach ($recs as $rec) {
			foreach ($rec as $key => $val) {
				$key = ($key === $oldname) ? $column : $key;
				$rec[$key] = $this->dbdriver->cast_val_for_column($val, $table, $key);
			}
			unset($rec[$oldname]);
			$this->dbdriver->insert_record($table, $rec, false, false);
		}
		$this->dbdriver->query('DROP TABLE {'.$tmptablename.'}');
		return true;
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

		$sqldatatype = $this->get_column_sql_datatype($schema['columns'][$column]);

		$tableq = $this->dbdriver->quote_column('{'.$table.'}');
		$columnq = $this->dbdriver->quote_column($column);

		$sql = 'ALTER TABLE '.$tableq.' ADD '.$columnq.' '.$sqldatatype;
		$this->dbdriver->query($sql);
		return true;
	}

	/**
	 * Rename a table.
	 *
	 * @param string $table The current name of the table (without prefix).
	 * @param string $newname The desired new name of the table.
	 * @return bool Success/Failure.
	 */
	protected function rename_table($table, $newname) {
		$sql = 'ALTER TABLE {'.$table.'} RENAME TO {'.$newname.'}';
		$this->dbdriver->query($sql);
		return true;
	}

	/**
	 * Create a new table, given raw table details.
	 *
	 * @param string $table Table name.
	 * @param array $columns Array of column information. Indexes are column names, values are arrays of column information.
	 *                           Column info arrays consist of:
	 *                           string name The column name
	 *                           string type The column type
	 *                           string notnull Whether the column can be null or not. If (string)1, column cannot be null.
	 *                           string dflt_value The default value of the column.
	 *                           string pk Whether the column is the primary key of the table. If (string)1, column is primary key.
	 * @param array $keys Array of table keys.
	 */
	protected function create_table_raw($table, array $columns, array $keys = array()) {
		$colentries = [];
		foreach ($columns as $name => $columninfo) {
			$colentry = $columninfo['name'].' '.$columninfo['type'];
			if (isset($columninfo['notnull']) && $columninfo['notnull'] === '1') {
				$colentry .= ' NOT NULL ';
			}
			if (isset($columninfo['dflt_value']) && $columninfo['dflt_value'] !== null) {
				$colentry .= ' DEFAULT '.$columninfo['dflt_value'];
			}
			if (isset($columninfo['pk']) && $columninfo['pk'] === '1') {
				$colentry .= ' PRIMARY KEY ';
				if (isset($keys['PRIMARY'][1]) && $keys['PRIMARY'][1] !== false) {
					$colentry .= ' AUTOINCREMENT ';
				}
			}
			$colentries[] = $colentry;
		}

		$sql = 'CREATE TABLE IF NOT EXISTS main.{'.$table.'} ('.implode(', ', $colentries).')';
		$this->dbdriver->query($sql);
	}

	/**
	 * Modify a column to make it match what's defined in the schema.
	 *
	 * @param string $table The table the column is in (without prefix).
	 * @param string $column The column to update.
	 * @return bool Success/Failure.
	 */
	public function update_column($table, $column) {
		$tmptablename = $table.'_'.uniqid();
		$this->rename_table($table, $tmptablename);

		// Get current state + modify selected column.
		$curtablestate = $this->dbdriver->get_recordset_sql('PRAGMA table_info({'.$tmptablename.'})');
		$newtablecols = [];
		$newtablekeys = [];
		foreach ($curtablestate as $curcol) {
			$newtablecols[$curcol['name']] = $curcol;
			if ($curcol['name'] === $column) {
				$newdatatype = $this->get_column_sql_datatype($this->dbdriver->get_column_datatype($table, $column));
				$newtablecols[$curcol['name']]['type'] = $newdatatype;
			}
		}

		$this->create_table_raw($table, $newtablecols, $newtablekeys);
		$recs = $this->dbdriver->get_records($tmptablename);
		foreach ($recs as $rec) {
			foreach ($rec as $key => $val) {
				$rec[$key] = $this->dbdriver->cast_val_for_column($val, $table, $key);
			}
			$this->dbdriver->insert_record($table, $rec, false, false);
		}
		$this->dbdriver->query('DROP TABLE {'.$tmptablename.'}');
		return true;
	}

	/**
	 * Drop a column.
	 *
	 * @param string $table The table name, defined in the database schema.
	 * @param string $column The column name, defined in the database schema.
	 * @return bool Success/Failure.
	 */
	public function drop_column($table, $column) {
		$tmptablename = $table.'_'.uniqid();
		$this->rename_table($table, $tmptablename);

		// Get current table state + remove column.
		$curtablestate = $this->dbdriver->get_recordset_sql('PRAGMA table_info({'.$tmptablename.'})');
		$newtablecols = [];
		$newtablekeys = [];
		foreach ($curtablestate as $curcol) {
			if ($curcol['name'] !== $column) {
				$newtablecols[$curcol['name']] = $curcol;
			}
		}

		$this->create_table_raw($table, $newtablecols, $newtablekeys);
		$recs = $this->dbdriver->get_records($tmptablename);
		foreach ($recs as $rec) {
			unset($rec[$column]);
			$this->dbdriver->insert_record($table, $rec, false, false);
		}
		$this->dbdriver->query('DROP TABLE {'.$tmptablename.'}');

		return false;
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
		if (!isset($schema[$table]['columns'][$column])) {
			$driverclass = get_class($this->dbdriver);
			throw new Exception('Column is not in table schema', $driverclass::ERR_DB_BAD_REQUEST);
		}

		$name = $table.'_'.$column;
		$sql = 'CREATE INDEX IF NOT EXISTS main.'.$name.' ON {'.$table.'} ('.$column.')';
		$this->dbdriver->query($sql);
	}
}
