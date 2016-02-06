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

namespace pdyn\database\tests\lib;

use \pdyn\base\Exception;

/**
 * An abstract test framework to test database drivers.
 */
abstract class DbDriverTestAbstract extends \PHPUnit_Framework_TestCase {
	/** The prefix to use for tables created during the test. */
	const DBPREFIX = 'pdyndatabasetest_';

	/**
	 * Construct the database driver.
	 *
	 * @return \pdyn\database\DbDriverInterface The database driver.
	 */
	abstract public function construct_driver();

	/**
	 * Run the test (wrapper around phpunit function)
	 */
	public function runBare() {
		if (file_exists(__DIR__.'/../../phpunit_config.php')) {
			require_once(__DIR__.'/../../phpunit_config.php');
		}

		if (!empty($DB)) {
			$DB2 = clone $DB;
		}
		$DB = $this->construct_driver();
		$this->DB = $DB;
		$this->initialize_database();

		try {
			parent::runBare();
		} catch (\Exception $e) {
			$this->clean_database();
			if (!empty($DB2)) {
				$DB = clone $DB2;
			}
			throw $e;
		}

		$this->clean_database();
		if (!empty($DB2)) {
			$DB = clone $DB2;
		}
	}

	/**
	 * Initialize the database.
	 */
	public function initialize_database() {
		// Create structure. We don't create 'testtable' as it is used to test creation/dropping.
		$this->DB->structure()->create_table('simplekv');
		$this->DB->structure()->create_table('config');

		// Initial data.
		$this->DB->delete_records('config');
	}

	/**
	 * Reset database after test.
	 */
	public function clean_database() {
		$this->DB->set_prefix(static::DBPREFIX);
		$tables = $this->DB->get_tables();
		foreach ($tables as $table) {
			$this->DB->query('DROP TABLE IF EXISTS {'.$table.'}');
		}
	}

	/**
	 * Dataprovider for test_set_prefix().
	 *
	 * @return array Array of arrays of test parameters.
	 */
	public function dataprovider_test_set_prefix() {
		return [
			[true, false, static::DBPREFIX],
			[false, false, static::DBPREFIX],
			[null, false, static::DBPREFIX],
			['', false, static::DBPREFIX],
			[[], false, static::DBPREFIX],
			['te!@#$%^&*()+={}[]:;\'"<>,.?/`~st', true, 'test'],
			['te-_1st', true, 'te-_1st'],
			['test', true, 'test'],
		];
	}

	/**
	 * Tests setting and getting the prefix.
	 *
	 * @dataProvider dataprovider_test_set_prefix
	 */
	public function test_set_prefix($prefix, $expected_return, $expected_prefix) {
		$actual_return = $this->DB->set_prefix($prefix);
		$actual_prefix = $this->DB->get_prefix();

		$this->assertEquals($expected_return, $actual_return);
		$this->assertEquals($expected_prefix, $actual_prefix);
	}

	/**
	 * Test $DB->get_tables()
	 */
	public function test_get_tables() {
		$actual_tables = $this->DB->get_tables();
		$expected_tables = ['testtable', 'simplekv', 'config'];
		$this->assertEquals($expected_tables, $actual_tables);
	}

