<?php

namespace li3_access\tests\mocks\extensions\adapter\auth;

class MockAuthAdapter extends \lithium\core\Object {

	public function check($credentials, array $options = array()) {
        return isset($options['success']) && !empty($credentials->data) ? $credentials->data : false;
	}

	public function set($data, array $options = array()) {
		if (isset($options['fail'])) {
			return false;
		}
		return $data;
	}

	public function clear(array $options = array()) {
	}

}

?>
