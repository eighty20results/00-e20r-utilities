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
use E20R\Licensing\Exceptions\ServerConnectionError;
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
use function E20R\Tests\Unit\Fixtures\e20r_unittest_stubs;
use function E20R\Tests\Unit\Fixtures\fixture_upload_dir;

class LicenseTest extends Unit {

	use AssertThrows;
	use MockeryPHPUnitIntegration;
	/**
	 * @var LicenseSettings $settings_mock
	 */
	private $settings_mock;

	/**
	 * @var LicenseServer $server_mock
	 */
	private $server_mock;

	/**
	 * @var LicensePage $page_mock
	 */
	private $page_mock;

	/**
	 * @var Utilities $utils_mock
	 */
	private $utils_mock;

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

		$this->loadFiles();
		e20r_unittest_stubs();

		$this->loadStubs();
		$this->loadStubbedClasses();
	}

	/**
	 * Define stubs for various WP functions
	 */
	protected function loadStubs() {

		try {
			Functions\expect( 'get_option' )
				->with( 'e20r_license_settings' )
				->andReturnUsing(
					function() {
						return 'test';
					}
				);
		} catch ( \Exception $e ) {
			echo 'Error: ' . $e->getMessage(); // phpcs:ignore
		}
	}

	/**
	 * Create mocked functions for the required License() arguments
	 *
	 * @throws \Exception
	 */
	protected function loadStubbedClasses() {

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
		require_once __DIR__ . '/../inc/unittest_stubs.php';
		require_once __DIR__ . '/../../../inc/autoload.php';
	}

	public function test_ajax_handler_verify_license() {

	}

	/**
	 * Test the enqueue method for JavaScript and styles
	 */
	public function test_enqueue() {

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
				License::class,
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

	/**
	 * Unit test the License::is_active method
	 *
	 * @param string      $test_sku
	 * @param bool|null   $is_new_version
	 * @param bool        $is_licensed
	 * @param string|null $domain
	 * @param string|null $status
	 * @param bool        $expected
	 *
	 * @covers       \E20R\Licensing\License::is_active
	 * @dataProvider fixture_is_active
	 * @throws \Exception
	 */
	public function test_is_active( string $test_sku, ?bool $is_new_version, $is_licensed, $domain, $status, $expected ) {
		$m_defaults = $this->makeEmpty(
			Defaults::class,
			array(
				'get' => function( $param_name ) use ( $is_new_version ) {
					$value = null;
					if ( 'debug_logging' === $param_name ) {
						$value = true;
					}
					if ( 'version' === $param_name ) {
						$value = $is_new_version;
					}
					return $value;
				},
			)
		);
		$m_settings = $this->makeEmpty(
			LicenseSettings::class,
			array(
				'get'      => function( $parameter ) use ( $m_defaults, $domain, $status ) {
					$value = null;
					if ( 'plugin_defaults' === $parameter ) {
						$value = $m_defaults;
					}
					if ( 'server_url' === $parameter ) {
						$value = 'https://eighty20results.com/';
					}
					if ( 'domain' === $parameter ) {
						$value = $domain;
					}
					if ( in_array( $parameter, array( 'key', 'the_key' ), true ) ) {
						$value = 'license_key_id';
					}
					if ( 'status' === $parameter ) {
						$value = $status;
					}
					return $value;
				},
				'defaults' => array(),
			)
		);
		$m_page     = $this->makeEmpty(
			LicensePage::class,
		);
		$m_server   = $this->makeEmpty(
			LicenseServer::class,
		);
		$m_utils    = $this->makeEmpty(
			Utilities::class,
			array(
				'log' => function( $msg ) {
					error_log( $msg ); // phpcs:ignore
				},
			)
		);

		if ( ! empty( $domain ) ) {
			$m_utils->log( "Setting the SERVER_NAME to {$domain}" );
			$_SERVER['SERVER_NAME'] = $domain;
		} else {
			$_SERVER['SERVER_NAME'] = 'example.com';
		}

		// Mocking parts of the License() so we can test the is_active() method
		// without having to run through the other methods
		$m_license = $this->construct(
			License::class,
			array( $test_sku, $m_settings, $m_server, $m_page, $m_utils ),
			array(
				'is_new_version' => function() use ( $is_new_version ) {
					return $is_new_version;
				},
			),
		);

		$result = $m_license->is_active( $test_sku, $is_licensed );
		self::assertSame( $expected, $result, "Error: Incorrect value returned from Licensing::is_active(). Expected '{$expected}', received: '{$result}'" );
	}

	/**
	 * Fixture for test_is_active()
	 *
	 * @return array[]
	 */
	public function fixture_is_active() {
		return array(
			// sku, is_new_version, is_licensed, domain, status, expected
			array( 'e20r_default_license', false, false, null, 'inactive', false ), // 0
			array( 'E20R_TEST_LICENSE', null, false, null, 'inactive', false ), // 1
			array( 'E20R_TEST_LICENSE', true, false, null, 'inactive', false ), // 2
			array( 'E20R_TEST_LICENSE', false, false, null, 'inactive', false ), // 3
			array( 'E20R_TEST_LICENSE', true, true, null, 'inactive', false ), // 4
			array( 'E20R_TEST_LICENSE', true, true, 'localhost.local', 'active', true ), // 5
			array( 'E20R_TEST_LICENSE', true, true, 'localhost.local', 'inactive', false ), // 6
			array( 'E20R_TEST_LICENSE', true, true, 'eighty20results.com', 'inactive', false ), // 7
			array( 'E20R_TEST_LICENSE', true, true, 'eighty20results.com', 'active', true ), // 8
		);
	}

	/**
	 * Unit-test the License::activate() method
	 *
	 * @param string               $test_sku
	 * @param bool                 $is_new_version
	 * @param string               $store_code
	 * @param string               $status
	 * @param \stdClass|bool       $decoded_payload
	 * @param string|null          $domain
	 * @param string|null          $thrown_exception
	 * @param string|int           $expected_status
	 * @param string|null          $expected_settings
	 *
	 * @covers \E20R\Licensing\License::activate
	 * @dataProvider fixture_activate
	 * @throws \Exception|\Throwable
	 */
	public function test_activate_license( $test_sku, $is_new_version, $store_code, $status, $decoded_payload, $domain, $thrown_exception, $expected_status, ?string $expected_settings ) {
		Functions\expect( 'wp_upload_dir' )
			->zeroOrMoreTimes()
			->andReturnUsing(
				function() {
					return fixture_upload_dir();
				}
			);
		$m_defaults = $this->makeEmpty(
			Defaults::class,
			array(
				'get'      => function( $param_name ) use ( $is_new_version, $store_code ) {
					$value = null;
					if ( 'debug_logging' === $param_name ) {
						$value = true;
					}
					if ( 'version' === $param_name ) {
						$value = $is_new_version;
					}
					if ( 'store_code' === $param_name ) {
						$value = $store_code;
					}
					return $value;
				},
				'constant' => function( $constant_name ) {
					$value = -1;
					switch ( $constant_name ) {
						case 'E20R_LICENSE_ERROR':
							$value = 256;
							break;
						case 'E20R_LICENSE_MAX_DOMAINS':
							$value = 2048;
							break;
						case 'E20R_LICENSE_REGISTERED':
							$value = 1024;
							break;
						case 'E20R_LICENSE_DOMAIN_ACTIVE':
							$value = 512;
							break;
						case 'E20R_LICENSE_BLOCKED':
							$value = 128;
							break;
					}

					return $value;
				},
			)
		);
		$m_settings = $this->makeEmpty(
			LicenseSettings::class,
			array(
				'get'      => function( $parameter ) use ( $m_defaults, $status ) {
					$value = null;
					if ( 'plugin_defaults' === $parameter ) {
						$value = $m_defaults;
					}
					if ( 'server_url' === $parameter ) {
						$value = 'https://eighty20results.com/';
					}
					if ( in_array( $parameter, array( 'key', 'the_key' ), true ) ) {
						$value = 'license_key_id';
					}
					if ( 'status' === $parameter ) {
						$value = $status;
					}
					return $value;
				},
				'defaults' => array(),
			)
		);
		$m_page     = $this->makeEmpty(
			LicensePage::class,
		);
		$m_server   = $this->makeEmpty(
			LicenseServer::class,
			array(
				'send' => function( $api_params ) use ( $decoded_payload ) {
					return $decoded_payload;
				},
			)
		);
		$m_utils    = $this->makeEmpty(
			Utilities::class,
			array(
				'log'         => function( $msg ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( "Stubbed log(): {$msg}" );
				},
				'add_message' => function( $msg, $type, $location ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( "Stubbed WP notice: {$msg}, type: {$type}, location: {$location}" );
					self::assertSame( 'backend', $location );
				},
			)
		);
		// Mocking parts of the License() so we can test the is_licensed() method
		// without having to run through the other methods
		$m_license = $this->construct(
			License::class,
			array( $test_sku, $m_settings, $m_server, $m_page, $m_utils ),
			array(
				'is_new_version' => function() use ( $is_new_version ) {
					return $is_new_version;
				},
			),
		);

		if ( ! empty( $thrown_exception ) ) {
			$this->assertThrows(
				$thrown_exception,
				function() use ( $m_license, $test_sku ) {
					$m_license->activate( $test_sku );
				}
			);
		} else {
			$result = $m_license->activate( $test_sku );
			self::assertIsArray( $result );
			self::assertSame( $expected_status, $result['status'] );
			if ( ! empty( $expected_settings ) ) {
				self::assertSame( $expected_settings, get_class( $result['settings'] ) );
			} else {
				self::assertNull( $result['settings'] );
			}
		}
	}

	/**
	 * Fixture for the License::activate() WPUnit test
	 * @return array
	 */
	public function fixture_activate() {
		$lsc      = LicenseSettings::class;
		$defaults = new Defaults();
		$active   = $defaults->constant( 'E20R_LICENSE_DOMAIN_ACTIVE' );
		$blocked  = $defaults->constant( 'E20R_LICENSE_BLOCKED' );
		$error    = $defaults->constant( 'E20R_LICENSE_ERROR' );

		// test_sku, is_new_version, store_code, status, decoded_payload, domain, thrown_exception, expected_status, expected_settings
		return array(
			array( 'E20R_LICENSE_TEST', false, 'dummy_store_1', 'active', null, 'localhost', ServerConnectionError::class, null, null ),
			array( 'E20R_LICENSE_TEST', true, 'dummy_store_1', 'active', false, 'localhost', null, $blocked, null ),
			array( 'E20R_LICENSE_TEST', true, 'dummy_store_1', 'active', $this->make_payload( 'error', 1 ), 'localhost', null, $blocked, $lsc ),
		);
	}

	/**
	 * Create a (fake) decoded request response (i.e. JSON -> object)
	 *
	 * @param string $type
	 * @param int    $error_status
	 *
	 * @return \stdClass
	 */
	private function make_payload( $type = 'success', $error_status = 200 ) {
		$payload = $this->default_payload();

		switch ( $type ) {
			case 'success':
				$payload->error   = false;
				$payload->status  = $error_status;
				$payload->message = 'Dummy text for successful activation';
				break;
			case 'error':
				$payload->error  = true;
				$payload->errors = $this->select_error( $error_status );
				$payload->status = 500;
				break;
		}

		return $payload;
	}

	/**
	 * Return error info based on the status code supplied
	 *
	 * @param int $status_code
	 *
	 * @return array
	 */
	private function select_error( $status_code ) {
		$error_list = array(
			1   => 'The store code provided do not match the one set for the API',
			2   => 'The license key provided do not match the license key string format established by the API',
			3   => 'SKU provided do not match the product SKU associated with the license key',
			4   => 'License key provided do not match the license key string format established by the API',
			5   => 'The license key provided was not found in the database',
			100 => 'No SKU was provided in the request',
			101 => 'No license key code was provided in the request',
			102 => 'No store code was provided in the request',
			103 => 'No activation ID was provided in the request',
			104 => 'No domain was provided in the request',
			200 => 'The license key provided has expired',
			201 => 'License key activation limit reached. Deactivate one of the registered activations to proceed',
			202 => 'License key domain activation limit reached. Deactivate one or more of the registered activations to proceed',
			203 => 'Invalid activation',
		);

		return array( $status_code => array( $error_list[ $status_code ] ) );
	}

	/**
	 * Generate an empty (mock) object for the LicenseServer->send() method
	 *
	 * @return \stdClass
	 */
	private function default_payload(): \stdClass {
		$defaults = new Defaults();
		$server   = $defaults->constant( 'E20R_LICENSE_SERVER_URL' );

		$new_payload                           = new \stdClass();
		$new_payload->error                    = false;
		$new_payload->errors                   = array();
		$new_payload->status                   = null;
		$new_payload->message                  = null;
		$new_payload->data                     = new \stdClass();
		$new_payload->data->expire             = time();
		$new_payload->data->activation_id      = null;
		$new_payload->data->expire_date        = date( 'Y-M-D h:s', time() );
		$new_payload->data->timezone           = 'UTC';
		$new_payload->data->the_key            = null;
		$new_payload->data->url                = $server . '/my-account/view-license-key/?key=E20R_LICENSE_TEST';
		$new_payload->data->status             = 'inactive';
		$new_payload->data->allow_offline      = true;
		$new_payload->data->offline_interval   = 'days';
		$new_payload->data->offline_value      = 1;
		$new_payload->data->ctoken             = null;
		$new_payload->data->downloadable       = new \stdClass();
		$new_payload->data->downloadable->name = 'pmpro-import-members-from-csv-2.0';
		$new_payload->data->downloadable->url  = $server . '/protected-downloads/pmpro-import-members-from-csv.zip';

		return $new_payload;
	}

	/**
	 * Unit test for the License::is_licensed() method
	 *
	 * @param bool $is_active
	 * @param bool $license_status
	 * @param bool $is_local_server
	 * @param string|null $test_sku
	 * @param bool $force
	 * @param array $settings_array
	 * @param string $version
	 * @param bool $expected
	 *
	 * @throws \Exception
	 *
	 * @dataProvider fixture_is_licensed
	 * @covers \E20R\Licensing\License::is_licensed
	 */
	public function test_is_licensed( $is_active, $license_status, $is_local_server, $test_sku, $force, $settings_array, $version, $expected ) {
		$m_defaults = $this->makeEmpty(
			Defaults::class,
			array(
				'get' => function( $param_name ) use ( $version ) {
					$value = null;
					if ( 'debug_logging' === $param_name ) {
						$value = true;
					}
					if ( 'version' === $param_name ) {
						$value = $version;
					}
					return $value;
				},
			)
		);
		$m_settings = $this->makeEmpty(
			LicenseSettings::class,
			array(
				'get'          => function( $parameter ) use ( $m_defaults ) {
					$value = null;
					if ( 'plugin_defaults' === $parameter ) {
						$value = $m_defaults;
					}
					if ( 'server_url' === $parameter ) {
						$value = 'https://eighty20results.com/';
					}
					if ( 'new_version' === $parameter ) {
						$value = version_compare( $m_defaults->get( 'version' ), '3.0', 'ge' );
					}
					return $value;
				},
				'all_settings' => function() use ( $settings_array ) {
					return $settings_array;
				},
				'defaults'     => array(),
			)
		);
		$m_page     = $this->makeEmpty(
			LicensePage::class,
		);
		$m_server   = $this->makeEmpty(
			LicenseServer::class,
			array(
				'status' => $license_status,
			)
		);
		$m_utils    = $this->makeEmpty(
			Utilities::class,
			array(
				'is_license_server' => $is_local_server,
				'log'               => function( $msg ) {
					error_log( $msg ); // phpcs:ignore
				},
			)
		);

		global $current_user;

		if ( version_compare( $version, '3.0', 'lt' ) && empty( $current_user ) ) {
			$current_user                 = new \stdClass(); // phpcs:ignore
			$current_user->user_firstname = 'Thomas';
			$current_user->first_name     = $current_user->user_firstname;
			$current_user->user_lastname  = 'Sjolshagen';
			$current_user->last_name      = $current_user->user_lastname;
			$current_user->user_email     = 'nobody@example.com';
		}

		// Mocking parts of the License() so we can test the is_licensed() method
		// without having to run through the other methods
		$m_license = $this->construct(
			License::class,
			array( $test_sku, $m_settings, $m_server, $m_page, $m_utils ),
			array(
				'is_active'      => function( $sku, $is_licensed ) use ( $is_active ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( "Running mocked is_active( '{$sku}', '{$is_licensed}' ) and returning '{$is_active}'" );
					return $is_active && $is_licensed;
				},
				'is_new_version' => function() use ( $version ) {
					return version_compare( $version, '3.0', 'ge' );
				},
			),
		);
		global $current_user;

		if ( version_compare( $version, '3.0', 'lt' ) && empty( $current_user ) ) {
			$current_user->user_firstname = 'Thomas';
			$current_user->first_name     = $current_user->user_firstname;
			$current_user->user_lastname  = 'Sjolshagen';
			$current_user->last_name      = $current_user->user_lastname;
			$current_user->user_email     = 'nobody@example.com';
		}

		try {
			$result = $m_license->is_licensed( $test_sku, $force );
			self::assertSame( $expected, $result, "Error: Incorrect value returned from Licensing::is_licensed(). Expected '{$expected}', received: '{$result}'" );
		} catch ( \Exception $e ) {
			self::assertFalse( true, $e->getMessage() );
		}
	}

	/**
	 * Fixture for the is_licensed()
	 *
	 * @return array[]
	 */
	public function fixture_is_licensed(): array {
		return array(
			// is_active, license_status, is_local_server, test_sku, force, settings_array, version, expected
			array( false, false, true, 'E20R_TEST_LICENSE', false, array(), '3.2', true ), // 0
			array( false, false, false, null, false, array(), null, false ), // 1
			array( false, false, false, '', false, array(), null, false ), // 2
			array( true, false, false, 'E20R_TEST_LICENSE', false, $this->fixture_default_settings( 'E20R_TEST_LICENSE' ), '3.2', false ), // 3
			array( true, true, false, 'E20R_TEST_LICENSE', false, $this->fixture_default_settings( 'E20R_TEST_LICENSE' ), '3.2', true ), // 4
			array( true, true, false, 'E20R_TEST_LICENSE', true, $this->fixture_default_settings( 'E20R_TEST_LICENSE' ), '3.2', true ), // 5
			array( true, false, false, 'E20R_TEST_LICENSE', false, $this->fixture_default_settings( 'E20R_TEST_LICENSE' ), '2.0', false ), // 6
			array( true, true, false, 'E20R_TEST_LICENSE', false, $this->fixture_default_settings( 'E20R_TEST_LICENSE' ), '2.0', true ), // 7
			array( true, true, false, 'E20R_TEST_LICENSE', true, $this->fixture_default_settings( 'E20R_TEST_LICENSE' ), '2.0', true ), // 8
		);
	}

	/**
	 * Default settings for the LicenseSettings class.
	 *
	 * @param string $sku
	 * @param string $type
	 *
	 * @return array
	 */
	private function fixture_default_settings( $sku, $type = 'new' ) {
		$all_settings = array();

		if ( 'new' === $type ) {
			$settings = array(
				'expire'            => 0,
				'activation_id'     => null,
				'expire_date'       => null,
				'timezone'          => 'UTC',
				'the_key'           => '',
				'url'               => '',
				'domain_name'       => '',
				'has_expired'       => true,
				'status'            => 'cancelled',
				'allow_offline'     => false,
				'offiline_interval' => 'days',
				'offline_interval'  => 0,
				'product_sku'       => null,
			);
		}

		if ( 'old' === $type ) {
			$settings = array(
				'product'     => '',
				'key'         => null,
				'renewed'     => null,
				'domain'      => null,
				'domain_name' => '',
				'expires'     => null,
				'status'      => '',
				'first_name'  => '',
				'last_name'   => '',
				'email'       => '',
				'timestamp'   => null,
			);
		}
		$all_settings[ $sku ] = $settings;
		return $all_settings;
	}

	public function test_get_text_domain() {

	}

	public function test_activate() {

	}

	public function test_is_license_expiring() {

	}
}
