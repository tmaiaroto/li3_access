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
use li3_access\extensions\adapter\security\access\Rules;

class RulesTest extends \lithium\test\Unit {

	public function testPatternBasedIpMatching() {
		$request = new Request(array('env' => array('REMOTE_ADDR' => '10.0.1.2')));
		$adapter = new Rules();

		$rules = array(array(
			'rule' => 'allowIp',
			'message' => 'You can not access this from your location.',
			'ip' => '/10\.0\.1\.\d+/'
		));
		$result = $adapter->check(array(), $request, compact('rules'));
		$this->assertEqual(array(), $result);

		$request = new Request(array('env' => array('REMOTE_ADDR' => '10.0.1.255')));
		$result = $adapter->check(array(), $request, compact('rules'));
		$this->assertEqual(array(), $result);

		$request = new Request(array('env' => array('REMOTE_ADDR' => '10.0.2.1')));
		$result = $adapter->check(array(), $request, compact('rules'));
		$this->assertEqual('You can not access this from your location.', $result['message']);
	}

	public function testArrayBasedIpMatching() {
		$adapter = new Rules();
		$rules = array(array(
			'rule' => 'allowIp',
			'message' => 'You can not access this from your location.',
			'ip' => array('10.0.1.2', '10.0.1.3', '10.0.1.4')
		));

		foreach (array(2, 3, 4) as $i) {
			$request = new Request(array('env' => array('REMOTE_ADDR' => "10.0.1.{$i}")));
			$result = $adapter->check(array(), $request, compact('rules'));
			$this->assertEqual(array(), $result);
		}

		foreach (array(1, 5, 255) as $i) {
			$request = new Request(array('env' => array('REMOTE_ADDR' => "10.0.1.{$i}")));
			$result = $adapter->check(array(), $request, compact('rules'));
			$this->assertEqual('You can not access this from your location.', $result['message']);
		}
	}

	public function testCheck() {
		$user = array('username' => 'Tom');
		$request = new Request(array('env' => array('REMOTE_ADDR' => '10.0.1.1')));
		$adapter = new Rules();

		$rules = array(
			array('rule' => 'allowAnyUser', 'message' => 'You must be logged in.'),
			array('rule' => 'allowAll', 'message' => 'You must be logged in.'),
			array(
				'rule' => 'allowIp',
				'message' => 'You can not access this from your location. (IP: 10.0.1.1)',
				'ip' => '10.0.1.1'
			)
		);
		$result = $adapter->check($user, $request, compact('rules'));
		$this->assertEqual(array(), $result);

		$rules = array(array('rule' => 'denyAll', 'message' => 'You must be logged in.'));
		$expected = array('rule' => 'denyAll', 'message' => 'You must be logged in.');
		$result = $adapter->check($user, $request, compact('rules'));
		$this->assertEqual($expected, $result);

		$rules = array('rule' => 'allowAnyUser', 'message' => 'You must be logged in.');
		$expected = array('rule' => 'allowAnyUser', 'message' => 'You must be logged in.');
		$result = $adapter->check(array(), $request, compact('rules'));
		$this->assertEqual($expected, $result);

		$result = $adapter->check(false, $request, compact('rules'));
		$this->assertEqual($expected, $result);
	}

	/**
	 * Test access checking with no passed or defined rules.
	 */
	public function testCheckNoRules() {
		$user = array('username' => 'Tom');
		$request = new Request();
		$adapter = new Rules();

		$expected = array('rule' => false, 'message' => null, 'redirect' => null);
		$result = $adapter->check($user, $request);
		$this->assertEqual($expected, $result);
	}

	/**
	 * Tests checking against a list of rules that are passed on the fly.
	 */
	public function testPassingRules() {
		$user = array('username' => 'Tom');
		$request = new Request();
		$adapter = new Rules();

		$rules = array(
			array('message' => 'Access denied.', 'rule' => function($user, $request, $options) {
				return $user['username'] == 'Tom';
			})
		);
		$expected = array();
		$result = $adapter->check($user, $request, compact('rules'));
		$this->assertEqual($expected, $result);
	}

	public function testAdd() {
		$request = new Request();
		$user = array('username' => 'Tom');
		$adapter = new Rules();

		$adapter->add('testDeny', function($user, $request, $options) {
			return false;
		});

		$rules = array(array('rule' => 'testDeny', 'message' => 'Access denied.'));
		$expected = array('rule' => 'testDeny', 'message' => 'Access denied.');
		$result = $adapter->check($user, $request, compact('rules'));
		$this->assertEqual($expected, $result);

		$this->assertTrue(is_callable($adapter->get('testDeny')));
		$this->assertEqual($adapter->get('testDeny'), $adapter->getRules('testDeny'));

		$rules = $adapter->get();
		$this->assertTrue(is_array($rules));
		$this->assertTrue(in_array('testDeny', array_keys($rules)));
	}

	/**
	 * Tests that calls only fail when all rules fail if `'allowAny'` is set.
	 */
	public function testAllowAnyRule() {
		$request = new Request();
		$adapter = new Rules();
		$user = array('username' => 'Tom');

		$rules = array(
			array('rule' => 'allowAll', 'message' => 'Access denied.'),
			array('rule' => 'denyAll', 'message' => 'Access denied.')
		);
		$result = $adapter->check($user, $request, compact('rules'));
		$this->assertEqual(array('rule' => 'denyAll', 'message' => 'Access denied.'), $result);

		$adapter = new Rules(array('allowAny' => true));
		$result = $adapter->check($user, $request, compact('rules'));
		$this->assertEqual(array(), $result);

		$result = $adapter->check($user, $request, array('rules' => array('denyAll', 'allowAll')));
		$this->assertEqual(array(), $result);
	}

	/**
	 * Tests that checks against invalid rules return an invalid rule array.
	 */
	public function testInvalidRule() {
		$request = new Request();
		$adapter = new Rules();
		$user = array('username' => 'Tom');

		$result = $adapter->check($user, $request, array('rules' => array('badness')));
		$this->assertEqual(array('rule' => 'badness'), $result);
	}

	/**
	 * Tests that options passed to `Rules::check()` are passed to each rule doing the checking.
	 */
	public function testOptionsPassedToRule() {
		$request = new Request();
		$user    = array('username' => 'Tom');
		$adapter = new Rules(array(
			'rules' => array(
				'foobar' => function($user, $request, $options) {
					return $options['foo'] == 'bar';
				}
			),
			'default' => array('foobar')
		));

		$result = $adapter->check($user, $request, array('foo' => 'baz'));
		$this->assertEqual(array('rule' => 'foobar', 'foo' => 'baz'), $result);
		$result = $adapter->check($user, $request, array('foo' => 'bar'));
		$this->assertEqual(array(), $result);
	}

	/**
	 * Tests that user information is automatically retrieved via the closure in the `'user'`
	 * config.
	 */
	public function testAutoUser() {
		$request = new Request();
		$user    = array('username' => 'Tom');
		$adapter = new Rules(array(
			'rules' => array(
				'user' => function($user, $request, $options) {
					return isset($user['username']) && $user['username'] == 'Tom';
				}
			),
			'default' => array('user'),
			'user' => function() use ($user) { return $user; }
		));

		$result = $adapter->check($user, $request);
		$this->assertEqual(array(), $result);

		$result = $adapter->check(null, $request);
		$this->assertEqual(array(), $result);

		$result = $adapter->check(array('username' => 'Bob'), $request);
		$this->assertEqual(array('rule' => 'user'), $result);
	}
}

?>