<?php
namespace li3_access\models;
//use lithium\util\String;
//use lithium\security\Password;

class Aros extends \li3_access\extensions\models\Acl {

	protected $_meta = array('source' => 'aros');

/**
 * Binds to AROs nodes through permissions settings
 *
 * @var array
 * @access public
 */
	public $hasAndBelongsToMany = array('Aros' => array('with' => 'Permission'));
}
?>