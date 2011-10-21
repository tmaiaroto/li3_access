<?php

/**
 * Acl superclass allow use methods `node` in Aros and Acos tree.
 *
 * This class use of external libraries (lastest version from my github)
 * li3_tree behavior https://github.com/agborkowski/li3_tree
 * li3_behavior https://github.com/agborkowski/li3_behavior
 * @todo rename class to Node
 */
namespace li3_access\extensions\data\model;

use lithium\core\Libraries;
use lithium\core\ClassNotFoundException;
use lithium\core\ConfigException;
use lithium\data\Connections;

class Acl extends \li3_behaviors\extensions\Model {

	/**
	 * ACL models use the Tree behavior
	 *
	 * @var array
	 * @access protected
	 */
	protected $_actsAs = array('Tree');

	/**
	 * Retrieves the Aro/Aco node for this model
	 *
	 * @param mixed $ref Array with 'model' and 'foreign_key', model object, or string value
	 * @return array Node found in database
	 * @access public
	 */
	public static function node($ref = null) {
		$db = self::connection(); //db postgres connection
		$type =  self::meta('name'); //Aros
		$result = null;

		$table = self::meta('source');
		if (empty($table)) {
			//$table = Inflector::pluralize(Inflector::underscore($type));
			throw new ConfigException('Set `source` name in model');
		}

		if (empty($ref)) {
			return null;
		} elseif (is_string($ref)) {
			$path = explode('/', $ref);
			$start = $path[0];
			unset($path[0]);

			$queryData = array(
				'conditions' => array(
					"{$type}.lft" . ' <= ' . "{$type}0.lft",
					"{$type}.rght" . ' >= ' . "{$type}0.rght",
				),
				'fields' => array('id', 'parent_id', 'model', 'foreign_key', 'alias'),
				'joins' => array(array(
					'source' => $table,
					'alias' => "{$type}0",
					'type' => 'LEFT',
					'constraint' => array("{$type}0.alias" =>$db->value($start, array('type' => 'string')))
				)),
				'order' => "{$type}.lft" . ' DESC'
			);

			foreach ($path as $i => $alias) {
				$j = $i - 1;

				$constraint = "({$type}{$i}.lft > {$type}{$j}.lft AND ";
				$constraint .= "{$type}{$i}.rght < {$type}{$j}.rght AND ";
				$constraint .= "{$type}{$i}.alias = ".$db->value($alias, array('type' => 'string'))." AND ";
				$constraint .= "{$type}{$j}.id = {$type}{$i}.parent_id)";

				$queryData['joins'][] = array(
					//'table' => $db->fullTableName($this),
					'source' => $table,
					'alias' => "{$type}{$i}",
					'type'  => 'LEFT',
//					'constraint' => array(
//						$db->name("{$type}{$i}.lft") . ' > ' . $db->name("{$type}{$j}.lft"),
//						$db->name("{$type}{$i}.rght") . ' < ' . $db->name("{$type}{$j}.rght"),
//						$db->name("{$type}{$i}.alias") . ' = ' . $db->value($alias, array('type'=>'string')),
//						$db->name("{$type}{$j}.id") . ' = ' . $db->name("{$type}{$i}.parent_id")
//					)
					'constraint' => $constraint
				);

				$queryData['conditions'] = array('or' => array(
					'(' . $db->name("{$type}.lft") . ' <= ' . $db->name("{$type}0.lft") . ' AND ' . $db->name("{$type}.rght") . ' >= ' . $db->name("{$type}0.rght") . ')',
					'(' . $db->name("{$type}.lft") . ' <= ' . $db->name("{$type}{$i}.lft") . ' AND ' . $db->name("{$type}.rght") . ' >= ' . $db->name("{$type}{$i}.rght") . ')'
				));
			}
			$result = self::find('all', $queryData + array('return' => 'array'));
			$path = array_values($path);

			if (
				!isset($result[0]) ||
				(!empty($path) && $result[0]['alias'] != $path[count($path) - 1]) ||
				(empty($path) && $result[0]['alias'] != $start)
			) {
				return false;
			}
		} elseif (is_object($ref) && is_a($ref, 'lithium\data\Model')) {
			$ref = array('model' => $ref::meta('name'), 'foreign_key' => $ref[$ref::meta('name')][$model::key()]);
		} elseif (is_object($ref) && is_a($ref, 'lithium\action\Request')) {
			$obj = $ref;
			//return $this::node($obj);
			return self::node($obj);
		} elseif (is_array($ref) && !(isset($ref['model']) && isset($ref['foreign_key']))) {
			//associate row
			$name = key($ref);
			$model = Libraries::locate('models', $name);
			if (empty($model)) {
				throw new ClassNotFoundException(sprintf("Model class '%s' not found in access\acl\Acl::node() when trying to bind %s object", $name, $type));
				return null;
			}

			//$model	null
			$tmpRef = null;
			if (method_exists($model, 'bindNode')) {
				// @link http://book.cakephp.org/view/1547/Acts-As-a-Requester#x11-2-4-1-Group-only-ACL-1646
				$tmpRef = $model::bindNode($ref);
			}
			if (empty($tmpRef)) {
				//$ref = array('model' => $name, 'foreign_key' => $ref[$name][$model->primaryKey]);
				$ref = array('model' => $name, 'foreign_key' => $ref[$name][$model::key()]);
			} else {
				if (is_string($tmpRef)) {
					return self::node($tmpRef);
				}
				$ref = $tmpRef;
			}
		}
		if (is_array($ref)) {
			if (is_array(current($ref)) && is_string(key($ref))) {
				$name = key($ref);
				$ref = current($ref);
			}
			foreach ($ref as $key => $val) {
				if (strpos($key, $type) !== 0 && strpos($key, '.') === false) {
					unset($ref[$key]);
					$ref["{$type}0.{$key}"] = $val;
				}
			}
			$queryData = array(
				'conditions' => $ref,
				'fields' => array('id', 'parent_id', 'model', 'foreign_key', 'alias'),
				'joins' => array(array(
					'source' => $table,
					'alias' => "{$type}0",
					'type' => 'LEFT',
//					'constraint' => array(
//						'and' => array(
//							"{$type}.lft" => array('<=' => "{$type}0.lft"),
//							"{$type}.rght" => array('>=' => "{$type}0.rght")
//						)
//					)
					'constraint' => "({$type}.lft <= {$type}0.lft AND {$type}.rght >= {$type}0.rght)"
				)),
				'order' => $db->name("{$type}.lft") . ' DESC'
			);

			$result = self::find('all', $queryData + array('return' => 'array'));
			if (!$result) {
				// should be trigger becouse throw stops behavior...
				trigger_error(sprintf("AclNode::node() - Couldn't find %s node identified by \"%s\"", $type, print_r($ref, true)), E_USER_WARNING);
			}
		}
		return $result;
	}
}
?>