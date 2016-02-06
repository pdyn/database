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
 * A implementation of the PDO DbDriver for Sqlite-specific quirks.
 */
class DbDriver extends \pdyn\database\pdo\DbDriver {
	/**
	 * Determine whether a table exists in the database.
	 *
	 * @param string $table The name of the table (without prefix).
	 * @return bool Exists/Not Exists
	 */
	public function table_exists($table) {
		if (is_string($table) || is_numeric($table)) {
			$this->query('SELECT * FROM sqlite_master WHERE type="table" AND name="{'.$table.'}"');
			$result = $this->fetch_arrayset();
			return (!empty($result)) ? true : false;
		} else {
			return false;
		}
	}

	/**
	 * Get a list of tables in the database.
	 *
	 * @param bool $schema Whether to use the supplied schema (if true), or whether to query the database (if false)
	 * @return array Array of tables (without prefix)
	 */
	public function get_tables($schema = true) {
		if ($schema === false) {
			$tables_raw = $this->get_recordset_sql('SELECT * FROM sqlite_master WHERE type="table"');
			$tables = [];
			foreach ($tables_raw as $row) {
				$table = $row['name'];
				if (mb_strpos($table, $this->prefix) === 0) {
					$tables[] = mb_substr($table, mb_strlen($this->prefix));
				}
			}
			return $tables;
		} else {
			return parent::get_tables(true);
		}
	}

	/**
	 * Get a list of datatypes supported by the driver.
	 *
	 * @return array Array of datatypes.
	 */
	public static function internal_datatypes() {
		return [
			'email' => [
				'vfunc' => '\pdyn\datatype\Validator::email',
				'sql_datatype' => 'TEXT NOT NULL DEFAULT \'\''
			],
			'timestamp' => [
				'vfunc' => '\pdyn\datatype\Validator::timestamp',
				'sql_datatype' => 'INTEGER NOT NULL DEFAULT \'0\''
			],
			'str' => [
				'vfunc' => '\pdyn\datatype\Validator::stringlike',
				'sql_datatype' => 'VARCHAR(255) NOT NULL DEFAULT \'\'',
			],
			'smallstr' => [
				'vfunc' => '\pdyn\datatype\Validator::stringlike',
				'sql_datatype' => 'VARCHAR(255) NOT NULL DEFAULT \'\''
			],
			'text' => [
				'vfunc' => '\pdyn\datatype\Validator::stringlike',
				'sql_datatype' => 'TEXT NOT NULL DEFAULT \'\''
			],
			'longtext' => [
				'vfunc' => '\pdyn\datatype\Validator::stringlike',
				'sql_datatype' => 'TEXT NOT NULL DEFAULT \'\''
			],
			'filename' => [
				'vfunc' => '\pdyn\datatype\Validator::filename',
				'sql_datatype' => 'VARCHAR(255) NOT NULL DEFAULT \'\''
			],
			'int' => [
				'vfunc' => '\pdyn\datatype\Validator::intlike',
				'sql_datatype' => 'INTEGER NOT NULL DEFAULT \'0\''
			],
			'bigint' => [
				'vfunc' => '\pdyn\datatype\Validator::intlike',
				'sql_datatype' => 'BIGINT NOT NULL DEFAULT \'0\''
			],
			'float' => [
				'vfunc' => '\pdyn\datatype\Validator::float',
				'sql_datatype' => ''
			],
			'id' => [
				'vfunc' => '\pdyn\datatype\Id::validate',
				'sql_datatype' => 'INTEGER NOT NULL DEFAULT \'0\''
			],
			'bool' => [
				'vfunc' => '\pdyn\datatype\Validator::boollike',
				'sql_datatype' => 'TINYINT(1) NOT NULL DEFAULT \'0\''
			],
			'user_id' => [
				'vfunc' => '\pdyn\datatype\Id::validate',
				'sql_datatype' => 'INTEGER NOT NULL DEFAULT \'0\''
			],
			'url' => [
				'vfunc' => '\pdyn\datatype\Url::validate',
				'sql_datatype' => 'TEXT NOT NULL DEFAULT \'\''
			],
			'mime' => [
				'vfunc' => '\pdyn\datatype\Validator::mime',
				'sql_datatype' => 'TEXT NOT NULL DEFAULT \'\''
			],
		];
	}

	/**
	 * Return a StructureManager implementation for database structure changes.
	 *
	 * @return \pdyn\database\pdo\sqlite\StructureManager A structure manager instance.
	 */
	public function structure() {
		return new StructureManager($this);
	}

	/**
	 * Transform a column name to quote it within a Query (i.e. add ` for mysql)
	 *
	 * @param string $column Column name.
	 * @return string Quoted column.
	 */
	public function quote_column($column) {
		return $column;
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
			$this->query('DELETE FROM {'.$table.'}');
			$this->query('UPDATE sqlite_sequence SET seq = 0 WHERE name = "{'.$table.'}"');
		} else {
			list($where, $params) = $this->sql_from_filters($table, $conditions);
			$sql = 'DELETE FROM {'.$table.'} '.((!empty($where)) ? 'WHERE '.implode(' AND ', $where) : '');
			$this->query($sql, $params);
		}
		return true;
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
				$sql .= $start.',9223372036854775807';
			} elseif (empty($start) && !empty($count)) {
				$sql .= '0,'.$count;
			}
		}
		return $sql;
	}
}
