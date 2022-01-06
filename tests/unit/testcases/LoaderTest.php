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
 * @package E20R\Tests\Unit\LoaderTest
 */

namespace E20R\Tests\Unit;

use Codeception\Test\Unit;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

use E20R\Utilities\Loader;
use E20R\Utilities\Utilities;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Brain\Monkey\Filters;
use Brain\Monkey\Actions;

use function E20R\Tests\Unit\Fixtures\e20r_unittest_stubs;

/**
 * Tests the Loader() class
 */
class LoaderTest extends Unit {

	use MockeryPHPUnitIntegration;

	/**
	 * Mock for the Utilities class
	 *
	 * @var null|\Mockery
	 */
	private $m_utils = null;

	/**
	 * Instance of the Loader class
	 *
	 * @var null|Loader
	 */
	private $loader = null;

	/**
	 * Test setup
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->loadFiles();
		e20r_unittest_stubs();
		$this->loadStubbedClasses();
	}

	/**
	 * Test tear-down
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Classes that can be mocked for the entire test
	 */
	public function loadStubbedClasses() {
		$this->m_utils = $this->makeEmpty(
			Utilities::class,
			array(
				'log'              => function( $text ) {
					error_log( "{$text}" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				},
				'load_text_domain' => null,
				'configure_update' => null,
			)
		);
	}

	/**
	 * Use the Composer PSR-4 autoloader to load the class(es) we're testing
	 *
	 * NOTE: Loading class-loader.php manually because it doesn't
	 * comply with the PSR-4 autoloader file naming conventions
	 * as it needs to be backwards compatible with previous versions
	 * of this plugin
	 */
	public function loadFiles() {
		require_once __DIR__ . '/../inc/unittest_stubs.php';
		require_once __DIR__ . '/../../../class-loader.php';
	}

	/**
	 * Unit test for the Loader() class instantiation
	 *
	 * @param string $class_name Name of the class
	 * @param int    $priority The priority of the callback(s)
	 * @param int    $init_priority The priority used when loading the init action
	 *
	 * @dataProvider fixture_instantiated
	 */
	public function test__construct( string $class_name, int $priority, int $init_priority ) {
		$this->loader = new Loader( $this->m_utils );
		self::assertInstanceOf( $class_name, $this->loader );

		Actions\has( 'plugins_loaded', array( $this->loader, 'utilities_loaded' ) );
		Actions\has( 'plugins_loaded', array( $this->m_utils, 'load_text_domain' ) );

		// self::assertSame( $priority, has_action( 'plugins_loaded', array( $this->loader, 'utilities_loaded' ) ) );
		self::assertSame( $init_priority, has_action( 'plugins_loaded', array( $this->m_utils, 'load_text_domain' ) ) );
	}

	/**
	 * Fixture for the Loader() class constructor test
	 *
	 * @return \string[][]
	 */
	public function fixture_instantiated() {
		return array(
			array( Loader::class, 10, 11 ),
		);
	}


	/**
	 * Tests that the Utilities class has been loaded
	 *
	 * @param bool $test_value The value to pass to the e20r_utilities_module_installed filter
	 * @param bool $expected The expected return value
	 *
	 * @dataProvider fixture_utilities_loaded
	 * @covers \E20R\Utilities\Loader::utilities_loaded
	 * @test
	 */
	public function test_utilities_loaded( $test_value, $expected ) {
		$this->loader = new Loader( $this->m_utils );
		$this->loader->utilities_loaded();
		Filters\has( 'e20r_utilities_module_installed', '__return_true' );
		$result = apply_filters( 'e20r_utilities_module_installed', $test_value );
		self::assertSame( $expected, $result );
	}

	/**
	 * Fixture for the test_utilities_loaded unit test
	 *
	 * @return array
	 */
	public function fixture_utilities_loaded() {
		return array(
			array( false, false ),
			array( true, true ),
		);
	}
}