	/**
	 * Dataprovider for test_get_table_schema().
	 *
	 * @return array Array of arrays of test parameters.
	 */
	public function dataprovider_test_get_table_schema() {
		$schema_columns = [
			'testtable' => [
				'id' => 'id',
				'value' => 'text',
				'col1' => 'int',
				'col2' => 'int',
			],
			'simplekv' => [
				'id' => 'id',
				'key' => 'str',
				'value' => 'text',
			],
			'config' => [
				'id' => 'id',
				'component' => 'str',
				'name' => 'str',
				'val' => 'longtext',
			],
		];

		$schema_indexes = [
			'testtable' => [
				'PRIMARY' => ['id', true],
			],
			'simplekv' => [
				'PRIMARY' => ['id', true],
				'key' => ['key', false],
			],
			'config' => [
				'PRIMARY' => ['id', true],
				'component' => ['component', false],
				'componentkey' => ['component,name', true],
			],
		];

		$schema_full = [];
		foreach ($schema_columns as $table => $columns) {
			$schema_full[$table]['columns'] = $columns;
			$schema_full[$table]['keys'] = $schema_indexes[$table];
		}

		$schema_simplekv_config = [];
		foreach (['simplekv', 'config'] as $table) {
			$schema_simplekv_config[$table] = [
				'columns' => $schema_columns[$table],
				'keys' => $schema_indexes[$table]
			];
		}

		$simplekv_indexes = ['simplekv' => ['keys' => $schema_indexes['simplekv']]];
		$simplekv_columns = ['simplekv' => ['columns' => $schema_columns['simplekv']]];
		$simplekv_full = [
			'simplekv' => [
				'columns' => $schema_columns['simplekv'],
				'keys' => $schema_indexes['simplekv']
			],
		];

		return [
			['*', $schema_full],
			[['simplekv', 'config'], $schema_simplekv_config],
			['simplekv', $simplekv_full],
		];
	}

	/**
	 * Test getting table schemas.
	 *
	 * @dataProvider dataprovider_test_get_table_schema
	 */
	public function test_get_table_schema($table, $expected_return) {
		$actual_return = $this->DB->get_table_schema($table);
		$this->assertEquals($expected_return, $actual_return);
	}

	/**
	 * Test get_column_datatype method.
	 */
	public function test_get_column_datatype() {
		// Test invalid table call.
		try {
			$datatype = $this->DB->get_column_datatype('nonexistent', 'none');
			$this->assertTrue(false); // should never get here
		} catch (\Exception $e) {
			$this->assertTrue(true);
			$this->assertEquals(Exception::ERR_RESOURCE_NOT_FOUND, $e->getCode());
		}

		// Test invalid column call.
		$datatype = $this->DB->get_column_datatype('simplekv', 'none');
		$this->assertFalse(false);

		// Test valid call.
		$datatype = $this->DB->get_column_datatype('simplekv', 'key');
		$this->assertEquals('str', $datatype);
	}

	/**
	 * Test set_schema method.
	 */
	public function test_set_schema() {
		$result = $this->DB->set_schema(true);
		$this->assertFalse($result);

		$result = $this->DB->set_schema(false);
		$this->assertFalse($result);

		$result = $this->DB->set_schema(null);
		$this->assertFalse($result);

		$result = $this->DB->set_schema('nonexistant');
		$this->assertFalse($result);

		$result = $this->DB->set_schema('\pdyn\database\tests\lib\DbTestSchema2');
		$this->assertTrue($result);

		$result = $this->DB->set_schema('\pdyn\database\tests\lib\DbTestSchema');
		$this->assertTrue($result);
	}

	/**
	 * Test table_exists method.
	 */
	public function test_table_exists() {
		$bad_inputs = [true, false, null, [], '', 1, new \stdClass, 'nonexistent'];
		foreach ($bad_inputs as $input) {
			$return = $this->DB->table_exists($input);
			$this->assertFalse($return);
		}

		$good_inputs = ['simplekv'];
		foreach ($good_inputs as $input) {
			$return = $this->DB->table_exists($input);
			$this->assertTrue($return);
		}
	}

