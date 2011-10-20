<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2011, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_access\extensions\adapter\security\access;

//use lithium\security\Auth;
use lithium\core\ConfigException;
use lithium\util\Set;
use li3_access\models\Acos;
use li3_access\models\Aros;
use li3_access\models\Permissions;
//use li3_access\security\Access;

/**
 * The `Acl` database adapter.
 */
class Acl extends \lithium\core\Object {

	/**
	 * Holds all permission of $requester
	 * @var array 
	 */
	protected $_permissions = array();

	/**
	 * Holds all permission of $requester
	 * @var array 
	 */
	protected static $_aroPath = false;

	/**
	 * Holds all permission of $requester
	 * @var array 
	 */
	protected static $_acoPath = false;

	public $permKeys = array(
		'_allow'
	);

	protected $_model = array();
	protected $_handlers = array();
	protected $_autoConfig = array('model', 'handlers');

	public function __construct(array $config = array()) {
		$defaults = array(
			'model' => array(
				'Aros' => 'app\models\aros',
				'Acos' => 'app\models\acos',
				'Permissions' =>'app\models\aros_acos'
			),
			'defaultNoUser' => array(),
			'defaultUser' => array(),
			'userIdentifier' => 'id'
		);
		parent::__construct($config + $defaults);
		$this->_handlers += array(
			'serialize' => function($data) {
				return serialize($data);
			},
			'unserialize' => function($data) {
				return unserialize($data);
			}
		);
	}
	
	/**
	 * Checks if the given `$requester` has access to action `$request`
	 *
	 * @param mixed $requester The user data array that holds all necessary information about
	 * the user requesting access. Or false (because Auth::check() can return false).
	 * This is an optional parameter, bercause we will fetch the users data trough Auth
	 * seperately.
	 * @param object $request The Lithium Request object.
	 * @param array $options An array of additional options for the _getRolesByAuth method.
	 * @return Array An empty array if access is allowed and an array with reasons for denial if denied.
	 */
	public function check($requester, $request, array $options = array()) {
		$defaults = array();
		$options += $defaults;

		if (!empty($requester)){
			$model = isset($this->_config['credentials']['model']) ? $this->_config['credentials']['model'] : null;
			if (!empty($model)){
				$requester = array($model => $requester);
			}
		}

		return self::_check($requester, $request, $options);
	}

	/**
	 * Get perms
	 *
	 * @param string $aro 
	 * @param string $aco 
	 * @return void
	 * @author Andrzej Grzegorz Borkowski
	 */
	public function get($aro, $aco) {
		$resources = array();

		$check = self::_check($aro, $aco);
 
		$aroPath = self::$_aroPath;
		$acoPath = self::$_acoPath;

		$acoNode = $acoPath[0];

		$acoRoot = Acos::findById($acoNode['id']);
		if(!$acoRoot){
			return $resources;
		}

		if($check){
			return array($acoRoot->data('alias'));
		}

		$resources = array($acoRoot->data('alias') => array());
		// aro_id => aco_id
		$acos = Acos::find('all', array(
			'conditions' => array(
				'lft' => array('>' => $acoRoot->data('lft')),
				'rght' => array('<' => $acoRoot->data('rght'))
			),
			'order' => array('lft' => 'asc'))
		);
		if(!$acos){
			return $resources;
		}
		$acosIDs = array_keys($acos->to('array'));
		$count = count($aroPath);
		//przelec po arosach user/rola
		$permAlias = Permissions::meta('name');
		$acoAlias = Acos::meta('name');
		for ($i = 0; $i < $count; $i++) {
			$perms = Permissions::find('all', array(
				'conditions' => array(
					"{$permAlias}.aro_id" => $aroPath[$i]['id'],
					"{$permAlias}.aco_id" => $acosIDs
				),
				'order' => "{$acoAlias}.lft DESC",
				'with' => array('Acos'),
				'recursive' => 0
			));
			if($perms){
				$resources[$acoRoot->data('alias')] = array_merge(
					$resources[$acoRoot->data('alias')],
					Set::extract(array_values($perms->to('array')), "/aco/foreign_key")
				);
			}
		}
		if(empty($resources[$acoRoot->data('alias')])){
			$resources[$acoRoot->data('alias')] = false;
		}
		return $resources;
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

	/**
	 * Pass-thru function for ACL allow instance. Allow methods
	 * are used to grant an ARO access to an ACO.
	 *
	 * @param string $aro ARO The requesting object identifier.
	 * @param string $aco ACO The controlled object identifier.
	 * @return boolean Success
	 * @access public
	 */
	public static function allow($aro, $aco) {
		return false;
	}

	/**
	 * Pass-thru function for ACL deny instance. Deny methods
	 * are used to remove permission from an ARO to access an ACO.
	 *
	 * @param string $aro ARO The requesting object identifier.
	 * @param string $aco ACO The controlled object identifier.
	 * @return boolean Success
	 * @access public
	 */
	public static function deny($aro, $aco) {
		return false;
	}

	/**
	 * Checks if the given $aro has access to action $action in $aco
	 *
	 * @param string $aro ARO The requesting object identifier.
	 * @param string $aco ACO The controlled object identifier.
	 * @return boolean Success (true if ARO has access to action in ACO, false otherwise)
	 * @access private
	 * @link http://book.cakephp.org/view/1249/Checking-Permissions-The-ACL-Component
	 */
	protected static function _check($aro, $aco) {
		if ($aro == null || $aco == null) {
			return false;
		}

		$permKeys = array(
			'_allow'
		);

		$aroPath = self::$_aroPath = Aros::node($aro);
		$acoPath = self::$_acoPath = Acos::node($aco);

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
		$permAlias = Permissions::meta('name');
		$acoAlias = Acos::meta('name');
		for ($i = 0; $i < $count; $i++) {
			$perms = Permissions::find('first', array(
				'conditions' => array(
					//"{$permAlias}.aro_id" => $aroPath[$i][Aros::meta('name')]['id'],
					//"{$permAlias}.aco_id" => $acoIDs
					"{$permAlias}.aro_id" => $aroPath[$i]['id'],
					"{$permAlias}.aco_id" => $acoIDs
				),
				'order' => $acoAlias .'.lft DESC',
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

}

?>