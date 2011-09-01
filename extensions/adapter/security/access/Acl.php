<?php

/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2011, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_access\extensions\adapter\security\access;

use lithium\security\Auth;
use lithium\core\ConfigException;
use lithium\util\Set;
use li3_access\models\Acos;
use li3_access\models\Aros;
use li3_access\models\Permissions;
use li3_access\security\Access;

class Acl extends \lithium\core\Object {

	/**
	 * Holds all permission of $requester
	 * @var type 
	 */
	private $_permissions = array();

	/**
	 * The `Acl` database adapter.
	 *
	 * @param mixed $user The user data array that holds all necessary information about
	 *        the user requesting access. Or false (because Auth::check() can return false).
	 *        This is an optional parameter, bercause we will fetch the users data trough Auth
	 *        seperately.
	 * @param object $request The Lithium Request object.
	 * @param array $options An array of additional options for the _getRolesByAuth method.
	 * @return Array An empty array if access is allowed and an array with reasons for denial if denied.
	 */
	public function check($requester, $request, array $options = array()) {
		$defaultOptions = array(
			'message' => '',
			'redirect' => ''
		);

		$options = array_merge($defaultOptions,$options);
		if(!empty($requester)){
			$model = isset($this->_config['credentials']['model']) ? $this->_config['credentials']['model'] : null;
			if(!empty($model)){
				$requester = array($model => $requester);
			}

			if($check = self::_check($requester, $request, $options)){
				return array('check'=> $check) + $options;
			}
		}

		return array();
	}

	/**
	 * Checks if the given $aro has access to action $action in $aco
	 *
	 * @param string $aro ARO The requesting object identifier.
	 * @param string $aco ACO The controlled object identifier.
	 * @return boolean Success (true if ARO has access to action in ACO, false otherwise)
	 * @access public
	 * @link http://book.cakephp.org/view/1249/Checking-Permissions-The-ACL-Component
	 */
	private static function _check($aro, $aco) {
		if ($aro == null || $aco == null) {
			return false;
		}

		$permKeys = array(
			'_allow'
		);

		$aroPath = Aros::node($aro);
		$acoPath = Acos::node($aco);

		if (empty($aroPath) || empty($acoPath)) {
			throw new \Exception("Auth\Acl::check() - Failed ARO/ACO node lookup in permissions check.  Node references:\nAro: " . print_r($aro, true) . "\nAco: " . print_r($aco, true));
			return false;
		}

		if ($acoPath == null || $acoPath == array()) {
			throw new \Exception("Auth\Acl::check() - Failed ACO node lookup in permissions check.  Node references:\nAro: " . print_r($aro, true) . "\nAco: " . print_r($aco, true));
			return false;
		}

		$aroNode = $aroPath[0];
		$acoNode = $acoPath[0];

		$inherited = array();
		//$acoIDs = Set::extract($acoPath, '{n}.' . $this->Aco->alias . '.id');
		$acoIDs = Set::extract($acoPath, '/id');

		$count = count($aroPath);
		for ($i = 0; $i < $count; $i++) {
			$permAlias = Permissions::meta('name');

			$perms = Permissions::find('first', array(
				'conditions' => array(
					//"{$permAlias}.aro_id" => $aroPath[$i][Aros::meta('name')]['id'],
					//"{$permAlias}.aco_id" => $acoIDs
					"{$permAlias}.aro_id" => $aroPath[$i]['id'],
					"{$permAlias}.aco_id" => $acoIDs
				),
				'order' => Acos::meta('name') . ".lft DESC",
				'with' => array('Acos'),
				'recursive' => 0
			));
			if(!$perms || !$perms->data()){
				continue;
			}else{
				$perms = Set::extract($perms->data(), '/');
				//foreach ($perms as $perm) {
					foreach ($permKeys as $key) {
						if (!empty($perms)) {
							if ($perms[$key] == -1) {
								return false;
							} elseif ($perms[$key] == 1) {
								$inherited[$key] = 1;
							}
						}
					//}
					if (count($inherited) === count($permKeys)) {
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 * A pass-through method called by `Auth`. Returns the value of `$data`, which is written to
	 * a user's session. When implementing a custom adapter, this method may be used to modify or
	 * reject data before it is written to the session.
	 *
	 * @param array $data User data to be written to the session.
	 * @param array $options Adapter-specific options. Not implemented in the `Form` adapter.
	 * @return array Returns the value of `$data`.
	 */
	public function set($data, array $options = array()) {
		return $data;
	}

	/**
	 * Called by `Auth` when a user session is terminated. Not implemented in the `Form` adapter.
	 *
	 * @param array $options Adapter-specific options. Not implemented in the `Form` adapter.
	 * @return void
	 */
	public function clear(array $options = array()) {
		
	}

	public static function allow($aro, $aco) {
		return false;
	}

	public static function deny($aro, $aco) {
		return false;
	}

}

?>