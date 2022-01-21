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
 * @package E20R\Tests\Integration\Cache_ItegrationTest
 */

namespace E20R\Tests\Integration;

use Codeception\TestCase\WPTestCase;
use E20R\Licensing\Exceptions\BadOperation;
use E20R\Utilities\Cache;
use E20R\Utilities\Message;
use E20R\Utilities\Utilities;

/**
 * Integration (WPUnit) test of the Cache_Object class
 */
class Cache_IntegrationTest extends WPTestCase {

	/**
	 * Instance of the Utilities class
	 *
	 * @var null|Utilities $utils
	 */
	private $utils = null;

	/**
	 * Initial setup for the test cases
	 */
	public function setUp() : void {
		parent::setUp();

		$message     = new Message();
		$this->utils = new Utilities( $message );

		// Force us to use the DB for testing purposes
		wp_using_ext_object_cache( false );
	}

	/**
	 * The tear-down function for the Codeception Integration tests
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->utils = null;
		parent::tearDown();
	}

	/**
	 * Fixture for the it_should_add_single_cached_object_to_options_table_in_db() test
	 *
	 * @return array[]
	 */
	public function fixture_individual_key_groups() {
		return array(
			array( 'dummy_key', 'e20r_tst1', true, true, 1 ),
			array( 'another_key', 'e20r_tst2', false, true, 1 ),
			array( 'an_integer', 'e20r_tst3', 10, true, 1 ),
			array( 'a_float', 'e20r_tst4', 10.1, true, 1 ),
			array( 'weird key', 'e20r_tst5', 'yes', true, 1 ),
			array( 1, 'e20r_tst6', 10, true, 1 ),
			array( 10, 'e20r_tst7', null, true, 1 ),
			array( md5( 'something' ), 'e20r_tst8', 'any_value', true, 1 ),
			array( md5( 'something', true ), 'e20r_tst9', 'any_other_value', false, 0 ),
		);
	}

	/**
	 * Test whether setting the cache object property is happening properly when the class is instantiated
	 *
	 * @param string $key_name   The name of the object property to set
	 * @param string $group_name The cache group name to use
	 * @param string $to_value   The value to set the cache to
	 * @param mixed  $expected   The resulting value from the set() operation
	 * @param int    $count      The resulting count of _transient_* records in the $wpdb->prefix_options table
	 *
	 * @dataProvider fixture_individual_key_groups
	 *
	 * @return void
	 * @test
	 */
	public function it_should_add_single_cached_object_to_options_table_in_db( $key_name, $group_name, $to_value, $expected, $count ) {

		$cache  = new Cache( $key_name, $group_name );
		$result = false;
		try {
			$result = $cache->set_data( $key_name, $to_value, 3600, $group_name );
		} catch ( BadOperation $e ) {
			self::assertFalse( true, 'Error: ' . $e->getMessage() );
		}
		$real_count = $this->get_cache_count_from_db( $key_name, $group_name );

		self::assertSame( $count, $real_count, "Error: The count for the key {$key_name} and group {$group_name} cache is {$real_count} vs expected {$count}" );
		self::assertSame( $expected, $result, "Error: The result from Cache::set_data( {$key_name}, 'any_value', 3600, $group_name) is: {$real_count} vs expected: {$count}" );
	}

	/**
	 * Fixture for the it_should_fail_to_add_to_options_table_in_db() test
	 *
	 * @return array[]
	 */
	public function fixture_individual_key_group_failures() {
		return array(
			// FIXME: Test that the expected exceptions are triggered!
			array( md5( 'something', true ), 'e20r_tst1', 'any_other_value', false, 0 ),
		);
	}

	/**
	 * Test whether setting the cache object property is happening properly when the class is instantiated
	 *
	 * @param string $key_name   The name of the object property to set
	 * @param string $group_name The cache group name to use
	 * @param string $to_value   The value to set the cache to
	 * @param mixed  $expected   The resulting value from the set() operation
	 * @param int    $count      The resulting count of _transient_* records in the $wpdb->prefix_options table
	 *
	 * @dataProvider fixture_individual_key_group_failures
	 *
	 * @return void
	 * @test
	 *
	 * FIXME: Test that the expected exceptions are triggered!
	 */
	public function it_should_fail_to_add_to_options_table_in_db( $key_name, $group_name, $to_value, $expected, $count ) {

		$cache  = new Cache( $key_name, $group_name );
		$result = false;
		try {
			$result = $cache->set_data( $key_name, $to_value, 3600, $group_name );
		} catch ( BadOperation $e ) {
			self::assertFalse( true, 'Error: ' . $e->getMessage() );
		}
		$real_count = $this->get_cache_count_from_db( $key_name, $group_name );

		self::assertSame( $count, $real_count, "Error: The count for the key {$key_name} and group {$group_name} cache is {$real_count} vs expected {$count}" );
		self::assertSame( $expected, $result, "Error: The result from Cache::set_data( {$key_name}, 'any_value', 3600, $group_name) is: {$real_count} vs expected: {$count}" );
	}

