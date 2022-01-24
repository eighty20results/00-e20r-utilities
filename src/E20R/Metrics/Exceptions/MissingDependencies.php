<?php
/**
 *   Copyright (c) 2021 - 2022. - Eighty / 20 Results by Wicked Strong Chicks.
 *   ALL RIGHTS RESERVED
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package E20R\Metrics\Exceptions\MissingDependencies
 */

namespace E20R\Metrics\Exceptions;

use Exception;
use Throwable;

/**
 * Custom exception when unable to connect to the Mixpanel API server(s)
 */
class MissingDependencies extends Exception {

	/**
	 * The default constructor for Exceptions
	 *
	 * @param string         $message String message to use when raising exception
	 * @param int            $code Error code (int) to use when raising exception
	 * @param Throwable|null $previous Previous exception type that led to raising ServerConnectionError
	 */
	public function __construct( string $message = '', int $code = 0, ?Throwable $previous = null ) { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found
		parent::__construct( $message, $code, $previous );
	}
}
