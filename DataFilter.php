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
 * A class representing a single filter in a database query.
 *
 * Is made up of a field, a value, and an operator.
 */
class DataFilter {
	/** @var array An array of valid comparison operators. */
	protected $valid_comp_op = array('=', '<', '>', '<=', '>=', '!=');

	/** @var array An array representation of the filter. */
	protected $val;

	/**
	 * Constructor.
	 * @param string $field The field name.
	 * @param string $data_type The type of data expected.
	 * @param mixed $data The data to compare.
	 * @param string $operator The comparison operator.
	 */
	public function __construct($field, $data_type, $data, $operator='=') {

		if (!in_array($operator, $this->valid_comp_op, true)) {
			throw new \Exception('Operator passed to create get_data filter was not a valid operator', 400);
		}
		if (!is_array($data) && !is_scalar($data)) {
			throw new \Exception('Bad data sent to a DataFilter', 400);
		}

		$this->val = [
			'field' => $field,
			'data_type' => $data_type,
			'data' => $data,
			'operator' => $operator,
		];
		$this->field = $field;
		$this->datatype = $data_type;
		$this->data = $data;
		$this->operator = (in_array($operator, $this->valid_comp_op)) ? $operator : '=';
	}

	/**
	 * Get an array representation of the filter.
	 *
	 * @return array The array representation of the filter.
	 */
	public function val() {
		return $this->val;
	}

	/**
	 * Magic method allowing read-only protected properties.
	 *
	 * @param string $name The name of the property to get.
	 * @return mixed The value of the property.
	 */
	public function __get($name) {
		return (isset($this->$name)) ? $this->$name : null;
	}

	/**
	 * Determine whether a protected property is set.
	 *
	 * @param string $name The name of the property to check.
	 * @return bool Whether the property is set.
	 */
	public function __isset($name) {
		return (isset($this->$name)) ? true : false;
	}
}
