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
 * @package E20R\Tests\Unit\MixpanelConnectorTest
 */

namespace E20R\Tests\Unit;

use Codeception\Test\Unit;
use E20R\Metrics\MixpanelConnector;
use E20R\Utilities\Utilities;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mixpanel;
use Mockery;

/**
 * Unit tests for Mixpanel connector
 */
class MixpanelConnectorTest extends Unit {

	/**
	 * Mock of the Utilities class
	 *
	 * @var null|Mockery|Utilities
	 */
	private $mock_utils = null;

	/**
	 * Mock of the MixpanelConnector class
	 *
	 * @var null|MixpanelConnector|Mockery
	 */
	private $mock_mp = null;

	/**
	 * The setup function for this Unit Test suite
	 */
	protected function setUp(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', __DIR__ );
		}
		if ( ! defined( 'PLUGIN_PHPUNIT' ) ) {
			define( 'PLUGIN_PHPUNIT', true );
		}
		parent::setUp();
		Monkey\setUp();

		$this->loadFiles();
		$this->loadStubbedFunctions();
	}

	/**
	 * Teardown function for the Unit Tests
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Create function stubs for testing purposes
	 */
	public function loadStubbedFunctions() {
		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			define( 'DAY_IN_SECONDS', 60 * 60 * 24 );
		}
		if ( ! defined( 'PLUGIN_PHPUNIT' ) ) {
			define( 'PLUGIN_PHPUNIT', true );
		}
		Functions\stubs(
			array(
				'plugins_url'         => 'https://localhost:7254/wp-content/plugins/00-e20r-utilities/',
				'get_current_blog_id' => 0,
				'date_i18n'           => function( $date_string, $time ) {
					return date( $date_string, $time ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				},
				'esc_attr'            => null,
				'esc_html__'          => null,
				'esc_attr__'          => null,
				'__'                  => null,
				'_e'                  => null,
			)
		);

		Functions\when( 'wp_die' )
			->justReturn( null );

		Functions\when( 'add_action' )
			->returnArg( 3 );

		Functions\expect( 'dirname' )
			->with( Mockery::contains( 'src/E20R/Metrics/MixpanelConnector.php' ) )
			->zeroOrMoreTimes()
			->andReturn( '../../../src/E20R/Metrics/' );

		Functions\expect( 'get_filesystem_method' )
			->zeroOrMoreTimes()
			->andReturn( 'direct' );

		Functions\expect( 'plugin_dir_path' )
			->zeroOrMoreTimes()
			->with( Mockery::contains( 'src/E20R/Metrics/MixpanelConnector.php' ) )
			->andReturn(
				function() {
					return __DIR__ . '/../../../src/E20R/Licensing/';
				}
			);

		$this->mock_utils = $this->makeEmpty(
			Utilities::class,
			array(
				'log' => function( $text ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( "Mocked log: {$text}" );
					return null;
				},
			)
		);

		$this->mock_mp = $this->makeEmpty(
			Mixpanel::class,
			array()
		);
	}

	/**
	 * Load source files for the Unit Test to execute
	 */
	public function loadFiles() {
		require_once __DIR__ . '/../../../inc/autoload.php';
		require_once __DIR__ . '/../inc/class-wp-filesystem-base.php';
		require_once __DIR__ . '/../inc/class-wp-filesystem-direct.php';
	}
}
