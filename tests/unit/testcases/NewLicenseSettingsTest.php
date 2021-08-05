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

use E20R\Licensing\Settings\Defaults;
use E20R\Licensing\Settings\NewLicenseSettings;
use Brain\Monkey;
use Brain\Monkey\Functions;
use E20R\Utilities\Utilities;
use Mockery\Mock;

class NewLicenseSettingsTest extends \Codeception\Test\Unit {

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
	 * @dataProvider fixture_license_settings
	 * @covers \E20R\Licensing\Settings\NewLicenseSettings()
	 */
	public function test_instantiate_new_license_settings( $sku, $license_settings, $server_url, $expected ) {
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

		$m_default = $this->makeEmpty(
			Defaults::class,
			array(
				'set' => true,
				'get' => function( $param_name ) use ( $server_url ) {
					if ( 'server_url' === $param_name ) {
						return $server_url;
					}
					return null;
				},
			)
		);

		$m_utils = $this->makeEmpty(
			Utilities::class,
			array( 'log' => null )
		);

		$m_default->set( 'server_url', $server_url );

		try {
			$settings = new NewLicenseSettings( $sku, $m_default, $m_utils );
			self::assertEquals( $expected, $settings->get( 'product_sku' ) );
			self::assertInstanceOf( NewLicenseSettings::class, $settings );
		} catch ( \Exception $e ) {
			self::assertFalse( true, $e->getMessage() );
		}
	}

	/**
	 * Fixture for new License settings
	 * @return array[]
	 */
	public function fixture_license_settings() {
		return array(
			// TODO: Add more fixtures for the NewLicenseSettingsTest::test_instantiate_new_license_settings() unit test
			array( 'PRODUCT_1', array(), 'https://eighty20results.co', 'PRODUCT_1' ),
		);
	}
}
