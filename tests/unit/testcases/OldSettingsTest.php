<?php
/**
 *
 * Copyright (c) 2021 - 2022. - Eighty / 20 Results by Wicked Strong Chicks.
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
 * @package E20R\Tests\Unit\OldSettingsTest
 */

namespace E20R\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Codeception\AssertThrows;
use Codeception\Test\Unit;
use E20R\Licensing\Exceptions\InvalidSettingsVersion;
use E20R\Licensing\Settings\Defaults;
use E20R\Licensing\Settings\OldSettings;
use E20R\Exceptions\InvalidSettingsKey;
use Exception;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\MockObject\MockObject;
use stdClass;
use Throwable;

/**
 * Unit testing the OldSettings() class
 */
class OldSettingsTest extends Unit {

	use MockeryPHPUnitIntegration;
	use AssertThrows;

	/**
	 * The mocked Defaults() class
	 *
	 * @var Defaults|MockObject|null
	 */
	private $m_default = null;

	/**
	 * The mocked expiration date
	 *
	 * @var string $exp_date
	 */
	private $exp_date;

	/**
	 * The setup function for this Unit Test suite
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->exp_date = '2021-08-20T16:49:00';

		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			define( 'DAY_IN_SECONDS', 60 * 60 * 24 );
		}

		if ( ! defined( 'PLUGIN_PHPUNIT' ) ) {
			define( 'PLUGIN_PHPUNIT', true );
		}

		$this->m_default = $this->makeEmpty(
			Defaults::class,
			array(
				'read_config' => true,
			)
		);

		Functions\expect( 'plugins_url' )
			->andReturn( sprintf( 'https://localhost:7254/wp-content/plugins/' ) );

		Functions\expect( 'admin_url' )
			->with( 'options-general.php' )
			->andReturn( 'https://localhost:7254/wp-admin/options-general.php' );

		Functions\expect( 'plugin_dir_path' )
			->andReturn( sprintf( __DIR__ . '/../../../src/E20R/Licensing/' ) );

		Functions\expect( 'get_current_blog_id' )
			->andReturn( 1 );

		Functions\when( 'esc_attr__' )
			->returnArg( 1 );

		Functions\when( 'esc_attr' )
			->returnArg( 1 );

		Functions\when( 'esc_html__' )
			->returnArg( 1 );

		Functions\expect( 'get_transient' )
			->with( Mockery::contains( 'err_info' ) )
			->andReturn( '' );

		Functions\expect( 'set_transient' )
			->with( 'err_info' )
			->andreturn( true );

		Functions\when( 'wp_unslash' )
			->alias(
				function( $value ) {
					return $value;
				}
			);

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost.local';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'];

		$this->loadFiles();
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
	 * Load source files for the Unit Test to execute
	 */
	public function loadFiles() {
		require_once __DIR__ . '/../../../inc/autoload.php';
	}

	/**
	 * Test create license settings class (new API)
	 *
	 * @param string $sku The product SKU to test old settings model with
	 * @param array  $license_settings The settings we want to apply
	 * @param array  $expected The expected settings after the class is sintantiated with the new settings
	 *
	 * @dataProvider fixture_license_settings
	 * @covers \E20R\Licensing\Settings\NewSettings()
	 */
	public function test_instantiate_old_settings( $sku, $license_settings, $expected ) {
		Functions\when( 'get_option' )
			->justReturn(
				function( $key, $default_value ) use ( $license_settings ) {
					$value = $default_value;
					if ( 'e20r_license_settings' === $key ) {
						if ( empty( $license_settings ) ) {
							return $default_value;
						}
						$value = $license_settings;
					}

					return $value;
				}
			);
		global $current_user;
		if ( empty( $current_user ) ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$current_user = new stdClass();
			// phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found
			$current_user->user_firstname = $current_user->first_name = 'Thomas';
			// phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found
			$current_user->user_lastname = $current_user->last_name = 'Sjolshagen';
			$current_user->user_email    = 'tester@example.com';
		}
		try {
			$settings = new OldSettings( $sku, $license_settings );
			foreach ( $expected as $key => $value ) {
				self::assertSame( $value, $settings->get( $key ), "Error: Different '{$key}' value returned: {$expected[$key]} => {$settings->get( $key )}" );
			}
			self::assertSame( $expected['product'], $settings->get( 'product_sku' ), 'Error: Unexpected SKU returned' );
			self::assertInstanceOf( OldSettings::class, $settings );
		} catch ( Exception $e ) {
			self::assertFalse( true, $e->getMessage() );
		}
	}

