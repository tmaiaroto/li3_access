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
	protected $_autoConfig = array('roles');

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

        $message = $options['message'];
        $redirect = $options['redirect'];
        unset($options['message'], $options['redirect']);
        $authedRoles = static::getRolesByAuth($request, $options);

        $accessGranted = false;
        foreach ($this->_roles as $role) {
            // if the role does not apply to the current request "continue;"
            if (empty($role['match']) || !$match = static::parseMatch($role['match'], $request)) {
                continue;
            }
            $accessGranted = $match;

            // if the user does not have access to this role set accessGranted false
            // if the $allow is set to deny set accessGranted to false
            $requesters = isset($role['requesters']) ? $role['requesters'] : '*';
            $allow = isset($role['allow']) ? (boolean) $role['allow'] : true;

            $diff = array_diff((array) $requesters, array_keys($authedRoles));
            if ((count($diff) === count($authedRoles)) || !$allow) {
                $accessGranted = false;
            }

            if (!$accessGranted) {
                $message = !empty($role['message']) ? $role['message'] : $message;
                $redirect = !empty($role['redirect']) ? $role['redirect'] : $redirect;
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

        if (is_array($match)) {
            if (!static::_parseClosures($match, $request)) {
                return false;
            }
        }

        $params = array();
        foreach ((array) $match as $key => $param) {
            if (is_string($param)) {
                if (preg_match('/^[A-Za-z0-9_\*]+::[A-Za-z0-9_\*]+$/', $param, $regexMatches)) {
                    list($controller, $action) = explode('::', reset($regexMatches));
                    $params += compact('controller', 'action');
                    continue;
                }
            }

            $params[$key] = $param;
        }

        foreach ($params as $type => $value) {
            if ($value === '*') {
                continue;
            }

            if ($type === 'controller') {
                $value = \lithium\util\Inflector::underscore($value);
            }

            if (!array_key_exists($type, $request->params) || $value !== $request->params[$type]) {
                return false;
            }
        }

        return true;
    }

    protected static function _parseClosures(array &$data = array(), $request = null) {
        if (empty($data)) {
            return false;
        }

        $return = true;
        foreach ($data as $key => $item) {
            if (is_callable($item)) {
                $return = $item($request);
                unset($data[$key]);
            }
        }

        return $return;
    }

}

?>
