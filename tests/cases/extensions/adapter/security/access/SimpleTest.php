<?php
/**
 * li3_access plugin for Lithium: the most rad php framework.
 *
 * @author        Tom Maiaroto
 * @copyright     Copyright 2010, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
*/

namespace li3_access\tests\cases\extensions\adapter\security\access;

use \li3_access\security\Access;
use \lithium\net\http\Request;

class SimpleTest extends \lithium\test\Unit {

    public function setUp() {
        Access::config(array(
            'test_access' => array(
                'adapter' => 'Simple'
            )
        ));
    }

    public function tearDown() {}

    public function testCheck() {
        $request = new Request();

        $expected = array();
        $result = Access::check('test_access', array('username' => 'Tom'), $request);
        $this->assertEqual($expected, $result);

        $expected = array('message' => 'Access denied.', 'redirect' => '/login');
        $result = Access::check('test_access', false, $request, array('redirect' => '/login', 'message' => 'Access denied.'));
        $this->assertEqual($expected, $result);
    }

}

?>
