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
            'test_check' => array(
                'adapter' => 'AuthRbac',
                'roles' => array(
                    'allow' => array(
                        'requesters' => 'user',
                        'match' => '*::*'
                    )
                )
            ),
            'test_message_override' => array(
                'adapter' => 'AuthRbac',
                'roles' => array(
                    array(
                        'allow' => false,
                        'requesters' => '*',
                        'match' => '*::*'
                    ),
                    array(
                        'message' => 'Rule access denied message.',
                        'redirect' => '/',
                        'requesters' => 'user',
                        'match' => 'TestControllers::test_action'
                    ),
                    array(
                        'message' => 'Test no overwrite.',
                        'redirect' => '/test_no_overwrite',
                        'requesters' => 'user',
                        'match' => null
                    )
                )
            )
        ));
    }

    public function tearDown() {
        Auth::clear('user');
    }

    public function testCheck() {
        $request = new Request(array('params' => array('library' => 'test_library', 'controller' => 'test_controllers', 'action' => 'test_action')));

        $guest = array();
        $user = array('username' => 'test');

        $request->data = $guest;
        $expected = array('message' => 'You are not permitted to access this area.', 'redirect' => '/');
        $result = Access::check('test_check', $guest, $request, array('checkSession' => false));
        $this->assertIdentical($expected, $result);

        $request->data = $user;
        $expected = array();
        $result = Access::check('test_check', $user, $request, array('checkSession' => false, 'success' => true));
        $this->assertIdentical($expected, $result);
    }

    public function testCheckMessageOverride() {
        $request = new Request(array('params' => array('library' => 'test_library', 'controller' => 'test_controllers', 'action' => 'test_action')));

        $guest = array();
        $user = array('username' => 'test');

        $request->data = $guest;
        $expected = array('message' => 'Rule access denied message.', 'redirect' => '/');
        $result = Access::check('test_message_override', $guest, $request, array('checkSession' => false));
        $this->assertIdentical($expected, $result);

        $request->data = $user;
        $expected = array();
        $result = Access::check('test_message_override', $user, $request, array('checkSession' => false, 'success' => 'true'));
        $this->assertIdentical($expected, $result);

        $request->params = array('controller' => 'test_controllers', 'action' => 'test_deinied_action');

        $request->data = $guest;
        $expected = array('message' => 'You are not permitted to access this area.', 'redirect' => '/');
        $result = Access::check('test_message_override', $guest, $request, array('checkSession' => false));
        $this->assertIdentical($expected, $result);

        $request->data = $user;
        $expected = array('message' => 'You are not permitted to access this area.', 'redirect' => '/');
        $result = Access::check('test_message_override', $user, $request, array('checkSession' => false));
        $this->assertIdentical($expected, $result);

        $request->data = $user;
        $expected = array('message' => 'Message override!', 'redirect' => '/new_redirect');
        $result = Access::check('test_message_override', $user, $request, array('checkSession' => false, 'message' => 'Message override!', 'redirect' => '/new_redirect'));
        $this->assertIdentical($expected, $result);
    }

    public function testGetRolesByAuth() {
        $request = new Request();
        $request->data = array('username' => 'test');

        $result = Access::adapter('test_check')->getRolesByAuth($request, array('checkSession' => false));
        $this->assertIdentical(array('*' => '*'), $result);

        $expected = array('*' => '*', 'user' => array('username' => 'test'));
        $result = Access::adapter('test_check')->getRolesByAuth($request, array('checkSession' => false, 'success' => true));
        $this->assertIdentical($expected, $result);
    }

    public function testParseMatch() {
        $request = new Request(array('params' => array('library' => 'test_library', 'controller' => 'test_controllers', 'action' => 'test_action')));

        $match = array('library' => 'test_library', 'controller' => 'TestControllers', 'action' => 'test_action');
        $this->assertTrue(Access::adapter('test_check')->parseMatch($match, $request));

        $match = array('controller' => 'TestControllers', 'action' => 'test_action');
        $this->assertTrue(Access::adapter('test_check')->parseMatch($match, $request));

        $match = array('library' => 'test_library', 'action' => 'test_action');
        $this->assertTrue(Access::adapter('test_check')->parseMatch($match, $request));

        $match = array('library' => 'test_library', 'controller' => 'TestControllers');
        $this->assertTrue(Access::adapter('test_check')->parseMatch($match, $request));

        $match = array('library' => 'test_no_match', 'controller' => 'TestControllers', 'action' => 'test_action');
        $this->assertFalse(Access::adapter('test_check')->parseMatch($match, $request));

        $match = 'TestControllers::test_action';
        $this->assertTrue(Access::adapter('test_check')->parseMatch($match, $request));

        $match = 'TestControllers::*';
        $this->assertTrue(Access::adapter('test_check')->parseMatch($match, $request));

        $match = '*::test_action';
        $this->assertTrue(Access::adapter('test_check')->parseMatch($match, $request));

        $match = '*::*';
        $this->assertTrue(Access::adapter('test_check')->parseMatch($match, $request));

        $match = array('library' => 'test_library', '*::*');
        $this->assertTrue(Access::adapter('test_check')->parseMatch($match, $request));

        $match = array('library' => 'test_no_match', '*::*');
        $this->assertFalse(Access::adapter('test_check')->parseMatch($match, $request));

        $match = null;
        $this->assertFalse(Access::adapter('test_check')->parseMatch($match, $request));
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
