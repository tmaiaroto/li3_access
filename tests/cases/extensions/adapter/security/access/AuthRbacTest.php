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

    protected $_user = array(
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
                        'match' => '*'
                    ),
                    'allow' => array(
                        'message' => 'Access granted.',
                        'redirect' => '/',
                        'auths' => '*',
                        'match' => 'Tests::granted'
                    )
                )
            )
        ));
    }

    public function tearDown() {}

    public function testSimpleCheck() {
        $request = new Request();

        $request->params = array('controller' => 'Tests', 'action' => 'denied');
        $expected = array('message' => 'Access denied.', 'redirect' => '/');
        $result = Access::check('test_simple_check', $this->_user, $request);
        $this->assertIdentical($expected, $result);

        $request = $request->params = array('controller' => 'Tests', 'action' => 'granted');
        $expected = array();
        $result = Access::check('test_simple_check', $this->_user, $request);
        $this->assertIdentical($expected, $result);
    }

    public function testGetRolesByAuth() {}

    public function testNoRolesConfigured() {
        $request = new Request();

        $config = Access::config('test_no_roles_configured');
        $this->assertTrue(empty($config['roles']));
        $this->expectException('No roles defined for adapter configuration.');
        Access::check('test_no_roles_configured', $this->_guest, $request);;
    }

}
?>
