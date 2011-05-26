#Access control library for the Lithium framework.

##Installation

Checkout the code to either of your library directories:

    cd libraries
    git clone git@github.com

Include the library in in your `/app/config/bootstrap/libraries.php`

    Libraries::add('li3_access');

##Usage

You must configure the adapter you wish to use first, but once you have it configured it's fairly simple to use.

    $access = Access::check('access_config_name', Auth::check('auth_config_name'), $this->request);
    if(!empty($access)) {
        $this->redirect($access['redirect']);
    }

If the request validates correctly based on your configuration then `Access::check()` will return an empty `array()` otherwise it will return and array with two keys; `message` and `redirect`. These values are built into the Access class but you can override them but passing them as `$options` to all three of the adapters in this repository.

##Configuration

In this repository there are three adapters. All three work in a slightly different way.

###Simple Adapter

The simple adapter is exactly what it says it is. The check method only checks that the data passed to is not empty and as a result the configuration is trivial.

    Access::config(
        'simple' => array('adapter' => 'Simple')
    );

And that's it!

###Rules Adapter

This adapter effectively allows you to tell it how it should work. It comes with a few preconfigured rules by default but it's very simple to add your own. Its configuration is the same as the `Simple` adapter if you only want to use the built in methods.

    Access::config(
        'rules' => array('adapter' => 'Rules')
    );

Then to deny all requests from the authenticated user.

    $access = Access::check('rules', Auth::check('auth_config_name'), $this->request, array('rule' => 'denyAll'));
    if(!empty($access)) {
        $this->redirect($access['redirect']);
    }

There are four built in rules; allowAll, denyAll, allowAnyUser and allowIp, for more information see the adapter itself. However, this adapter is at it's most useful when you add your own rules.

    Access::adapter('custom_rule')->add(function($user, $request, $options) {
        // Your logic here. Just make sure it returns an array.
    });

Then to use your new rule:

    $access = Access::check('rules', Auth::check('auth_config_name'), $this->request, array('rule' => 'custom_rule'));

One more to go!

###AuthRbac Adapter

This is the most complex adapter in this repository at this time. It's used for Role Based Access Control. You define a set of roles (or conditions) to match the request against, if the request matches your conditions the adapter then checks to see if the user is authenticated with the appropriate `\lithium\security\Auth` configurations to be granted access.

It's difficult to explain (I hope that's clear enough) so lets look at an example configuration to try and achive some clarity:

    Access::config(
        'auth_rbac' => array(
            'adapter' => 'AuthRbac',
            'roles' => array(
                array(
                    'requesters' => '*',
                    'match' => '*::*'
                ),
                array(
                    'message' => 'No panel for you!',
                    'redirect' => array('library' => 'admin', 'Users::login'),
                    'requesters' => 'admin',
                    'match' => array('library' => 'admin', '*::*')
                ),
                array(
                    'requesters' => '*',
                    'match' => array('library' => 'admin', 'Users::login')
                ),
                array(
                    'requesters' => '*',
                    'match' => array('library' => 'admin', 'Users::logout')
                )
            )
        )
    )

First we tell it which adapter to use:

    'adapter' => 'AuthRbac',

Then we set the roles array. This array is required if you want to use this adapter. The roles are evaluated from top to bottom. So if a role at the bottom contradicts one closer to the top, the bottom will take precedence.

####There are five possible options you can specify for a single role.

*match*

A rule used to match (see: `AuthRbac::parseMatch()`) this role against the request object passed from the `check()` method. You may use a parameters array where you explicitly set the parameter/value pairs, a shorthand syntax very similar to the one you use when generating urls or a combination of the two. Without match being set the role will always deny access.

Examples:

* `'Dashboards::index'` -> `array('controller' => 'Dashboards', 'action' => 'index')`
* `'Dashboards::*'` -> `array('controller' => 'Dashboards', 'action' => '*')` -> `Any action in the Dasboards controller.`
* `array('library' => 'admin', '*::*');` -> `array('library' => 'admin_plugin', 'controller' => '*', 'action' => '*')` -> `Any controller/action combination under the admin library.`

**requester**

A string or an array of auth configuration keys that this rule applies to. The string `*` denotes everyone, even those who are not authenticated. A string of `admin` will apply this to everyone who can be authenticated against the user defined `admin` Auth configuration. An array of configuration keys does the same but you can apply it to multiple Auth configurations in one go.

*Example*:

Assuming we have an Auth configuration like so:

    Auth::config(array(
    	'user' => array(
    		'adapter' => 'Form',
    		'model' => 'User',
    		'fields' => array('email' => 'email', 'password' => 'password'),
    		'scope' => array('active' => true)
    	),
    	'editor' => array(
    		'adapter' => 'Form',
    		'model' => 'Editor',
    		'fields' => array('email' => 'email', 'password' => 'password'),
    		'scope' => array('active' => true, 'group' => 1)
    	),
    	'customer' => array(
    		'adapter' => 'Form',
    		'model' => 'Customer',
    		'fields' => array('email' => 'email', 'password' => 'password'),
    		'scope' => array('active' => true, 'group' => 2)
    	)
    ));

Setting 'requester' => array('user', 'customer') would only apply the rule to anyone that could authenticate as a user or customer. Setting 'requester' => '*' would mean that all of these auth configurations and people that are not authenticated would have this role applied to them.

**allow**

A boolean that if set to false forces a role that would have been granted access to deny access. This way you can apply a rule to everyone and then proceed to exclude requesters manualy.

###Filters

The Access::check() method is filterable. You can apply the filters in the configuration like so:

    Access::config(array(
        'rule_based' => array(
            'adapter' => 'Rules',
            'filters' => array(
                function($self, $params, $chain) {
                    // Filter logic goes here
                    return $chain->next($self, $params, $chain);
                }
            )
        )
    ));

##Credits

###Tom Maiaroto

The original author of this library.

Github: [tmaiaroto](https://github.com/tmaiaroto/li3_access)

Website: [Shift8 Creative](http://www.shift8creative.com)

##Weluse

Wrote the original Rbac adapter.

Github: [dgAlien](https://github.com/dgAlien/li3_access) [weluse](https://github.com/weluse/li3_access)

Website: [Weluse](http://www.weluse.de)

##rich97

Modified the original Rbac adapter, added some tests and wrote this version of the documentation.

Github: [rich97](https://github.com/rich97/li3_access)

Website: [Enrich.it](http://www.enrich.it)
