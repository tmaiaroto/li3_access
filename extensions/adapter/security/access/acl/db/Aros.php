<?php
namespace li3_access\extensions\adapter\security\access\acl\db;
//use \lithium\util\String;
//use lithium\security\Password;

class Aros extends \li3_access\extensions\adapter\security\access\acl\Acl {

	protected $_meta = array('source' => 'aros');

/**
 * Binds to AROs nodes through permissions settings
 *
 * @var array
 * @access public
 */
	public $hasAndBelongsToMany = array('Aro' => array('with' => 'Permission'));
}
?>