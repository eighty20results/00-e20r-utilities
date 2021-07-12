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

namespace E20R\Test\Unit;

use Codeception\AssertThrows;
use E20R\Utilities\Licensing\AjaxHandler;
use E20R\Utilities\Licensing\LicensePage;
use E20R\Utilities\Licensing\Licensing;
use E20R\Utilities\Message;
use E20R\Utilities\Utilities;
use PHPUnit\Framework\TestCase;
use Codeception\Test\Unit;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

class LicensingTest extends Unit {

	use AssertThrows;

	/**
	 * The setup function for this Unit Test suite
	 *
	 */
	protected function setUp(): void {
		parent::setUp();
		setUp();

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

		$this->loadFiles();
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
//		require_once __DIR__ . '/../../../inc/autoload.php';
//		require_once __DIR__ . '/../../../class-loader.php';
		require_once __DIR__ . '/../../../src/licensing/class-licensing.php';
		require_once __DIR__ . '/../../../src/licensing/class-licensepage.php';
		require_once __DIR__ . '/../../../src/licensing/class-ajaxhandler.php';
		require_once __DIR__ . '/../../../src/utilities/class-utilities.php';
		require_once __DIR__ . '/../../../src/utilities/class-message.php';
	}

	public function testAjax_handler_verify_license() {

	}

	/**
	 * Tests the load_hooks() function
	 *
	 * @param string $key_name
	 * @param string $sku_value
	 *
	 * @test
	 * @covers \E20R\Utilities\Licensing\Licensing::load_hooks
	 */
	public function test_load_hooks() {
		$licensing = new Licensing();
		try {
			Actions\expectAdded( 'admin_enqueue_scripts' )
				->with( array( $licensing, 'enqueue' ), 10 );
		} catch ( \Exception $e ) {
			error_log( 'Error: ' . $e->getMessage() ); // phpcs:ignore
		}
		$licensing->load_hooks();
	}

	/**
	 * Unit test of the load_settings() method in the Licensing() class
	 *
	 * @param $sku
	 * @param $expected_settings
	 *
	 * @dataProvider fixture_settings
	 * @covers \E20R\Utilities\Licensing\Licensing::load_settings
	 */
	public function test_load_settings( $sku, $use_new, $expected_settings ) {

	}

	public function fixture_settings() {
		return $this->fixture_new_settings() + $this->fixture_old_settings();
	}

	public function fixture_new_settings() {
		// Assuming new licensing API service
		return array(
			array( 'some-sku', true, array( '' ) ),
		);
	}

	public function fixture_old_settings() {
		// Assuming old licensing API service
		return array(
			array( 'some-sku', false, array( '' ) ),
		);
	}

	public function test_enqueue() {

	}

	public function test_is_active() {

	}

	public function test_is_licensed() {

	}

	/**
	 * Unit tests for get_license_page_url()
	 *
	 * @param $stub
	 * @param $expected
	 *
	 * @dataProvider page_url_fixture
	 * @test
	 */
	public function test_get_license_page_url( $stub, $expected ) {

		try {
			Functions\expect( 'esc_url_raw' )
				->andReturnFirstArg();
		} catch ( \Exception $e ) {
			echo 'Error: ' . $e->getMessage(); // phpcs:ignore
		}

		try {
			Functions\expect( 'add_query_arg' )
				->with(
					\Mockery::contains(
						array(
							'page'         => 'e20r-licensing',
							'license_stub' => $stub,
						)
					)
				)
				->andReturn(
					sprintf(
						'https://localhost:7254/wp-admin/options-general.php?page=e20r-licensing&license_stub=%s',
						rawurlencode( $stub )
					)
				);
		} catch ( \Exception $e ) {
			echo 'Error: ' . $e->getMessage(); // phpcs:ignore
		}

		$licensing = new Licensing( $stub );

		self::assertEquals(
			$expected,
			$licensing->get_license_page_url( $stub ),
			sprintf( 'Testing that license server URL contains "%s"', $stub )
		);
	}

