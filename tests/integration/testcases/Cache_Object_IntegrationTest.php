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
 * @package E20R\Tests\Integration\Cache_Object_ItegrationTest
 */

namespace E20R\Tests\Integration;

use Codeception\TestCase\WPTestCase;
use E20R\Utilities\Cache_Object;
use E20R\Utilities\Message;
use E20R\Utilities\Utilities;

/**
 * Integration (WPUnit) test of the Cache_Object class
 */
class Cache_Object_IntegrationTest extends WPTestCase {

	/**
	 * Initial setup for the test cases
	 */
	public function setUp(): void {
		parent::setUp();
		$message     = new Message();
		$this->utils = new Utilities( $message );
	}


	/**
	 * Test whether setting the cache object property is happening
	 *
	 * @param string $key_name The name of the object property to set
	 * @param mixed  $to_value The target value for the property
	 * @param string $expected_key The resulting key stored in the property
	 * @param mixed  $expected_value The resulting value of the property
	 *
	 * @dataProvider fixture_cache_object_set
	 *
	 * @return void
	 * @test
	 */
	public function it_should_get_set( $key_name, $to_value, $expected_key, $expected_value ) {

		$cache_object = new Cache_Object( 'test_key', 'default' );

		self::assertTrue( property_exists( $cache_object, 'key' ) );
		self::assertTrue( property_exists( $cache_object, 'value' ) );

		$cache_object->set( 'key', $key_name );
		$cache_object->set( 'value', $to_value );

		$key_result   = $cache_object->get( 'key' );
		$value_result = $cache_object->get( 'value' );

		self::assertSame( $expected_key, $key_result );
		self::assertSame( $expected_value, $value_result );
	}

	/**
	 * Fixture for the it_should_get_set() test
	 *
	 * @return array[]
	 */
	public function fixture_cache_object_set() {
		return array(
			array( 'dummy_key', true, 'dummy_key', true ),
			array( 'another_key', false, 'another_key', false ),
			array( 'an_integer', 10, 'an_integer', 10 ),
			array( 'a_float', 10.1, 'a_float', 10.1 ),
			array( 'weird key', 'yes', 'weird key', 'yes' ),
		);
	}
}
