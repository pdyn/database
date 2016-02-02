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

namespace pdyn\database\pdo\mysql\tests;

/**
 * Test the PDO-MySQL DbDriver's structure manager.
 *
 * @group atlas
 * @group atlas_db
 * @group atlas_db_pdo
 * @group atlas_db_pdo_mysql
 */
class StructureManagerTest extends \pdyn\database\tests\lib\StructureManagerTestAbstract {
	/**
	 * Construct the database driver.
	 *
	 * @return \pdyn\database\pdo\mysql\DbDriver The mock database driver.
	 */
	public function construct_driver() {
		global $CFG;
		if ($CFG->db_driver !== 'mysql') {
			$this->markTestSkipped('Not using MySQL driver');
			return false;
		}
		$DB = new \pdyn\database\pdo\mysql\DbDriver('\pdyn\database\tests\lib\DbTestSchema');
		$dsn = 'mysql:host='.$CFG->db_host.';dbname='.$CFG->db_database;
		$DB->connect($dsn, $CFG->db_user, $CFG->db_pass);
		$DB->set_prefix(static::DBPREFIX);
		return $DB;
	}
}
