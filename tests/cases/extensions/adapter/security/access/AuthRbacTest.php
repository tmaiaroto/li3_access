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

	protected $_request;

	public function setUp() {
		$this->_request = new Request(array(
			'params' => array(
				'library' => 'test_library',
				'controller' => 'test_controllers',
				'action' => 'test_action'
			)
		));

		Auth::config(array(
			'user' => array(
				'adapter' => 'li3_access\tests\mocks\extensions\adapter\auth\MockAuthAdapter'
			)
		));

		Access::config(array(
			'no_roles' => array(
				'adapter' => 'AuthRbac'
			),
			'test_check' => array(
				'adapter' => 'AuthRbac',
				'roles' => array(
					array(
						'requesters' => 'user',
						'match' => '*::*'
					),
					array(
						'requesters' => 'user',
						'match' => 'Pages::index'
					)
				)
			),
			'test_closures' => array(
				'adapter' => 'AuthRbac',
				'roles' => array(
					array(
						'requesters' => '*',
						'allow' => array(function($request, &$roleOptions) {
							$roleOptions['message'] = 'Test allow options set.';
							return $request->params['allow'];
						}),
						'match' => array(
							function($request) {
								return $request->params['match'];
							},
							'controller' => 'TestControllers',
							'action' => 'test_action'
						)
					)
				)
			),
			'test_option_override' => array(
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
						'options' => array(
							'class' => 'notice'
						),
						'requesters' => 'user',
						'match' => 'TestControllers::test_action'
					),
					array(
						'message' => 'Test no overwrite.',
						'redirect' => 'Test::no_overwrite',
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
		$expected = array(
			'message' => 'You are not authorized to access this page.',
			'redirect' => '/',
			'options' => array(
				'class' => 'error'
			)
		);
		$result = Access::check('test_check', $this->_request, false);
		$this->assertIdentical($expected, $result);

		$expected = array();
		$user = array('username' => 'test');
		$result = Access::check('test_check', $this->_request, $user);
		$this->assertIdentical($expected, $result);
	}

	public function testRoles() {
		$this->expectException('No roles defined for adapter configuration.');
		Access::check('no_roles', $this->_request);
	}

	public function testClosures() {
		$request = $this->_request;

		$request->params['allow'] = $request->params['match'] = true;
		$expected = array();
		$result = Access::check('test_closures', $request, false);
		$this->assertIdentical($expected, $result);

		$request->params['allow'] = false;
		$expected = array(
			'message' => 'Test allow options set.',
			'redirect' => '/',
			'options' => array(
				'class' => 'error'
			)
		);
		$result = Access::check('test_closures', $request, false);
		$this->assertIdentical($expected, $result);

		$request->params['allow'] = true;
		$request->params['match'] = false;
		$expected = array(
			'message' => 'You are not authorized to access this page.',
			'redirect' => '/',
			'options' => array(
				'class' => 'error'
			)
		);
		$result = Access::check('test_closures', $request, false);
		$this->assertIdentical($expected, $result);

		$roles = array(array(
			'match' => '*::*',
			'allow' => 'bad_closure'
		));
		$result = Access::check('test_closures', $request, false, compact('roles'));
		$this->assertIdentical($expected, $result);
	}

	public function testNoMatch() {
		$expected = array(
			'message' => 'You are not authorized to access this page.',
			'redirect' => '/',
			'options' => array(
				'class' => 'error'
			)
		);
		$roles = array(array(
			'match' => array('Pages::index')
		));
		$result = Access::check('no_roles', $this->_request, false, compact('roles'));
		$this->assertIdentical($expected, $result);
	}

	public function testRoleOverride() {
		$expected = array();
		$result = Access::check('no_roles', $this->_request, false, array(
			'roles' => array(
				array(
					'match' => '*::*',
					'requesters' => '*'
				)
			)
		));
		$this->assertIdentical($expected, $result);
	}

	public function testOptionOverride() {
		$expected = array(
			'message' => 'Rule access denied message.',
			'redirect' => '/',
			'options' => array(
				'class' => 'notice'
			)
		);
		$result = Access::check('test_option_override', $this->_request, false, array('whatt'));
		$this->assertIdentical($expected, $result);
	}
}
?>