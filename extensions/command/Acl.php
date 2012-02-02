<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2011, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_access\extensions\command;

/**
 *
 * @author AgBorkowski
 */
//use lithium\util\String;
use lithium\core\Libraries;
//use lithium\util\Inflector;
use lithium\util\Inflector;
use lithium\analysis\Inspector;
use lithium\core\ClassNotFoundException;
use li3_access\models\Acos;
use li3_access\models\Aros;
use app\models\Users;
use app\models\Queues;

/**
 * The `acl` command allows you to rapidly develop your permission in Aros Acos trees.
 *
 * `li3 acl refresh`
 *
 */
class Acl extends \lithium\console\Command {

	/**
	 * Run the create command. Takes `$command` and delegates to `$command::$method`
	 *
	 * @param string $command
	 * @return boolean
	 */
	public function run($command = null) {
		$this->header('Access Control List Managament');

		$this->out('[refresh] add controllers and actions to atos tree');
		$this->out('[syncQueue] add controllers and actions to atos tree');

		$command = $this->in('put command', array('choices' => array('refresh')));

		if (!$command) {
			return false;
		}

		$this->_execute($command);

		$this->error("{$command} could not be executed.");
		return false;
	}

	/**
	 * Execute the given sub-command for the current request.
	 *
	 * @param string $command The sub-command name. example: Model, Controller, Test
	 * @return boolean
	 */
	protected function _execute($command) {
		try {
			var_dump($this->{$command});
		} catch (ClassNotFoundException $e) {
			$this->error($e);
			return false;
		}
	}

	/**
	 * refresh aco tree
	 */
	protected function refresh() {
		$this->header('Sunchronize ACO tree');
		$controllers = (array) Libraries::locate('controllers');
		$root = Acos::node('controllers');
		//root
		if(isset($root[0]) && $root = $root[0]['id'] > 0){
			foreach($controllers as $controller){
				$methods = (array) Inspector::methods($controller, 'extents');
				$alias = str_replace('Controller', '', end(explode('\\', $controller)));
				//controllers
				$parent = Acos::first(array('conditions' => array('alias' => $alias)));
				if($parent && $parent->exists()){
					$parent = $parent->data('id');
				}else{
					$parent = Acos::create();
					if($parent->save(array('parent_id' => $root, 'alias' => $alias))){
						$this->out("[controller] {$alias} add to ACO succes");
						$parent = $parent->data('id');
					}else{
						$this->out("[controller] {$alias} add to ACO fail");
						$parent = false;
					}
				}
				//methods
				if($parent && $parent > 0){
					$this->out("[method] get in {$alias} and scan methods");
					foreach (array_keys($methods) as $method) {
						$child = Acos::create();
						if($child->save(array('parent_id' => $parent, 'alias' => $method))){
							$this->out("[method] {$method} created in {$alias} success");
							$child = $child->data('id');
						}else{
							$this->out("[method] {$method} created in {$alias} fail");
							$child = false;
						}
					}
				}
			}
		}else{
			//root
			$child = Acos::create();
			if($child->save(array('alias' => 'controllers'))){
				$this->out("[root] created, run command again");
			}else{
				$this->out("[root] creating fail");
				return false;
			}
		}
		return true;
	}

	/**
	 * synchronize users
	 *
	 */
	protected function users() {
		$this->header('Sunchronize ARO tree');
		$defaultsSettings = array(
			'parent_id' => 1, //1. Role.7 => users @todo its hardcode role id
			'model' => Users::meta('name')
		);
		$users = Users::find('all');
		foreach ($users as $user){
			$data = Aros::first(array('conditions' => array('foreign_key' => $user->data('id'))));
			if(!$data || !$data->exists()){
				$this->out("[user] {$user->data('login')} not found, creating ARO");
				$node = Aros::create();
				if($node->save(array(
					'parent_id' => $defaultsSettings['parent_id'],
					'model' => $defaultsSettings['model'],
					'foreign_key' => $user->data('id'),
					'alias' => $user->data('login')
				))){
					$this->out("[node] {$node->data('alias')} add to ARO (fk:{$node->data('foreign_key')}, p:{$node->data('parent_id')}) success");
				}else{
					$this->out("[controller] {$alias} add to ACO fail");
					return false;
				}
			}else{
				$this->out("[user] {$user->data('login')} found in ARO (id:{$user->data('id')}), skipping");
			}
		}
	}

	/**
	 * refresh aco tree
	 */
	protected function syncQueue() {
		$this->header('Sunchronize ACO Queues tree');
		$root = Acos::node('models/Queues');
		if(!$root){
			exit('root not found');
		}
		$defaultsSettings = array(
			'parent_id' => $root[0]['id'], // 244
			'model' => Queues::meta('name') // Queues
		);
		$data = Queues::find('all', array('li3_access' => false));
		foreach ($data as $queue){
			$aco = Acos::node(array(
				'model' => $defaultsSettings['model'],
				'foreign_key' => $queue->data('id')
			));
			if(!$aco){
				$this->out("[aco] [notfound] {$defaultsSettings['model']} > {$queue->data('name')}");
				$node = Acos::create();
				if($node->save(array(
					'parent_id' => $defaultsSettings['parent_id'],
					'model' => $defaultsSettings['model'],
					'foreign_key' => $queue->data('id'),
					'alias' => $queue->data('name')
				))){
					$this->out("[create node] {$node->data('alias')} add to ARO (fk:{$node->data('foreign_key')}, p:{$node->data('parent_id')}) success");
				}else{
					$this->out("[error] {$alias} add to ACO fail");
					return false;
				}
			}else{
				$this->out("[aco] [exist] {$defaultsSettings['model']} > {$queue->data('name')} found in ARO (id:{$queue->data('id')}), skipping");
			}
		}
		//root
	}
}
?>