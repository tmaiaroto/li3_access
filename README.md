Access library for the Lithium framework for PHP 5.3.

Example Usage
==============
Check out into your libraries directory.
Include the library in your /app/bootstrap/libraries.php file with:

Libraries::add('li3_access');


Add to your bootstrap process the config, for example:

use li3_access\security\Access;
Access::config(array(
    'rule_based' => array(
        'adapter' => 'Rules',
        // optional filters applied to check() method
        'filters' => array(
            function($self, $params, $chain) {
                // any config can define filters to do some stuff
                return $chain->next($self, $params, $chain);
            }
        )
    )
));


Then you can make a call to check acces (say in a controller) like so:

$access = Access::check('rule_based', Auth::check('configname'), $this->request);
if(!empty($access) {
    $this->redirect($access['redirect']);
}


The Access::check() method will always return an array. It will be empty if access is granted and will contain some data if denied. This array should contain a "message" key that explains why access was denied as well as a "redirect" key.
However, the array can contain any additional data that the adapter may choose to put in it.
You may also wish to call Access::check() from a different point in your application than the controller.

See the Rules adapter for a simple example of an adapter and more information including information about the add() method.
Note: The above example assumes you're using Lithium's Auth class to obtain user data.
Also Note: To call methods from the adapter that are not in the Access class, you will need to access it like so:

Access::adapter('rule_based')->add();

=============================================================================================
Example for Role Based Access via the Rbac Adapter and Auth Roles (config/bootstrap/auth.php)
Info:
      A user could assigned to more than one Role!
      The roles are check sequentially. You could do :
		allow,deny,allow,allow,deny = deny
		allow,deny,allow,allow = allow
=============================================================================================



use lithium\action\Dispatcher;
use lithium\security\Auth;
use lithium\storage\Session;
use lithium\core\Libraries;

Libraries::add('li3_access');
use li3_access\security\Access;

Session::config(array(
	'default' => array('adapter' => 'Php')
));


Auth::config(array(
	//these are the roles as an array!
	'user' => array(
		'adapter' => 'Form',
		'model' => 'User',
		'fields' => array(
			'email' => 'email',
			'password' => 'password',
		),
		'scope' => array(
			'active' => true,
		),
	),
	'editor' => array(
		'adapter' => 'Form',
		'model' => 'Editor',
		'fields' => array(
			'email' => 'email',
			'password' => 'password',
		),
		'scope' => array(
			'active' => true,
			'group' => 1,
		),
	),
	'customer' => array(
		'adapter' => 'Form',
		'model' => 'Customer',
		'fields' => array(
			'email' => 'email',
			'password' => 'password',
		),
		'scope' => array(
			'active' => true,
			'group' => 2,
		),
	),
));

Access::config(array(
    'rbac' => array( //rule based access
        'adapter' => 'AuthRbac',
		'data' => array (
			/*-array(
				'allow', //allow || deny
				'role', //owner ..not yet supported
				'*', //role-name
				'*', //controller_name without Controller Suffix
				'*' //action (CaSe SensitiVE !)
			),*/
			array('allow', 'role', '*', 'Pages', '*'),
			array('allow', 'role', '*',	'Users', 'login'),
			array('allow', 'role', '*', 'Customers', 'index'),
			array('allow', 'role', '*', 'Customers', 'create'),
			array('allow', 'role', 'user', 'Users', 'logout'),
			array('allow', 'role', 'user', 'Users', 'dashboard'),
			array('allow', 'role', 'customer', 'Customers', 'dashboard'),
			array('allow', 'role', 'editor', '*', '*'), //grant editor everything
			array('deny' , 'role', 'user', 'Secret', '*'), //deny user access to Secrets actions
			array('allow', 'role', 'user', 'Secret', 'wtf'), //but allow the wtf action
		),
    )
));
