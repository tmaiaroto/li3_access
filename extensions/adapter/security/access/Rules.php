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
	 * Lists a subset of rules defined in `$_rules` which should be checked by default on every
	 * call to `check()` (unless overridden by passed options).
	 *
	 * @var array
	 */
	protected $_default = array();

	/**
	 * Configuration that will be automatically assigned to class properties.
	 *
	 * @var array
	 */
	protected $_autoConfig = array('rules', 'default');

	/**
	 * Sets default adapter configuration.
	 *
	 * @param array $config Adapter configuration, which includes the following default options:
	 *              - `'rules'` _array_: An array of rules to be added to the default rules
	 *                initialized by the adapter. See the `'rules'` option of the `check()` method
	 *                for more information on the acceptable format of these values.
	 *              - `'default'` _array_: The default list of rules to use when performing access
	 *                checks.
	 *              - `'allowAny'` _boolean_: If set to `true`, access checks will return successful
	 *                if _any_ access rule passes. Otherwise, all are required to pass in order for
	 *                the check to succeed. Defaults to `false`.
	 */
	public function __construct(array $config = array()) {
		$defaults = array('rules' => array(), 'default' => array(), 'allowAny' => false);
		parent::__construct($config + $defaults);
	}

	/**
	 * Initializes default rules to use.
	 *
	 * @return void
	 */
	protected function _init() {
		parent::_init();

		$this->_rules += array(
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
		$defaults = array(
			'rules' => $this->_config['default'],
			'allowAny' => $this->_config['allowAny']
		);
		$options += $defaults;

		if (!$options['rules']) {
			$base = array('rule' => false, 'message' => null, 'redirect' => null);
			return array_diff_key($options, $defaults) + $base;
		}

		$rules = (isset($options['rules']['rule'])) ? array($options['rules']) : $options['rules'];
		$result = array();

		foreach ($rules as $rule) {
			if (is_string($rule)) {
				$rule = compact('rule');
			}
			$ruleResult = $this->_call($rule, $user, $request);

			switch (true) {
				case ($ruleResult === false && $options['allowAny']):
					$result = $rule + array_diff_key($options, $defaults);
				break;
				case ($ruleResult === false):
					return $rule + array_diff_key($options, $defaults);
				case ($ruleResult !== false && $options['allowAny']):
					return array();
			}
		}
		return $result;
	}

	/**
	 * Extracts a callable rule either from a rule definition assigned as a closure, or a string
	 * reference to a rule defined in a key in the `$_rules` array.
	 *
	 * @param array $rule The rule definition array.
	 * @param mixed $user The value representing the user making the request. Usually an array.
	 * @param mixed $request The value representing request data or the object being access.
	 * @return boolean Returns `true` if the call to the rule was successful, otherwise `false` if
	 *         the call failed, or if a callable rule was not found.
	 */
	protected function _call($rule, $user, $request) {
		$callable = null;

		switch (true) {
			case (is_callable($rule['rule'])):
				$callable = $rule['rule'];
			break;
			case (in_array($rule['rule'], array_keys($this->_rules))):
				$callable = $this->_rules[$rule['rule']];
			break;
		}
		return is_callable($callable) ? call_user_func($callable, $user, $request, $rule) : false;
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