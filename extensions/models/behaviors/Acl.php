<?php
namespace li3_access\extensions\models\behaviors;

//use li3_access\models\Acos;
//use li3_access\models\Aros;
use lithium\core\Libraries;

class Acl extends \lithium\data\Model {
	
	/**
	 * default tree configuration
	 * @var Array
	 */
	protected static $_defaults = array(
		'typeMaps' => array(
			'requester' => 'Aros', 'controlled' => 'Acos'
		),
//		'fields' => array(
//			'parnet_id' => 'parent_id',
//			'foreign_key' => 'foreign_key',
//			'model' => 'model'
//		)
	);

	/**
	 * tree config
	 * @var Array holding Arrays of Configuration Arrays
	 */
	protected static $_config = array();
	/**
	 * applyBehavior
	 *
	 * Applies Behaviour to the Model and configures its use
	 *
	 * @param \lithium\data\Model $self The Model using this behaviour
	 */
	public static function applyBehavior($self, $config = array()) {
		static::$_config[$self] = array_merge(static::$_defaults, $config);
		//static::$_config[$self]['type'] = strtolower(static::$_config[$self]['type']);

		//$type = static::$_defaults['typeMaps'][static::$_config[$self]['type']];

		static::applyFilter('save', function($self, $params, $chain) {
			$exist = $params['entity']->exists();
			$save = $chain->next($self, $params, $chain);
			$self::afterSave($self, $params, $save, $exist);
			return $save;
		});

		static::applyFilter('delete', function($self, $params, $chain) {
			$delete = $chain->next($self, $params, $chain);
			$self::afterDelete($self, $params, $delete);
			return $delete;
		});
	}

	/**
	 * Creates a new ARO/ACO node bound to this record
	 *
	 * @return void
	 * @access public
	 */
	static public function afterSave($self, $params, $save, $exist) {
		extract(static::$_config[$self]);
		$entity = $params['entity'];
		$type = static::$_defaults['typeMaps'][$type]; // Aro, Aco
		$parent = $self::parentNode($entity);
		if (!empty($parent)) {
			$parent = $self::node($self, $parent);
		}
		$data = array(
			'parent_id' => isset($parent[0]['id']) ? $parent[0]['id'] : null,
			//'model' => $model->alias,
			'model' => $self::_name(),
			//'foreign_key' => $model->id
			'foreign_key' => $entity->data('id')
		);

		if ($exist) {
			$node = $self::node($self, array('model' => $self::_name(), 'foreign_key' => $entity->data('id')));
			$data['id'] = isset($node[0][$type]['id']) ? $node[0][$type]['id'] : null;
		}
		/*
		 * Warning: AclNode::node() - Couldn't find Aros node identified by "Array ( [Aros0.model] => Users [Aros0.foreign_key] => 168 ) " in /Users/nim/Sites/holicon/pwi2/libraries/li3_access/extensions/models/Acl.php on line 166
		 * Fatal error: Class 'Aros' not found in /Users/nim/Sites/holicon/pwi2/libraries/li3_access/extensions/models/behaviors/Acl.php on line 83
		 */
		$model = Libraries::locate('models', $type, array('libraries' => 'li3_access'));
		if (empty($model)) {
			throw new ClassNotFoundException(sprintf("Model class '%s' not found in access\acl\Acl::node() when trying to bind %s object", $model, $type));
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
	static public function afterDelete($self, $params, $delete) {
		extract ( static::$_config [$self] );
		$type = static::$_defaults ['typeMaps'] [$type]; // Aro, Aco
		//@todo extract it
		$node = Set::extract($self::node($model), "0.{$type}.id");

		if (!empty($node)) {
			$type::delete($node);
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
		extract ( static::$_config [$self] );
		$type = static::$_defaults['typeMaps'][$type]; // Aro, Aco
		if (empty($ref)) {
			throw new \Excepction('Not found config for `ref` param');
			//@fixme get data from self, its inposible i think
			$ref = array('model' => $self::_name(), 'foreign_key' => $self::data($self::key()));
		}
		$model = Libraries::locate('models', $type, array('libraries' => 'li3_access'));
		if (empty($model)) {
			throw new ClassNotFoundException(sprintf("Model class '%s' not found in access\acl\Acl::node() when trying to bind %s object", $model, $type));
			return null;
		}
		return $model::node($ref);
		//Fatal error: Class 'Aros' not found in /Users/nim/Sites/holicon/pwi2/libraries/li3_access/extensions/models/behaviors/Acl.php on line 124		
		
	}
}
?>