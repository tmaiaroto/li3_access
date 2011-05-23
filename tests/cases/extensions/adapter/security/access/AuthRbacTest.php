<?php
/**
 * li3_access plugin for Lithium: the most rad php framework.
 *
 * @author        Tom Maiaroto
 * @copyright     Copyright 2010, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_access\tests\cases\extensions\adapter\security\access;

use lithium\net\http\Request;
use lithium\security\Auth;

use li3_access\security\Access;

class AuthRbacTest extends \lithium\test\Unit {

    protected $_guest = null;

    protected $user = array(
        'user' => null
    );

    public function setUp() {
        Auth::config(array(
            'user' => array(
                'adapter' => '\li3_access\tests\mocks\extensions\adapter\auth\MockSimple'
            )
        ));

        Access::config(array(
            'test_no_roles_configured' => array(
                'adapter' => 'AuthRbac'
            ),
            'test_simple_check' => array(
                'adapter' => 'AuthRbac',
                'roles' => array(
                    'deny' => array(
                        'message' => 'Access denied.',
                        'redirect' => '/',
                        'auths' => '*',
                        'params' => '*'
                    ),
                    'allow' => array(
                        'message' => 'Access granted.',
                        'redirect' => '/',
                        'auths' => '*',
                        'params' => 'user'
                    )
                )
            )
        ));
    }

    public function tearDown() {}

    public function testCheck() {
        $request = new Request();
    }

    public function testNoRolesConfigured() {
        $request = new Request();

        $config = Access::config('test_no_roles_configured');
        $this->assertTrue(empty($config['roles']));
        $this->expectException('No roles defined for adapter configuration.');
        Access::check('test_no_roles_configured', array('user' => null), $request);
    }

}
?>
