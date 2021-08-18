<?php
/*
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
 */

namespace E20R\Tests\WPUnit;

use Codeception\AssertThrows;
use Codeception\TestCase\WPTestCase;
use E20R\Licensing\Exceptions\InvalidSettingsKey;
use E20R\Licensing\Exceptions\MissingServerURL;
use E20R\Licensing\Exceptions\ServerConnectionError;
use E20R\Licensing\License;
use E20R\Licensing\LicensePage;
use E20R\Licensing\LicenseServer;
use E20R\Licensing\Settings\LicenseSettings;
use E20R\Licensing\Settings\Defaults;
use E20R\Utilities\Utilities;

class License_WPUnitTest extends WPTestCase {

	use AssertThrows;

	private $server = null;

	private $page = null;

	private $utils = null;

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
		$message  = new Message();
		$utils    = new Utilities( $message );
		$defaults = new Defaults( true, $utils );
		$defaults->set( 'debug_logging', true );

		if ( empty( $this->utils ) ) {
			$this->utils = $utils;
		}

		if ( true === $is_new_version ) {
			$defaults->set( 'version', '3.2' );
		}

		$settings = new LicenseSettings( $test_sku, $defaults, $utils );
		$settings->set( 'domain_name', $domain );

		if ( true === $is_new_version ) {
			$settings->set( 'status', $status );
			$settings->set( 'the_key', 'license_key_id' );
		} else {
			$settings->set( 'key', 'license_key_id' );
		}
		$page   = new LicensePage( $settings, $utils );
		$server = $this->makeEmpty(
			LicenseServer::class
		);

		if ( empty( $this->page ) ) {
			$this->page = new LicensePage( $settings, $this->utils );
		}
		if ( empty( $this->server ) ) {
			$this->server = new LicenseServer( $settings, $this->utils );
		}
		if ( ! empty( $domain ) ) {
			$utils->log( "Setting the SERVER_NAME to {$domain}" );
			$_SERVER['SERVER_NAME'] = $domain;
		} else {
			$_SERVER['SERVER_NAME'] = 'example.com';
		}

		$license = new License( $test_sku, $settings, $server, $page, $utils );

		$result = $license->is_active( $test_sku, $is_licensed );
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
	 * @param LicenseSettings|null $expected_settings
	 *
	 * @covers \E20R\Licensing\License::activate
	 * @dataProvider fixture_activate
	 * @throws \Exception|\Throwable
	 */
	public function test_activate_license( $test_sku, $is_new_version, $store_code, $status, $decoded_payload, $domain, $thrown_exception, $expected_status, ?LicenseSettings $expected_settings ) {
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
			LicensePage::class
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
					error_log( $msg ); // phpcs:ignore
				},
				'add_message' => null,
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
			)
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
		$new_payload->data->expire_date        = date_i18n( 'Y-M-D h:s', time() );
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
				'defaults'     => array()
			)
		);
		$m_page     = $this->makeEmpty(
			LicensePage::class
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
			)
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

	/**
	 * Test whether we'll use SSL verify or not
	 *
	 * @param string|null          $sku
	 * @param LicenseSettings|null $settings
	 * @param bool                 $expected
	 *
	 * @dataProvider fixture_ssl_verify
	 */
	public function test_get_ssl_verify( ?string $sku, ?LicenseSettings $settings, bool $expected ) {
		try {
			$license = new License( $sku, $settings, $this->server, $this->page, $this->utils );
			self::assertSame( $expected, $license->get_ssl_verify() );
		} catch ( \Exception $e ) {
			self::assertFalse( true, $e->getMessage() );
		}
	}

	/**
	 * Generate fixture for the get_ssl_verify() tests
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function fixture_ssl_verify() {

		$possible_values = array(
			0 => true,
			1 => false,
		);
		$fixture_values  = array();

		foreach ( $possible_values as $ssl_value ) {
			try {
				$defaults = new Defaults();
				$settings = new LicenseSettings( 'e20r_default_license', $defaults );
				$settings->set( 'ssl_verify', $ssl_value );
				$fixture_values[] = array( 'e20r_default_license', $settings, $ssl_value );
			} catch ( InvalidSettingsKey | MissingServerURL $e ) {
				self::assertFalse( true, $e->getMessage() );
			}
		}
		return $fixture_values;
	}

	public function test_is_expiring() {

	}

	public function test_is_new_version() {

	}

	public function test_deactivate() {

	}

	public function test_register() {

	}
}