	/**
	 * Private function to fetch data from wp_options when testing Cache() class
	 *
	 * @param string|null $key The cache key name to use
	 * @param string|null $group The cache group name to use
	 *
	 * @return int
	 */
	private function get_cache_count_from_db( $key, $group ) {
		global $wpdb;
		$transient_label = "_transient_{$group}_{$key}" . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(option_id) AS found_entries FROM {$wpdb->options} WHERE option_name LIKE %s",
				$transient_label
			)
		);
	}

	/**
	 * Fixture for the it_should_add_multiple_cache_objects_to_cache_group() test
	 *
	 * @return array[]
	 */
	public function fixture_multiple_keys_per_group() {
		$fixture = array();

		foreach ( range( 0, 5 ) as $group_no ) {
			$fixture[ $group_no ] = array();
			foreach ( range( 1, 10 ) as $counter ) {
				$fixture[ $group_no ][] = array( "key_{$counter}", true, $counter );
			}
		}
		return array( $fixture );
	}

	/**
	 * Test multiple cache set() operations for distinct keys but the same group name.
	 *
	 * @param array[] $test_group The group of cache key/value pairs and results to test/test for
	 *
	 * @return void
	 *
	 * @dataProvider fixture_multiple_keys_per_group
	 * @test
	 */
	public function it_should_add_multiple_cache_objects_to_single_cache_group( $test_group ) {
		$group_name = 'moicg';

		/**
		 * Iterates through the array of test variables used for multiple cache keys
		 * and a single group (default or otherwise)
		 *
		 * @param string $key Is the Cache Key to use
		 * @param bool   $expected Is the expected return value from the Cache::set_data() or Cache::set() method
		 * @param int    $count    Is the expected number of entries in the cache
		 */
		foreach ( $test_group as $group => $item ) {
			$key      = $item[0];
			$expected = $item[1];
			$count    = $item[2];
			$cache    = new Cache( $key, $group_name );

			$result = false;
			try {
				$result = $cache->set_data( $key, 'any_value', 3600, $group_name );
			} catch ( BadOperation $e ) {
				self::assertFalse( true, 'Error: ' . $e->getMessage() );
			}
			$real_count = $this->get_group_count_from_db( $group_name );

			self::assertSame( $count, $real_count, "Error: The count for the key {$key} and group {$group_name} cache is {$real_count} vs expected {$count}" );
			self::assertSame( $expected, $result, "Error: The result from Cache::set_data( {$key}, 'any_value', 3600, $group_name) is: {$real_count} vs expected: {$count}" );
		}
	}

	/**
	 * Test multiple cache set() operations for distinct keys but the same group name.
	 *
	 * @param array[] $test_group The name of the object property to set
	 *
	 * @return void
	 *
	 * @dataProvider fixture_multiple_keys_per_group
	 * @test
	 */
	public function it_should_add_multiple_cache_objects_to_default_cache_group( $test_group ) {

		/**
		 * Iterates through the array of test variables used for multiple cache keys
		 * and a single group (default or otherwise)
		 *
		 * @param string $key Is the Cache Key to use
		 * @param bool   $expected Is the expected return value from the Cache::set_data() or Cache::set() method
		 * @param int    $count    Is the expected number of entries in the cache
		 */
		foreach ( $test_group as $group => $item ) {
			$key        = $item[0];
			$expected   = $item[1];
			$count      = $item[2];
			$cache      = new Cache( $key );
			$result     = false;
			$group_name = '';

			try {
				$result = $cache->set_data( $key, 'any_value' );
			} catch ( BadOperation $e ) {
				self::assertFalse( true, 'Error: ' . $e->getMessage() );
			}

			// Fetch the default cache group name from the Cache() class
			try {
				$group_name = $cache->property_get( 'group' );
			} catch ( BadOperation $e ) {
				self::assertFalse( true, 'Error: ' . $e->getMessage() );
			}

			self::assertSame( 'e20r_group', $group_name, "Error: Expected a group name of 'e20r_group' but got {$group_name} instead!" );
			$real_count = $this->get_group_count_from_db( $group_name );

			self::assertSame( $count, $real_count, "Error: The count for the key {$key} and group {$group_name} cache is {$real_count} vs expected {$count}" );
			self::assertSame( $expected, $result, "Error: The result from Cache::set_data( {$key}, 'any_value', 3600, $group_name) is: {$real_count} vs expected: {$count}" );

		}
	}

	/**
	 * Private function to fetch data from wp_options when testing Cache() class
	 *
	 * @param string|null $group The cache group name to use
	 *
	 * @return int
	 */
	private function get_group_count_from_db( $group ) {
		global $wpdb;
		$transient_label = "_transient_{$group}_" . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT count(option_id) FROM {$wpdb->options} WHERE option_name LIKE %s",
				$transient_label
			)
		);
	}

}
