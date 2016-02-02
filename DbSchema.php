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

/**
 * Base class for defining a database schema.
 */
class DbSchema {
	/** An error code that indicates an internal error occurred. Probably a bug. */
	const ERR_DB_INTERNAL_ERROR = 500;

	final public static function get_all() {
		$schema = new \ReflectionClass(get_called_class());
		$methods = $schema->getMethods(\ReflectionMethod::IS_STATIC);
		$tables = [];
		foreach ($methods as $method) {
			if ($method->name{0} !== '_' && $method->name !== 'get_all') {
				$tables[] = $method->name;
			}
		}
		return $tables;
	}

	/**
	 * Magic method to throw an exception if there is no schema for a requested table.
	 *
	 * @param string $name The called method name.
	 * @param array $args Array of arguments.
	 */
	public static function __callStatic($name, $args) {
		throw new \Exception($name.' is not defined in database schema.', static::ERR_DB_INTERNAL_ERROR);
	}
}
