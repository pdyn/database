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
 * Tests a DbDriver's StructureManagerInterface implementation.
 *
 * @codeCoverageIgnore
 */
abstract class StructureManagerTestAbstract extends \PHPUnit_Framework_TestCase {
	/** The prefix to use for any created database tables. */
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
		$this->DB->insert_record('config', ['component' => 'core', 'name' => 'db_version', 'val' => '1.5']);
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
	 * Test creating and dropping a table.
	 */
	public function test_create_drop_table() {
		// Assert Starting State.
		$expected_starting_tables = array('simplekv', 'config');
		$starting_tables = $this->DB->get_tables(false);
		$this->assertEquals(sort($expected_starting_tables), sort($starting_tables));

		// Create Table.
		$this->DB->structure()->create_table('testtable');
		$tables_post_create = $this->DB->get_tables(false);
		$expected_tables_post_create = array('simplekv', 'config', 'testtable');
		$this->assertEquals(sort($expected_tables_post_create), sort($tables_post_create));

		// Drop table.
		$this->DB->structure()->drop_table('testtable');
		$tables_post_drop = $this->DB->get_tables(false);
		$expected_tables_post_drop = array('simplekv', 'config');
		$this->assertEquals(sort($expected_tables_post_drop), sort($tables_post_drop));
	}

	/**
	 * Test renaming a column.
	 */
	public function test_rename_column() {
		// Setup.
		$this->DB->structure()->create_table('testtable');
		$this->DB->insert_record('testtable', array('value' => 'testvalue', 'col1' => '2', 'col2' => '3'));
		$this->DB->set_schema('\pdyn\database\tests\lib\DbTestSchema2');

		// Perform.
		$this->DB->structure()->rename_column('testtable', 'col1', 'newcol1');

		// Verify
		$records = $this->DB->get_records('testtable');
		$expected = array(
			array('id' => 1, 'value' => 'testvalue', 'newcol1' => '2', 'col2' => '3')
		);
		$this->assertEquals($expected, $records);

		// Reset.
		$this->DB->set_schema('\pdyn\database\tests\lib\DbTestSchema');
		$this->DB->structure()->drop_table('testtable');
	}

	/**
	 * Test adding a column.
	 */
	public function test_add_column() {
		// Setup.
		$this->DB->structure()->create_table('testtable');
		$this->DB->insert_record('testtable', array('value' => 'testvalue', 'col1' => '2', 'col2' => '3'));
		$this->DB->set_schema('\pdyn\database\tests\lib\DbTestSchema2');

		// Test non-existant column
		try {
			$this->DB->structure()->add_column('testtable', 'nonexistent');
			$this->assertTrue(false);
		} catch (\Exception $e) {
			$this->assertTrue(true);
			$this->assertEquals(Exception::ERR_BAD_REQUEST, $e->getCode());
		}

		// Test existant column
		$this->DB->structure()->add_column('testtable', 'col3');
		$records = $this->DB->get_records('testtable');
		$expected = array(
			array('id' => 1, 'value' => 'testvalue', 'col1' => '2', 'col2' => '3', 'col3' => '0')
		);
		$this->assertEquals($expected, $records);

		// Reset.
		$this->DB->set_schema('\pdyn\database\tests\lib\DbTestSchema');
		$this->DB->structure()->drop_table('testtable');
	}

	/**
	 * Test updating a column.
	 */
	public function test_update_column() {
		// Setup.
		$this->DB->structure()->create_table('testtable');
		$this->DB->insert_record('testtable', array('value' => 'testvalue', 'col1' => '2', 'col2' => '3'));
		$this->DB->set_schema('\pdyn\database\tests\lib\DbTestSchema2');

		// Test non-existant column.
		/*
		try {
			$this->DB->structure()->update_column('testtable', 'nonexistent');
			$this->assertTrue(false);
		} catch (\Exception $e) {
			$this->assertTrue(true);
			$this->assertEquals(Exception::ERR_BAD_REQUEST, $e->getCode());
		}*/

		// Test existant column
		$this->DB->structure()->update_column('testtable', 'col2');
		$this->DB->update_records('testtable', array('col2' => 'testcol2'), array('id' => 1));
		$records = $this->DB->get_records('testtable');
		$expected = array(
			array('id' => 1, 'value' => 'testvalue', 'col1' => '2', 'col2' => 'testcol2')
		);
		$this->assertEquals($expected, $records);

		// Reset.
		$this->DB->set_schema('\pdyn\database\tests\lib\DbTestSchema');
		$this->DB->structure()->drop_table('testtable');
	}

	/**
	 * Test adding an index.
	 */
	public function test_add_index() {
		// Setup.
		$this->DB->structure()->create_table('testtable');
		$this->DB->set_schema('\pdyn\database\tests\lib\DbTestSchema2');

		// Perform.
		try {
			$this->DB->structure()->add_index('testtable', 'nonexistent');
			$this->assertTrue(false);
		} catch (\Exception $e) {
			$this->assertTrue(true);
			$this->assertEquals(Exception::ERR_BAD_REQUEST, $e->getCode());
		}
		try {
			$this->DB->structure()->add_index('testtable', 'col2');
			$this->assertTrue(true);
		} catch (\Exception $e) {
			$this->assertTrue(false);
		}

		// Reset.
		$this->DB->set_schema('\pdyn\database\tests\lib\DbTestSchema');
		$this->DB->structure()->drop_table('testtable');
	}
}
