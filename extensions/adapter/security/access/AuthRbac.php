<?php

namespace li3_access\extensions\adapter\security\access;

use lithium\security\Auth;
use lithium\core\ConfigException;

use li3_access\security\Access;

class AuthRbac extends \lithium\core\Object {

    /**
     * @var array $_autoConfig
     * @see lithium\core\Object::$_autoConfig
     */
	protected $_autoConfig = array('redirect', 'message', 'roles');

    /**
     * @var mixed $_redirect Where to return if Rbac denies access.
     */
    protected $_redirect = null;

    /**
     * @var string $_message A message containing a reason for Rbac failing to deny access.
     */
    protected $_message = '';

	/**
	 * @var mixed $_roles null if unset array otherwise with fetched AuthObjects
	 */
	protected $_roles = null;

	/**
	 * The `Rbac` adapter will iterate trough the rbac data Array.
	 * @todo: Implement file based data access
	 * @todo: Shorter $match syntax
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
        if (empty($this->_roles)) {
            throw new ConfigException('No roles defined for adapter configuration.');
        }

        $message = null;
        $redirect = null;
        $authedRoles = static::getRolesByAuth($request);

        foreach ($this->_roles as $type => $role) {
            $types = array('allow', 'deny');
            if (!in_array($type, $types)) {
                continue;
            }

            extract($role);
            extract($match);

            $auths = (array) $auths;
            foreach ($auths as $auth) {
                if (array_key_exists($auth, $authedRoles)) {
			        if ($controller === '*' && ($action === '*' || $action === $request->params['action'])) {
                        $accessGranted = false;
                        if ($type === 'allow') {
                            $accessGranted = true;
                        }
                        break;
			        }

                    if ($controller == $request->params['controller'] && ($action == '*' || $action == $request->params['action'])) {
                        $accessGranted = false;
                        if ($type === 'allow') {
                            $accessGranted = true;
                        }
                        break;
			        }
                }
            }
        }

        if (!$accessGranted) {
            $this->_message = $message ?: $this->_message;
            $this->_redirect = $redirect ?: $this->_redirect;

            return array(
                'message' => $this->_message,
                'redirect' => $this->_redirect
            );
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
		return $roles = array_filter($roles);
	}

}

?>
