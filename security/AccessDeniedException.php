<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2011, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_access\security;

/**
 * An `AccessDeniedException` is thrown whenever an unhandled attempt is made to access a restricted
 * resource.
 */
class AccessDeniedException extends \RuntimeException {

	protected $code = 403;
}

?>