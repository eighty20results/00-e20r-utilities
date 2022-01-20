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
	 * Initial setup for the test cases
	 */
	public function setUp(): void {
		parent::setUp();
		$message     = new Message();
		$this->utils = new Utilities( $message );
	}

	/**
	 * Fixture for the it_should_get_set() test
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
			array( md5( 'something', true ), 'e20r_tst9', 'any_other_value', true, 1 ),
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
			$result = $cache->set_data( $key_name, $to_value, 10, $group_name );
		} catch ( BadOperation $e ) {
			self::assertFalse( true, 'Error: Unexpected BadOperation() exception caught in this test!' );
		}
		$real_count = $this->get_cache_count_from_db( $key_name, $group_name );

		self::assertSame( $count, $real_count );
		self::assertSame( $expected, $result );
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
		return (int) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT(option_id) FROM {$wpdb->options} WHERE option_name LIKE %s",
				"_transient_{$group}_{$key}" . '%'
			)
		);
	}
}
