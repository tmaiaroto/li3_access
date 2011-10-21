<?php
namespace li3_access\models;
//use \lithium\util\String;
//use lithium\security\Password;

class Acos extends \li3_access\extensions\data\model\Acl {

	protected $_meta = array('source' => 'acos');

/**
 * Binds to ACOs nodes through permissions settings
 * @fixme not suportet in li3
 * @var array
 * @access public
 */
	//public $hasAndBelongsToMany = array('Acos' => array('with' => 'Permission'));
}
?>