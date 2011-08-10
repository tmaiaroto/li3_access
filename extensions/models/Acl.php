<?php
namespace li3_access\extensions\models;

//use lithium\core\Libraries;
//use UnexpectedValueException;
//use lithium\security\Password;
use lithium\core\Libraries;
use lithium\core\ClassNotFoundException;
use lithium\data\Connections;

class Acl extends \li3_tree\extensions\Model {

/**
 * Retrieves the Aro/Aco node for this model
 *
 * @param mixed $ref Array with 'model' and 'foreign_key', model object, or string value
 * @return array Node found in database
 * @access public
 */
	public static function node($ref = null) {
		$type =  self::_name(); //Aros
		$db = self::connection(); //db postgres connection
		$meta = self::meta(); //meta (array)
		$key = self::key(); // id

		$result = null;

		if (!empty($meta['source'])) {
			$table = $meta['source']; // aros
		} else {
			//$table = Inflector::pluralize(Inflector::underscore($type));
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
				'fields' => array('id', 'parent_id', 'model', 'foreign_key', 'alias'),
				'joins' => array(array(
					'source' => $table,
					'alias' => "{$type}0",
					'type' => 'LEFT',
					'constraint' => array("{$type}0.alias" => $start)
				)),
				'order' => "{$type}.lft" . ' DESC'
			));

			foreach ($path as $i => $alias) {
				$j = $i - 1;

				$queryData['joins'][] = array(
					//'table' => $db->fullTableName($this),
					'source' => $table,
					'alias' => "{$type}{$i}",
					'type'  => 'LEFT',
					'constraint' => array(
						$db->name("{$type}{$i}.lft") . ' > ' . $db->name("{$type}{$j}.lft"),
						$db->name("{$type}{$i}.rght") . ' < ' . $db->name("{$type}{$j}.rght"),
						$db->name("{$type}{$i}.alias") . ' = ' . $db->value($alias, array('type'=>'string')),
						$db->name("{$type}{$j}.id") . ' = ' . $db->name("{$type}{$i}.parent_id")
					)
				);

				$queryData['conditions'] = array('or' => array(
					$db->name("{$type}.lft") . ' <= ' . $db->name("{$type}0.lft") . ' AND ' . $db->name("{$type}.rght") . ' >= ' . $db->name("{$type}0.rght"),
					$db->name("{$type}.lft") . ' <= ' . $db->name("{$type}{$i}.lft") . ' AND ' . $db->name("{$type}.rght") . ' >= ' . $db->name("{$type}{$i}.rght"))
				);
			}
			$result = self::find('first', $queryData);
			$path = array_values($path);

			if (
				!isset($result[0][$type]) ||
				(!empty($path) && $result[0][$type]['alias'] != $path[count($path) - 1]) ||
				(empty($path) && $result[0][$type]['alias'] != $start)
			) {
				return false;
			}
		} elseif (is_object($ref) && is_a($ref, 'lithium\data\Model')) {
			$ref = array('model' => $ref::_name(), 'foreign_key' => $ref::key());
		} elseif (is_object($ref) && is_a($ref, 'lithium\action\Request')) {
			$obj = $ref;
			return $this->node($obj);
		} elseif (is_array($ref) && !(isset($ref['model']) && isset($ref['foreign_key']))) {
			//associate row
			$name = key($ref);

			$model = Libraries::locate('models', $name);
			if (empty($model)) {
				throw new ClassNotFoundException(sprintf("Model class '%s' not found in access\acl\Acl::node() when trying to bind %s object", $model, $type));
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
					return $this->node($tmpRef);
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
					'constraint' => array(
						"{$type}.lft" => array(' <= ' => "{$type}0.lft"),
						"{$type}.rght" => array(' <= ' => "{$type}0.rght")
					)
				)),
				'order' => $db->name("{$type}.lft") . ' DESC'
			);

			$result = self::read($queryData);

			if (!$result) {
				throw new \Exception(sprintf(__("AclNode::node() - Couldn't find %s node identified by \"%s\"", true), $type, print_r($ref, true)));
			}
		}
		return $result;
	}

	/**
	 * Creates a new ARO/ACO node bound to this record
	 *
	 * @param boolean $created True if this is a new record
	 * @return void
	 * @access public
	 */
	public static function afterSave(&$model, $created) {
		$type = self::_name();
		$model = Libraries::locate('models', $name);
		$parent = $model::parentNode();
		if (!empty($parent)) {
			$parent = $this->node($model, $parent);
		}
		$data = array(
			'parent_id' => isset($parent[0][$type]['id']) ? $parent[0][$type]['id'] : null,
			'model' => $model->alias,
			'foreign_key' => $model->id
		);
		if (!$created) {
			$node = $this->node($model);
			$data['id'] = isset($node[0][$type]['id']) ? $node[0][$type]['id'] : null;
		}
		$model->{$type}->create();
		$model->{$type}->save($data);
	}

	/**
	 * Destroys the ARO/ACO node bound to the deleted record
	 *
	 * @return void
	 * @access public
	 */
	public static function afterDelete(&$model) {
		$type = $this->__typeMaps[$this->settings[$model->name]['type']];
		$node = Set::extract($this->node($model), "0.{$type}.id");
		if (!empty($node)) {
			$model->{$type}->delete($node);
		}
	}

}
?>