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
 * A test database schema.
 */
class DbTestSchema2 {
	/**
	 * Magic method to throw an exception if there is no schema for a requested table.
	 *
	 * @param string $name The called method name.
	 * @param array $args Array of arguments.
	 */
	public static function __callStatic($name, $args) {
		throw new Exception($name.' is not defined in database schema.', Exception::ERR_INTERNAL_ERROR);
	}

	public static function testtable($columns=true, $keys=false) {
		$ret = array();
		if ($columns === true) {
			$ret['columns'] = array(
				'id' => 'id',
				'value' => 'text',
				'newcol1' => 'int',
				'col2' => 'text',
				'col3' => 'int',
			);
		}
		if ($keys === true) {
			$ret['keys'] = array(
				'PRIMARY' => array('id', true),
			);
		}
		return $ret;
	}

	public static function simplekv($columns=true, $keys=false) {
		$ret = array();
		if ($columns === true) {
			$ret['columns'] = array(
				'id' => 'id',
				'key' => 'str',
				'value' => 'text',
			);
		}
		if ($keys === true) {
			$ret['keys'] = array(
				'PRIMARY' => array('id', true),
				'key' => array('key', false),
			);
		}
		return $ret;
	}

	public static function config() {
		$ret = [];
		if ($columns === true) {
			$ret['columns'] = [
				'id' => 'id',
				'component' => 'str',
				'name' => 'str',
				'val' => 'longtext',
			];
		}
		if ($keys === true) {
			$ret['keys'] = [
				'PRIMARY' => ['id', true],
				'component' => ['component', false],
				'componentkey' => ['component,name', true],
			];
		}
		return $ret;
	}
}
