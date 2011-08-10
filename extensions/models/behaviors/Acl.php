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
		)
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
	public static function applyBehavior($self, $config = array('li3_access' => array())) {
		//parent::applyBehavior($self, $config);

		static::$_config[$self] = array_merge(static::$_defaults, $config['li3_access']);

		/**
		 * Creates a new ARO/ACO node bound to this record
		 *
		 * @param boolean $created True if this is a new record
		 * @return void
		 * @access public
		 */
		static::applyFilter('save', function($self, $params, $chain) {
			//beforeSave
			extract(static::$_config[$self]);
			$entity = $params['entity'];
			//$this->[$model->name]['type']
			exit(var_dump($self));
			$type = $this->_config['type'];

			if (!$entity->data('id')) {
				//new
			}else{
				//overwrite
			}

			$save = $chain->next($self, $params, $chain);

			//afterSave
			$parent = $model->parentNode();
			if (!empty($parent)) {
			$parent = $this->node($model, $parent);
			}
			$data = array(
			'parent_id' => isset($parent[0][$type]['id']) ? $parent[0][$type]['id'] : null,
			 'model' => $model->alias,
			 'foreign_key' => $model->id
			);
			//if (!$created) {
			if(!$entity->data('id')){
				$node = $this->node($model);
				$data['id'] = isset($node[0][$type]['id']) ? $node[0][$type]['id'] : null;
			}
			$model->{$type}->create();
			$model->{$type}->save($data);

			//afterSave
			return $save;
		});

		/**
		 * Destroys the ARO/ACO node bound to the deleted record
		 *
		 * @return void
		 * @access public
		 */
		static::applyFilter('delete', function($self, $params, $chain) {
			$type = $this->__typeMaps[$this->settings[$model->name]['type']];
			$node = Set::extract($this->node($model), "0.{$type}.id");

			$delete = $chain->next($self, $params, $chain);

			if (!empty($node)) {
				$model->{$type}->delete($node);
			}
			return $delete;
		});
	}

	/**
 * Retrieves the Aro/Aco node for this model
 *
 * @param mixed $ref
 * @return array
 * @access private
 * @link http://book.cakephp.org/view/1322/node
 */
	private static function _node($self,$ref = null){

	}
}
?>