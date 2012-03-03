<?php

namespace li3_access\extensions\adapter\security\access;

use lithium\security\Auth;
use lithium\util\Inflector;
use lithium\core\ConfigException;

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
	 * @todo: write better tests!
	 *
	 * @param mixed $requester The user data array that holds all necessary information about
	 *        the user requesting access. Or false (because Auth::check() can return false).
	 *        This is an optional parameter, because we will fetch the users data through Auth
	 *        seperately.
	 * @param mixed $params The Lithium `Request` object, or an array with at least
	 *        'request', and 'params'
	 * @param array $options An array of additional options for the _getRolesByAuth method.
	 * @return Array An empty array if access is allowed or
	 *         an array with reasons for denial if denied.
	 */
	public function check($requester, $params, array $options = array()) {
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

		$accessible = false;
		foreach ($this->_roles as $role) {
			$role += $roleDefaults;
			if (is_callable($role['allow'])) {
				$role['allow'] = (array) $role['allow'];
			}

			// Check to see if this role applies to this request
			if (!static::parseMatch($role['match'], $params)) {
				continue;
			}

			$accessible = static::_isAccessible($role, $params, $options);

			if (!$accessible) {
				$message = !empty($role['message']) ? $role['message'] : $message;
				$redirect = !empty($role['redirect']) ? $role['redirect'] : $redirect;
			}
		}

		return !$accessible ? compact('message', 'redirect') : array();
	}

	/**
	 * Checks if the Role grants access
	 * If allow === false => no access
	 * If requesters has no role => no access
	 * If allows contains closures => return closures return
	 * Otherwise => grants access
	 *
	 * @param array $role Array Set of Roles (dereferenced)
	 * @param mixed $quest A lithium Request object.
	 * @param array $options An array of additional options for the _getRolesByAuth method.
	 * @return boolean $accessable
	 */
	protected static function _isAccessible(&$role, $params, $options) {
		if ($role['allow'] === false) {
			return false;
		}
		if (!static::_hasRole($role['requesters'], $params, $options)) {
			return false;
		}
		if (is_array($role['allow'])) {
			return static::_parseClosures($role['allow'], $params['request'], $role);
		}
		return true;
	}

	/**
	 * parseMatch Matches the current request parameters against a set of given parameters.
	 * Can match against a shorthand string (Controller::action) or a full array. If a parameter
	 * is provided then it must have an equivilent in the Request objects parmeters in order
	 * to validate. * Is also acceptable to match a parameter without a specific value.
	 *
	 * @param mixed $match A set of parameters to validate the request against.
	 * @param mixed $params The Lithium `Request` object, or an array with at least
	 *        'request', and 'params'
	 * @access public
	 * @return boolean True if a match is found.
	 */
	public static function parseMatch($match, $params) {
		if (empty($match)) {
			return false;
		}

		if (is_array($match)) {
			$_params = $params;
			if (!static::_parseClosures($match, $params['request'], $_params)) {
				return false;
			}
		} elseif (is_callable($match)) {
			return (boolean) $match($params['request'], $params);
		}

		$matchParams = array();
		foreach ((array) $match as $key => $param) {
			if (is_string($param)) {
				if (preg_match('/^([A-Za-z0-9_\*\\\]+)::([A-Za-z0-9_\*]+)$/', $param, $regexMatches)) {
					$matchParams += array(
						'controller' => $regexMatches[1],
						'action' => $regexMatches[2]
					);
					continue;
				}
			}

			$matchParams[$key] = $param;
		}

		foreach ($matchParams as $type => $value) {
			if ($value === '*') {
				continue;
			}

			if ($type === 'controller') {
				$value = Inflector::underscore($value);
			}

			$exists_in_request = array_key_exists($type, $params['params']);
			if (!$exists_in_request || $value !== Inflector::underscore($params['params'][$type])) {
				return false;
			}
		}
		return true;
	}

	/**
	 * _parseClosures Iterates over an array and runs any anonymous functions it
	 * finds. Returns true if all of the closures it runs evaluate to true. $match
	 * is passed by refference and any closures found are removed from it before the
	 * method is complete.
	 *
	 * @static
	 * @access protected
	 *
	 * @param array $data dereferenced Array
	 * @param object $request The Lithium `Request` object
	 * @param array $roleOptions dereferenced Array
	 * @return boolean
	 */
	protected static function _parseClosures(array &$data, $request, array &$roleOptions = array()) {
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
	 * @param mixed $params The Lithium `Request` object, or an array with at least
	 *        'request', and 'params'
	 * @param array $options
	 * @return array|mixed $roles Roles with attachted User Models
	 */
	protected static function _getRolesByAuth($params, array $options = array()) {
		$roles = array('*' => '*');
		foreach (array_keys(Auth::config()) as $key) {
			if ($check = Auth::check($key, $params['request'], $options)) {
				$roles[$key] = $check;
			}
		}
		return $roles = array_filter($roles);
	}

	/**
	 * _hasRole Compares the results from _getRolesByAuth with the array passed to it.
	 *
	 * @param mixed $requesters
	 * @param mixed $params
	 * @param array $options
	 * @access protected
	 * @return void
	 */
	protected function _accessable($resources, $roles, array $options = array()) {
		$resources = (array) $resources;
		if (in_array('*', $resources)) {
			return true;
		}

		foreach ($resources as $resource) {
			if (array_key_exists($resource, $roles)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Itterates over an array and runs any anonymous functions it finds. Returns
	 * true if all of the closures it runs evaluate to true. $match is passed by
	 * reference and any closures found are removed from it before the method is complete.
	 *
	 * @param array $data
	 * @param mixed $request
	 * @access protected
	 * @return void
	 */
	protected function _run(&$data, $request = null, array &$options = array()) {
		if (is_bool($data)) {
			return $data;
		}

		if (!is_array($data)) {
			return false;
		}

		$allow = true;
		foreach ($data as $key => $item) {
			if (is_callable($item)) {
				if ($allow === true) {
					$allow = (boolean) $item($request, $options);
				}
				unset($data[$key]);
			}
		}
		return $allow;
	}

	protected static function _hasRole($requesters, $params, array $options = array()) {
		$authed = array_keys(static::_getRolesByAuth($params, $options));

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
