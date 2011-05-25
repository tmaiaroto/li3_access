#Access control library for the Lithium framework for PHP 5.3.

##Installation

Checkout the code to either of your library directories:

    cd libraries
    git clone git@github.com

Include the library in in your /{*app*|*app/libraries/library_name*}/config/bootstrap/libraries.php

    Libraries::add('li3_access');

##Usage

Usage is simple enough, you must configure the adapter you wish to use first. But once you have it configured it's faily simple.

    $access = Access::check('access_config_name', Auth::check('auth_config_name'), $this->request);
    if(!empty($access)) {
        $this->redirect($access['redirect']);
    }

If the data request validates correctly based on your configuration then check() will return an empty array() otherwise it will return and array with two keys; `message` and `redirect`. These values are built into the Access class but you can override them but passing them as `$options` to the `Simple` and `Rules` adapters and defining them in the config in the case of the `AuthRbac` adapter.

##Configuration

In this repository there are three adapters. And all three work in a slightly different way.

###Simple Adapter

The simple adapter is exactly what it says it is. It's so simple, in fact, that there aren't any tests for it. The simple adapter only checks that the data passed to is not empty and as a result its' configuration is just as easy.

    Access::config(
        'simple' => array('adapter' => 'Simple')
    );

And that's it!

###Rules Adapter

This adapter effectively allows you to tell it how it should work. It containes a set of simple access rules by default but it's very simple to add your own. It's configuration is the same as the `Simple` adapter if you only want to use the built in methods.

    Access::config(
        'rules' => array('adapter' => 'Rules')
    );

Then to deny all requests from this user.

    $access = Access::check('rules', Auth::check('auth_config_name'), $this->request, array('rule' => 'denyAll'));
    if(!empty($access)) {
        $this->redirect($access['redirect']);
    }

There are four built in rules; allowAll, denyAll, allowAnyUser and allowIp. However, this adapter is at it's most useful when you add your own rules.

    Access::adapter('custom_rule')->add(function($user, $request, $options) {
        // Your logic here. Just make sure it returns an array.
    });

Then to use your new rule:

    $access = Access::check('rules', Auth::check('auth_config_name'), $this->request, array('rule' => 'custom_rule'));

One more to go!

###AuthRbac Adapter

This is the most complex adapter in this repository at this time. It's used for finely-tuned Role Based Access Control. It doesn't access a database like some RBAC implementations so you can't manage it through a pretty interface you designed, but it does help to cut down on access control code being dotted all arround your application in some cases.

    Access::config(
        'auth_rbac' => array(
            'adapter' => 'AuthRbac',
            'message' => 'Access denied.',
            'redirect' => 'Dashboards::index',
            'roles' => array(
                array(
                    'allow' => false,
                    'requesters' => '*',
                    'match' => '*::*'
                ),
                array(
                    'message' => 'No you cannot access the admin panel! Who do you think you are?',
                    'redirect' => 'Users::login',
                    'requesters' => 'admin',
                    'match' => array('library' => 'admin_panel', '*::*')
                )
            )
        )
    )

Lets take it apart peice by piece. First we tell it which adapter to use:

    'adapter' => 'AuthRbac',

Then we set the default `message` and `redirect` options. If we don't specify it will fall back to the values set in \security\Access::check().

    'message' => 'Access denied.',
    'redirect' => 'Dashboards::index',

Next is the roles array. This array is required if you want to use this adapter. The roles are evaluated from top to bottom. So if a role at the bottom contradicts one closer to the top, the bottom will take precedence.

There are five possible options you can specify for a single role.

**`match`**

A rule used to "match" this role against the request passed to the Access::check() method. You may use a parameters array where you explicitly set the parameter/value pairs or you may use a shorthand syntax very similar to the one you may use when generating urls. Without match being set the role will always deny access.

*Examples*:

* 'Dashboards::index' -> array('controller' => 'Dashboards', 'action' => 'index')
* 'Dashboards::*' -> array('controller' => 'Dashboards', 'action' => '*') -> Any action in the Dasboards controller.
* array('library' => 'admin_plugin', '*::*'); -> array('library' => 'admin_plugin', 'controller' => '*', 'action' => '*') -> Any controller/action combination under the admin_panel library.

**`requester`**

A string or an array of auth configuration keys that this rule applies to. A '*' denotes everyone, even those who are not logged in. A string of 'admin' will apply this to everyone who can be authenticated against the user defined 'admin' Auth configuration. An as the string array is the same but, you can apply it to multiple Auth configurations.

`Example`:

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

Setting 'requester' => array('user', 'customer') would only apply the rule to anyone that could authenticat as a user or customer. Setting 'requester' => '*' would mean that all of these users and people that are not authenticated would have this role applied to them.

**`message`**

Sets the message that will be used **if the `match` paramter is relevant to the request** and **if the user is denied access as a result**. Just like the roles themselves these override each other from top to bottom.

**`redirect`**

Same behavior as message.

**`allow`**

Forces a role that would have been granted access to deny access. This way you can apply a rule to everyone and then proceed to exclude requesters manualy.

###Filters

The Access::check() method is filterable and can be confiured like so:

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

##Example configurations

##TODO

* -Ensure AuthRbac adapter takes $options['message'] and $options['redirect'] into account.-
* Don't allow the default message and redirect to be set in the configuration. Set it in the $options array instead to make AuthRbac adapter fall inline API.
* See if there is a way to not have to include the User parameter in AuthRbac.
* Fix the regex match condition, right now this is acceptable as a controller/action parameter T*s*::*
* Make 'requester' default to '*' so that it's not a required parameter.
* Add tests for deny ing a request manutally as described in this guide.
* Move these todo items over to github project issues.
* Give credit to those who have contributed
* Fix docblocks where appropriate

##Credit where credit's due.
