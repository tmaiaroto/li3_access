<?php
namespace li3_access\extensions\adapter\security\access\acl\db;
//use \lithium\util\String;
//use lithium\security\Password;

class Permissions extends \li3_access\extensions\adapter\security\access\acl\Acl {
	
/**
 * Override default `_meta` options
 *
 * @var array
 * @access protected
 */
	protected $_meta = array('source' => 'aros_acos');

/**
 * Permissions link AROs with ACOs
 *
 * @var array
 * @access public
 */
	public $belongsTo = array('Aros', 'Acos');
}
?>