	/**
	 * Fixture for new License settings
	 *
	 * @return array[]
	 */
	public function fixture_license_settings() {
		$old_defaults = array(
			'product'    => '',
			'key'        => null,
			'renewed'    => null,
			'domain'     => '',
			'expires'    => null,
			'status'     => 'cancelled',
			'first_name' => 'Thomas',
			'last_name'  => '',
			'email'      => 'tester@example.com',
			'timestamp'  => time(),
		);

		return array(
			array(
				'PRODUCT_1',
				$old_defaults,
				array(
					'product'    => 'PRODUCT_1',
					'email'      => 'tester@example.com',
					'domain'     => '',
					'status'     => 'cancelled',
					'first_name' => 'Thomas',
				),
			),
			array(
				'PRODUCT_2',
				null,
				array(
					'product' => 'PRODUCT_2',
					'email'   => 'tester@example.com',
					'domain'  => 'localhost.local',
					'status'  => 'cancelled',
				),
			),
		);
	}

	/**
	 * Test both valid and invalid setting keys
	 *
	 * @param string $sku The product SKU we use when testing
	 * @param array  $license_settings The settings to apply when testing for invalid settings
	 * @param string $exception_class The returned exception when using the invalid setting(s)
	 *
	 * @throws InvalidSettingsVersion Raised exception (expected)
	 * @throws Throwable Default exception when running Codeception Unit tests
	 *
	 * @dataProvider fixture_invalid_settings
	 * @covers \E20R\Licensing\Settings\NewSettings
	 */
	public function test_invalid_setting( $sku, $license_settings, $exception_class ) {
		if ( ! empty( $exception_class ) ) {
			$this->assertThrows(
				$exception_class,
				function() use ( $sku, $license_settings ) {
					new OldSettings( $sku, $license_settings );
				}
			);
		} else {
			$settings = new OldSettings( $sku, $license_settings );
			self::assertInstanceOf( 'E20R\Licensing\Settings\OldSettings', $settings );
		}
	}

