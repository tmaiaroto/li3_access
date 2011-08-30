<?php
/**
 * li3_access plugin for Lithium: the most rad php framework.
 *
 * @author        Tom Maiaroto
 * @copyright     Copyright 2010, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_access\tests\cases\extensions\adapter\security\access;

use lithium\action\Request;
use li3_access\security\Access;

class RulesTest extends \lithium\test\Unit {

	public function setUp() {
		Access::config(array(
			'test_rulebased' => array('adapter' => 'Rules')
		));
	}

	public function tearDown() {}

	public function testPatternBasedIpMatching() {
		$request = new Request(array('env' => array('REMOTE_ADDR' => '10.0.1.2')));

		// Multiple rules, they should all pass
		$rules = array(
			array(
				'rule' => 'allowIp',
				'message' => 'You can not access this from your location.',
				'ip' => '/10\.0\.1\.\d+/'
			)
		);
		$result = Access::check('test_rulebased', array(), $request, compact('rules'));
		$this->assertEqual(array(), $result);

		$request = new Request(array('env' => array('REMOTE_ADDR' => '10.0.1.255')));
		$result = Access::check('test_rulebased', array(), $request, compact('rules'));
		$this->assertEqual(array(), $result);

		$request = new Request(array('env' => array('REMOTE_ADDR' => '10.0.2.1')));
		$result = Access::check('test_rulebased', array(), $request, compact('rules'));
		$this->assertEqual('You can not access this from your location.', $result['message']);
	}

	public function testArrayBasedIpMatching() {
		// Multiple rules, they should all pass
		$rules = array(
			array(
				'rule' => 'allowIp',
				'message' => 'You can not access this from your location.',
				'ip' => array('10.0.1.2', '10.0.1.3', '10.0.1.4')
			)
		);

		foreach (array(2, 3, 4) as $i) {
			$request = new Request(array('env' => array('REMOTE_ADDR' => "10.0.1.{$i}")));
			$result = Access::check('test_rulebased', array(), $request, compact('rules'));
			$this->assertEqual(array(), $result);
		}

		foreach (array(1, 5, 255) as $i) {
			$request = new Request(array('env' => array('REMOTE_ADDR' => "10.0.1.{$i}")));
			$result = Access::check('test_rulebased', array(), $request, compact('rules'));
			$this->assertEqual('You can not access this from your location.', $result['message']);
		}
	}

	public function testCheck() {
		$request = new Request(array('env' => array('REMOTE_ADDR' => '10.0.1.1')));

		// Multiple rules, they should all pass
		$rules = array(
			array('rule' => 'allowAnyUser', 'message' => 'You must be logged in.'),
			array('rule' => 'allowAll', 'message' => 'You must be logged in.'),
			array(
				'rule' => 'allowIp',
				'message' => 'You can not access this from your location. (IP: 10.0.1.1)',
				'ip' => '10.0.1.1'
			)
		);
		$result = Access::check('test_rulebased', array('username' => 'Tom'), $request, array(
			'rules' => $rules
		));
		$this->assertEqual(array(), $result);

		// Single rule in multi-demnsional array
		$rules = array(array('rule' => 'denyAll', 'message' => 'You must be logged in.'));
		$expected = array(
			'rule' => 'denyAll', 'message' => 'You must be logged in.', 'redirect' => '/'
		);
		$result = Access::check('test_rulebased', array('username' => 'Tom'), $request, array(
			'rules' => $rules
		));
		$this->assertEqual($expected, $result);

		// Single rule (single array), but it should fail because user is an empty array
		$rules = array('rule' => 'allowAnyUser', 'message' => 'You must be logged in.');
		$expected = array(
			'rule' => 'allowAnyUser', 'message' => 'You must be logged in.', 'redirect' => '/'
		);
		$result = Access::check('test_rulebased', array(), $request, array('rules' => $rules));
		$this->assertEqual($expected, $result);

		// and if false instead of an empty array (because one might typically run Auth:check()
		// which could return false)
		$result = Access::check('test_rulebased', false, $request, array('rules' => $rules));
		$this->assertEqual($expected, $result);

		// No rules
		$expected = array(
			'rule' => false,
			'message' => 'You are not permitted to access this area.',
			'redirect' => '/'
		);
		$result = Access::check('test_rulebased', array('username' => 'Tom'), $request);
		$this->assertEqual($expected, $result);

		// Adding a rule "on the fly" by passing a closure, this rule should pass
		$rules = array(
			array(
				'rule' => function($user, $request, $options) {
					return $user['username'] == 'Tom';
				},
				'message' => 'Access denied.'
			)
		);
		$expected = array();
		$result = Access::check('test_rulebased', array('username' => 'Tom'), $request, array(
			'rules' => $rules
		));
		$this->assertEqual($expected, $result);
	}

	public function testAdd() {
		$request = new Request();

		// The add() method to add a rule
		Access::adapter('test_rulebased')->add('testDeny', function($user, $request, $options) {
			return false;
		});

		$rules = array(array('rule' => 'testDeny', 'message' => 'Access denied.'));
		$expected = array('rule' => 'testDeny', 'message' => 'Access denied.', 'redirect' => '/');
		$result = Access::check('test_rulebased', array('username' => 'Tom'), $request, array(
			'rules' => $rules
		));
		$this->assertEqual($expected, $result);

		// Make sure the rule got added to the $_rules property
		$this->assertTrue(is_callable(Access::adapter('test_rulebased')->get('testDeny')));
		$this->assertTrue(is_array(Access::adapter('test_rulebased')->get()));
	}
}

?>
