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
 * Various database utility functions that have yet to be integrated elsewhere.
 */
class DbUtils {
	/**
	 * Generate a unqiue text-identifier for a given table and column from any input string.
	 *
	 * Will generate a text identifier from the string (lowercase, alphanum-only), search for the value in the given table/column,
	 * and keep incrementing a suffixed counter until the value is unique.
	 *
	 * @param \pdyn\database\DbDriverInterface $DB An active database connection.
	 * @param string $input Any input text.
	 * @param string $table The table in which to ensure uniqueness.
	 * @param string $field The column in which to ensure uniqueness.
	 * @param array $restriction_list Array of values the text-identifier cannot match.
	 * @return string The generated, unique text identifier.
	 */
	public static function generate_slug(\pdyn\database\DbDriverInterface $DB, $input, $table, $field, array $restriction_list = array()) {
		$i = 0;
		while (true) {
			$slug = \pdyn\datatype\Text::make_slug($input);
			$slug .= ($i != 0) ? '_'.$i : ''; // This is so we don't add the increment on the first loop.
			if (!empty($restriction_list) && in_array($slug, $restriction_list, true)) {
				$i++;
				continue;
			}
			$found = $DB->get_record($table, [$field => $slug]);
			if (empty($found)) {
				break;
			}
			$i++;
		}
		return $slug;
	}
}
