<?php

namespace li3_access\tests\mocks\extensions\adapter\auth;

class MockSimple extends \lithium\core\object {

    public static function config() {
        return array('it worked');
    }

    public static function check() {
        return array('id' => 1);
    }

    public static function set($data, array $options = array()) {
        return $data;
    }

}
