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

    public function setUp() {
        Auth::config(array(
            'user' => array(
                'adapter' => '\li3_access\tests\mocks\extensions\adapter\auth\MockAuthAdapter'
            )
        ));

        Access::config(array(
            'test_no_roles_configured' => array(
                'adapter' => 'AuthRbac'
            ),
            'test_simple_check' => array(
                'adapter' => 'AuthRbac',
                'message' => 'Generic access denied message.',
                'redirect' => '',
                'roles' => array(
                    'deny' => array(
                        'auths' => '*',
                        'match' => array('controller' => '*', 'action' => '*')
                    ),
                    'allow' => array(
                        'message' => 'Rule access denied message.',
                        'redirect' => '/',
                        'auths' => 'user',
                        'match' => array('controller' => 'Tests', 'action' => 'granted')
                    )
                )
            )
        ));
    }

    public function tearDown() {
        Auth::clear('user');
    }

    public function testCheck() {
        $request = new Request();

        $request->params = array('controller' => 'Tests', 'action' => 'denied');

        $guest = Auth::check('user', array());
        $success = true;
        $user = Auth::check('user', array('user' => array('id' => 1)), compact('success'));

        /*$expected = array('message' => 'Generic access denied message.', 'redirect' => '/');
        $result = Access::check('test_simple_check', $user, $request);
        $this->assertIdentical($expected, $result);

        /*$request->params = array('controller' => 'Tests', 'action' => 'granted');
        $expected = array('message' => 'Rule access denied message.', 'redirect' => '/');
        $result = Access::check('test_simple_check', $guest, $request);
        $this->assertIdentical($expected, $result);

        $expected = array();
        $result = Access::check('test_simple_check', $user, $request);
        $this->assertIdentical($expected, $result);*/
    }

    public function testGetRolesByAuth() {
        $request = new Request();
        $request->data = array('username' => 'richard');

        $result = Access::adapter('test_simple_check')->getRolesByAuth($request, array('checkSession' => false));
        $this->assertIdentical(array('*' => '*'), $result);

        $expected = array('*' => '*', 'user' => array('username' => 'richard'));
        $result = Access::adapter('test_simple_check')->getRolesByAuth($request, array('checkSession' => false, 'success' => true));
        $this->assertIdentical($expected, $result);
    }

    public function testParseMatch() {
        $request = new Request(array('library' => 'test_library', 'controller' => 'test_controller', 'action' => 'test_action'));

        $match = array('library' => 'test_library', 'controller' => 'test_controller', 'action' => 'test_action');
        $this->assertTrue(Access::adapter('test_simple_check')->parseMatch($match, $request));

        $match = array('controller' => 'test_controller', 'action' => 'test_action');
        $this->assertTrue(Access::adapter('test_simple_check')->parseMatch($match, $request));

        $match = array('library' => 'test_library', 'action' => 'test_action');
        $this->assertTrue(Access::adapter('test_simple_check')->parseMatch($match, $request));

        $match = array('library' => 'test_library', 'controller' => 'test_controller');
        $this->assertTrue(Access::adapter('test_simple_check')->parseMatch($match, $request));

        $match = array('library' => 'test_no_match', 'controller' => 'test_controller', 'action' => 'test_action');
        $this->assertFalse(Access::adapter('test_simple_check')->parseMatch($match, $request));

        $match = 'TestControllers::test_action';
        $this->assertTrue(Access::adapter('test_simple_check')->parseMatch($match, $request));

        $match = 'TestControllers::*';
        $this->assertTrue(Access::adapter('test_simple_check')->parseMatch($match, $request));

        $match = '*::test_action';
        $this->assertTrue(Access::adapter('test_simple_check')->parseMatch($match, $request));

        $match = '*::*';
        $this->assertTrue(Access::adapter('test_simple_check')->parseMatch($match, $request));

        $match = array('library' => 'test_library', '*::*');
        $this->assertTrue(Access::adapter('test_simple_check')->parseMatch($match, $request));

        $match = array('library' => 'test_no_match', '*::*');
        $this->assertFalse(Access::adapter('test_simple_check')->parseMatch($match, $request));
    }

    public function testNoRolesConfigured() {
        $request = new Request();

        $config = Access::config('test_no_roles_configured');
        $request->params = array('controller' => 'Tests', 'action' => 'granted');

        $this->assertTrue(empty($config['roles']));
        $this->expectException('No roles defined for adapter configuration.');
        Access::check('test_no_roles_configured', array('guest' => null), $request);
    }

}
?>
