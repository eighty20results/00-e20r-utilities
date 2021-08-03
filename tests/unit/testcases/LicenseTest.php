<?php
/*
 * *
 *   * Copyright (c) 2021. - Eighty / 20 Results by Wicked Strong Chicks.
 *   * ALL RIGHTS RESERVED
 *   *
 *   * This program is free software: you can redistribute it and/or modify
 *   * it under the terms of the GNU General Public License as published by
 *   * the Free Software Foundation, either version 3 of the License, or
 *   * (at your option) any later version.
 *   *
 *   * This program is distributed in the hope that it will be useful,
 *   * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   * GNU General Public License for more details.
 *   *
 *   * You should have received a copy of the GNU General Public License
 *   * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace E20R\Tests\Unit;

use Codeception\AssertThrows;
use E20R\Licensing\AjaxHandler;
use E20R\Licensing\Exceptions\InvalidSettingsKey;
use E20R\Licensing\Exceptions\MissingServerURL;
use E20R\Licensing\LicensePage;
use E20R\Licensing\LicenseServer;
use E20R\Licensing\Settings\LicenseSettings;
use E20R\Licensing\License;
use E20R\Licensing\Settings\Defaults;
use E20R\Utilities\Message;
use E20R\Utilities\Utilities;
use Codeception\Test\Unit;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

class LicenseTest extends Unit {

	use AssertThrows;
	use MockeryPHPUnitIntegration;
	/**
	 * @var LicenseSettings $settings_mock
	 */
	private LicenseSettings $settings_mock;

	/**
	 * @var LicenseServer $server_mock
	 */
	private LicenseServer $server_mock;

	/**
	 * @var LicensePage $page_mock
	 */
	private LicensePage $page_mock;

	/**
	 * @var Utilities $utils_mock
	 */
	private Utilities $utils_mock;

	/**
	 * The setup function for this Unit Test suite
	 *
	 */
	protected function setUp(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', __DIR__ );
		}
		if ( ! defined( 'PLUGIN_PHPUNIT' ) ) {
			define( 'PLUGIN_PHPUNIT', true );
		}
		parent::setUp();
		setUp();

		$this->loadStubs();
		$this->loadDefaultMocks();
		$this->loadFiles();
	}

	/**
	 * Define stubs for various WP functions
	 */
	protected function loadStubs() {
		Functions\expect( 'home_url' )
			->andReturn( 'https://localhost:7254/' );

		Functions\expect( 'plugins_url' )
			->andReturn( 'https://localhost:7254/wp-content/plugins/' );

		try {
			Functions\expect( 'admin_url' )
				->with( \Mockery::contains( 'options-general.php' ) )
				->andReturn( 'https://localhost:7254/wp-admin/options-general.php' );
		} catch ( \Exception $e ) {
			echo 'Error: ' . $e->getMessage(); // phpcs:ignore
		}

		try {
			Functions\expect( 'get_option' )
				->with( \Mockery::contains( 'e20r_license_settings' ) )
				->andReturnUsing(
					function() {
						return 'test';
					}
				);
		} catch ( \Exception $e ) {
			echo 'Error: ' . $e->getMessage(); // phpcs:ignore
		}

		try {
			Functions\expect( 'get_option' )
				->with( \Mockery::contains( 'home' ) )
				->andReturn( 'https://localhost:7254/' );
		} catch ( \Exception $e ) {
			echo 'Error: ' . $e->getMessage(); // phpcs:ignore
		}

		Functions\expect( 'plugin_dir_path' )
			->andReturn( '/var/www/html/wp-content/plugins/00-e20r-utilities/' );

		Functions\expect( 'get_current_blog_id' )
			->andReturn( 1 );

		Functions\expect( 'date_i18n' )
			->andReturn(
				function( $date_string, $time ) {
					return date( $date_string, $time ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				}
			);
	}

	/**
	 * Create mocked functions for the required License() arguments
	 *
	 * @throws \Exception
	 */
	protected function loadDefaultMocks() {

		$defaults_mock = $this->makeEmpty(
			Defaults::class,
			array(
				'get' => false,
			)
		);

		$this->settings_mock = $this->makeEmpty(
			LicenseSettings::class,
			array(
				'update_plugin_defaults' => null,
				'get'                    => function( $param_name ) use ( $defaults_mock ) {
					$retval = null;
					switch ( $param_name ) {
						case 'plugin_defaults':
							$retval = $defaults_mock;
							break;
					}

					return $retval;
				},
			)
		);

		$this->server_mock = $this->makeEmpty(
			LicenseServer::class,
		);

		$this->page_mock = $this->makeEmpty(
			LicensePage::class
		);

		$this->utils_mock = $this->makeEmpty(
			Utilities::class,
			array(
				'is_license_server' => false,
				'log'               => function( $text ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( $text );
					return null;
				},
			)
		);
	}
	/**
	 * Teardown function for the Unit Tests
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		tearDown();
		parent::tearDown();
	}

	/**
	 * Load source files for the Unit Test to execute
	 */
	public function loadFiles() {
		require_once __DIR__ . '/../../../inc/autoload.php';
	}

	public function test_ajax_handler_verify_license() {

	}

	/**
	 * Tests the load_hooks() function
	 *
	 * @covers \E20R\Licensing\License::load_hooks()
	 */
	public function test_load_hooks() {

		try {
			$license = new License( null, $this->settings_mock, $this->server_mock, $this->page_mock, $this->utils_mock );
		} catch ( \Exception $e ) {
			self::assertFalse( true, $e->getMessage() );
		}

		try {
			Actions\expectAdded( 'admin_enqueue_scripts' )
				->with( array( $license, 'enqueue' ), 10 );
		} catch ( \Exception $e ) {
			self::assertFalse( true, $e->getMessage() );
		}

		$license->load_hooks();
	}

	/**
	 * Test the enqueue method for JavaScript and styles
	 */
	public function test_enqueue() {

	}

	public function test_is_active() {

	}

	public function test_is_licensed() {

	}

	/**
	 * Unit tests for get_license_page_url()
	 *
	 * @param string $stub
	 * @param string $expected
	 *
	 * @dataProvider fixture_page_url
	 * @covers \E20R\Licensing\License::get_license_page_url()
	 */
	public function test_get_license_page_url( string $stub, string $expected ) {

		try {
			Functions\expect( 'esc_url_raw' )
				->andReturnFirstArg();
		} catch ( \Exception $e ) {
			self::assertFalse( true, 'Error: ' . $e->getMessage() );
		}

		try {
			Functions\expect( 'add_query_arg' )
				->with(
					\Mockery::contains(
						array(
							'page'         => 'e20r-Licensing',
							'license_stub' => $stub,
						)
					)
				)
				->andReturn(
					sprintf(
						'https://localhost:7254/wp-admin/options-general.php?page=e20r-Licensing&license_stub=%s',
						rawurlencode( $stub )
					)
				);
		} catch ( \Exception $e ) {
			self::assertFalse( true, 'Error: ' . $e->getMessage() );
		}

		try {
			$license = new License( $stub, $this->settings_mock, $this->server_mock, $this->page_mock, $this->utils_mock );
		} catch ( InvalidSettingsKey | MissingServerURL $e ) {
			self::assertFalse( true, 'get_license_page_url() - ' . $e->getMessage() );
		}

		self::assertEquals(
			$expected,
			$license->get_license_page_url( $stub ),
			sprintf( 'License server URL did not contain "%s"', $stub )
		);
	}

	/**
	 * Fixture for the get_license_page_url function
	 *
	 * @return \string[][]
	 */
	public function fixture_page_url() {
		return array(
			array( 'test-license-1', 'https://localhost:7254/wp-admin/options-general.php?page=e20r-Licensing&license_stub=test-license-1' ),
			array( 'test license 1', 'https://localhost:7254/wp-admin/options-general.php?page=e20r-Licensing&license_stub=test%20license%201' ),
			array( 'vXzfjW9M2O4sP1a57DG399SmA2-176', 'https://localhost:7254/wp-admin/options-general.php?page=e20r-Licensing&license_stub=vXzfjW9M2O4sP1a57DG399SmA2-176' ),
			array( 'ovCCBklB8cz2H9Q787Asv2w0rC-166', 'https://localhost:7254/wp-admin/options-general.php?page=e20r-Licensing&license_stub=ovCCBklB8cz2H9Q787Asv2w0rC-166' ),
			array( 'vXzfjW9M2O4sP1a57DG399SmA2%176', 'https://localhost:7254/wp-admin/options-general.php?page=e20r-Licensing&license_stub=vXzfjW9M2O4sP1a57DG399SmA2%25176' ),
		);
	}

	/**
	 * Negative tests for get_license_page_url()
	 *
	 * @param string $stub
	 * @param string $expected
	 *
	 * @dataProvider fixture_page_url_neg
	 * @covers \E20R\Licensing\License::get_license_page_url()
	 */
	public function test_neg_get_license_page_url( string $stub, string $expected ) {
		try {
			Functions\expect( 'esc_url_raw' )
				->andReturnFirstArg();
		} catch ( \Exception $e ) {
			self::assertFalse( true, 'Error: ' . $e->getMessage() );
		}

		try {
			Functions\expect( 'add_query_arg' )
				->with(
					\Mockery::contains(
						array(
							'page'         => 'e20r-Licensing',
							'license_stub' => $stub,
						)
					)
				)
				->andReturn(
					"https://localhost:7254/wp-admin/options-general.php?page=e20r-Licensing&license_stub={$stub}"
				);
		} catch ( \Exception $e ) {
			self::assertFalse( true, 'Error: ' . $e->getMessage() );
		}

		try {
			$license = new License( $stub, $this->settings_mock, $this->server_mock, $this->page_mock, $this->utils_mock );
			self::assertNotEquals(
				$expected,
				$license->get_license_page_url( $stub ),
				sprintf( 'Testing that license server URL contains "%s"', $stub )
			);
		} catch ( \Exception $e ) {
			self::assertFalse( true, 'Error: ' . $e->getMessage() );
		}
	}

	/**
	 * Fixture for the negative get_license_page_url test
	 *
	 * @return \string[][]
	 */
	public function fixture_page_url_neg() {
		return array(
			array( 'test license 1', 'https://localhost:7254/wp-admin/options-general.php?page=e20r-Licensing&license_stub=test-license-1' ),
			array( 'test license 1', 'https://localhost:7254/wp-admin/options-general.php?page=e20r-Licensing&license_stub=test+license+1' ),
		);
	}

	/**
	 * Test the is_new_version() function
	 *
	 * @param $expected
	 *
	 * @dataProvider fixture_new_version
	 * @covers \E20R\Licensing\License::is_new_version()
	 */
	public function test_is_new_version( $expected ) {

		if ( ! extension_loaded( 'runkit' ) ) {
			self::markTestSkipped( 'This test requires the runkit extension.' );
		}

		runkit_constant_remove( 'WP_PLUGIN_DIR' );
		try {
			$license = new License( null, $this->settings_mock, $this->server_mock, $this->page_mock, $this->utils_mock );
			self::assertEquals( $expected, $license->is_new_version() );
		} catch ( InvalidSettingsKey | MissingServerURL $e ) {
			self::assertFalse( true, $e->getMessage() );
		}
	}

	/**
	 * The fixture for the is_new_version() unit test
	 *
	 * @return \bool[][]
	 */
	public function fixture_new_version() {
		return array(
			array( true ),
		);
	}

	public function test_deactivate() {

	}

	/**
	 * Test that the get_instance() function returns the correct class type
	 *
	 * @covers \E20R\Licensing\License()
	 * @dataProvider fixture_skus
	 */
	public function test_get_instance( $test_sku ) {

		Filters\expectApplied( 'e20r_licensing_text_domain' )
			->with( '00-e20r-utilities' );

		try {
			$license = new License( $test_sku, $this->settings_mock, $this->server_mock, $this->page_mock, $this->utils_mock );
			self::assertInstanceOf(
				'\\E20R\\Utilities\\Licensing\\License',
				$license
			);
		} catch ( \Exception $e ) {
			self::assertFalse( true, $e->getMessage() );
		}
	}

	/**
	 * Fixture for the test_get_instance() tests
	 *
	 * @return \string[][]
	 */
	public function fixture_skus() {
		return array(
			array( 'E20R_LICENSING' ),
			array( 'E20RMC' ),
		);
	}
	public function test_get_text_domain() {

	}

	public function test_activate() {

	}

	public function test_is_license_expiring() {

	}
}
