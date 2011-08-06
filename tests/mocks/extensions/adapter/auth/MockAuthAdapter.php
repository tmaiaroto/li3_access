<?php

namespace li3_access\tests\mocks\extensions\adapter\auth;

class MockAuthAdapter extends \lithium\core\Object {

	public function check($credentials) {
		return !empty($credentials->data) ? $credentials->data : false;
	}

	public function set($data) {
		return $data;
	}

	public function clear() {}

}

?>