	/**
	 * Fixture to supply both invalid (old) and valid (new) settings values
	 *
	 * @return array[]
	 */
	public function fixture_invalid_settings() {
		$new_settings = array(
			'expire'           => -1,
			'activation_id'    => null,
			'expire_date'      => gmdate( 'Y-m-d\TH:i' ),
			'timezone'         => 'CET',
			'the_key'          => '',
			'url'              => '',
			'has_expired'      => true,
			'status'           => 'cancelled',
			'allow_offline'    => false,
			'offline_interval' => 'days',
			'offline_value'    => 0,
		);

		$old_settings = array(
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

		return array(
			// sku, invalid_settings, expected_exception
			array( 'E20R_TEST_LICENSE', $new_settings, InvalidSettingsVersion::class ),
			array( 'E20R_NEW_LICENSE', $old_settings, null ),
		);
	}

	/**
	 * Test the BaseSettings::set() function
	 *
	 * @param string $old_sku The original SKU to use when instantiating the settings class
	 * @param string $new_sku The new sku we update to and refresh settings for
	 * @param array  $new_settings The refreshed settings we want
	 * @param array  $expected The expected settings values after the refresh
	 *
	 * @covers       \E20R\Licensing\Settings\BaseSettings::set()
	 * @dataProvider fixture_update_old_settings
	 * @throws InvalidSettingsKey|InvalidSettingsVersion The possible exceptions raised during the test(s)
	 */
	public function test_set_old_license_parameters( $old_sku, $new_sku, $new_settings, $expected ) {

		$settings = new OldSettings( $old_sku );
		foreach ( $new_settings as $key => $value ) {
			$settings->set( $key, $value );
		}

		self::assertSame(
			$old_sku,
			$settings->get( 'product_sku' ),
			'Error: SKU is not the expected value: ' . $old_sku
		);
		foreach ( $expected as $key => $value ) {
			self::assertSame(
				$expected[ $key ],
				$settings->get( $key ),
				"Error: Unable to set '{$key}' to '{$value}'. Actual value: '{$settings->get( $key )}'"
			);
		}
		$settings->set( 'product_sku', $new_sku );
		self::assertSame(
			$new_sku,
			$settings->get( 'product_sku' ),
			'Error: SKU is not the expected value: ' . $old_sku
		);
	}

	/**
	 * Fixture for test_set_license_parameter()
	 *
	 * @return array[]
	 */
	public function fixture_update_old_settings() {
		$settings = $this->fixture_old_settings();

		return array(
			// Initial sku, updated sku, settings array, expected results array
			array(
				'E20R_DUMMY_1',
				'E20R_DUMMY_2',
				array(
					'product'    => '',
					'key'        => null,
					'renewed'    => null,
					'domain'     => '',
					'expires'    => $this->exp_date,
					'status'     => 'cancelled',
					'first_name' => '',
					'last_name'  => '',
					'email'      => '',
					'timestamp'  => 1629466277, // Friday, August 20, 2021 1:31:17 PM,
				),
				array(
					'product'    => '',
					'key'        => null,
					'renewed'    => null,
					'domain'     => '',
					'expires'    => $this->exp_date,
					'status'     => 'cancelled',
					'first_name' => '',
					'last_name'  => '',
					'email'      => '',
					'timestamp'  => 1629466277, // Friday, August 20, 2021 1:31:17 PM,
				),
			),
			array(
				'E20R_DUMMY_1',
				'E20R_DUMMY_2',
				$settings,
				array(
					'product'    => '',
					'key'        => null,
					'renewed'    => null,
					'domain'     => null,
					'expires'    => $this->exp_date,
					'status'     => 'cancelled',
					'first_name' => '',
					'last_name'  => '',
					'email'      => '',
					'timestamp'  => 1629466277, // Friday, August 20, 2021 1:31:17 PM,
				),
			),
		);
	}
	/**
	 * Fixture: Matches the expected settings for the OldSettings() class
	 *
	 * @return array
	 */
	private function fixture_old_settings() {
		return array(
			'product'    => '',
			'key'        => null,
			'renewed'    => null,
			'domain'     => null,
			'expires'    => $this->exp_date,
			'status'     => 'cancelled',
			'first_name' => '',
			'last_name'  => '',
			'email'      => '',
			'timestamp'  => 1629466277, // Friday, August 20, 2021 1:31:17 PM,
		);
	}

	/**
	 * Test the get() method
	 *
	 * @param string $property The property to test the getter() method for
	 * @param mixed  $expected_value The expected return value
	 * @param string $expected_exception The expected exception if one is expected
	 *
	 * @dataProvider fixture_properties_to_get
	 * @covers \E20R\Licensing\Settings\BaseSettings::get()
	 */
	public function test_get_old_settings( $property, $expected_value, $expected_exception ) {
		$new_settings = new OldSettings();
		try {
			$result = $new_settings->get( $property );
			self::assertSame( $expected_value, $result, "Error: '{$result}' is not the expected value for '{$property}'" );
		} catch ( InvalidSettingsKey $exception ) {
			self::assertInstanceOf( $expected_exception, $exception );
		}
	}

	/**
	 * Fixtures for the test_get_old_settings() unit test
	 *
	 * @return array
	 */
	public function fixture_properties_to_get() {
		return array(
			array( 'expire', -1, InvalidSettingsKey::class ),
			array( 'activation_id', null, InvalidSettingsKey::class ),
			array( 'expire_date', null, InvalidSettingsKey::class ),
			array( 'timezone', 'UTC', InvalidSettingsKey::class ),
			array( 'the_key', '', InvalidSettingsKey::class ),
			array( 'url', '', InvalidSettingsKey::class ),
			array( 'has_expired', true, InvalidSettingsKey::class ),
			array( 'status', 'cancelled', InvalidSettingsKey::class ),
			array( 'allow_offline', false, InvalidSettingsKey::class ),
			array( 'offline_interval', 'days', InvalidSettingsKey::class ),
			array( 'offline_value', 0, InvalidSettingsKey::class ),
			array( '', 0, InvalidSettingsKey::class ),
			array( null, 0, InvalidSettingsKey::class ),
			array( false, 0, InvalidSettingsKey::class ),
			array( 'EXPIRE', 0, InvalidSettingsKey::class ),
			array( 'URL', 0, InvalidSettingsKey::class ),
			array( 'product', 'e20r_default_license', null ),
			array( 'key', null, null ),
			array( 'renewed', null, null ),
			array( 'domain', 'localhost.local', null ),
			array( 'expires', null, null ),
			array( 'status', 'cancelled', null ),
			array( 'first_name', '', null ),
			array( 'last_name', '', null ),
			array( 'email', '', null ),
			array( 'timestamp', null, null ),
			array( 'product_sku', 'e20r_default_license', null ),
		);
	}
}
