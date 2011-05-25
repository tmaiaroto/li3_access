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
	 * @param array $options An array of additional options for the getRolesByAuth method.
	 * @return Array An empty array if access is allowed and an array with reasons for denial if denied.
	 */
	public function check($user, $request, array $options = array()) {
        if (empty($this->_roles)) {
            throw new ConfigException('No roles defined for adapter configuration.');
        }

        $message = $redirect = null;
        $authedRoles = static::getRolesByAuth($request, array('checkSession' => false, 'success' => true));

        foreach ($this->_roles as $type => $role) {
            $diff = array_diff((array) $role['requesters'], array_keys($authedRoles));
            if (count($diff) === count($authedRoles)) {
                $accessGranted = false;
            } elseif (empty($role['match'])) {
                $accessGranted = false;
            } else {
                $accessGranted = static::parseMatch($role['match'], $request);
                if ($type === 'deny') {
                    $accessGranted = false;
                }
            }

            if (!$accessGranted) {
                $message = isset($role['message']) ? $role['message'] : $this->_message;
                $redirect = isset($role['redirect']) ? $role['redirect'] : $this->_redirect;
            }
        }

        return !$accessGranted ? compact('message', 'redirect') : array();
	}

	/**
	 * @todo reduce Model Overhead (will duplicated in each model)
	 *
	 * @param Request $request Object
	 * @return array|mixed $roles Roles with attachted User Models
	 */
	public static function getRolesByAuth($request, array $options = array()){
		$roles = array('*' => '*');
		foreach (array_keys(Auth::config()) as $key){
            if ($check = Auth::check($key, $request, $options)) {
			    $roles[$key] = $check;
            }
		}
		return $roles = array_filter($roles);
	}

    /**
     * parseMatch Matches the current request parameters against a set of given parameters.
     * Can match against a shorthand string (Controller::action) or a full array. If a parameter
     * is provided then it must have an equivilent in the Request objects parmeters in order
     * to validate. * Is also acceptable to match a parameter without a specific value.
     *
     * @param mixed $match A set of parameters to validate the request against.
     * @param mixed $request A lithium Request object.
     * @access public
     * @return boolean True if a match is found.
     */
    public static function parseMatch($match, $request) {
        if (empty($match)) {
            return false;
        }

        $result = array();
        foreach ((array) $match as $key => $param) {
            if (preg_match('/^[A-Za-z0-9_\*]+::[A-Za-z0-9_\*]+$/', $param, $regexMatches)) {
                list($controller, $action) = explode('::', reset($regexMatches));
                $result += compact('controller', 'action');
                continue;
            }
            $result[$key] = $param;
        }

        $allowAccess = true;
        foreach ($result as $param => $value) {
            if ($value === '*') {
                continue;
            }

            if (!array_key_exists($param, $request->params) || $value !== $request->params[$param]) {
                $allowAccess = false;
                break;
            }
        }

        return $allowAccess;
    }

}

?>
