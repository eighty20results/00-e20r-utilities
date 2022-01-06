<?php
/**
 * Copyright (c) 2016 - 20212 - Eighty / 20 Results by Wicked Strong Chicks.
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
 * @package E20R\Tests\Integration\License_IntegrationTest
 */

namespace E20R\Tests\Integration;

use Codeception\AssertThrows;
use Codeception\TestCase\WPTestCase;
use E20R\Licensing\Exceptions\BadOperation;
use E20R\Licensing\Exceptions\ConfigDataNotFound;
use E20R\Licensing\Exceptions\DefinedByConstant;
use E20R\Licensing\Exceptions\InvalidSettingsKey;
use E20R\Licensing\Exceptions\InvalidSettingsVersion;
use E20R\Licensing\Exceptions\MissingServerURL;
use E20R\Licensing\Exceptions\ServerConnectionError;
use E20R\Licensing\License;
use E20R\Licensing\LicensePage;
use E20R\Licensing\LicenseServer;
use E20R\Licensing\Settings\LicenseSettings;
use E20R\Licensing\Settings\Defaults;
use E20R\Utilities\Message;
use E20R\Utilities\Utilities;
use Exception;
use Mockery;
use stdClass;

/**
 * Integration tests for the License() class
 */
class License_IntegrationTest extends WPTestCase {

	use AssertThrows;

	/**
	 * Mock license server class
	 *
	 * @var null|LicenseServer|Mockery
	 */
	private $server = null;

	/**
	 * Mock LicensePage class
	 *
	 * @var null|LicensePage|Mockery
	 */
	private $page = null;

	/**
	 * Mock Utilities class
	 *
	 * @var Utilities|null|Mockery
	 */
	private $utils = null;

	/**
	 * Initial setup for the test cases
	 */
	public function setUp(): void {
		parent::setUp();
		$message     = new Message();
		$this->utils = new Utilities( $message );
	}