	/**
	 * Test inserting records.
	 */
	public function test_insert() {
		// Test insert array.
		$id = $this->DB->insert_record('simplekv', ['key' => 'testkey1', 'value' => 'testvalue1']);
		$id = $this->DB->insert_record('simplekv', ['key' => 'testkey2', 'value' => 'testvalue2'], null);

		// Verify.
		$this->DB->query('SELECT * FROM {simplekv}');
		$rows = $this->DB->fetch_arrayset();
		$this->assertEquals(2, count($rows));
		$expected_row = ['id' => 1, 'key' => 'testkey1', 'value' => 'testvalue1'];
		$this->assertEquals($expected_row, $rows[0]);

		// Test insert rows.
		$fields = ['key', 'value'];
		$rows = [
			['testkey3', 'testvalue3'],
			['testkey4', 'testvalue4'],
			['testkey5', 'testvalue5'],
			['testkey6', 'testvalue6'],
		];
		$return = $this->DB->insert_records('simplekv', $fields, $rows);

		// Verify.
		$expected_return = [
			'affected_rows' => 4,
			'last_id' => 6
		];
		$this->assertEquals($expected_return, $return);
		$this->DB->query('SELECT * FROM {simplekv}');
		$rows = $this->DB->fetch_arrayset();
		$this->assertEquals(6, count($rows));
		$expected_rows = [
			['id' => 1, 'key' => 'testkey1', 'value' => 'testvalue1'],
			['id' => 2, 'key' => 'testkey2', 'value' => 'testvalue2'],
			['id' => 3, 'key' => 'testkey3', 'value' => 'testvalue3'],
			['id' => 4, 'key' => 'testkey4', 'value' => 'testvalue4'],
			['id' => 5, 'key' => 'testkey5', 'value' => 'testvalue5'],
			['id' => 6, 'key' => 'testkey6', 'value' => 'testvalue6'],
		];
		$this->assertEquals($expected_rows, $rows);
	}

	/**
	 * Test get_record method.
	 */
	public function test_get_record() {
		$testrecs = [
			1 => ['id' => 1, 'key' => 'testkey1', 'value' => 'testvalue1'],
			2 => ['id' => 2, 'key' => 'testkey2', 'value' => 'testvalue2'],
			3 => ['id' => 3, 'key' => 'testkey3', 'value' => 'testvalue3'],
			4 => ['id' => 4, 'key' => 'testkey3', 'value' => 'testvalue4'],
		];

		// Insert test recs.
		foreach ($testrecs as $record) {
			$insert_rec = $record;
			unset($insert_rec['id']);
			$this->DB->insert_record('simplekv', $insert_rec);
		}

		// Test no params.
		$record = $this->DB->get_record('simplekv');
		$this->assertEquals($testrecs[1], $record);

		// Test filter param.
		$record = $this->DB->get_record('simplekv', ['key' => 'testkey2']);
		$this->assertEquals($testrecs[2], $record);

		// Test sort param.
		$record = $this->DB->get_record('simplekv', [], ['id' => 'DESC']);
		$this->assertEquals($testrecs[4], $record);
		$record = $this->DB->get_record('simplekv', [], ['id' => 'ASC']);
		$this->assertEquals($testrecs[1], $record);
		$record = $this->DB->get_record('simplekv', [], ['key' => 'DESC', 'value' => 'ASC']);
		$this->assertEquals($testrecs[3], $record);
		$record = $this->DB->get_record('simplekv', [], ['key' => 'DESC', 'value' => 'DESC']);
		$this->assertEquals($testrecs[4], $record);

		// Test filter and sort param.
		$record = $this->DB->get_record('simplekv', ['key' => 'testkey3'], ['id' => 'DESC']);
		$this->assertEquals($testrecs[4], $record);
		$record = $this->DB->get_record('simplekv', ['key' => 'testkey3'], ['id' => 'ASC']);
		$this->assertEquals($testrecs[3], $record);
	}

	/**
	 * Create some test records to test record retrieval.
	 *
	 * @return array Array of created records to assert against.
	 */
	protected function create_test_records() {
		$testrecs = [
			1 => ['id' => 1, 'key' => 'testkey1', 'value' => 'testvalue1'],
			2 => ['id' => 2, 'key' => 'testkey1', 'value' => 'testvalue2'],
			3 => ['id' => 3, 'key' => 'testkey2', 'value' => 'testvalue3'],
			4 => ['id' => 4, 'key' => 'testkey3', 'value' => 'testvalue4'],
		];

		// Insert test recs.
		foreach ($testrecs as $record) {
			$insert_rec = $record;
			unset($insert_rec['id']);
			$this->DB->insert_record('simplekv', $insert_rec);
		}

		return $testrecs;
	}

