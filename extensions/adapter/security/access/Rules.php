<?php

namespace li3_access\extensions\adapter\security\access;

use lithium\util\Set;

/**
 * undocumented class
 */
class Rules extends \lithium\core\Object {

	/**
	 * Rules are named closures that must either return `true` or `false`.
	 *
	 * @var array
	 */
	protected $_rules = array();

	/**
	 * Initializes default rules to use.
	 *
	 * @return void
	 */
	protected function _init() {
		$this->_rules = array(
			'allowAll' => function() {
				return true;
			},
			'denyAll' => function() {
				return false;
			},
			'allowAnyUser' => function($user) {
				return $user ? true : false;
			},
			'allowIp' => function($user, $request, $options) {
				$options += array('ip' => false);

				if (is_string($options['ip']) && strpos($options['ip'], '/') === 0) {
					return (boolean) preg_match($options['ip'], $request->env('REMOTE_ADDR'));
				}
				if (is_array($options['ip'])) {
					return in_array($request->env('REMOTE_ADDR'), $options['ip']);
				}
				return $request->env('REMOTE_ADDR') == $options['ip'];
			}
		);
	}

	/**
	 * The `Rules` adapter will use check to test the provided data
	 * against a number of given rules. Extra data that may be required
	 * to make an informed decision about access can be passed in the
	 * `$options` array. This extra data will vary from app to app and rules
	 * will need to be added to handle it. The default rules assume some
	 * general cases and more can be added or passed directly to this method.
	 *
	 * @param mixed $user The user data array that holds all necessary information about
	 *        the user requesting access. Or false (because `Auth::check()` can return `false`).
	 * @param object $request The Lithium `Request` object.
	 * @param array $options An array of additional options.
	 * @return array An empty array if access is allowed and an array with reasons for denial
	 *         if denied.
	 */
	public function check($user, $request, array $options = array()) {
		$defaults = array('rules' => array());
		$options += $defaults;

		if (!$options['rules']) {
			return array(
				'rule' => false,
				'message' => $options['message'],
				'redirect' => $options['redirect']
			);
		}

		// If a single rule was passed, wrap it in an array so it can be iterated as if
		// there were multiple
		$rules = (isset($options['rules']['rule'])) ? array($options['rules']) : $options['rules'];

		// Loop through all the rules. They must all pass.
		foreach ($rules as $rule) {
			// make sure the rule is set and is a string to check for a closure to call or a
			// closure itself.
			$hasRule = (
				isset($rule['rule']) &&
				(is_string($rule['rule']) || is_callable($rule['rule']))
			);
			if (!$hasRule) {
				continue;
			}
			if ($this->_call($rule, $user, $request) === false) {
				return $rule + array_diff_key($options, $defaults);
			}
		}
		return array();
	}

	protected function _call($rule, $user, $request) {
		// The added rule closure will be passed the user data
		if (in_array($rule['rule'], array_keys($this->_rules))) {
			// The rule closure will be passed the user, request and the rule array itself
			// which could contain extra data required by the specific rule.
			return call_user_func($this->_rules[$rule['rule']], $user, $request, $rule);
		}
		if (is_callable($rule['rule'])) {
			// The rule can be defined as a closure on the fly, no need to call add()
			return call_user_func($rule['rule'], $user, $request, $rule);
		}
		return false;
	}

	/**
	 * Adds an Access rule. This works much like the Validator class.
	 * All rules should be anonymous functions and will be passed
	 * $user, $request, and $options which will contain the entire
	 * rule array which contains its own name plus other data that
	 * could be used to determine access.
	 *
	 * @param string $name The rule name.
	 * @param function $rule The closure for the rule, which has to return true or false.
	 */
	public function add($name, $rule = null) {
		$this->_rules = Set::merge($this->_rules, is_array($name) ? $name : array($name => $rule));
	}

	/**
	 * Simply returns the rules that are currently available. Optionally, passing a name will return
	 * just that rule or `false` if it doesn't exist.
	 *
	 * @param string $name The rule name (optional).
	 * @return mixed Either an array of rule closures, a single rule closure, or `false`.
	 */
	public function get($name = null) {
		if ($name) {
			return isset($this->_rules[$name]) ? $this->_rules[$name] : false;
		}
		return $this->_rules;
	}

	/**
	 * @deprecated
	 */
	public function getRules($name = null) {
		return $this->get($name);
	}
}

?>