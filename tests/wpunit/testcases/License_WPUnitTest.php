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

use Codeception\Test\Test;
use Codeception\TestCase\WPTestCase;
use E20R\Licensing\Exceptions\InvalidSettingsKey;
use E20R\Licensing\Exceptions\MissingServerURL;
use E20R\Licensing\License;
use E20R\Licensing\LicensePage;
use E20R\Licensing\LicenseServer;
use E20R\Licensing\Settings\LicenseSettings;
use E20R\Licensing\Settings\Defaults;
use E20R\Utilities\Utilities;

class License_WPUnitTest extends WPTestCase {

	private $server = null;

	private $page = null;

	/**
	 * @param string      $test_sku
	 * @param bool|null   $is_new_version
	 * @param bool        $is_licensed
	 * @param string|null $domain
	 * @param string|null $status
	 * @param bool        $expected
	 *
	 * @covers \E20R\Licensing\License::is_active
	 * @dataProvider fixture_is_active
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

	public function test_activate() {

	}

	/**
	 * WP Unit test for the is_licensed() method
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
				'product'    => '',
				'key'        => null,
				'renewed'    => null,
				'domain'     => null,
				'expires'    => null,
				'status'     => '',
				'first_name' => '',
				'last_name'  => '',
				'email'      => '',
				'timestamp'  => null,
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
			$license = new License( $sku, $settings );
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
