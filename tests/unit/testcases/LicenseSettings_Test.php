<?php
/**
 *
 * Copyright (c) 2021. - Eighty / 20 Results by Wicked Strong Chicks.
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
 * @package E20R\Tests\Unit\LicenseSettings_Test
 */

namespace E20R\Tests\Unit;

use Codeception\AssertThrows;
use Codeception\Test\Unit;
use Brain\Monkey;
use Brain\Monkey\Functions;
use E20R\Licensing\Exceptions\BadOperation;
use E20R\Licensing\Exceptions\DefinedByConstant;
use E20R\Licensing\Exceptions\InvalidSettingsKey;
use E20R\Licensing\Settings\Defaults;
use E20R\Licensing\Exceptions\MissingServerURL;
use E20R\Licensing\Settings\LicenseSettings;
use E20R\Licensing\Settings\NewSettings;
use E20R\Licensing\Settings\OldSettings;
use E20R\Utilities\Utilities;
use Exception;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\Mock;
use ReflectionException;
use Throwable;
use function E20R\Tests\Unit\Fixtures\e20r_unittest_stubs;

/**
 * Unit tests for the LicenseSettings class
 */
class LicenseSettings_Test extends Unit {

	use MockeryPHPUnitIntegration;
	use AssertThrows;

	/**
	 * Mocked Utilities class
	 *
	 * @var Utilities|Mock
	 */
	private $m_utils;

	/**
	 * The setup function for this Unit Test suite
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		// So we can update the default settings for the License component of the E20R Utilities module
		// For testing purposes
		if ( ! defined( 'PLUGIN_PHPUNIT' ) ) {
			define( 'PLUGIN_PHPUNIT', true );
		}

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', __DIR__ );
		}

		if ( ! defined( 'WP_DEBUG' ) ) {
			define( 'WP_DEBUG', true );
		}

		$this->loadFiles();
		e20r_unittest_stubs();
		$this->loadMockedFunctions();
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
	 * Define mocked functions we need
	 */
	public function loadMockedFunctions() {
		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			define( 'DAY_IN_SECONDS', 60 * 60 * 24 );
		}

		try {
			$this->m_utils = $this->makeEmpty(
				Utilities::class,
				array(
					'add_message'        => function( $msg, $severity, $location ) { error_log( "Mocked add_message({$severity} to {$location}): {$msg}" ); /* phpcs:ignore */ },
					'log'                => function( $msg ) { error_log( "Mocked log(): {$msg}" ); /* phpcs:ignore */ },
					'get_util_cache_key' => 'e20r_pw_utils_0',
				)
			);
		} catch ( Exception $exception ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Utilities() mocker: ' . $exception->getMessage() );
		}

