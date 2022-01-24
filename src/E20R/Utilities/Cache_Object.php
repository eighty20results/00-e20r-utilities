<?php
/**
 * Copyright (c) 2016 - 2022 - Eighty / 20 Results by Wicked Strong Chicks.
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
 * @package E20R\Utilities\Cache_Object
 */

namespace E20R\Utilities;

// Deny direct access to the file
if ( ! defined( 'ABSPATH' ) && ( ! defined( 'PLUGIN_PATH' ) ) ) {
	die( 'Cannot access file directly' );
}

if ( ! class_exists( '\\E20R\\Utilities\\Cache_Object' ) ) {

	/**
	 * All cached values for this plugin are treated as objects. This is the cacheable entity (object)
	 */
	class Cache_Object {

		/**
		 * The Cache Key
		 *
		 * @var string $key The cache key to use for this object
		 */
		private $key = null;

		/**
		 * The Cached value
		 *
		 * @var mixed $value The value to store as the cacheable object
		 */
		private $value = null;

		/**
		 * Cache_Object constructor.
		 *
		 * @param string $key The cache key to use for this cacheable object
		 * @param mixed  $value The value to cache for this cacheable object
		 */
		public function __construct( $key, $value ) {

			$this->key   = $key;
			$this->value = $value;
		}

		/**
		 * Setter for the key and value properties
		 *
		 * @param string $name The name of the property to link the cached value to
		 * @param mixed  $value The value to cache
		 */
		public function set( $name, $value ) {

			switch ( $name ) {
				case 'key':
				case 'value':
					$this->{$name} = $value;
					break;
			}
		}

		/**
		 * Getter for the key and value properties
		 *
		 * @param string $name The name of the property to return the cached value of
		 *
		 * @return mixed|null - Property value (for Key or Value property)
		 */
		public function get( string $name ) {

			$result = null;

			switch ( $name ) {
				case 'key':
					// Intentionally falling through for both key and value
				case 'value':
					$result = $this->{$name};
					break;
			}

			return $result;
		}

		/**
		 * Default test method for whether the specified parameter is set/exists
		 *
		 * @param string $name The name of the property storing the cacheable value
		 *
		 * @return bool
		 */
		public function __isset( $name ) {

			return isset( $this->{$name} );
		}
	}
}