	/**
	 * Test get_records method.
	 */
	public function test_get_records() {
		$testrecs = $this->create_test_records();

		// Test no params.
		$records = $this->DB->get_records('simplekv');
		$this->assertEquals([$testrecs[1], $testrecs[2], $testrecs[3], $testrecs[4]], $records);

		// Test filters.
		$records = $this->DB->get_records('simplekv', array('key' => 'testkey1'));
		$this->assertEquals(array($testrecs[1], $testrecs[2]), $records);
		$records = $this->DB->get_records('simplekv', array('key' => 'testkey1', 'value' => 'testvalue2'));
		$this->assertEquals(array($testrecs[2]), $records);

		// Test sort.
		$records = $this->DB->get_records('simplekv', array(), array('id' => 'DESC'));
		$this->assertEquals(array($testrecs[4], $testrecs[3], $testrecs[2], $testrecs[1]), $records);
		$records = $this->DB->get_records('simplekv', array(), array('id' => 'ASC'));
		$this->assertEquals(array($testrecs[1], $testrecs[2], $testrecs[3], $testrecs[4]), $records);
		$records = $this->DB->get_records('simplekv', array(), array('key' => 'ASC', 'value' => 'DESC'));
		$this->assertEquals(array($testrecs[2], $testrecs[1], $testrecs[3], $testrecs[4]), $records);

		// Test filters and sort.
		$records = $this->DB->get_records('simplekv', array('key' => 'testkey1'), array('value' => 'DESC'));
		$this->assertEquals(array($testrecs[2], $testrecs[1]), $records);

		// Test ranges and limits.
		$records = $this->DB->get_records('simplekv', array(), array('id' => 'ASC'), '*', 1);
		$expected = array($testrecs[2], $testrecs[3], $testrecs[4]);
		$this->assertEquals($expected, $records);

		$records = $this->DB->get_records('simplekv', array(), array('id' => 'ASC'), '*', 0, 2);
		$expected = array($testrecs[1], $testrecs[2]);
		$this->assertEquals($expected, $records);

		$records = $this->DB->get_records('simplekv', array(), array('id' => 'ASC'), '*', 1, 2);
		$expected = array($testrecs[2], $testrecs[3]);
		$this->assertEquals($expected, $records);
	}

	/**
	 * Assert a recordset against an array of expected records.
	 *
	 * @param array $expected_recs Array of expected records.
	 * @param \pdyn\database\DbRecordset $recordset The recordset to assert.
	 */
	protected function assertRecordset($expected_recs, $recordset) {
		$this->assertTrue(($recordset instanceof \pdyn\database\DbRecordset));
		$i = 0;
		foreach ($recordset as $record) {
			$this->assertTrue(isset($expected_recs[$i]));
			$this->assertEquals($expected_recs[$i], $record);
			$i++;
		}
	}