		try {
			Functions\expect( 'wp_upload_dir' )
				->zeroOrMoreTimes()
				->andReturn(
					array(
						'path'    => '/var/www/html/wp-content/uploads/2021/08/',
						'url'     => 'https://localhost:7254/wp-content/uploads',
						'subdir'  => '2021/08',
						'basedir' => '/var/www/html/wp-content/uploads',
						'error'   => false,
					)
				);
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'wp_upload_dir() mock error: ' . esc_attr( $e->getMessage() ) );
		}

		try {
			Functions\expect( 'wp_unslash' )
				->zeroOrMoreTimes()
				->andReturnFirstArg();
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'wp_unslash() mock error: ' . esc_attr( $e->getMessage() ) );
		}

		try {
			Functions\expect( 'date_i18n' )
				->with( Mockery::contains( 'Y_M_D' ) )
				->zeroOrMoreTimes()
				->andReturn( '2021_07_28' );
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'date_i18n() mock error: ' . esc_attr( $e->getMessage() ) );
		}

		try {
			Functions\expect( 'file_exists' )
				->with( Mockery::contains( 'e20r_debug/debug_2021_07_28.log' ) )
				->zeroOrMoreTimes()
				->andReturn( true );
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'file_exists() mock error: ' . esc_attr( $e->getMessage() ) );
		}

	}

	/**
	 * Load source files for the Unit Test to execute
	 */
	public function loadFiles() {
		require_once __DIR__ . '/../../../inc/autoload.php';
		require_once __DIR__ . '/../inc/unittest_stubs.php';
	}

	/**
	 * Test the instantiation of the LicenseSettings() class (happy path)
	 *
	 * @param string $sku Product SKU to use for testing
	 * @param string $domain The FQDN
	 * @param bool   $with_debug Whether to use the DEBUG functionality or not
	 * @param string $version Expected settings class version
	 * @param array  $expected The expected values for the test
	 *
	 * @dataProvider fixture_instantiate_class
	 * @throws Throwable|Exception Generic exceptions
	 */
	public function test_instantiate_class( $sku, $domain, $with_debug, $version, $expected ) {

		$settings = array();

		Functions\expect( 'dirname' )
			->zeroOrMoreTimes()
			->with( Mockery::contains( '/.info.json' ) )
			->andReturn( __DIR__ . '/../../../src/E20R/Licensing/.info.json' );

		Functions\when( 'get_transient' )
			->justReturn( '' );

		Functions\when( 'set_transient' )
			->justReturn( true );
		Functions\expect( 'get_option' )
			->with( Mockery::contains( 'e20r_license_settings' ) )
			->andReturn(
				function( $name, $defaults ) use ( $settings ) {
					$value = $defaults;
					if ( 'e20r_license_settings' === $name ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log( "Mocked get_option() for {$name}" );
					}

					return $value; // TODO: Return better settings
				}
			);
		try {
			Functions\expect( 'get_option' )
				->with( 'timezone_string' )
				->zeroOrMoreTimes()
				->andReturn( 'Europe/Oslo' );
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'get_options() mock error: ' . esc_attr( $e->getMessage() ) );
		}

		$config = $this->fixture_config_file( $sku );

		try {
			$mocked_plugin_defaults = $this->makeEmpty(
				Defaults::class,
				array(
					'read_config' => true,
					'set'         => true,
					'get'         => function ( $param_name ) use ( $with_debug, $version, $config ) {

						$retval = null;
						switch ( $param_name ) {
							case 'debug_logging':
								$retval = $with_debug;
								break;
							case 'version':
								$retval = $version;
								break;
							case 'store_code':
								$retval = $config['store_code'];
								break;
							case 'server_url':
								$retval = $config['server_url'];
								break;
						}
						return $retval;
					},
				)
			);
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $e->getMessage() );
			return false;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST'] ?? $domain;
		$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? $domain;

		if ( empty( $config['server_url'] ) ) {
			$this->assertThrowsWithMessage(
				MissingServerURL::class,
				"Error: Haven't configured the license server URL, or the URL is malformed. Can be configured in the wp-config.php file.",
				function() use ( $sku, $mocked_plugin_defaults ) {
					return new LicenseSettings( $sku, $mocked_plugin_defaults, $this->m_utils );
				}
			);
			return false;
		} else {
			try {
				$settings = new LicenseSettings( $sku, $mocked_plugin_defaults, $this->m_utils );
			} catch ( Exception $e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Error: Unable to instantiate the LicenseSettings class: ' . $e->getMessage() );
				throw $e;
			}
		}

		// For testing purposes, we override the default plugin settings
		try {
			$settings->set( 'plugin_defaults', $mocked_plugin_defaults );
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Error: Unable to set new plugin_defaults: ' . $e->getMessage() );
		}

		self::assertSame( $expected['to_debug'], $settings->get( 'to_debug' ), "Error: The to_debug variable should have been set to {$expected['to_debug']}, it is {$settings->get( 'to_debug' )}" );
		self::assertSame( $expected['ssl_verify'], $settings->get( 'ssl_verify' ), "Error: The ssl_verify variable should have been set to {$expected['ssl_verify']}, it is {$settings->get( 'ssl_verify' )}" );
		self::assertSame( $expected['product_sku'], $settings->get( 'product_sku' ), "Error: The product_sku variable should have been set to {$expected['product_sku']}" );
		self::assertSame( $expected['new_version'], $settings->get( 'new_version' ), "Error: The new_version variable should have been set to {$expected['new_version']}" );
		self::assertSame( $expected['license_version'], $settings->get( 'plugin_defaults' )->get( 'version' ), "Error: The license_version variable should have been set to {$expected['license_version']}" );
		self::assertSame( $expected['store_code'], $settings->get( 'plugin_defaults' )->get( 'store_code' ), "Error: The store code variable should have been {$expected['store_code']}!" );

		return false;
	}

	/**
	 * The mocked contents for the fake `.info.json` file
	 *
	 * @param string $sku The test Product SKU to use
	 *
	 * @return string[]
	 */
	public function fixture_config_file( ?string $sku ): array {
		if ( empty( $sku ) ) {
			$sku = 'e20r_default_license';
		}
		$config_content = array(
			'E20R_TEST_LICENSE'    => array(
				'store_code' => 'dummy_store_code_1',
				'server_url' => 'https://eighty20results.com',
			),
			'e20r_default_license' => array(
				'store_code' => 'dummy_store_code_2',
				'server_url' => 'https://eighty20results.com/',
			),
			'e20r_no_server_url'   => array(
				'store_code' => 'dummy_store_code_4',
				'server_url' => null,
			),
		);

		return $config_content[ $sku ];
	}

	/**
	 * Fixture for the LicenseSettings constructor test
	 *
	 * @return array[]
	 */
	public function fixture_instantiate_class(): array {
		return array(
			// SKU, domain, with_debug_logging, Licensing version, result array
			array(
				'E20R_TEST_LICENSE',
				'example.net',
				false, // to_debug
				'3.1',
				array(
					'product_sku'     => 'E20R_TEST_LICENSE',
					'store_code'      => $this->fixture_config_file( 'E20R_TEST_LICENSE' )['store_code'],
					'ssl_verify'      => true,
					'to_debug'        => false,
					'license_version' => '3.1',
					'new_version'     => true,
				),
			),
			array(
				null,
				'eighty20results.com',
				true, // to_debug
				'2.0',
				array(
					'product_sku'     => 'e20r_default_license',
					'store_code'      => $this->fixture_config_file( 'e20r_default_license' )['store_code'],
					'ssl_verify'      => false,
					'to_debug'        => true,
					'license_version' => '2.0',
					'new_version'     => false,
				),
			),
			array(
				'e20r_no_server_url',
				'example.net',
				false, // to_debug
				'3.1',
				array(
					'product_sku'     => 'E20R_TEST_LICENSE',
					'store_code'      => $this->fixture_config_file( 'e20r_no_server_url' )['store_code'],
					'ssl_verify'      => true,
					'to_debug'        => false,
					'license_version' => '3.1',
					'new_version'     => true,
				),
			),
		);
	}


	/**
	 * Unit test for Loading settings for the license
	 *
	 * @param string $test_sku The test SKU to use
	 * @param array  $settings The Settings to use (array)
	 * @param array  $defaults The default settings
	 * @param array  $expected The expected results
	 *
	 * @throws ReflectionException|Exception Standard exceptions
	 *
	 * @dataProvider fixture_license_settings
	 */
	public function test_load_settings( $test_sku, $settings, $defaults, $expected ) {

		$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? $defaults['SERVER_NAME'];
		$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST'] ?? $defaults['SERVER_NAME'];

		Functions\when( '_deprecated_function' )
			->justEcho( 'Deprecated function warning printed' );

		Functions\when( 'update_option' )
			->alias(
				function( $option_name, $values, $cache ) use ( $defaults ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
					error_log( "Saving to {$option_name} (cached: {$cache}) -> " . print_r( $values, true ) );
					return $defaults['update_option'];
				}
			);

		Functions\when( 'get_option' )
			->alias(
				function( $name, $defaults = null ) use ( $settings, $test_sku ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( "Mocked get_option() for {$name}" );

					switch ( $name ) {
						case 'timezone_string':
							$value = 'Europe/Oslo';
							break;
						case 'e20r_license_settings':
							$using = $test_sku;
							if ( empty( $test_sku ) ) {
								$using = 'e20r_default_license';
							}
							$value = array( $using => $settings );
							break;
						default:
							$value = $defaults;
					}
					return $value;
				}
			);

		$m_defaults = $this->makeEmpty(
			Defaults::class,
			array(
				'get'      => function( $param_name ) use ( $defaults ) {
					$value = null;
					if ( 'debug_logging' === $param_name ) {
						$value = true;
					}
					if ( 'version' === $param_name ) {
						$value = $defaults['new_version'];
					}
					if ( 'store_code' === $param_name ) {
						$value = $defaults['store_code'];
					}
					if ( 'server_url' === $param_name ) {
						$value = $defaults['server_url'];
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

		$m_license_settings = $this->construct(
			LicenseSettings::class,
			array( $test_sku, $m_defaults, $this->m_utils ),
			array(
				'save' => $defaults['update_option'],
			)
		);
		// phpcs:ignore
		// $license_settings = new LicenseSettings( $test_sku, $m_defaults, $this->m_utils );
		$result = $m_license_settings->load_settings( $test_sku, $settings );
		self::assertSame( $expected, $result );
	}

	/**
	 * Fixture for License_WPUnitTest::test_load_settings()
	 *
	 * @return array[]
	 */
	public function fixture_license_settings() {

		return array(
			// Sku, saved_settings, new_version, expected
			array( // # 0
				'E20R_TEST_LICENSE',
				array(
					'expire'           => -1,
					'activation_id'    => null,
					'expire_date'      => '2021-08-21T08:30:30',
					'timezone'         => 'UTC',
					'the_key'          => '',
					'url'              => '',
					'has_expired'      => true,
					'status'           => 'cancelled',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
				array(
					'new_version'   => '3.2',
					'store_code'    => 'store_code_1',
					'server_url'    => 'https://eighty20results.com/',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'expire'           => -1,
					'activation_id'    => null,
					'expire_date'      => '2021-08-21T08:30:30',
					'timezone'         => 'UTC',
					'the_key'          => '',
					'url'              => '',
					'has_expired'      => true,
					'status'           => 'cancelled',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
			),
			array( // # 1
				'E20R_LICENSING',
				array(
					'product'    => 'Dummy license for Unit tests',
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'key'        => base64_encode( 'mysecretkey' ),
					'renewed'    => null,
					'domain'     => 'example.com',
					'expires'    => null,
					'status'     => 'cancelled',
					'first_name' => '',
					'last_name'  => '',
					'email'      => '',
					'timestamp'  => 10230,
				),
				array(
					'new_version'   => '2.0',
					'store_code'    => 'store_code_1',
					'server_url'    => 'https://eighty20results.com/',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'product'    => 'Dummy license for Unit tests',
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'key'        => base64_encode( 'mysecretkey' ),
					'renewed'    => null,
					'domain'     => 'example.com',
					'expires'    => null,
					'status'     => 'cancelled',
					'first_name' => '',
					'last_name'  => '',
					'email'      => '',
					'timestamp'  => 10230,
				),
			),
			array( // # 2
				null,
				array(
					'expire'           => -1,
					'activation_id'    => null,
					'expire_date'      => '2021-08-21T08:30:30',
					'timezone'         => 'UTC',
					'the_key'          => '',
					'url'              => '',
					'has_expired'      => true,
					'status'           => 'cancelled',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
				array(
					'new_version'   => '3.2',
					'store_code'    => 'store_code_1',
					'server_url'    => 'https://eighty20results.com/',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'e20r_default_license' => array(
						'expire'           => -1,
						'activation_id'    => null,
						'expire_date'      => '2021-08-21T08:30:30',
						'timezone'         => 'UTC',
						'the_key'          => '',
						'url'              => '',
						'has_expired'      => true,
						'status'           => 'cancelled',
						'allow_offline'    => false,
						'offline_interval' => 'days',
						'offline_value'    => 0,
					),
				),
			),
		);
	}

	/**
	 * Fixture for settings using new class model
	 *
	 * @return array
	 */
	public function fixture_new_settings() {
		return array(
			'expire'           => -1,
			'activation_id'    => null,
			'expire_date'      => gmdate( 'Y-m-d\Th:i:s' ),
			'timezone'         => 'UTC',
			'the_key'          => '',
			'url'              => '',
			'has_expired'      => true,
			'status'           => 'cancelled',
			'allow_offline'    => false,
			'offline_interval' => 'days',
			'offline_value'    => 0,
		);
	}

	/**
	 * Fixture for settings using old class model
	 *
	 * @return array
	 */
	public function fixture_old_settings() {
		return array(
			'product'    => '',
			'key'        => null,
			'renewed'    => null,
			'domain'     => '',
			'expires'    => null,
			'status'     => 'cancelled',
			'first_name' => '',
			'last_name'  => '',
			'email'      => '',
			'timestamp'  => time(),
		);
	}

	/**
	 * Test the `get()` member function for LicenseSettings()
	 *
	 * @param array  $defaults The default values to use
	 * @param array  $license_settings The license settings to use
	 * @param string $param_name The Defaults(), NewSettings() or OldSettings() parameter name
	 * @param mixed  $expected The expected return values from the function being tested
	 *
	 * @throws Exception Default exception thrown during test execution
	 * @dataProvider fixture_get_parameters
	 * @covers \E20R\Licensing\Settings\LicenseSettings::get()
	 */
	public function test_get_parameters( $defaults, $license_settings, $param_name, $expected ) {
		Functions\when( 'get_option' )
			->alias(
				function( $name, $defaults = null ) use ( $license_settings ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( "Mocked get_option() for {$name}" );

					switch ( $name ) {
						case 'timezone_string':
							$value = 'Europe/Oslo';
							break;
						case 'e20r_license_settings':
							$value = array( 'e20r_test_license' => $license_settings );
							break;
						default:
							$value = $defaults;
					}
					return $value;
				}
			);
		$m_defaults = $this->makeEmpty(
			Defaults::class,
			array(
				'get'      => function( $param_name ) use ( $defaults ) {
					$value = null;
					if ( 'debug_logging' === $param_name ) {
						$value = true;
					}
					if ( 'version' === $param_name ) {
						$value = $defaults['version'];
					}
					if ( 'store_code' === $param_name ) {
						$value = $defaults['store_code'];
					}
					if ( 'server_url' === $param_name ) {
						$value = $defaults['server_url'];
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

		if ( version_compare( $m_defaults->get( 'version' ), '3.0', 'ge' ) ) {
			$m_settings = $this->construct(
				NewSettings::class,
				array( 'e20r_test_license', $license_settings )
			);
		} else {
			$m_settings = $this->construct(
				OldSettings::class,
				array( 'e20r_test_license', $license_settings )
			);
		}

		$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? $defaults['SERVER_NAME'];
		$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST'] ?? $defaults['SERVER_NAME'];

		$m_license_settings = $this->construct(
			LicenseSettings::class,
			array( 'e20r_test_license', $m_defaults, $this->m_utils, $m_settings ),
			array(
				'save' => $defaults['update_option'],
			)
		);

		try {
			$result = $m_license_settings->get( $param_name );
			self::assertSame( $expected, $result );
		} catch ( InvalidSettingsKey $e ) {
			self::assertInstanceOf( InvalidSettingsKey::class, $e );
		}
	}

	/**
	 * Fixture for the test_get_parameter()
	 *
	 * @return array[]
	 */
	public function fixture_get_parameters() {
		return array(
			// plugin defaults, parameter name, expected value
			array( // #0 - NewSettings()
				array(
					'debug_logging' => true,
					'version'       => '3.2',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'expire'           => -1,
					'activation_id'    => null,
					'expire_date'      => '',
					'timezone'         => 'UTC',
					'the_key'          => '',
					'url'              => '',
					'has_expired'      => true,
					'status'           => 'cancelled',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
				'server_url',
				'https://eighty20results.com',
			),
			array( // #1 - NewSettings()
				array(
					'debug_logging' => true,
					'version'       => '3.2',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'expire'           => 1630236998,
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'activation_id'    => base64_encode( 'test_activation_id' ),
					'expire_date'      => '2021-08-29T13:37:00',
					'timezone'         => 'CET',
					'the_key'          => '123e4567-e89b-12d3-a456-426614174000',
					'url'              => 'https://eighty20results.com/license_key',
					'has_expired'      => false,
					'status'           => 'active',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
				'expire',
				1630236998,
			),
			array( // 2 - NewSettings()
				array(
					'debug_logging' => true,
					'version'       => '3.2',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'expire'           => 1630236998,
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'activation_id'    => base64_encode( 'test_activation_id' ),
					'expire_date'      => '2021-08-29T13:37:00',
					'timezone'         => 'CET',
					'the_key'          => '123e4567-e89b-12d3-a456-426614174000',
					'url'              => 'https://eighty20results.com/license_key',
					'has_expired'      => false,
					'status'           => 'active',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
				'activation_id',
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
				'dGVzdF9hY3RpdmF0aW9uX2lk', // test_activation_id
			),
			array(
				// #3 - NewSettings
				array(
					'debug_logging' => true,
					'version'       => '3.2',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'expire'           => 1630236998,
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'activation_id'    => base64_encode( 'test_activation_id' ),
					'expire_date'      => '2021-08-29T13:37:00',
					'timezone'         => 'CET',
					'the_key'          => '123e4567-e89b-12d3-a456-426614174000',
					'url'              => 'https://eighty20results.com/license_key',
					'has_expired'      => false,
					'status'           => 'active',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
				'expire_date',
				'2021-08-29T13:37:00',
			),
			array(
				// #4 - NewSettings
				array(
					'debug_logging' => true,
					'version'       => '3.2',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'expire'           => 1630236998,
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'activation_id'    => base64_encode( 'test_activation_id' ),
					'expire_date'      => '2021-08-29T13:37:00',
					'timezone'         => 'CET',
					'the_key'          => '123e4567-e89b-12d3-a456-426614174000',
					'url'              => 'https://eighty20results.com/license_key',
					'has_expired'      => false,
					'status'           => 'active',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
				'the_key',
				'123e4567-e89b-12d3-a456-426614174000',
			),
			array(
				// #5 - NewSettings
				array(
					'debug_logging' => true,
					'version'       => '3.2',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'expire'           => 1630236998,
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'activation_id'    => base64_encode( 'test_activation_id' ),
					'expire_date'      => '2021-08-29T13:37:00',
					'timezone'         => 'CET',
					'the_key'          => '123e4567-e89b-12d3-a456-426614174000',
					'url'              => 'https://eighty20results.com/license_key',
					'has_expired'      => false,
					'status'           => 'active',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
				'has_expired',
				false,
			),
			array(
				// #6 - NewSettings
				array(
					'debug_logging' => true,
					'version'       => '3.2',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'expire'           => 1630236998,
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'activation_id'    => base64_encode( 'test_activation_id' ),
					'expire_date'      => '2021-08-29T13:37:00',
					'timezone'         => 'CET',
					'the_key'          => '123e4567-e89b-12d3-a456-426614174000',
					'url'              => 'https://eighty20results.com/license_key',
					'has_expired'      => false,
					'status'           => 'active',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
				'status',
				'active',
			),
			array(
				// #7 - NewSettings
				array(
					'debug_logging' => true,
					'version'       => '3.2',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'expire'           => 1630236998,
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'activation_id'    => base64_encode( 'test_activation_id' ),
					'expire_date'      => '2021-08-29T13:37:00',
					'timezone'         => 'CET',
					'the_key'          => '123e4567-e89b-12d3-a456-426614174000',
					'url'              => 'https://eighty20results.com/license_key',
					'has_expired'      => false,
					'status'           => 'active',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
				'allow_offline',
				false,
			),
			array(
				// #7 - NewSettings
				array(
					'debug_logging' => true,
					'version'       => '3.2',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'expire'           => 1630236998,
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'activation_id'    => base64_encode( 'test_activation_id' ),
					'expire_date'      => '2021-08-29T13:37:00',
					'timezone'         => 'CET',
					'the_key'          => '123e4567-e89b-12d3-a456-426614174000',
					'url'              => 'https://eighty20results.com/license_key',
					'has_expired'      => false,
					'status'           => 'active',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
				'not_a_valid_NewSettings_key',
				InvalidSettingsKey::class,
			),
			array(
				// #8 - NewSettings
				array(
					'debug_logging' => true,
					'version'       => '3.2',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'expire'           => 1630236998,
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'activation_id'    => base64_encode( 'test_activation_id' ),
					'expire_date'      => '2021-08-29T13:37:00',
					'timezone'         => 'CET',
					'the_key'          => '123e4567-e89b-12d3-a456-426614174000',
					'url'              => 'https://eighty20results.com/license_key',
					'has_expired'      => false,
					'status'           => 'active',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
				'store_code',
				'abc123456',
			),
			array(
				// #9 - OldSettings()
				array(
					'debug_logging' => true,
					'version'       => '2.0',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'product'    => '',
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'key'        => base64_encode( 'test_activation_id' ),
					'renewed'    => false,
					'domain'     => 'example.com',
					'expires'    => '2021-08-29T13:37:00',
					'status'     => 'active',
					'first_name' => 'Tester',
					'last_name'  => 'MrsExample',
					'email'      => 'test@example.com',
					'timestamp'  => 1630236998,
				),
				'new_version',
				false,
			),
			array(
				// #9 - OldSettings()
				array(
					'debug_logging' => true,
					'version'       => '2.0',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'product'    => 'The E20R test license',
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'key'        => base64_encode( 'test_activation_id' ),
					'renewed'    => false,
					'domain'     => 'example.com',
					'expires'    => '2021-08-29T13:37:00',
					'status'     => 'active',
					'first_name' => 'Tester',
					'last_name'  => 'MrsExample',
					'email'      => 'test@example.com',
					'timestamp'  => 1630236998,
				),
				'product',
				'The E20R test license',
			),
			array(
				// #9 - OldSettings()
				array(
					'debug_logging' => true,
					'version'       => '1.0',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'product'    => 'The E20R test license',
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'key'        => base64_encode( 'test_activation_id' ),
					'renewed'    => false,
					'domain'     => 'example.com',
					'expires'    => '2021-08-29T13:37:00',
					'status'     => 'active',
					'first_name' => 'Tester',
					'last_name'  => 'MrsExample',
					'email'      => 'test@example.com',
					'timestamp'  => 1630236998,
				),
				'new_version',
				false,
			),
			array(
				// #10 - OldSettings()
				array(
					'debug_logging' => true,
					'version'       => '2.0',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'product'    => 'The E20R test license',
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'key'        => base64_encode( 'test_activation_id' ),
					'renewed'    => false,
					'domain'     => 'example.com',
					'expires'    => '2021-08-29T13:37:00',
					'status'     => 'active',
					'first_name' => 'Tester',
					'last_name'  => 'MrsExample',
					'email'      => 'test@example.com',
					'timestamp'  => 1630236998,
				),
				'key',
				'dGVzdF9hY3RpdmF0aW9uX2lk',
			),
			array(
				// #11 - OldSettings()
				array(
					'debug_logging' => true,
					'version'       => '2.0',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'product'    => 'The E20R test license',
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'key'        => base64_encode( 'test_activation_id' ),
					'renewed'    => true,
					'domain'     => 'example.com',
					'expires'    => '2021-08-29T13:37:00',
					'status'     => 'active',
					'first_name' => 'Tester',
					'last_name'  => 'MrsExample',
					'email'      => 'test@example.com',
					'timestamp'  => 1630236998,
				),
				'renewed',
				true,
			),
			array(
				// #12 - OldSettings()
				array(
					'debug_logging' => true,
					'version'       => '2.0',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'product'    => 'The E20R test license',
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'key'        => base64_encode( 'test_activation_id' ),
					'renewed'    => false,
					'domain'     => 'example.com',
					'expires'    => '2021-08-29T13:37:00',
					'status'     => 'active',
					'first_name' => 'Tester',
					'last_name'  => 'MrsExample',
					'email'      => 'test@example.com',
					'timestamp'  => 1630236998,
				),
				'domain',
				'example.com',
			),
			array(
				// #13 - OldSettings()
				array(
					'debug_logging' => true,
					'version'       => '2.0',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'product'    => 'The E20R test license',
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'key'        => base64_encode( 'test_activation_id' ),
					'renewed'    => false,
					'domain'     => 'example.com',
					'expires'    => '2021-08-29T13:37:00',
					'status'     => 'active',
					'first_name' => 'Tester',
					'last_name'  => 'MrsExample',
					'email'      => 'test@example.com',
					'timestamp'  => 1630236998,
				),
				'timestamp',
				1630236998,
			),
			array(
				// #13 - OldSettings()
				array(
					'debug_logging' => true,
					'version'       => '2.0',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'product'    => 'The E20R test license',
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'key'        => base64_encode( 'test_activation_id' ),
					'renewed'    => false,
					'domain'     => 'example.com',
					'expires'    => '2021-08-29T13:37:00',
					'status'     => 'active',
					'first_name' => 'Tester',
					'last_name'  => 'MrsExample',
					'email'      => 'test@example.com',
					'timestamp'  => 1630236998,
				),
				'firstname',
				InvalidSettingsKey::class,
			),
			array(
				// #13 - OldSettings()
				array(
					'debug_logging' => true,
					'version'       => '2.0',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'product'    => 'The E20R test license',
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'key'        => base64_encode( 'test_activation_id' ),
					'renewed'    => false,
					'domain'     => 'example.com',
					'expires'    => '2021-08-29T13:37:00',
					'status'     => 'active',
					'first_name' => 'Tester',
					'last_name'  => 'MrsExample',
					'email'      => 'test@example.com',
					'timestamp'  => 1630236998,
				),
				'first_name',
				'Tester',
			),
		);
	}

	/**
	 * Test the `set()` member function for LicenseSettings()
	 *
	 * @param array  $defaults         Default settings to use
	 * @param array  $license_settings License settings to use
	 * @param string $param_name       The Defaults(), NewSettings() or OldSettings() parameter name
	 * @param mixed  $param_value      The parameter value we attempt to set
	 * @param mixed  $expected         The expected status/value after attempting the set operation
	 *
	 * @dataProvider fixture_set_parameters
	 * @covers       \E20R\Licensing\Settings\LicenseSettings::set()
	 *
	 * @throws InvalidSettingsKey Exceptions thrown during test execution
	 * @throws BadOperation|ReflectionException|DefinedByConstant Exceptions thrown during test
	 *                                                                               execution
	 * @throws Exception Raised if Mockery::construct() fails
	 */
	public function test_set_parameters( $defaults, $license_settings, $param_name, $param_value, $expected ) {
		Functions\when( 'get_option' )
			->alias(
				function( $name, $defaults = null ) use ( $license_settings ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( "Mocked get_option() for {$name}" );

					switch ( $name ) {
						case 'timezone_string':
							$value = 'Europe/Oslo';
							break;
						case 'e20r_license_settings':
							$value = array( 'e20r_test_license' => $license_settings );
							break;
						default:
							$value = $defaults;
					}
					return $value;
				}
			);
		$m_defaults = $this->makeEmpty(
			Defaults::class,
			array(
				'get'      => function( $param_name ) use ( $defaults ) {
					$value = null;
					if ( 'debug_logging' === $param_name ) {
						$value = true;
					}
					if ( 'version' === $param_name ) {
						$value = $defaults['version'];
					}
					if ( 'store_code' === $param_name ) {
						$value = $defaults['store_code'];
					}
					if ( 'server_url' === $param_name ) {
						$value = $defaults['server_url'];
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

		if ( version_compare( $m_defaults->get( 'version' ), '3.0', 'ge' ) ) {
			$m_settings = $this->construct(
				NewSettings::class,
				array( 'e20r_test_license', $license_settings )
			);
		} else {
			$m_settings = $this->construct(
				OldSettings::class,
				array( 'e20r_test_license', $license_settings )
			);
		}

		$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? $defaults['SERVER_NAME'];
		$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST'] ?? $defaults['SERVER_NAME'];

		$m_license_settings = $this->construct(
			LicenseSettings::class,
			array( 'e20r_test_license', $m_defaults, $this->m_utils, $m_settings ),
			array(
				'save' => $defaults['update_option'],
			)
		);

		try {
			$set_result = $m_license_settings->set( $param_name, $param_value );
			$result     = $m_license_settings->get( $param_name );
			self::assertTrue( $set_result );
			self::assertSame( $expected, $result );
		} catch ( InvalidSettingsKey $e ) {
			self::assertInstanceOf( InvalidSettingsKey::class, $e );
		}
	}

	/**
	 * Fixture for the test_set_parameters()
	 *
	 * @return array[]
	 */
	public function fixture_set_parameters() {
		return array(
			// plugin defaults, parameter name, expected value
			array( // #0 - NewSettings()
				array(
					'debug_logging' => true,
					'version'       => '3.2',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'expire'           => - 1,
					'activation_id'    => null,
					'expire_date'      => '',
					'timezone'         => 'UTC',
					'the_key'          => '',
					'url'              => '',
					'has_expired'      => true,
					'status'           => 'cancelled',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
				'server_url',
				'https://eighty20results.com',
				'https://eighty20results.com',
			),
			array( // #1 - NewSettings()
				array(
					'debug_logging' => true,
					'version'       => '3.2',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'expire'           => - 1,
					'activation_id'    => null,
					'expire_date'      => '',
					'timezone'         => 'UTC',
					'the_key'          => '',
					'url'              => '',
					'has_expired'      => true,
					'status'           => 'cancelled',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
				'expire',
				1630236998,
				1630236998,
			),
			array( // #2 - Uses the NewSettings class
				array(
					'debug_logging' => true,
					'version'       => '3.2',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'expire'           => - 1,
					'activation_id'    => null,
					'expire_date'      => '',
					'timezone'         => 'UTC',
					'the_key'          => '',
					'url'              => '',
					'has_expired'      => true,
					'status'           => 'cancelled',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
				'activation_id',
				'dGVzdF9hY3RpdmF0aW9uX2lk', // test_activation_id
				'dGVzdF9hY3RpdmF0aW9uX2lk', // test_activation_id
			),
			array(
				// #3 - NewSettings
				array(
					'debug_logging' => true,
					'version'       => '3.2',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'expire'           => - 1,
					'activation_id'    => null,
					'expire_date'      => '',
					'timezone'         => 'UTC',
					'the_key'          => '',
					'url'              => '',
					'has_expired'      => true,
					'status'           => 'cancelled',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
				'expire_date',
				'2021-08-29T13:37:00',
				'2021-08-29T13:37:00',
			),
			array(
				// #4 - NewSettings
				array(
					'debug_logging' => true,
					'version'       => '3.2',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'expire'           => - 1,
					'activation_id'    => null,
					'expire_date'      => '',
					'timezone'         => 'UTC',
					'the_key'          => '',
					'url'              => '',
					'has_expired'      => true,
					'status'           => 'cancelled',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
				'the_key',
				'123e4567-e89b-12d3-a456-426614174000',
				'123e4567-e89b-12d3-a456-426614174000',
			),
			array(
				// #5 - NewSettings
				array(
					'debug_logging' => true,
					'version'       => '3.2',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'expire'           => - 1,
					'activation_id'    => null,
					'expire_date'      => '',
					'timezone'         => 'UTC',
					'the_key'          => '',
					'url'              => '',
					'has_expired'      => true,
					'status'           => 'cancelled',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
				'has_expired',
				false,
				false,
			),
			array(
				// #6 - NewSettings
				array(
					'debug_logging' => true,
					'version'       => '3.2',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'expire'           => - 1,
					'activation_id'    => null,
					'expire_date'      => '',
					'timezone'         => 'UTC',
					'the_key'          => '',
					'url'              => '',
					'has_expired'      => true,
					'status'           => 'cancelled',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
				'status',
				'active',
				'active',
			),
			array(
				// #7 - NewSettings
				array(
					'debug_logging' => true,
					'version'       => '3.2',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'expire'           => - 1,
					'activation_id'    => null,
					'expire_date'      => '',
					'timezone'         => 'UTC',
					'the_key'          => '',
					'url'              => '',
					'has_expired'      => true,
					'status'           => 'cancelled',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
				'allow_offline',
				false,
				false,
			),
			array(
				// #8 - NewSettings
				array(
					'debug_logging' => true,
					'version'       => '3.2',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'expire'           => - 1,
					'activation_id'    => null,
					'expire_date'      => '',
					'timezone'         => 'UTC',
					'the_key'          => '',
					'url'              => '',
					'has_expired'      => true,
					'status'           => 'cancelled',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
				'not_a_valid_NewSettings_key',
				'we_dont_care',
				InvalidSettingsKey::class,
			),
			array(
				// #9 - NewSettings
				array(
					'debug_logging' => true,
					'version'       => '3.2',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'expire'           => - 1,
					'activation_id'    => null,
					'expire_date'      => '',
					'timezone'         => 'UTC',
					'the_key'          => '',
					'url'              => '',
					'has_expired'      => true,
					'status'           => 'cancelled',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
				'store_code',
				'abc123456',
				'abc123456',
			),
			array(
				// #10 - OldSettings()
				array(
					'debug_logging' => true,
					'version'       => '2.0',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'product'    => '',
					'key'        => null,
					'renewed'    => null,
					'domain'     => $_SERVER['HTTP_HOST'] ?? 'localhost.local',  // phpcs:ignore
					'expires'    => null,
					'status'     => 'cancelled',
					'first_name' => '',
					'last_name'  => '',
					'email'      => '',
					'timestamp'  => null,
				),
				'new_version',
				false,
				InvalidSettingsKey::class,
			),
			array(
				// #11 - OldSettings()
				array(
					'debug_logging' => true,
					'version'       => '2.0',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'product'    => '',
					'key'        => null,
					'renewed'    => null,
					'domain'     => $_SERVER['HTTP_HOST'] ?? 'localhost.local', // phpcs:ignore
					'expires'    => null,
					'status'     => 'cancelled',
					'first_name' => '',
					'last_name'  => '',
					'email'      => '',
					'timestamp'  => null,
				),
				'product',
				'The E20R test license',
				'The E20R test license',
			),
			array(
				// #12 - OldSettings()
				array(
					'debug_logging' => true,
					'version'       => '1.0',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'product'    => '',
					'key'        => null,
					'renewed'    => null,
					'domain'     => $_SERVER['HTTP_HOST'] ?? 'localhost.local', // phpcs:ignore
					'expires'    => null,
					'status'     => 'cancelled',
					'first_name' => '',
					'last_name'  => '',
					'email'      => '',
					'timestamp'  => null,
				),
				'new_version',
				false,
				InvalidSettingsKey::class,
			),
			array(
				// #13 - OldSettings()
				array(
					'debug_logging' => true,
					'version'       => '2.0',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'product'    => '',
					'key'        => null,
					'renewed'    => null,
					'domain'     => $_SERVER['HTTP_HOST'] ?? 'localhost.local', // phpcs:ignore
					'expires'    => null,
					'status'     => 'cancelled',
					'first_name' => '',
					'last_name'  => '',
					'email'      => '',
					'timestamp'  => null,
				),
				'key',
				'dGVzdF9hY3RpdmF0aW9uX2lk',
				'dGVzdF9hY3RpdmF0aW9uX2lk',
			),
			array(
				// #14 - OldSettings()
				array(
					'debug_logging' => true,
					'version'       => '2.0',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'product'    => '',
					'key'        => null,
					'renewed'    => null,
					'domain'     => $_SERVER['HTTP_HOST'] ?? 'localhost.local', // phpcs:ignore
					'expires'    => null,
					'status'     => 'cancelled',
					'first_name' => '',
					'last_name'  => '',
					'email'      => '',
					'timestamp'  => null,
				),
				'renewed',
				true,
				true,
			),
			array(
				// #15 - OldSettings()
				array(
					'debug_logging' => true,
					'version'       => '2.0',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'product'    => '',
					'key'        => null,
					'renewed'    => null,
					'domain'     => $_SERVER['HTTP_HOST'] ?? 'localhost.local', // phpcs:ignore
					'expires'    => null,
					'status'     => 'cancelled',
					'first_name' => '',
					'last_name'  => '',
					'email'      => '',
					'timestamp'  => null,
				),
				'domain',
				'example.com',
				'example.com',
			),
			array(
				// #16 - OldSettings()
				array(
					'debug_logging' => true,
					'version'       => '2.0',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'product'    => '',
					'key'        => null,
					'renewed'    => null,
					'domain'     => $_SERVER['HTTP_HOST'] ?? 'localhost.local', // phpcs:ignore
					'expires'    => null,
					'status'     => 'cancelled',
					'first_name' => '',
					'last_name'  => '',
					'email'      => '',
					'timestamp'  => null,
				),
				'timestamp',
				1630236998,
				1630236998,
			),
			array(
				// #17 - OldSettings()
				array(
					'debug_logging' => true,
					'version'       => '2.0',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'product'    => '',
					'key'        => null,
					'renewed'    => null,
					'domain'     => $_SERVER['HTTP_HOST'] ?? 'localhost.local', // phpcs:ignore
					'expires'    => null,
					'status'     => 'cancelled',
					'first_name' => '',
					'last_name'  => '',
					'email'      => '',
					'timestamp'  => null,
				),
				'firstname',
				'doesnt_matter',
				InvalidSettingsKey::class,
			),
			array(
				// #18 - OldSettings()
				array(
					'debug_logging' => true,
					'version'       => '2.0',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'product'    => '',
					'key'        => null,
					'renewed'    => null,
					'domain'     => $_SERVER['HTTP_HOST'] ?? 'localhost.local', // phpcs:ignore
					'expires'    => null,
					'status'     => 'cancelled',
					'first_name' => '',
					'last_name'  => '',
					'email'      => '',
					'timestamp'  => null,
				),
				'first_name',
				'Tester',
				'Tester',
			),
			array(
				// #19 - NewSettings
				array(
					'debug_logging' => true,
					'version'       => '3.2',
					'store_code'    => 'abc123456',
					'server_url'    => 'https://eighty20results.com',
					'SERVER_NAME'   => 'eighty20results.com',
					'update_option' => true,
				),
				array(
					'expire'           => - 1,
					'activation_id'    => null,
					'expire_date'      => '',
					'timezone'         => 'UTC',
					'the_key'          => 'abc987654321',
					'url'              => '',
					'has_expired'      => true,
					'status'           => 'cancelled',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
				'license_key',
				'abc987654321',
				'abc987654321',
			),
		);
	}
}