	/**
	 * Integration test for the License::is_active() method
	 *
	 * @param string      $test_sku The test product sku to use
	 * @param bool|null   $is_new_version Using new or old licensing plugin during test
	 * @param bool        $is_licensed Whether we pretend the product is licensed or not for this test
	 * @param string|null $domain The FQDN to use
	 * @param string|null $status The return status
	 * @param bool        $expected The expected return value(s)
	 *
	 * @covers       \E20R\Licensing\License::is_active
	 * @dataProvider fixture_is_active
	 * @throws Exception Generic exception handler
	 * @test
	 */
	public function it_should_be_active( string $test_sku, ?bool $is_new_version, $is_licensed, $domain, $status, $expected ) {
		if ( empty( $this->utils ) ) {
			$message     = new Message();
			$this->utils = new Utilities( $message );
		}
		$defaults = new Defaults( true, $this->utils );
		if ( true === $is_new_version ) {
			$defaults->unlock( 'version' );
			$defaults->set( 'version', '3.2' );
			$defaults->lock( 'version' );
		} else {
			$defaults->unlock( 'version' );
			$defaults->set( 'version', '2.0' );
			$defaults->lock( 'version' );
		}

		$settings = new LicenseSettings( $test_sku, $defaults, $this->utils );

		if ( $is_new_version ) {
			$settings->set( 'the_key', 'license_key_id' );
		} else {
			$settings->set( 'domain', $domain );
			$settings->set( 'key', 'license_key_id' );
		}

		$settings->set( 'status', $status );

		if ( empty( $this->page ) ) {
			$this->page = new LicensePage( $settings, $this->utils );
		}

		$m_server = $this->makeEmpty(
			LicenseServer::class
		);

		if ( ! empty( $domain ) ) {
			$this->utils->log( "Setting the SERVER_NAME to {$domain}" );
			$_SERVER['SERVER_NAME'] = $domain;
		} else {
			$_SERVER['SERVER_NAME'] = 'example.com';
		}

		$license = new License( $test_sku, $settings, $m_server, $this->page, $this->utils );
		$result  = $license->is_active( $test_sku, $is_licensed );
		self::assertSame( $expected, $result, "Error: Incorrect value returned from License()->is_active(). Expected '{$expected}', received: '{$result}'" );
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
	 * Integration test for the License::activate() method
	 *
	 * @param string        $test_sku Product SKU to use for testing
	 * @param array         $plugin_defaults Default settings for the Licensing plugin
	 * @param string        $status The status
	 * @param stdClass|bool $decoded_payload StdClass payload (or false)
	 * @param string|null   $domain The FQDN for the test domain
	 * @param string|null   $thrown_exception Exception to test for
	 * @param string|int    $expected_status Expected status from the (mock) activate operation
	 * @param string|null   $expected_settings Expected settings to be returned based on decoded payload information
	 *
	 * @throws InvalidSettingsKey|MissingServerURL|ServerConnectionError|BadOperation|ConfigDataNotFound|\Throwable Exceptions thrown by the test
	 * @covers \E20R\Licensing\License::activate
	 * @dataProvider fixture_activate
	 * @test
	 */
	public function it_should_become_activated( $test_sku, $plugin_defaults, $status, $decoded_payload, $domain, $thrown_exception, $expected_status, ?string $expected_settings ) {
		if ( empty( $this->utils ) ) {
			$message     = new Message();
			$this->utils = new Utilities( $message );
		}

		$defaults = new Defaults( true, $this->utils );
		foreach ( $plugin_defaults as $key => $value ) {
			$defaults->unlock( $key );
			$defaults->set( $key, $value );
			$defaults->unlock( $key );
		}

		$settings = new LicenseSettings( $test_sku, $defaults, $this->utils );
		$settings->set( 'status', $status );
		if ( true === (bool) $settings->get( 'new_version' ) ) {
			$settings->set( 'the_key', 'license_key_id' );
		} else {
			$settings->set( 'key', 'license_key_id' );
		}
		$page     = new LicensePage( $settings, $this->utils );
		$m_server = $this->makeEmpty(
			LicenseServer::class,
			array(
				'send' => function( $sent_arguments ) use ( $decoded_payload ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
					error_log( 'Mocked LicenseServer::send(): Using arguments => ' . print_r( $sent_arguments, true ) );
					return $decoded_payload;
				},
			)
		);

		$license = new License( $test_sku, $settings, $m_server, $page, $this->utils );

		if ( ! empty( $thrown_exception ) ) {
			$this->assertThrows(
				$thrown_exception,
				function() use ( $license, $test_sku ) {
					$license->activate( $test_sku );
				}
			);
		} else {
			$result = $license->activate( $test_sku, $m_server );
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
	 * Fixture for the License::activate() Integration test
	 *
	 * @return array
	 *
	 * @throws Exception Generic exception handler
	 */
	public function fixture_activate() {
		$defaults = new Defaults();
		$blocked  = $defaults->constant( 'E20R_LICENSE_BLOCKED' );
		$error    = $defaults->constant( 'E20R_LICENSE_ERROR' );

		// test_sku, plugin_defaults, status, decoded_payload, domain, thrown_exception, expected_status, expected_settings
		return array(
			array(
				'E20R_LICENSE_TEST',
				array(
					'debug_logging' => true,
					'version'       => '3.2',
					'store_code'    => 'dummy_store_1',
				),
				'active',
				null,
				'localhost',
				null,
				$blocked,
				null,
			),
			array(
				'E20R_LICENSE_TEST',
				array(
					'debug_logging' => true,
					'version'       => '2.0',
					'store_code'    => 'dummy_store_1',
				),
				'active',
				null,
				'localhost',
				ServerConnectionError::class,
				null,
				null,
			),
			array(
				'E20R_LICENSE_TEST',
				array(
					'debug_logging' => true,
					'version'       => '3.2',
					'store_code'    => 'dummy_store_1',
				),
				'active',
				false,
				'localhost',
				null,
				$blocked,
				null,
			),
			array(
				'E20R_LICENSE_TEST',
				array(
					'debug_logging' => true,
					'version'       => '3.2',
					'store_code'    => 'dummy_store_1',
				),
				'active',
				$this->make_payload( 'error', 1 ),
				'localhost',
				null,
				$error,
				LicenseSettings::class,
			),
		);
	}

	/**
	 * Create a (fake) decoded request response (i.e. JSON -> object)
	 *
	 * @param string $type The request payload type to return
	 * @param int    $error_status The HTTP status to use
	 *
	 * @return stdClass
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
	 * @param int $status_code The error code to return a message for
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
	 * @return stdClass
	 */
	private function default_payload(): stdClass {
		$defaults = new Defaults();
		$server   = $defaults->constant( 'E20R_LICENSE_SERVER_URL' );

		$new_payload                           = new stdClass();
		$new_payload->error                    = false;
		$new_payload->errors                   = array();
		$new_payload->status                   = null;
		$new_payload->message                  = null;
		$new_payload->data                     = new stdClass();
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
		$new_payload->data->downloadable       = new stdClass();
		$new_payload->data->downloadable->name = 'pmpro-import-members-from-csv-2.0';
		$new_payload->data->downloadable->url  = $server . '/protected-downloads/pmpro-import-members-from-csv.zip';

		return $new_payload;
	}

	/**
	 * Unit test for the License::is_licensed() method
	 *
	 * @param Defaults|Mockery $defaults The defaults to use for the LicenseSettings class
	 * @param bool             $is_active Whether the license is to be treated as "Active" or not
	 * @param bool             $license_status The status of the license we're checking
	 * @param bool             $is_local_server Are we mocking as a server where the licensing plugin runs locally
	 * @param string|null      $test_sku The test product SKU to use
	 * @param bool             $force Do we force a network connection for the test
	 * @param array            $settings_array License settings to use
	 * @param string           $version The license class version
	 * @param bool             $expected The expected return value
	 *
	 * @throws Exception Generic exception handler
	 *
	 * @dataProvider fixture_is_licensed
	 * @covers \E20R\Licensing\License::is_licensed
	 * @test
	 */
	public function it_should_be_licensed( $defaults, $is_active, $license_status, $is_local_server, $test_sku, $force, $settings_array, $version, $expected ) {

		preg_match( '/https:\/\/(.*)\//', $defaults->get( 'server_url' ), $match );

		$_SERVER['SERVER_NAME'] = $match[1];
		$this->utils->log( "Setting SERVER_NAME to: {$_SERVER['SERVER_NAME']}" ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidatedNotSanitized

		$defaults->unlock( 'debug_logging' );
		$defaults->set( 'debug_logging', true );
		$defaults->lock( 'debug_logging' );
		$defaults->unlock( 'version' );
		$defaults->set( 'version', $version );
		$defaults->lock( 'version' );

		if ( true === $is_local_server ) {
			$this->utils->log( 'Intentionally updating the E20R_LICENSE_SERVER setting' );
			$defaults->constant(
				'E20R_LICENSE_SERVER',
				Defaults::UPDATE_CONSTANT,
				$_SERVER['SERVER_NAME'] // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			);
		}

		$this->utils->log( 'Server URL: ' . $defaults->get( 'server_url' ) );
		$this->utils->log( 'E20R_LICENSE_SERVER: ' . $defaults->constant( 'E20R_LICENSE_SERVER_URL' ) );

		$settings = new LicenseSettings( $test_sku, $defaults, $this->utils );
		$settings->merge( $settings_array );
		$page     = new LicensePage( $settings, $this->utils );
		$m_server = $this->makeEmpty(
			LicenseServer::class,
			array(
				'status'    => function( $product_sku, $force ) use ( $license_status ) {
					$this->utils->log( "Using license status of {$license_status}" );
					return $license_status;
				},
				'is_active' => function( $product_sku, $is_licensed ) use ( $is_active ) {
					$this->utils->log( "Is Licensed='{$is_licensed}' -> Setting is_active to {$is_active}" );
					return $is_active;
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

		$license = new License( $test_sku, $settings, $m_server, $page, $this->utils );

		global $current_user;

		if ( version_compare( $version, '3.0', 'lt' ) && empty( $current_user ) ) {
			$current_user->user_firstname = 'Thomas';
			$current_user->first_name     = $current_user->user_firstname;
			$current_user->user_lastname  = 'Sjolshagen';
			$current_user->last_name      = $current_user->user_lastname;
			$current_user->user_email     = 'nobody@example.com';
		}

		try {
			$result = $license->is_licensed( $test_sku, $force );
			self::assertSame( $expected, $result, "Error: Incorrect value returned from Licensing::is_licensed(). Expected '{$expected}', received: '{$result}'" );
		} catch ( Exception $e ) {
			self::assertFalse( true, $e->getMessage() );
		}
	}

	/**
	 * Fixture for the is_licensed()
	 *
	 * @return array[]
	 *
	 * @throws BadOperation Raised when attempting an unsupported operation on a constant/setting
	 * @throws InvalidSettingsKey Raised when the specified key is not present in that version of the settings
	 * @throws ConfigDataNotFound Raised when the store config is not found
	 * @throws Exception Default exception raised
	 */
	public function fixture_is_licensed(): array {
		if ( empty( $this->utils ) ) {
			$message     = new Message();
			$this->utils = new Utilities( $message );
		}

		try {
			$local_server_defaults = new Defaults( true, $this->utils );
		} catch ( ConfigDataNotFound | InvalidSettingsKey | Exception $e ) {
			$this->utils->log( 'Error: Cannot create mock Defaults class: ' . $e->getMessage() );
			throw $e;
		}

		$local_server_defaults->unlock( 'server_url' );
		try {
			$local_server_defaults->set( 'server_url', 'https://' . gethostname() . '/' );
		} catch ( BadOperation | DefinedByConstant | InvalidSettingsKey $e ) {
			$this->utils->log( 'Error: ' . $e->getMessage() );
		}
		try {
			$local_server_defaults->lock( 'server_url' );
		} catch ( BadOperation $e ) {
			$this->utils->log( 'Error: ' . $e->getMessage() );
			throw $e;
		}

		$remote_server_defaults = new Defaults( true, $this->utils );
		$remote_server_defaults->unlock( 'server_url' );
		$remote_server_defaults->set( 'server_url', 'https://example.com/' );
		$remote_server_defaults->lock( 'server_url' );

		return array(
			// plugin_defaults, is_active, license_status, is_local_server, test_sku, force, settings_array, version, expected
			array( $remote_server_defaults, false, false, false, null, false, array(), null, false ), // 0
			array( $remote_server_defaults, false, false, false, '', false, array(), null, false ), // 1
			array( $local_server_defaults, true, false, true, 'E20R_TEST_LICENSE', false, array(), '3.2', true ), // 2
			array( $remote_server_defaults, true, false, false, 'E20R_TEST_LICENSE', false, $this->fixture_default_settings( 'E20R_TEST_LICENSE', 'new' ), '3.2', false ), // 3
			array( $remote_server_defaults, true, true, false, 'E20R_TEST_LICENSE', false, $this->fixture_default_settings( 'E20R_TEST_LICENSE', 'new', 'active' ), '3.2', true ), // 4
			array( $remote_server_defaults, true, true, false, 'E20R_TEST_LICENSE', true, $this->fixture_default_settings( 'E20R_TEST_LICENSE', 'new', 'active' ), '3.2', true ), // 5
			array( $remote_server_defaults, true, false, false, 'E20R_TEST_LICENSE', false, $this->fixture_default_settings( 'E20R_TEST_LICENSE', 'old' ), '2.0', false ), // 6
			array( $remote_server_defaults, true, true, false, 'E20R_TEST_LICENSE', false, $this->fixture_default_settings( 'E20R_TEST_LICENSE', 'old', 'active' ), '2.0', true ), // 7
			array( $remote_server_defaults, true, true, false, 'E20R_TEST_LICENSE', true, $this->fixture_default_settings( 'E20R_TEST_LICENSE', 'old', 'active' ), '2.0', true ), // 8
			array( $local_server_defaults, true, true, true, 'E20R_TEST_LICENSE', false, $this->fixture_default_settings( 'E20R_TEST_LICENSE', 'old', 'active' ), '2.0', true ), // 9
		);
	}

	/**
	 * Default settings for the LicenseSettings class.
	 *
	 * @param string $sku The product SKU we're using for testing
	 * @param string $type LicenseSettings type (new|old)
	 * @param string $status The status to use for the 'licensed' field
	 *
	 * @return array
	 */
	private function fixture_default_settings( $sku, $type = 'new', $status = 'cancelled' ) {
		if ( 'new' === $type ) {
			$settings = array(
				'expire'           => 0,
				'activation_id'    => null,
				'expire_date'      => null,
				'timezone'         => 'UTC',
				'the_key'          => $sku,
				'url'              => '',
				'domain_name'      => '',
				'has_expired'      => true,
				'status'           => $status,
				'allow_offline'    => false,
				'offline_interval' => 'days',
				'offline_value'    => 0,
				'product_sku'      => $sku,
			);
		}

		if ( 'old' === $type ) {
			$settings = array(
				'product'     => $sku,
				'key'         => $sku,
				'renewed'     => null,
				'domain'      => 'example.com',
				'domain_name' => '',
				'expires'     => null,
				'status'      => $status,
				'first_name'  => '',
				'last_name'   => '',
				'email'       => '',
				'timestamp'   => null,
			);
		}

		return $settings;
	}

	/**
	 * Test whether we'll use SSL verify or not
	 *
	 * @param string|null          $sku The product SKU to test with
	 * @param LicenseSettings|null $settings The license settings we're using
	 * @param bool                 $expected The expected result from the test
	 *
	 * @dataProvider fixture_ssl_verify
	 *
	 * @throws Exception The exception thrown by the makeEmpty() mockery class
	 * @test
	 */
	public function it_should_show_the_SSL_as_verified( ?string $sku, ?LicenseSettings $settings, bool $expected ) {
		$m_server = $this->makeEmpty(
			LicenseServer::class
		);
		$page     = new LicensePage( $settings, $this->utils );
		try {
			$license = new License( $sku, $settings, $m_server, $page, $this->utils );
			self::assertSame( $expected, $license->get_ssl_verify() );
		} catch ( InvalidSettingsKey | MissingServerURL | ConfigDataNotFound | BadOperation | InvalidSettingsVersion | Exception $e ) {
			self::assertFalse( true, $e->getMessage() );
		}
	}

	/**
	 * Generate fixture for the get_ssl_verify() tests
	 *
	 * @return array
	 * @throws Exception Generic exception handler
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
				$settings = new LicenseSettings( 'e20r_default_license', $defaults, $this->utils );
				$settings->set( 'ssl_verify', $ssl_value );
				$fixture_values[] = array( 'e20r_default_license', $settings, $ssl_value );
			} catch ( InvalidSettingsKey | MissingServerURL | ConfigDataNotFound | BadOperation | InvalidSettingsVersion $e ) {
				self::assertFalse( true, $e->getMessage() );
			}
		}
		return $fixture_values;
	}

	/**
	 * Integration test of the is_expiring() method
	 *
	 * @test
	 */
	public function it_should_expire_the_license() {

	}

	/**
	 * Integration test of the is_new_version() method
	 *
	 * @test
	 */
	public function it_should_show_that_we_are_using_the_new_licensing_code() {

	}

	/**
	 * Integration test of the deactivate() method
	 *
	 * @test
	 */
	public function it_should_deactivate_the_license() {

	}

	/**
	 * Integration test of the register() method
	 *
	 * @test
	 */
	public function it_should_register_the_license_with_the_license_server() {

	}
}
