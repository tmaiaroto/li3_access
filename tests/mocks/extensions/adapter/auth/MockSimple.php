<?php

namespace li3_access\tests\mocks\extensions\adapter\auth;

class MockSimple extends \lithium\core\object {

    public static function check($credentials, $options) {
        return !empty($credentials->data) ? $credentials->data : array();
    }

    public static function set($name, $data) {
        return $data;
    }

}
