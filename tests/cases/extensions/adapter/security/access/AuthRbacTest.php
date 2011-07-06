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
			'test_closures' => array(
				'adapter' => 'AuthRbac',
				'roles' => array(
					array(
						'requesters' => '*',
						'allow' => array(function($request, &$roleOptions) {
							$roleOptions['message'] = 'Test allow options set.';
							return $request->params['allow'] ? true : false;
						}),
						'match' => array(
							function($request) {
								return $request->params['match'] ? true : false;
							},
							'controller' => 'TestControllers',
							'action' => 'test_action'
						)
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
		$expected = array(
			'message' => 'You are not permitted to access this area.',
			'redirect' => '/',
			'options' => array(
				'class' => 'error'
			)
		);
		$result = Access::check('test_check', $this->_request, false, array(
			'checkSession' => false
		));
		$this->assertIdentical($expected, $result);

		$user = array('username' => 'test');
		$result = Access::check('test_check', $this->_request, $user, array(
			'checkSession' => false
		));
		$expected = array();
		$this->assertIdentical($expected, $result);
	}

	public function testNoRoles() {
		$this->expectException('No roles defined for adapter configuration.');
		Access::check('test_no_roles_configured', $this->_request);
	}

}
?>