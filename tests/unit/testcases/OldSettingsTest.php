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
use E20R\Licensing\Exceptions\InvalidSettingsVersion;
use E20R\Licensing\Settings\Defaults;
use E20R\Licensing\Settings\NewSettings;
use Brain\Monkey;
use Brain\Monkey\Functions;
use E20R\Licensing\Settings\OldSettings;
use E20R\Utilities\Utilities;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\Mock;

class OldSettingsTest extends \Codeception\Test\Unit {

	use MockeryPHPUnitIntegration;
	use AssertThrows;

	private $m_default = null;

	/**
	 * The setup function for this Unit Test suite
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

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
			->with( \Mockery::contains( 'err_info' ) )
			->andReturn( '' );

		Functions\expect( 'set_transient' )
			->with( 'err_info' )
			->andreturn( true );
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
	 * @param string $sku
	 * @param array $license_settings
	 * @param array $expected
	 *
	 * @dataProvider fixture_license_settings
	 * @covers \E20R\Licensing\Settings\NewSettings()
	 */
	public function test_instantiate_old_settings( $sku, $license_settings, $expected ) {
		Functions\expect( 'get_option' )
			->with( \Mockery::contains( 'e20r_license_settings' ) )
			->andReturn(
				function( $key, $default_value ) use ( $license_settings ) {
					if ( empty( $license_settings ) ) {
						return $default_value;
					}
					return $license_settings;
				}
			);
		global $current_user;
		if ( empty( $current_user ) ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$current_user = new \stdClass();
			// phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found
			$current_user->user_firstname = $current_user->first_name = 'Thomas';
			// phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found
			$current_user->user_lastname = $current_user->last_name = 'Sjolshagen';
			$current_user->user_email    = 'tester@example.com';
		}
		try {
			$settings = new OldSettings( $sku, $license_settings );
			foreach ( $expected as $key => $value ) {
				self::assertSame( $value, $settings->get( $key ), "Error: Different '{$key}' value returned! {$expected[$key]} => {$settings->get( $key )}" );
			}
			self::assertSame( $expected['product'], $settings->get( 'product_sku' ), 'Error: Unexpected SKU returned' );
			self::assertInstanceOf( OldSettings::class, $settings );
		} catch ( \Exception $e ) {
			self::assertFalse( true, $e->getMessage() );
		}
	}

	/**
	 * Fixture for new License settings
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
					'status'  => 'expired',
				),
			),
		);
	}

	/**
	 * Test both valid and invalid setting keys
	 *
	 * @param string $sku
	 * @param array $license_settings
	 * @param string $exception_class
	 *
	 * @throws InvalidSettingsVersion
	 * @throws \Throwable
	 *
	 * @dataProvider fixture_invalid_settings
	 * @covers \E20R\Licensing\Settings\NewSettings
	 */
	public function test_invalid_setting( $sku, $license_settings, $exception_class ) {
		if ( ! empty( $exception_class ) ) {
			$this->assertThrows(
				$exception_class,
				function() use ( $sku, $license_settings ) {
					$settings = new OldSettings( $sku, $license_settings );
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
}
