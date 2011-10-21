<?php
/**
 * li3_access plugin for Lithium: the most rad php framework.
 *
 * @author        Tom Maiaroto
 * @author Andrzej Grzegorz Borkowski
 * @copyright     Copyright 2010, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
*/

namespace li3_access\tests\cases\extensions\adapter\security\access;

use lithium\net\http\Request;
use lithium\security\Auth;
use li3_access\security\Access;

/**
 * undocumented class
 *
 * Aro tree:
 * ---------------------------------------------------------------
 *  [2] staff 
 *    [5813] staff1 (ab2)
 * [3] administrator
 * [5817] agent
 *    [769] agent1
 *    [967] agent2
 * [5818] user
 *    [943] user1 (mk7)
 * 
 * @author Andrzej Grzegorz Borkowski
 * @package app.tests.cases.models
 */

class AclTest extends \lithium\test\Unit {

	private $users = array(
		'staff1' => array(
			'Users' => array(
				'id' => 6317 #ab2
			)
		),
		'agent1' => array(
			'Users' => array(
				'id' => 448
			)
		),
		'agent2' => array(
			'Users' => array(
				'id' => 248 #ad0
			)
		),
		'user1' => array(
			'Users' => array(
				'id' => 869
			)
		)
	);

	private $queues = array(
		'6001' => array(
			'Queues' => array(
				'id' => 6001
			)
		),
		'3014' => array(
			'Queues' => array(
				'id' => 3014
			)
		),
		'3114' => array(
			'Queues' => array(
				'id' => 3114
			)
		),
		'3116' => array(
			'Queues' => array(
				'id' => 3116
			)
		)
	);

	public function setUp() {
		Auth::config(array(
			'user' => array(
				'adapter' => 'li3_access\tests\mocks\extensions\adapter\auth\MockAuthAdapter'
			)
		));

		Access::config(array(
			'test_acl_access' => array('adapter' => 'Acl')
		));
	}

	public function tearDown() {
		Auth::clear('user');
	}

	/**
	 * Rola [staff] i uzytkownik [staff1] ma na dostep do wszystkich kolejek
	 * 6001, 3014, 3114, 6317
	 *
	 * @return void
	 * @author Andrzej Grzegorz Borkowski
	 */
	public function testCheckAccessRoleStaffToQueues() {
		$result = Access::check('test_acl_access', $this->users['staff1'], 'models/Queues');
		$this->assertTrue($result, 'AccessDenied: models/Queues');

		foreach ($this->queues as $queue) {
			unset($result);
			$result = Access::check('test_acl_access', $this->users['staff1'], $queue);
			$this->assertTrue($result, 'AccessDenied: ' . print_r($queue, true));
		}
	}

	/**
	 * Rola [agent] ,uzytkownik [agent1] wybranych kolejek: 6001, 3014,Nie ma dostepu do 3114, 3116
	 *
	 * @return void
	 * @author Andrzej Grzegorz Borkowski
	 */
	public function testCheckAccessRoleAgentToQueues() {
		$result = Access::check('test_acl_access', $this->users['agent1'], 'models/Queues');
		$this->assertFalse($result);

		foreach (array($this->queues[6001], $this->queues[3014]) as $queue) {
			unset($result);
			$result = Access::check('test_acl_access', $this->users['agent1'], $queue);
			$this->assertTrue($result, 'AccessDenied: ' . print_r($queue, true));
		}

		foreach (array($this->queues[3114], $this->queues[3116]) as $queue) {
			unset($result);
			$result = Access::check('test_acl_access', $this->users['agent1'], $queue);
			$this->assertFalse($result);
		}
	}

	/**
	 * Rola [user], uzytkownik [user1] nie ma dostepu do zadnych kolejek
	 *
	 * @return void
	 * @author Andrzej Grzegorz Borkowski
	 */
	public function testCheckAccessRoleUserToQueues() {
		$result = Access::check('test_acl_access', $this->users['user1'], 'models/Queues');
		$this->assertFalse($result, 'AccessGrant: models/Queues');

		foreach ($this->queues as $queue) {
			unset($result);
			$result = Access::check('test_acl_access', $this->users['user1'], $queue);
			$this->assertFalse($result, 'AccessGrant: ' . print_r($queue, true));
		}
	}

	/**
	 * User [agent2], w grupie [agent] ma extra dostep do 3114, oraz do kolejek grupy [agent] 6001, 3014
	 *
	 * @author Andrzej Grzegorz Borkowski
	 */
	public function testCheckUserAgentToQueue(){
		foreach (array($this->queues[6001], $this->queues[3014], $this->queues[3114]) as $queue) {
			$result = Access::check('test_acl_access', $this->users['agent2'], $queue);
			$this->assertTrue($result, 'AccessDenied: ' . print_r($queue, true));
			unset($result);
		}

		$result = Access::check('test_acl_access', $this->users['agent2'], $this->queues[3116]);
		$this->assertFalse($result, 'AccessGrant: ' . print_r($queue, true));
	}

	/**
	 * List perms for role user and user1 and models/Queues
	 *
	 * @return void
	 * @author Andrzej Grzegorz Borkowski
	 */
	public function testGetRoleUser(){
		$excepted = array(
			'Queues' => false
		);
		$result = Access::get('test_acl_access', $this->users['user1'], 'models/Queues');
		$this->assertEqual($excepted, $result);
	}

	/**
	 * List perms for role agent and models/Queues
	 *
	 * @return void
	 * @author Andrzej Grzegorz Borkowski
	 */
	public function testGetRoleAgent(){
		$excepted = array(
			'Queues' => array(3014, 6001)
		);
		$result = Access::get('test_acl_access', $this->users['agent1'], 'models/Queues');
		$this->assertEqual($excepted, $result);
	}

	/**
	 * List perms for role staf and models/Queues
	 *
	 * @return void
	 * @author Andrzej Grzegorz Borkowski
	 */
	public function testGetRoleStaff(){
		$excepted = array(
			'Queues'
		);
		$result = Access::get('test_acl_access', $this->users['staff1'], 'models/Queues');
		$this->assertEqual($excepted, $result);
	}

	/**
	 * List perms for user agent2 and role agent and models/Queues
	 *
	 * @author Andrzej Grzegorz Borkowski
	 */
	public function testGetUserAgent2(){
		$excepted = array(
			'Queues' => array(3114, 3014, 6001)
		);
		$result = Access::get('test_acl_access', $this->users['agent2'], 'models/Queues');
		$this->assertEqual($excepted, $result);
	}
}

?>