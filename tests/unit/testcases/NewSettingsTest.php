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
 * @package E20R\Tests\Unit\NewSettingsTest
 */

namespace E20R\Tests\Unit;

use Codeception\AssertThrows;
use Codeception\Test\Unit;
use E20R\Licensing\Exceptions\InvalidSettingsKey;
use E20R\Licensing\Exceptions\InvalidSettingsVersion;
use E20R\Licensing\Settings\BaseSettings;
use E20R\Licensing\Settings\Defaults;
use E20R\Licensing\Settings\NewSettings;
use Brain\Monkey;
use Brain\Monkey\Functions;
use E20R\Licensing\Settings\OldSettings;
use Exception;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionException;
use Throwable;

/**
 * Testing the WooCommerce License Server settings (v3.x of the E20R Utilities licensing code)
 */
class NewSettingsTest extends Unit {

	use MockeryPHPUnitIntegration;
	use AssertThrows;

	/**
	 * Mocked Defaults class to use for Settings tests
	 *
	 * @var Defaults|MockObject|null
	 */
	private $m_default = null;

	/**
	 * The mocked expiration date (used by tests)
	 *
	 * @var string|null $exp_date
	 */
	private $exp_date = null;
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

		Functions\expect( 'get_option' )
			->with( 'timezone_string' )
			->andReturn( 'Europe/Oslo' );

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
					return stripslashes_deep( $value );
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
	 * @param string $sku The product SKU to test with
	 * @param array  $license_settings The array of license settings to apply after instantiating the settings class
	 * @param array  $expected Array of expected setting key/value pairs
	 *
	 * @dataProvider fixture_license_settings
	 * @covers \E20R\Licensing\Settings\NewSettings()
	 */
	public function test_instantiate_new_settings( $sku, $license_settings, $expected ) {
		Functions\expect( 'get_option' )
			->with( Mockery::contains( 'e20r_license_settings' ) )
			->andReturn(
				function( $key, $default_value ) use ( $license_settings ) {
					if ( empty( $license_settings ) ) {
						return $default_value;
					}
					return $license_settings;
				}
			);
		try {
			$settings = new NewSettings( $sku, $license_settings );
			foreach ( $expected as $key => $value ) {
				self::assertSame( $value, $settings->get( $key ), "Error: Different '{$key}' value returned! {$expected[$key]} => {$settings->get( $key )}" );
			}
			self::assertInstanceOf( NewSettings::class, $settings );
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
		$new_defaults = array(
			'expire'           => 1629466277, // Friday, August 20, 2021 1:31:17 PM
			'activation_id'    => null,
			'expire_date'      => $this->exp_date,
			'timezone'         => 'CET',
			'the_key'          => '',
			'url'              => '',
			'has_expired'      => true,
			'status'           => 'cancelled',
			'allow_offline'    => false,
			'offline_interval' => 'days',
			'offline_value'    => 0,
		);

		return array(
			array(
				'PRODUCT_1',
				$new_defaults,
				array(
					'product_sku' => 'PRODUCT_1',
					'expire_date' => $this->exp_date,
					'timezone'    => 'CET',
					'has_expired' => true,
				),
			),
			array(
				'PRODUCT_2',
				null,
				array(
					'product_sku' => 'PRODUCT_2',
					'expire_date' => '',
					'timezone'    => 'UTC',
					'has_expired' => true,
				),
			),
		);
	}

	/**
	 * Test both valid and invalid setting keys
	 *
	 * @param string $sku The product SKU to use for the test
	 * @param array  $license_settings The settings to apply after instantiating the settings class
	 * @param string $exception_class The exception raised if a setting supplied is incorrect
	 *
	 * @throws InvalidSettingsVersion A possible exception being raised during the tests
	 * @throws Throwable Another possible exception being raised during the tests
	 *
	 * @dataProvider fixture_invalid_settings
	 * @covers \E20R\Licensing\Settings\NewSettings
	 */
	public function test_invalid_setting( $sku, $license_settings, $exception_class ) {
		if ( ! empty( $exception_class ) ) {
			$this->assertThrows(
				$exception_class,
				function() use ( $sku, $license_settings ) {
					new NewSettings( $sku, $license_settings );
				}
			);
		} else {
			$settings = new NewSettings( $sku, $license_settings );
			self::assertInstanceOf( NewSettings::class, $settings );
		}
	}

	/**
	 * Fixture to supply both invalid (old) and valid (new) settings values
	 *
	 * @return array[]
	 */
	public function fixture_invalid_settings() {
		$new_settings       = $this->fixture_new_settings();
		$old_class_settings = $this->fixture_old_settings();

		return array(
			// sku, invalid_settings, expected_exception
			array( 'E20R_TEST_LICENSE', $old_class_settings, InvalidSettingsVersion::class ),
			array( 'E20R_NEW_LICENSE', $new_settings, null ),
		);
	}

	/**
	 * Fixture: Matches the expected settings for the NewSettings() class
	 *
	 * @return array
	 */
	private function fixture_new_settings() {
		return array(
			'expire'           => 1629466277, // Friday, August 20, 2021 1:31:17 PM
			'activation_id'    => null,
			'expire_date'      => $this->exp_date,
			'timezone'         => 'CET',
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
	 * Fixture: Matches the expected settings for the OldSettings() class
	 *
	 * @return array
	 */
	private function fixture_old_settings() {
		return array(
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
		);
	}

	/**
	 * Test the BaseSettings::set() function
	 *
	 * @param string $old_sku The original sku to instantiate the settings class with
	 * @param string $new_sku The SKU to set and reload the settings class with
	 * @param array  $new_settings The new settings to apply when instantianting the new SKU
	 * @param array  $expected The expected settings array
	 *
	 * @covers       \E20R\Licensing\Settings\BaseSettings::set()
	 * @dataProvider fixture_update_new_settings
	 * @throws InvalidSettingsKey|InvalidSettingsVersion|Throwable The possible exceptions raised by the test
	 */
	public function test_set_new_license_params( $old_sku, $new_sku, $new_settings, $expected ) {

		$new = new NewSettings( $old_sku );
		foreach ( $new_settings as $key => $value ) {
			$new->set( $key, $value );
		}

		self::assertSame( $old_sku, $new->get( 'product_sku' ), 'Error: SKU is not the expected value: ' . $old_sku );
		foreach ( $expected as $key => $value ) {
			self::assertSame( $expected[ $key ], $new->get( $key ), "Error: Unable to set '{$key}' to '{$value}'. Actual value: '{$new->get( $key )}'" );
		}
		$new->set( 'product_sku', $new_sku );
		self::assertSame( $new_sku, $new->get( 'product_sku' ), 'Error: SKU is not the expected value: ' . $old_sku );

		$this->assertThrows(
			InvalidSettingsKey::class,
			function() use ( $new ) {
				$new->set( 'invalid_setting', false );
			}
		);
	}

	/**
	 * Fixture for test_set_new_license_params()
	 *
	 * @return array[]
	 */
	public function fixture_update_new_settings() {
		$settings = $this->fixture_new_settings();

		return array(
			// Initial sku, updated sku, settings array, expected results array
			array(
				'E20R_DUMMY_1',
				'E20R_DUMMY_2',
				array(
					'expire'           => 1629466277, // Friday, August 20, 2021 1:31:17 PM
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'activation_id'    => base64_encode( 'E20R_DUMMY_1' ),
					'expire_date'      => $this->exp_date,
					'timezone'         => 'CET',
					'the_key'          => '',
					'url'              => '',
					'has_expired'      => true,
					'status'           => 'cancelled',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
				array(
					'expire'           => 1629466277, // Friday, August 20, 2021 1:31:17 PM
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'activation_id'    => base64_encode( 'E20R_DUMMY_1' ),
					'expire_date'      => $this->exp_date,
					'timezone'         => 'CET',
					'the_key'          => '',
					'url'              => '',
					'has_expired'      => true,
					'status'           => 'cancelled',
					'allow_offline'    => false,
					'offline_interval' => 'days',
					'offline_value'    => 0,
				),
			),
			array(
				'E20R_NEW_KEY_1',
				'E20R_OLD_KEY_1',
				$settings,
				array(
					'timezone'         => 'CET',
					'offline_interval' => 'days',
					'activation_id'    => null,
				),
			),
		);
	}
	/**
	 * Make sure the properties for the class match our expectations
	 *
	 * @param NewSettings|OldSettings|BaseSettings $class_to_test The class instance to test properties for
	 * @param string[]                             $expected The expected array of key/values
	 *
	 * @dataProvider fixture_properties_test
	 * @throws ReflectionException Default exception thrown if error in mocked class
	 */
	public function test_get_properties( $class_to_test, $expected ) {
		$result = $class_to_test->get_properties();
		self::assertSameSize( $expected, $result );
		self::assertIsArray( $result );
		self::assertSame( $expected, $result );
	}

	/**
	 * Fixture for the test_get_properties() method
	 *
	 * @return array[]
	 */
	public function fixture_properties_test() {
		$new_properties = array_keys( $this->fixture_new_settings() );
		$old_properties = array_keys( $this->fixture_old_settings() );
		return array(
			array( new NewSettings(), $new_properties ),
			array( new OldSettings(), $old_properties ),
		);
	}

	/**
	 * Test the get() method
	 *
	 * @param string $property The property to return the value for with the get() method
	 * @param mixed  $expected_value The expected value returned
	 * @param string $expected_exception The exception returned if the property doesn't exist
	 *
	 * @dataProvider fixture_properties_to_get
	 * @covers \E20R\Licensing\Settings\BaseSettings::get()
	 */
	public function test_get_new_settings( $property, $expected_value, $expected_exception ) {
		$new_settings = new NewSettings();
		try {
			$result = $new_settings->get( $property );
			self::assertSame( $expected_value, $result, "Error: '{$result}' is not the expected value for '{$property}'" );
		} catch ( InvalidSettingsKey $exception ) {
			self::assertInstanceOf( $expected_exception, $exception );
		}
	}

	/**
	 * Fixture for the test_get_new_settings() method
	 *
	 * @return array
	 */
	public function fixture_properties_to_get() {
		return array(
			array( 'expire', -1, null ),
			array( 'activation_id', null, null ),
			array( 'expire_date', '', null ),
			array( 'timezone', 'UTC', null ),
			array( 'the_key', '', null ),
			array( 'url', '', null ),
			array( 'has_expired', true, null ),
			array( 'status', 'cancelled', null ),
			array( 'allow_offline', false, null ),
			array( 'offline_interval', 'days', null ),
			array( 'offline_value', 0, null ),
			array( '', 0, InvalidSettingsKey::class ),
			array( null, 0, InvalidSettingsKey::class ),
			array( false, 0, InvalidSettingsKey::class ),
			array( 'EXPIRE', 0, InvalidSettingsKey::class ),
			array( 'URL', 0, InvalidSettingsKey::class ),
			array( 'product', 'e20r_default_license', InvalidSettingsKey::class ),
			array( 'key', null, InvalidSettingsKey::class ),
			array( 'renewed', null, InvalidSettingsKey::class ),
			array( 'domain', '', InvalidSettingsKey::class ),
			array( 'expires', null, InvalidSettingsKey::class ),
			array( 'status', 'cancelled', InvalidSettingsKey::class ),
			array( 'first_name', '', InvalidSettingsKey::class ),
			array( 'last_name', '', InvalidSettingsKey::class ),
			array( 'email', '', InvalidSettingsKey::class ),
			array( 'timestamp', null, InvalidSettingsKey::class ),
			array( 'product_sku', 'e20r_default_license', null ),
		);
	}
}
