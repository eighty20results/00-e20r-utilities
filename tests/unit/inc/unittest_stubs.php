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

use Brain\Monkey\Functions;

function e20r_unittest_stubs() {
	Functions\when( 'wp_die' )
		->justReturn(
			function( $string ) {
				// phpcs:ignore
				error_log( "Should have died: {$string}" );
			}
		);

	Functions\when( 'esc_attr__' )
		->returnArg( 1 );

	Functions\when( '__return_true' )
		->justReturn( true );

	Functions\when( '__return_false' )
		->justReturn( false );

	Functions\when( 'plugin_dir_path' )
		->justReturn( __DIR__ . '/../../../' );

	Functions\when( 'get_current_blog_id' )
		->justReturn( 1 );

	Functions\when( 'date_i18n' )
		->justReturn(
			function( $date_string, $time ) {
				return date( $date_string, $time ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			}
		);

	try {
		Functions\expect( 'get_option' )
			->with( 'home' )
			->andReturn( 'https://localhost:7254/' );
	} catch ( \Exception $e ) {
		echo 'Error: ' . $e->getMessage(); // phpcs:ignore
	}

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
}

