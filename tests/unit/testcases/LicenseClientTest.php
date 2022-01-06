<?php
/**
 * Copyright (c) 2016 - 2022 - Eighty / 20 Results by Wicked Strong Chicks.
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
 * @package E20R\Tests\Unit\LicenseClientTest
 */

namespace E20R\Tests\Unit;

use Codeception\AssertThrows;
use Codeception\Test\Unit;
use Brain\Monkey;
use Brain\Monkey\Functions;
use E20R\Licensing\LicenseClient;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;


/**
 * Tests for the Licensing Client class
 */
class LicenseClientTest extends Unit {

	use MockeryPHPUnitIntegration;
	use AssertThrows;

	/**
	 * The setup function for this Unit Test suite
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->loadFiles();
		$this->loadMocks();
	}

	/**
	 * Mocked WP and PHP functions for unit test purposes
	 */
	public function loadMocks() {
		Functions\stubs(
			array(
				'plugins_url'    => 'https://development.local:7254/wp-content/plugins/00-e20r-utilities/',
				'sanitize_email' => null,
			)
		);
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
	 * Tests the check_licenses() method
	 *
	 * @return void
	 * @test
	 */
	public function test_check_licenses() {

	}

	/**
	 * Tests the load_hooks() method
	 *
	 * @return void
	 * @test
	 */
	public function test_load_hooks() {

	}

	/**
	 * Tests the get_instance() method
	 *
	 * @return void
	 * @test
	 */
	public function test_get_instance() {

	}
}
