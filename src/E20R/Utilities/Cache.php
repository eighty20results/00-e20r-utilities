<?php
/**
 * Copyright (c) 2016 - 2021 - Eighty / 20 Results by Wicked Strong Chicks.
 * ALL RIGHTS RESERVED
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package E20R\Utilities\Cache
 */

namespace E20R\Utilities;

// Deny direct access to the file
if ( ! defined( 'ABSPATH' ) && function_exists( 'wp_die' ) ) {
	wp_die( 'Cannot access file directly' );
}

if ( ! class_exists( '\E20R\Utilities\Cache' ) ) {

	/**
	 * Cache handler
	 */
	class Cache {

		/**
		 * Default cache group
		 *
		 * @var string The default cache group to use (e20r_group)
		 */
		const CACHE_GROUP = 'e20r_group';

		/**
		 * Fetch entry from cache
		 *
		 * @param string $key The cache key to fetch for
		 * @param string $group The cache group to fetch for (has default of 'e20r_group')
		 *
		 * @return bool|mixed|null
		 */
		public static function get( $key, $group = self::CACHE_GROUP ) {
			$value = get_transient( "{$group}_{$key}" );

			if ( false === $value || false === ( $value instanceof Cache_Object ) ) {
				$value = null;
			} else {
				$value = $value->get( 'value' );
			}

			return $value;
		}

		/**
		 * Store entry in cache
		 *
		 * @param string $key The cache key to store for
		 * @param mixed  $value The value to store in the cache
		 * @param int    $expires The timeout value for the cached value
		 * @param string $group The cache group the value belongs to
		 *
		 * @return bool
		 */
		public static function set( $key, $value, $expires = 3600, $group = self::CACHE_GROUP ) {
			$data = new Cache_Object( $key, $value );
			return set_transient( "{$group}_{$key}", $data, $expires );
		}

		/**
		 * Delete a cache entry
		 *
		 * @param string $key The cache key to delete
		 * @param string $group The group to delete cached values from (based on key)
		 *
		 * @return bool - True if successful, false otherwise
		 */
		public static function delete( $key, $group = self::CACHE_GROUP ) {
			return delete_transient( "{$group}_{$key}" );
		}
	}
}