	/**
	 * Test get_recordset method.
	 */
	public function test_get_recordset() {
		$testrecs = $this->create_test_records();

		// Test no params.
		$recordset = $this->DB->get_recordset('simplekv');
		$this->assertRecordset(array($testrecs[1], $testrecs[2], $testrecs[3], $testrecs[4]), $recordset);

		// Test filters.
		$recordset = $this->DB->get_recordset('simplekv', array('key' => 'testkey1'));
		$this->assertRecordset(array($testrecs[1], $testrecs[2]), $recordset);
		$recordset = $this->DB->get_recordset('simplekv', array('key' => 'testkey1', 'value' => 'testvalue2'));
		$this->assertRecordset(array($testrecs[2]), $recordset);

		// Test sort.
		$recordset = $this->DB->get_recordset('simplekv', array(), array('id' => 'DESC'));
		$this->assertRecordset(array($testrecs[4], $testrecs[3], $testrecs[2], $testrecs[1]), $recordset);
		$recordset = $this->DB->get_recordset('simplekv', array(), array('id' => 'ASC'));
		$this->assertRecordset(array($testrecs[1], $testrecs[2], $testrecs[3], $testrecs[4]), $recordset);
		$recordset = $this->DB->get_recordset('simplekv', array(), array('key' => 'ASC', 'value' => 'DESC'));
		$this->assertRecordset(array($testrecs[2], $testrecs[1], $testrecs[3], $testrecs[4]), $recordset);

		// Test filters and sort.
		$recordset = $this->DB->get_recordset('simplekv', array('key' => 'testkey1'), array('value' => 'DESC'));
		$this->assertRecordset(array($testrecs[2], $testrecs[1]), $recordset);
	}

	/**
	 * Test count_records method.
	 */
	public function test_count_records() {
		$num = $this->DB->count_records('simplekv');
		$this->assertEquals(0, $num);

		$testrecs = $this->create_test_records();

		$num = $this->DB->count_records('simplekv');
		$this->assertEquals(4, $num);

		$num = $this->DB->count_records('simplekv', array('key' => 'testkey1'));
		$this->assertEquals(2, $num);

		$num = $this->DB->count_records('simplekv', array('key' => 'testkey2'));
		$this->assertEquals(1, $num);

		$num = $this->DB->count_records('simplekv', array('key' => 'testkey1', 'value' => 'testvalue2'));
		$this->assertEquals(1, $num);
	}

	/**
	 * Test update_records method.
	 */
	public function test_update_records() {
		$testrecs = $this->create_test_records();
		$expected = array($testrecs[1], $testrecs[2], $testrecs[3], $testrecs[4]);

		// Test updating (1).
		$this->DB->update_records('simplekv', array('value' => 'updated1'), array('key' => 'testkey1'));
		$expected[0]['value'] = 'updated1';
		$expected[1]['value'] = 'updated1';
		$rows = $this->DB->get_records('simplekv');
		$this->assertEquals($expected, $rows);

		// Test updating (2).
		$this->DB->update_records('simplekv', array('value' => 'updated2'), array('key' => 'testkey2'));
		$expected[2]['value'] = 'updated2';
		$rows = $this->DB->get_records('simplekv');
		$this->assertEquals($expected, $rows);

		// Test updating with a non-existant where condition.
		$this->DB->update_records('simplekv', array('value' => 'updated3'), array('key' => 'nonexistent'));
		$rows = $this->DB->get_records('simplekv');
		$this->assertEquals($expected, $rows);

		// Test updating all values.
		$this->DB->update_records('simplekv', array('value' => 'allupdate'));
		$expected[0]['value'] = 'allupdate';
		$expected[1]['value'] = 'allupdate';
		$expected[2]['value'] = 'allupdate';
		$expected[3]['value'] = 'allupdate';
		$rows = $this->DB->get_records('simplekv');
		$this->assertEquals($expected, $rows);
	}

	/**
	 * Test delete_records method.
	 */
	public function test_delete_records() {
		$testrecs = $this->create_test_records();

		$this->DB->delete_records('simplekv', array('key' => 'testkey2'));
		$rows = $this->DB->get_records('simplekv');
		$expected = array($testrecs[1], $testrecs[2], $testrecs[4]);
		$this->assertEquals($expected, $rows);

		$this->DB->delete_records('simplekv', array('key' => 'testkey1'));
		$rows = $this->DB->get_records('simplekv');
		$expected = array($testrecs[4]);
		$this->assertEquals($expected, $rows);

		$this->DB->delete_records('simplekv');
		$rows = $this->DB->get_records('simplekv');
		$expected = [];
		$this->assertEquals($expected, $rows);
	}

