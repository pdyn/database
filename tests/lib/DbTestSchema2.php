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
 *
 * @codeCoverageIgnore
 */
class DbTestSchema2 extends \pdyn\database\DbSchema {
	public static function testtable() {
		return [
			'columns' => [
				'id' => 'id',
				'value' => 'text',
				'newcol1' => 'int',
				'col2' => 'text',
				'col3' => 'int',
			],
			'keys' => [
				'PRIMARY' => ['id', true],
			],
		];
	}

	public static function simplekv() {
		return [
			'columns' => [
				'id' => 'id',
				'key' => 'str',
				'value' => 'text',
			],
			'keys' => [
				'PRIMARY' => ['id', true],
				'key' => ['key', false],
			],
		];
	}

	public static function config() {
		return [
			'columns' => [
				'id' => 'id',
				'component' => 'str',
				'name' => 'str',
				'val' => 'longtext',
			],
			'keys' => [
				'PRIMARY' => ['id', true],
				'component' => ['component', false],
				'componentkey' => ['component,name', true],
			],
		];
	}
}
