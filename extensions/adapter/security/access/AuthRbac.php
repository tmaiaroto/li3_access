<?php

namespace li3_access\extensions\adapter\security\access;

use lithium\security\Auth;
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
	 * @param mixed $requester The user data array that holds all necessary information about
	 *        the user requesting access. Or false (because Auth::check() can return false).
	 *        This is an optional parameter, bercause we will fetch the users data trough Auth
	 *        seperately.
	 * @param object $request The Lithium Request object.
	 * @param array $options An array of additional options for the _getRoles method.
	 * @return Array An empty array on success. Array with message and redirect params on failure.
	 */
	public function check($request, $requester = false, array $options = array()) {
		if (!empty($options['roles'])) {
			$this->_roles = $options['roles'];
		}

		if (empty($this->_roles)) {
			throw new ConfigException('No roles defined for adapter configuration.');
		}

		$options += array('options' => array('class' => 'error'));
		$roleDefaults = array(
			'message' => '',
			'redirect' => '',
			'allow' => true,
			'requesters' => '*',
			'match' => '*::*',
			'options' => array()
		);

		extract($options);

		$accessable = false;
		foreach ($this->_roles as $role) {
			$role += $roleDefaults;

			// Check to see if this role applies to this request
			if (static::_match($role['match'], $request) === false) {
				continue;
			}
			$accessable = true;


			if (
				$role['allow'] === false ||
				!static::_hasRole($role['requesters'], $request, $options) ||
				(
					is_array($role['allow']) &&
					!static::_runClosures($role['allow'], $request, $role)
				)
			) {
				$accessable = false;
			}

			if (!$accessable) {
				$message = !empty($role['message']) ? $role['message'] : $message;
				$redirect = !empty($role['redirect']) ? $role['redirect'] : $redirect;
				$options = !empty($role['options']) ? $role['options'] : $options;
			}
		}

		return !$accessable ? compact('message', 'redirect', 'options') : array();
	}

	/**
	 * Matches the current request parameters against a set of given parameters. Can match
	 * against a shorthand string (Controller::action) or a full array. If a parameter
	 * is provided then it must have an equivilent in the Request objects parmeters in order
	 * to validate. * Is also acceptable to match a parameter without a specific value.
	 *
	 * @param mixed $match A set of parameters to validate the request against.
	 * @param mixed $request A lithium Request object.
	 * @access public
	 * @return boolean True if a match is found.
	 */
	protected static function _match($match, $request) {
		if (empty($match)) {
			return false;
		}

		if (is_array($match)) {
			if (!static::_runClosures($match, $request)) {
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

	/**
	 * Itterates over an array and runs any anonymous functions it finds. Returns
	 * true if all of the closures it runs evaluate to true. $match is passed by
	 * reference and any closures found are removed from it before the method is complete.
	 *
	 * @param array $data
	 * @param mixed $request
	 * @static
	 * @access protected
	 * @return void
	 */
	protected static function _runClosures(array &$data = array(), $request = null, array &$roleOptions = array()) {
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
	protected static function _getRoles($request, array $options = array()) {
		$roles = array('*' => '*');
		foreach (array_keys(Auth::config()) as $key) {
			if ($check = Auth::check($key, $request, $options)) {
				$roles[$key] = $check;
			}
		}
		return $roles = array_filter($roles);
	}

	/**
	 * Compares the results from _getRoles with the array passed to it.
	 *
	 * @param mixed $requesters
	 * @param mixed $request
	 * @param array $options
	 * @access protected
	 * @return void
	 */
	protected function _hasRole($requesters, $request, array $options = array()) {
		$authed = array_keys(static::_getRoles($request, $options));

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