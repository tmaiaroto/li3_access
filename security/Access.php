<?php
/**
 * li3_access plugin for Lithium: the most rad php framework.
 *
 * @author        Tom Maiaroto
 * @copyright     Copyright 2010, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_access\security;

use lithium\core\ConfigException;

/**
 * The `Access` class provides...
 *
 * `Access` exposes a set of methods which adapters can implement: `set()`, `check()` and `clear()`.
 * You can read more about each method below.
 *
 * For additional information on configuring and working with `Access`, see the `Simple` adapter.
 *
 * @see li3_access\extensions\adapter\auth\Simple
 */
class Access extends \lithium\core\Adaptable {

	/**
	 * Stores configurations for various authentication adapters.
	 *
	 * @var object `Collection` of authentication configurations.
	 */
	protected static $_configurations = array();

	/**
	 * Libraries::locate() compatible path to adapters for this class.
	 *
	 * @see lithium\core\Libraries::locate()
	 * @var string Dot-delimited path.
	 */
	protected static $_adapters = 'adapter.security.access';

	/**
	 * Called when an adapter configuration is first accessed, this method sets the default
	 * configuration for session handling. While each configuration can use its own session class
	 * and options, this method initializes them to the default dependencies written into the class.
	 * For the session key name, the default value is set to the name of the configuration.
	 *
	 * @param string $name The name of the adapter configuration being accessed.
	 * @param array $config The user-specified configuration.
	 * @return array Returns an array that merges the user-specified configuration with the
	 *         generated default values.
	 */
	protected static function _initConfig($name, $config) {
		$defaults = array();
		$config = parent::_initConfig($name, $config) + $defaults;
		return $config;
	}

	/**
	 * Performs an access check against the specified configuration, and returns true
	 * if access is permitted and an array with additional details if access is denied.
	 *
	 * The data return when access is not permitted will vary by adapter, but it is
	 * ideal to have a "message" and a "redirect" so that a user can be notified
	 * about why they were denied access and so they can be redirected somewhere to,
	 * perhaps, login.
	 *
	 * @param string $name The name of the `Access` configuration/adapter to check against.
	 * @param object $request A Lithium Request object.
	 * @param mixed $resource The user data that holds all necessary information about
	 *        the user requesting access. Or `false` (because Auth::check() can return `false`).
	 * @param array $options An array of additional options.
	 * @return Array An empty array if access is allowed and an array with reasons for denial if denied.
	 */
	public static function check($name, $resource = null, $request, array $options = array()) {
		$defaults = array(
			'message' => 'You are not authorized to access this page.', 'redirect' => '/'
		);
		$options += $defaults;

		if (($config = static::_config($name)) === null) {
			throw new ConfigException("Configuration `{$name}` has not been defined.");
		}
		$filter = function($self, $params) use ($name) {
			return $self::adapter($name)->check($params['resource'], $params['request'], $params['options']);
		};
		$filters = (array) $config['filters'];
		$params = compact('resource', 'request', 'options');
		return static::_filter(__FUNCTION__, $params, $filter, $filters);
	}
}

?>