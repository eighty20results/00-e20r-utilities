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
 * @package E20R\Utilities\Cache
 */

namespace E20R\Utilities;

// Deny direct access to the file
use E20R\Licensing\Exceptions\BadOperation;

if ( ! defined( 'ABSPATH' ) && ( ! defined( 'PLUGIN_PATH' ) ) ) {
	die( 'Cannot access file directly' );
}

if ( ! class_exists( '\\E20R\\Utilities\\Cache' ) ) {

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
		 * The current class instance
		 *
		 * @var null|Cache $instance
		 */
		private static $instance = null;

		/**
		 * The transient key to use for the cached object(s)
		 *
		 * @var null|string $key_group
		 */
		private $key_group = null;

		/**
		 * The Cache_Object class we're going to add to the WP cache
		 *
		 * @var null|Cache_Object $data
		 */
		private $data = null;

		/**
		 * The cache key to use
		 *
		 * @var null|string $key
		 */
		private $key = null;

		/**
		 * The cache group to link keys to
		 *
		 * @var null|string $group
		 */
		private $group = null;

		/**
		 * Constructor for the Cache() class
		 *
		 * @param string            $key The key to use for the cached object(s)
		 * @param string            $group The group name to use for the cached object(s)
		 * @param null|Cache_Object $data The Cache_Object class we're going to add to the WP cache
		 */
		public function __construct( $key, $group = self::CACHE_GROUP, $data = null ) {
			self::$instance = $this;
			if ( null === $data ) {
				$data = new Cache_Object( $key, $group );
			}
			$this->data = $data;
			$this->set_group_key( $key, $group );
		}

		/**
		 * Returns the value of the specified class property
		 *
		 * @param string $property The name of the class property to attempt to retrieve the value of
		 *
		 * @return mixed
		 * @throws BadOperation Thrown if attempting to access a non-existent property
		 */
		public function property_get( $property ) {
			if ( ! property_exists( self::class, $property ) ) {
				throw new BadOperation( "Error: {$property} is not a valid class property" );
			}

			return $this->{$property};
		}

		/**
		 * Static version of the get() function (for backwards compatibility)
		 *
		 * @param string $key   The cache key to fetch for
		 * @param string $group The cache group to fetch for (has default of 'e20r_group')
		 *
		 * @return bool|mixed|null
		 * @throws BadOperation Thrown if the key or group values are empty
		 */
		public static function get( $key, $group = self::CACHE_GROUP ) {
			if ( null === self::$instance ) {
				self::$instance = new self( $key, $group );
			}
			return self::$instance->get_data( $key, $group );
		}

		/**
		 * Set the key and group variables for the cached item
		 *
		 * @param null|string $key The cache key
		 * @param null|string $group The cache group (more than one key per group is supported)
		 *
		 * @return void
		 */
		private function set_group_key( $key, $group ) {
			if ( ! empty( $key ) ) {
				$this->key = $key;
			}
			if ( ! empty( $group ) ) {
				$this->group = $group;
			}
			$this->key_group = "{$this->group}_{$this->key}";
		}

		/**
		 * Actually fetch entry from cache
		 *
		 * @param string $key The cache key to fetch for
		 * @param string $group The cache group to fetch for (has default of 'e20r_group')
		 *
		 * @return bool|mixed|null
		 * @throws BadOperation Thrown if the key or group values are empty
		 */
		public function get_data( $key, $group = self::CACHE_GROUP ) {

			if ( empty( $key ) && empty( $this->key ) ) {
				throw new BadOperation( esc_attr__( 'Missing cache key name!', 'e20r-utilities' ) );
			}
			if ( empty( $group ) ) {
				throw new BadOperation( esc_attr__( 'Missing cache group name!', 'e20r-utilities' ) );
			}

			$this->set_group_key( $key, $group );
			$this->data = wp_cache_get( $this->key, $this->group, false );

			if ( false === $this->data || ! is_a( $this->data, '\\E20R\\Utilities\\Cache_Object' ) ) {
				$value = null;
			} else {
				$value = $this->data->get( 'value' );
			}

			return $value;
		}

		/**
		 * Static version of the set() function (for backwards compatibility)
		 *
		 * @param string $key The cache key to store for
		 * @param mixed  $value The value to store in the cache
		 * @param int    $expires The timeout value for the cached value
		 * @param string $group The cache group the value belongs to
		 *
		 * @return bool
		 * @throws BadOperation Thrown if the key or group values are empty
		 */
		public static function set( $key, $value, $expires = 3600, $group = self::CACHE_GROUP ) {
			if ( null === self::$instance ) {
				self::$instance = new self( $key, $group );
			}
			return self::$instance->set_data( $key, $value, $expires, $group );
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
		 *
		 * @throws BadOperation Thrown if group or key is empty (not set)
		 */
		public function set_data( $key, $value, $expires = 3600, $group = self::CACHE_GROUP ) {
			if ( empty( $key ) && empty( $this->key ) ) {
				throw new BadOperation( esc_attr__( 'Missing cache key name!', 'e20r-utilities' ) );
			}
			if ( empty( $group ) ) {
				$this->group = self::CACHE_GROUP;
			}

			$this->data = new Cache_Object( $key, $value );
			$this->set_group_key( $key, $group );
			return wp_cache_add( $this->key, $this->data, $this->group, $expires );
		}

		/**
		 * Delete a cache entry or a group of cache entries by key or group
		 *
		 * @param string|null $key The cache key to delete (null will use a wildcard for the key)
		 * @param string|null $group The group to delete cached values from (null will use a wildcard for the group)
		 *
		 * @return bool - True if successful, false otherwise
		 */
		public function delete_data( $key, $group = self::CACHE_GROUP ) {
			// If both are set, just use delete_transient
			if ( ! empty( $key ) && ! empty( $group ) ) {
				$this->data = null;
				$this->set_group_key( $key, $group );
				return wp_cache_delete( $this->key, $this->group );
			}

			global $wpdb;
			$wildcard  = '%';
			$to_delete = null;

			// The key was intentionally set to null so performing a wildcard search
			if ( null === $key ) {
				$to_delete = $wpdb->esc_like( "_transient_{$group}_" ) . $wildcard;
			}

			// The group was intentionally set to null so performing a wildcard search
			if ( null === $group && null !== $key ) {
				$to_delete = '_transient__' . $wpdb->esc_like( $key ) . $wildcard;
			}

			// Onl prepare and execute if we configured a wildcard search
			if ( ! empty( $to_delete ) ) {
				$_sql = $wpdb->prepare(
					'DELETE FROM %s WHERE option_name LIKE %s',
					$wpdb->options,
					$to_delete
				);

				$this->data = null;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
				return ! ( null === $wpdb->get_var( $_sql ) );
			}

			$this->data = null;
			return false;
		}
		/**
		 * Delete a cache entry or a group of cache entries by key or group
		 *
		 * @param string|null $key The cache key to delete (null will use a wildcard for the key)
		 * @param string|null $group The group to delete cached values from (null will use a wildcard for the group)
		 *
		 * @return bool - True if successful, false otherwise
		 */
		public static function delete( $key, $group = self::CACHE_GROUP ) {
			if ( null === self::$instance ) {
				self::$instance = new self( $key, $group );
			}
			return self::$instance->delete_data( $key, $group );
		}
	}
}
