<?php
namespace li3_access\extensions\data\behavior;

// @fixme dont work when i try call models in `node` method
//use li3_access\models\Acos;
//use li3_access\models\Aros;
use lithium\core\Libraries;
use lithium\util\Set;

class Acl extends \lithium\core\StaticObject {

	/**
	 * An array of configurations indexed by model class name, for each model to which this class
	 * is bound.
	 *
	 * @var array
	 */
	protected static $_configurations = array();

	/**
	 * Beahvior init setup
	 *
	 * @param object $class
	 * @param array	$config
	 */
	public static function bind($class, array $config = array()) {

		$defaults = array(
			'typeMaps' => array(
				'requester' => 'Aros', 'controlled' => 'Acos'
			),
			'type' => false
		);
		$config += $defaults;
		//@todo throw exception when `type` false

		$config['type'] = $defaults['typeMaps'][$config['type']]; // Aros, Acos

		$class::applyFilter('save', function($self, $params, $chain) use ($class) {
			$exist = $params['entity']->exists();
			$save = $chain->next($self, $params, $chain);
			Acl::invokeMethod('_afterSave', array($class, $params, $save, $exist));
			return $save;
		});

		$class::applyFilter('delete', function($self, $params, $chain) use ($class) {
			$delete = $chain->next($self, $params, $chain);
			Acl::invokeMethod('_afterDelete', array($class, $params, $delete));
			return $delete;
		});

		return static::$_configurations[$class] = $config;
	}

	/**
	 * Creates a new ARO/ACO node bound to this record
	 *
	 * @return void
	 * @access public
	 */
	protected static function _afterSave($self, $params, $save, $exist) {
		extract(static::$_configurations[$self]);
		$entity = $params['entity'];
		$parent = $self::parentNode($entity);
		if (!empty($parent)) {
			$parent = self::node($self, $parent);
		}
		$data = array(
			'parent_id' => isset($parent[0]['id']) ? $parent[0]['id'] : null,
			'model' => $self::meta('name'),
			'foreign_key' => $entity->data('id')
		);

		if ($exist) {
			return true;
			$node = self::node($self, array('model' => $self::meta('name'), 'foreign_key' => $entity->data('id')));
			$data['id'] = isset($node[0]['id']) ? $node[0]['id'] : null;
		}
		/*
		 * Warning: AclNode::node() - Couldn't find Aros node identified by "Array ( [Aros0.model] => Users [Aros0.foreign_key] => 168 ) " in /Users/nim/Sites/holicon/pwi2/libraries/li3_access/extensions/models/Acl.php on line 166
		 * Fatal error: Class 'Aros' not found in /Users/nim/Sites/holicon/pwi2/libraries/li3_access/extensions/models/behaviors/Acl.php on line 83
		 */
		$model = Libraries::locate('models', $type, array('libraries' => 'li3_access'));
		if (empty($model)) {
			throw new ClassNotFoundException(
				sprintf("Model class '%s' not found in access\acl\Acl::node() when trying to bind %s object", $model, $type));
			return null;
		}
		$model = $model::create();
		$model->save($data);
	}

	/**
	 * Destroys the ARO/ACO node bound to the deleted record
	 *
	 * @return void
	 * @access public
	 */
	protected static function _afterDelete($self, $params, $delete) {
		extract(static::$_configurations[$self]);
		$entity = $params['entity'];
		$node = self::node($self, array('model' => $self::meta('name'), 'foreign_key' => $entity->data('id')));
		if (!empty($node)) {
			$model = Libraries::locate('models', $type, array('libraries' => 'li3_access'));
			if (empty($model)) {
				throw new ClassNotFoundException(sprintf("Model class '%s' not found in access\acl\Acl::node() when trying to bind %s object", $model, $type));
				return null;
			}
			$model::find($node[0]['id'])->delete();
		}
	}

	/**
	 * Retrieves the Aro/Aco node for this model
	 *
	 * @param mixed $ref
	 * @return array
	 * @access public
	 * @link http://book.cakephp.org/view/1322/node
	 */
	static public function node($self, $ref = null){
		extract(static::$_configurations[$self]);
		if (empty($ref)) {
			throw new \Excepction('Not found config for `ref` param');
			//@fixme get data from self, its inposible i think
			$ref = array('model' => $self::meta('name'), 'foreign_key' => $self::data($self::key()));
		}
		$model = Libraries::locate('models', $type, array('libraries' => 'li3_access'));
		if (empty($model)) {
			throw new ClassNotFoundException(sprintf("Model class '%s' not found in access\acl\Acl::node() when trying to bind %s object", $model, $type));
			return null;
		}
		return $model::node($ref);
		//i haved defined `use` statment at head
		//Fatal error: Class 'Aros' not found in /Users/nim/Sites/holicon/pwi2/libraries/li3_access/extensions/models/behaviors/Acl.php on line 124

	}
}
?>