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
	 * @param mixed $requester The user data array that holds all necessary information about
	 *        the user requesting access. Or false (because Auth::check() can return false).
	 *        This is an optional parameter, because we will fetch the users data through Auth
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
		$defaults = array(
			'message' => '',
			'redirect' => '',
			'allow' => true,
			'requesters' => '*',
			'match' => '*::*',
			'options' => array()
		);

		extract($options);

		if (empty($request->data) && $requester) {
			$request->data = $requester;
		}

		$allow = false;
		foreach ($this->_roles as $role) {
			$role += $defaults;

			if ($this->_match($role['match'], $request) === false) {
				continue;
			}
			$allow = false;

			$roles = $this->_roles($request);
			$accessable = $this->_accessable($role['requesters'], $roles, $options);
			$allow = $this->_run($role['allow'], $request, $role) && $accessable;

			if (!$allow) {
				$message = !empty($role['message']) ? $role['message'] : $message;
				$redirect = !empty($role['redirect']) ? $role['redirect'] : $redirect;
				$options = !empty($role['options']) ? $role['options'] : $options;
			}
		}

		return !$allow ? compact('message', 'redirect', 'options') : array();
	}

	/**
	 * Matches the current request parameters against a set of given parameters. Can match
	 * against a shorthand string (Controller::action) or a full array. If a parameter
	 * is provided then it must have an equivalent in the Request objects parmeters in order
	 * to validate. * Is also acceptable to match a parameter without a specific value.
	 *
	 * @param mixed $match A set of parameters to validate the request against.
	 * @param mixed $request A lithium Request object.
	 * @access public
	 * @return boolean True if a match is found.
	 */
	protected function _match($match, $request) {
		if (empty($match)) {
			return false;
		}

		if (is_array($match)) {
			if (!$this->_run($match, $request)) {
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
				$value = Inflector::underscore($value);
			}

			if (!array_key_exists($type, $request->params) || $value !== $request->params[$type]) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @todo reduce Model Overhead (will duplicated in each model)
	 *
	 * @param Request $request Object
	 * @return array|mixed $roles Roles with attached User Models
	 */
	protected function _roles($request, array $options = array()) {
		$roles = array('*' => '*');
		foreach (array_keys(Auth::config()) as $key) {
			if ($check = Auth::check($key, $request, $options)) {
				$roles[$key] = $check;
			}
		}
		return $roles = array_filter($roles);
	}

	/**
	 * Compares the results from _roles with the array passed to it.
	 *
	 * @param mixed $requesters
	 * @param mixed $request
	 * @param array $options
	 * @access protected
	 * @return void
	 */
	protected function _accessable($requesters, $roles, array $options = array()) {
		$requesters = (array) $requesters;
		if (in_array('*', $requesters)) {
			return true;
		}

		foreach ($requesters as $requester) {
			if (array_key_exists($requester, $roles)) {
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

}

?>