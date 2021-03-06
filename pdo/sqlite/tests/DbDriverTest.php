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

namespace pdyn\database\pdo\sqlite\tests;

/**
 * A mock pdo-sqlite database driver allowing inspection of all properties and running of all methods.
 */
class MockDriver extends \pdyn\database\pdo\sqlite\DbDriver {
	use \pdyn\testing\AccessibleObjectTrait;
}

/**
 * Test the SQLite DbDriver.
 *
 * @group pdyn
 * @group pdyn_database
 * @group pdyn_database_sqlite
 * @codeCoverageIgnore
 */
class DbDriverTest extends \pdyn\database\tests\lib\DbDriverTestAbstract {
	/**
	 * Construct the database driver.
	 *
	 * @return \pdyn\database\pdo\sqlite\tests\MockDriver The mock database driver.
	 */
	public function construct_driver() {
		$DB = new MockDriver(['\pdyn\database\tests\lib\DbTestSchema']);
		$dsn = 'sqlite::memory:';
		$DB->connect($dsn);
		$DB->set_prefix(static::DBPREFIX);
		return $DB;
	}

	/**
	 * Test connect and disconnect methods.
	 */
	public function test_connect() {
		$DB = $this->construct_driver();
		$this->assertTrue($DB->connected);

		$DB->disconnect();
		$this->assertFalse($DB->connected);
	}

	/**
	 * Test testConnect method.
	 */
	public function test_testConnect() {
		$dsn = 'sqlite::memory:';
		$result = MockDriver::test_connect($dsn);
		$this->assertTrue($result);
	}
}
