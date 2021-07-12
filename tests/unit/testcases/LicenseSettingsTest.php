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

use Codeception\Test\Unit;
use Brain\Monkey;
use Brain\Monkey\Functions;
use E20R\Utilities\Licensing\LicenseSettings;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class LicenseSettingsTest extends Unit {

	use MockeryPHPUnitIntegration;

	/**
	 * The setup function for this Unit Test suite
	 *
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', sys_get_temp_dir() );
		}

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
		require_once __DIR__ . '/../../../src/licensing/class-licensesettings.php';
	}

	public function testMerge() {
		Functions\stubs(
			array(
				'plugin_dir_path' => '/var/www/html/wp-content/plugins/00-e20r-utilities/',
				'sanitize_email'  => null,
			)
		);
	}

	public function testValidate() {

	}

	public function testLoadPage() {

	}

	public function testGetInstance() {

	}

	public function testRegister() {

	}

	public function testGetSettings() {

	}

	public function testIsPageLoaded() {

	}

	public function testDefaultSettings() {

	}

	public function testAddOptionsPage() {

	}

	public function testUpdate() {

	}
}
