<?php
/**
 *
 *   Copyright (c) 2021. - Eighty / 20 Results by Wicked Strong Chicks.
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
 * @package E20R\Licensing\Exceptions\DefinedByConstant
 */

namespace E20R\Licensing\Exceptions;

use Exception;
use Throwable;

/**
 * Exception thrown when something was already defined as a constant
 */
class DefinedByConstant extends Exception {

	/**
	 * Constructor for custom exception
	 *
	 * @param string         $message The text message for the exception.
	 * @param int            $code    Error code.
	 * @param Throwable|null $previous Last exception if it exists.
	 */
	public function __construct( string $message = '', int $code = 0, ?Throwable $previous = null ) { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found

		parent::__construct( $message, $code, $previous );
	}
}