	/**
	 * Fixture for the get_license_page_url function
	 *
	 * @return \string[][]
	 */
	public function page_url_fixture() {
		return array(
			array( 'test-license-1', 'https://localhost:7254/wp-admin/options-general.php?page=e20r-licensing&license_stub=test-license-1' ),
			array( 'test license 1', 'https://localhost:7254/wp-admin/options-general.php?page=e20r-licensing&license_stub=test%20license%201' ),
			array( 'vXzfjW9M2O4sP1a57DG399SmA2-176', 'https://localhost:7254/wp-admin/options-general.php?page=e20r-licensing&license_stub=vXzfjW9M2O4sP1a57DG399SmA2-176' ),
			array( 'ovCCBklB8cz2H9Q787Asv2w0rC-166', 'https://localhost:7254/wp-admin/options-general.php?page=e20r-licensing&license_stub=ovCCBklB8cz2H9Q787Asv2w0rC-166' ),
			array( 'vXzfjW9M2O4sP1a57DG399SmA2%176', 'https://localhost:7254/wp-admin/options-general.php?page=e20r-licensing&license_stub=vXzfjW9M2O4sP1a57DG399SmA2%25176' ),
		);
	}

	/**
	 * Negative tests for get_license_page_url()
	 *
	 * @param $stub
	 * @param $expected
	 *
	 * @dataProvider page_url_neg_fixture
	 * @test
	 */
	public function test_neg_get_license_page_url( $stub, $expected ) {
		try {
			Functions\expect( 'esc_url_raw' )
				->andReturnFirstArg();
		} catch ( \Exception $e ) {
			echo 'Error: ' . $e->getMessage(); // phpcs:ignore
		}

		try {
			Functions\expect( 'add_query_arg' )
				->with(
					\Mockery::contains(
						array(
							'page'         => 'e20r-licensing',
							'license_stub' => $stub,
						)
					)
				)
				->andReturn(
					"https://localhost:7254/wp-admin/options-general.php?page=e20r-licensing&license_stub={$stub}"
				);
		} catch ( \Exception $e ) {
			echo 'Error: ' . $e->getMessage(); // phpcs:ignore
		}

		$licensing = new Licensing( $stub );
		self::assertNotEquals(
			$expected,
			$licensing->get_license_page_url( $stub ),
			sprintf( 'Testing that license server URL contains "%s"', $stub )
		);
	}

	/**
	 * Fixture for the negative get_license_page_url test
	 *
	 * @return \string[][]
	 */
	public function page_url_neg_fixture() {
		return array(
			array( 'test license 1', 'https://localhost:7254/wp-admin/options-general.php?page=e20r-licensing&license_stub=test-license-1' ),
			array( 'test license 1', 'https://localhost:7254/wp-admin/options-general.php?page=e20r-licensing&license_stub=test+license+1' ),
		);
	}

	public function test_get_ssl_verify() {

	}

	/**
	 * @param $expected
	 *
	 * @dataProvider fixture_new_version
	 */
	public function test_is_new_version( $expected ) {

		if ( ! extension_loaded( 'runkit' ) ) {
			self::markTestSkipped( 'This test requires the runkit extension.' );
		}

		runkit_constant_remove( 'WP_PLUGIN_DIR' );
		$licensing = new Licensing();

		self::assertEquals( $expected, $licensing->is_new_version() );
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
	 * @covers \E20R\Utilities\Licensing\Licensing()
	 * @dataProvider fixture_skus
	 */
	public function test_get_instance( $test_sku ) {

		Filters\expectApplied( 'e20r_licensing_text_domain' )
			->with( '00-e20r-utilities' );

		$message_mock = $this->getMockBuilder( Message::class )
			->onlyMethods( array( 'convert_destination' ) )
			->getMock();

		$message_mock->method( 'convert_destination' )
			->willReturn( 'backend' );

		$utilities_mock = $this->getMockBuilder( Utilities::class )
								->onlyMethods( array( 'log', 'is_local_server' ) )
								->getMock();

		$utilities_mock->method( 'log' )
				->willReturn( null );

		$utilities_mock->method( 'is_local_server' )
			->willReturn( true );

		self::assertInstanceOf( '\\E20R\\Utilities\\Licensing\\Licensing', new Licensing( $test_sku ) );
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
