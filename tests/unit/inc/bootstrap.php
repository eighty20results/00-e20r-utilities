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

\Codeception\Util\Autoload::addNamespace( 'E20R\\Utilities', __DIR__ . '/../../../src/' );

/**
 * The following snippets uses `PLUGIN` to prefix
 * the constants and class names. You should replace
 * it with something that matches your plugin name.
 */
// define test environment
define( 'PLUGIN_PHPUNIT', true );

// define fake ABSPATH
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() );
}
// define fake PLUGIN_ABSPATH
if ( ! defined( 'PLUGIN_ABSPATH' ) ) {
	define( 'PLUGIN_ABSPATH', sys_get_temp_dir() . '/wp-content/plugins/00-e20r-utilities/' );
}

if ( ! defined( 'PLUGIN_PATH' ) ) {
	define( 'PLUGIN_PATH', __DIR__ . '/../../../' );
}

require_once __DIR__ . '/../../../inc/autoload.php';

# Load fixtures for testing
if ( file_exists( __DIR__ . '/class-unittestfixtures.php' ) ) {
	require_once __DIR__ . '/class-unittestfixtures.php';
}

# Load the class autoloader
require_once __DIR__ . '/../../../class-loader.php';
