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
 * @package E20R\Tests\WPUnit\Loader_WPUnitTest
 */

namespace E20R\Tests\Integration;

use Codeception\TestCase\WPTestCase;
use E20R\Utilities\Loader;
use E20R\Utilities\Utilities;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Exception;

/**
 * WP tests ("unit") for testing the Loader class
 */
class Loader_IntegrationTest extends WPTestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Mocked Utilities class
	 *
	 * @var mixed $m_utils
	 */
	private $m_utils = null;

	/**
	 * Real Utilities class
	 *
	 * @var null|Utilities $utils
	 */
	private $utils = null;

	/**
	 * Test setup
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->loadMocks();
	}


	/**
	 * Classes that can be mocked for the entire test
	 */
	public function loadMocks() {
		try {
			$this->m_utils = $this->makeEmpty(
				Utilities::class,
				array(
					'load_text_domain' => null,
					'configure_update' => null,
					'dummy_function'   => null,
				)
			);
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $e->getMessage() );
		}
	}

	/**
	 * Unit test for the Loader() class instantiation
	 *
	 * @param string   $action_name     Name of action to test
	 * @param array    $hook_function   Function to use as hook
	 * @param int|bool $expected        The expected result
	 * @param int      $execution_count The number of executions
	 *
	 * @dataProvider fixture_loaded_actions
	 * @test
	 */
	public function it_instantiates_the_class_and_verifies_the_plugins_loaded_action_ran( $action_name, $hook_function, $expected, $execution_count ) {
		$run_count = did_action( 'plugins_loaded' );
		self::assertSame( $execution_count, $run_count, "Error: {$run_count} is an unexpected number of executions for the 'plugins_loaded' action hook" );
		$result = has_action( $action_name, $hook_function );
		self::assertSame( $expected, $result, "Error: has_action() should return '{$expected}' but we got '{$result}'" );
	}

	/**
	 * Fixture for the Loader() class constructor test
	 *
	 * @return string[][]
	 */
	public function fixture_loaded_actions() {
		$utils  = new Utilities();
		$loader = new Loader( $utils );
		return array(
			array( 'plugins_loaded', array( $utils, 'load_text_domain' ), 11, 1 ),
			array( 'init', array( $utils, 'dummy_function' ), false, 1 ),
			array( 'plugins_loaded', array( $loader, 'utilities_loaded' ), false, 1 ),
		);
	}

	/**
	 * Make sure the filter is loaded when the plugin starts up
	 *
	 * @param bool $test_value Value to pass to the filter
	 * @param bool $expected The expected return value
	 *
	 * @dataProvider fixture_utilities_loaded
	 * @covers \E20R\Utilities\Loader::utilities_loaded()
	 * @test
	 */
	public function it_instantiates_the_utilities_class( $test_value, $expected ) {
		$utils  = new Utilities();
		$loader = new Loader( $utils );
		$loader->utilities_loaded();

		$priority = has_filter( 'e20r_utilities_module_installed', array( $loader, 'making_sure_we_win' ) );
		$default  = 99999;

		self::assertSame( $default, $priority, "Error: Expected filter priority for 'making_sure_we_win' hook to be {$default}, but it is: '{$priority}'" );
		$utils->log( "Running apply_filters() to see if we get the expected '{$expected}' test result" );
		$result = apply_filters( 'e20r_utilities_module_installed', $test_value );
		$utils->log( "Filter execution returns '{$result}' and we expected '{$expected}'" );
		self::assertSame( $expected, $result, "Error: Expected return value to be '{$expected}' but got '{$result}'" );
	}

	/**
	 * Fixture for the test_utilities_loaded unit test
	 *
	 * @return array
	 */
	public function fixture_utilities_loaded(): array {
		return array(
			array( false, true ),
			array( true, true ),
		);
	}

	/**
	 * Test the 'e20r_utilities_module_installed' filter
	 *
	 * @param bool $first_filter_value The value to pass to and return from the first filter hook function
	 * @param int  $first_priority The priority to use for the filter
	 * @param bool $second_filter_value The value to pass to and return from the second filter hook function
	 * @param int  $second_priority The priority to use for the 2nd filter
	 * @param bool $expected The value we expect the filters to return in the end
	 *
	 * @dataProvider fixture_loaded_filter
	 * @test
	 */
	public function it_verifies_the_e20r_utilities_module_installed_filter_ran( $first_filter_value, $first_priority, $second_filter_value, $second_priority, $expected ) {
		add_filter(
			'e20r_utilities_module_installed',
			function ( $received_value ) use ( $first_filter_value ) {
				return $received_value;
			},
			$first_priority,
			1
		);

		add_filter(
			'e20r_utilities_module_installed',
			function ( $received_value ) use ( $second_filter_value ) {
				return $received_value;
			},
			$second_priority,
			1
		);

		// Then run our filter(s)
		$result = apply_filters( 'e20r_utilities_module_installed', false );
		self::assertSame( $expected, $result );
	}

	/**
	 * Fixture for the 'e20r_utilities_module_installed' filter
	 *
	 * @return array[]
	 */
	public function fixture_loaded_filter() {
		return array(
			array( true, 1, false, 100000, true ),
			array( false, 10, false, -1, true ),
			array( false, 20, false, 30, true ),
		);
	}

	/**
	 * Test to make sure we return true even if someone injected a later 'e20r_utilities_module_installed' hook
	 *
	 * @param array $custom_hook Custom hook function for the filter
	 * @param int   $priority Hook priority
	 * @param bool  $expected_result The result we expect
	 * @param bool  $expected_priority The priority we expect the filter to have run against
	 *
	 * @covers \E20R\Utilities\Loader::making_sure_we_win
	 * @dataProvider fixture_install_handler
	 * @test
	 */
	public function it_makes_sure_we_always_return_true_that_the_module_is_installed_when_the_plugin_is_active( $custom_hook, $priority, $expected_result, $expected_priority ) {
		$this->utils = new Utilities();
		$loader      = new Loader( $this->utils );
		add_filter( 'e20r_utilities_module_installed', $custom_hook, $priority, 1 );

		$filter_priority = has_filter( 'e20r_utilities_module_installed', $custom_hook );
		$result          = $loader->making_sure_we_win( false );
		$max_priority    = $loader->get_max_hook_priority();

		self::assertSame( $priority, $filter_priority, "Error: Priority for the custom hook saved as '{$filter_priority}' but we gave it '{$priority}'" );
		self::assertSame( $expected_priority, $max_priority, "Error: Expected max priority for hook: '{$expected_priority}' but the priority is '{$max_priority}'" );
		self::assertSame( $expected_result, $result, "Error: Expecting filter to return '{$expected_result}' but got '{$result}'" );
		self::assertTrue(
			remove_filter(
				'e20r_utilities_module_installed',
				$custom_hook,
				$priority
			),
			"Error: Could not remove the newly added filter hook with priority {$priority}"
		);

		if ( $max_priority > 99999 ) {
			self::assertTrue(
				remove_filter(
					'e20r_utilities_module_installed',
					'__return_true',
					$max_priority
				)
			);
		}
	}

	/**
	 * Fixture for the Loader_WPUnitTest::test_making_sure_we_win test
	 *
	 * @return array
	 */
	public function fixture_install_handler() {
		return array(
			array(
				function() {
					return false;
				},
				99998, // priority
				true, // expected_result
				99999, // expected_priority
			),
			array(
				function() {
					return false;
				},
				99999, // priority
				true, // expected_result
				100009, // expected_priority
			),
			array(
				function() {
					return false;
				},
				100000, // priority
				true, // expected_result
				100010, // expected_priority
			),
		);
	}

	/**
	 * Test whether Loader::make_sure_we_win() does the expected thing (sets the priority of the
	 * '__return_true' handler to the highest value it can)
	 *
	 * @param int $hook_priority The priority to use when adding the filter
	 * @param int $expected The expected priority value that was returned
	 *
	 * @covers \E20R\Utilities\Loader::get_max_hook_priority()
	 * @dataProvider fixture_hook_priority
	 * @test
	 */
	public function it_makes_sure_we_return_the_expected_filter_priority( $hook_priority, $expected ) {
		$utils  = new Utilities();
		$loader = new Loader( $utils );
		// Add a hook with a given priority & then trigger Loader::making_sure_we_win()
		add_filter( 'e20r_utilities_module_installed', '__return_false', $hook_priority );
		$loader->making_sure_we_win( false );
		$result = $loader->get_max_hook_priority();
		self::assertSame( $expected, $result );
		self::assertTrue(
			remove_filter(
				'e20r_utilities_module_installed',
				'__return_false',
				$hook_priority
			)
		);
	}

	/**
	 * Fixture for the get_max_hook_priority test
	 *
	 * @return \int[][]
	 */
	public function fixture_hook_priority(): array {
		return array(
			array( 10, 99999 ),
			array( -1, 99999 ),
			array( 99999, 100009 ),
		);
	}
}
