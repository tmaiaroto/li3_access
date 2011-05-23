<?php

namespace li3_access\extensions\adapter\security\access;

use lithium\security\Auth;

use li3_access\security\Access;

class AuthRbac extends \lithium\core\Object {

	/**
	 * @var array $_roles null if unset array otherwise with fetched AuthObjects
	 */
	protected $_roles = null;

	/**
	 * The `Rbac` adapter will iterate trough the rbac data Array.
	 * @todo: implement file based data access
	 *
	 * @param mixed $user The user data array that holds all necessary information about
	 *        the user requesting access. Or false (because Auth::check() can return false).
	 *        This is an optional parameter, bercause we will fetch the users data trough Auth
	 *        seperately.
	 * @param object $request The Lithium Request object.
	 * @param array $options An array of additional options.
	 * @return Array An empty array if access is allowed and an array with reasons for denial if denied.
	 */
	public function check($user, $request, array $options = array()) {
		if(is_null($this->_roles)){
			$this->_roles = static::getRolesByAuth($request);
		}

		if(!static::checkRules($this->_roles, $request, $this->_config['data'])) {
			return $options;
		}

		return array();
	}

	/**
	 * @todo reduce Model Overhead (will duplicated in each model)
	 *
	 * @param Request $request Object
	 * @return array|mixed $roles Roles with attachted User Models
	 */
	public static function getRolesByAuth($request){
		$roles = array('*' => '*');
		foreach (array_keys(Auth::config()) as $key){
			$roles[$key] = Auth::check($key, $request); //check against each role
		}
		return $roles = \array_filter($roles);
	}

	/**
	 * Checks Request against the configured Auth data array. If the request matches the rule
	 * it will return true => if no rule matches it will return false.
	 *
	 * @param array $roles
	 * @param Request $request
	 * @param array $rules
	 * @return boolean $granted
	 */
	public static function checkRules($roles, $request, array $rules = array()){
		$defaultUser = array('*' => '*');

		$roles += $defaultUser;

		$accessGranted = false;
		foreach ($rules as $rule) {
			list($access, $role, $roleName, $controller, $action) = $rule;
			//sanitize access list items
			$role = \strtolower($role);
			$controller = \strtolower($controller);

			if ($role != 'role') { //currently without owner support
				continue;
			}

			if (
				\array_key_exists($roleName, $roles) && //role matches
				$controller == '*' &&
				($action == '*' || $action == $request->action)
			) {
				$accessGranted = ($access === 'allow') ?: false;
			}

			if (
				\array_key_exists($roleName, $roles) && //role matches
				$controller == $request->controller &&
				($action == '*' || $action == $request->action)
			){
				$accessGranted = ($access === 'allow') ?: false;
			}

		}
		return (boolean) $accessGranted;
	}

}

?>
