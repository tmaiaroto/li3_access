<?php
namespace li3_access\extensions\models\behaviors;

//use lithium\core\Libraries;
//use UnexpectedValueException;
//use lithium\security\Password;
//use lithium\core\Libraries;
//use lithium\core\ClassNotFoundException;
//use lithium\data\Connections;

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
			$save = $chain->next($self, $params, $chain);
			$self::afterSave($self, $params, $save);
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
	static public function afterSave($self, $params, $save) {
		extract(static::$_config[$self]);
		$entity = $params['entity'];
		$type = static::$_defaults['typeMaps'][$type]; // Aro, Aco
		$parent = $self::parentNode($entity);
		if (!empty($parent)) {
			$parent = $self::node($self, $parent);
		}
		$data = array(
			'parent_id' => isset($parent[$type]['id']) ? $parent[$type]['id'] : null,
			//'model' => $model->alias,
			'model' => $self::_name(),
			//'foreign_key' => $model->id
			'foreign_key' => $entity->data('id')
		);

		if ($entity->exists()) {
			$node = $self::node($self);
			$data['id'] = isset($node[0][$type]['id']) ? $node[0][$type]['id'] : null;
		}
		//@todo new throw
		$type::create();
		$type::save($data);
	}

	/**
	 * Destroys the ARO/ACO node bound to the deleted record
	 *
	 * @return void
	 * @access public
	 */
	static public function afterDelete($self, $params, $delete){
		extract(static::$_config[$self]);
		$type = static::$_defaults['typeMaps'][$type]; // Aro, Aco
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
	static public function node($model, $ref = null){
		extract(static::$_config[$model]);
		$type = static::$_defaults['typeMaps'][$model]; // Aro, Aco
		if (empty($ref)) {
			$ref = array('model' => $model::_name(), 'foreign_key' => $model::data($model::key()));
		}
		return $type::node($ref);
	}
}
?>