	/**
	 * Test raw query method.
	 */
	public function test_raw_query() {
		$testrecs = $this->create_test_records();

		// Test invalid query w/no params.
		try {
			$this->DB->query('abcdefg');
			$this->assertTrue(false);
		} catch (\Exception $e) {
			$this->assertEquals(Exception::ERR_BAD_REQUEST, $e->getCode());
		}

		// Test invalid query w/ params.
		try {
			$this->DB->query('abcdefg', array('test'));
			$this->assertTrue(false);
		} catch (\Exception $e) {
			$this->assertEquals(\pdyn\database\DbDriver::ERR_DB_BAD_REQUEST, $e->getCode());
		}
	}

	/**
	 * Test fetch_array method.
	 */
	public function test_fetch_array() {
		$testrecs = $this->create_test_records();

		// Test fetch_array();
		$this->DB->query('SELECT * FROM {simplekv} ORDER BY id ASC');
		$rec = $this->DB->fetch_array();
		$this->assertEquals($testrecs[1], $rec);
	}

	/**
	 * Test fetch_arrayset method.
	 */
	public function test_fetch_arrayset() {
		$testrecs = $this->create_test_records();

		// Test w/ no params.
		$this->DB->query('SELECT * FROM {simplekv} ORDER BY id ASC');
		$recs = $this->DB->fetch_arrayset();
		$expected = array($testrecs[1], $testrecs[2], $testrecs[3], $testrecs[4]);
		$this->assertEquals($expected, $recs);

		$this->DB->query('SELECT * FROM {simplekv} ORDER BY id ASC');
		$recs = $this->DB->fetch_arrayset('idindexed', 'id');
		$this->assertEquals($testrecs, $recs);

		$this->DB->query('SELECT * FROM {simplekv} ORDER BY id ASC');
		$recs = $this->DB->fetch_arrayset('idsorted', 'key');
		$expected = array(
			'testkey1' => array(
				$testrecs[1],
				$testrecs[2],
			),
			'testkey2' => array(
				$testrecs[3]
			),
			'testkey3' => array(
				$testrecs[4]
			),
		);
		$this->assertEquals($expected, $recs);

		// Test w/ params.
		$this->DB->query('SELECT * FROM {simplekv} WHERE `key` = ? ORDER BY id ASC', array('testkey1'));
		$recs = $this->DB->fetch_arrayset();
		$expected = array($testrecs[1], $testrecs[2]);
		$this->assertEquals($expected, $recs);

		$this->DB->query('SELECT * FROM {simplekv} WHERE `key` = ? ORDER BY id ASC', array('testkey1'));
		$recs = $this->DB->fetch_arrayset('idindexed', 'id');
		$expected = array(1 => $testrecs[1], 2 => $testrecs[2]);
		$this->assertEquals($expected, $recs);

		$this->DB->query(
			'SELECT * FROM {simplekv} WHERE `key` = ? OR `key` = ? OR `key` = ? ORDER BY id ASC',
			array('testkey1', 'testkey2', 'testkey3')
		);
		$recs = $this->DB->fetch_arrayset('idsorted', 'key');
		$expected = array(
			'testkey1' => array(
				$testrecs[1],
				$testrecs[2],
			),
			'testkey2' => array(
				$testrecs[3]
			),
			'testkey3' => array(
				$testrecs[4]
			),
		);
		$this->assertEquals($expected, $recs);
	}

	/**
	 * Test fetch_recordset method.
	 */
	public function test_fetch_recordset() {
		$testrecs = $this->create_test_records();

		$this->DB->query('SELECT * FROM {simplekv}');
		$recordset = $this->DB->fetch_recordset();
		$expected = array($testrecs[1], $testrecs[2], $testrecs[3], $testrecs[4]);
		$this->assertRecordset($expected, $recordset);
	}
}
