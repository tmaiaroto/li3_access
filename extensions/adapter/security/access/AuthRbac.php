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
        if (empty($this->_roles)) {
            throw new ConfigException('No roles defined for adapter configuration.');
        }

        $roleDefaults = array(
            'message' => '',
            'redirect' => '',
            'allow' => true,
            'requesters' => '*',
            'match' => '*::*'
        );

        $message = $options['message'];
        $redirect = $options['redirect'];

        $accessable = false;
        foreach ($this->_roles as $role) {
            $role += $roleDefaults;

            // Check to see if this role applies to this request
            if (!static::parseMatch($role['match'], $request)) {
                continue;
            }
            $accessable = true;

            if (($role['allow'] === false) ||
                (!static::_hasRole($role['requesters'], $request, $options)) ||
                (is_array($role['allow']) && !static::_parseClosures($role['allow'], $request, $role))
            ) {
                $accessable = false;
            }

            if (!$accessable) {
                $message = !empty($role['message']) ? $role['message'] : $message;
                $redirect = !empty($role['redirect']) ? $role['redirect'] : $redirect;
            }
        }

        return !$accessable ? compact('message', 'redirect') : array();
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

            if (!array_key_exists($type, $request->params) ||
				$value !== \lithium\util\Inflector::underscore($request->params[$type])) {
                return false;
            }
        }
        return true;
    }

    /**
     * _parseClosures Itterates over an array and runs any anonymous functions it
     * finds. Returns true if all of the closures it runs evaluate to true. $match
     * is passed by refference and any closures found are removed from it before the
     * method is complete.
     *
     * @param array $data
     * @param mixed $request
     * @static
     * @access protected
     * @return void
     */
    protected static function _parseClosures(array &$data = array(), $request = null, array &$roleOptions = array()) {
        $return = true;
        foreach ($data as $key => $item) {
            if (is_callable($item)) {
                if ($return === true) {
                    $return = (boolean) $item($request, $roleOptions);
                }
                unset($data[$key]);
            }
        }
        return $return;
    }

	/**
	 * @todo reduce Model Overhead (will duplicated in each model)
	 *
	 * @param Request $request Object
	 * @return array|mixed $roles Roles with attachted User Models
	 */
	protected static function _getRolesByAuth($request, array $options = array()){
		$roles = array('*' => '*');
		foreach (array_keys(Auth::config()) as $key){
            if ($check = Auth::check($key, $request, $options)) {
			    $roles[$key] = $check;
            }
		}
		return $roles = array_filter($roles);
	}

    /**
     * _hasRole Compares the results from _getRolesByAuth with the array passed to it.
     *
     * @param mixed $requesters
     * @param mixed $request
     * @param array $options
     * @access protected
     * @return void
     */
    protected function _hasRole($requesters, $request, array $options = array()) {
        $authed = array_keys(static::_getRolesByAuth($request, $options));

        $requesters = (array) $requesters;
        if (in_array('*', $requesters)) {
            return true;
        }

        foreach ($requesters as $requester) {
            if (in_array($requester, $authed)) {
                return true;
            }
        }
        return false;
    }

}

?>
