<?php
/**
 * 
 * @author Ikezaki
 *
 */
class MoccosFinder {
	
	/**
	 * 
	 * @var unknown_type
	 */
	public $self;
	
	/**
	 * 
	 * @var unknown_type
	 */
	public $limit;
	
	/**
	 * 
	 * @var unknown_type
	 */
	public $offset;
	
	/**
	 * 
	 * @var unknown_type
	 */
	public $order;
	
	/**
	 * 
	 * @var unknown_type
	 */
	public $desc;
	
	/**
	 * 
	 * @var unknown_type
	 */
	public $filter;
	
	/**
	 * 
	 * @var unknown_type
	 */
	public $includes = array();
	
	/**
	 * 
	 * @var unknown_type
	 */
	public $excludes = array();
	
	/**
	 * 
	 * @var unknown_type
	 */
	public $through;
	
	/**
	 * 
	 * @var unknown_type
	 */
	public $group;
	
	/**
	 * 
	 * @var unknown_type
	 */
	public $eagerLoad = true;
	
	/**
	 * 
	 * @param unknown_type $options
	 */
	public function __construct($options = array()) {
		foreach ($options as $key => $value)
			$this->{$key} = $value;
		if ($this->filter === null)
			$this->filter = new MoccosFilter();
	}
	
	/**
	 * 
	 */
	public function getStructure() {
		$reflection = MoccosReflectionClass::getInstance($this->self);
		$structure = array(
			snake_case($this->self) => array(
				'class' => $this->self,
				'type' => 0,
				'primaryKey' => $reflection->primaryKey,
				'includes' => $this->_getStructure($this->includes, $this->self),
			)
		);
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('structure = %s.', MoccosLog::dumper($structure)));
		return $structure;
	}
	
	/**
	 * 
	 * @param array $structure
	 * @param unknown_type $parentClassName
	 */
	private function _getStructure(array $structure, $parentClassName) {
		$arrays = array();
		$parentReflection = MoccosReflectionClass::getInstance($parentClassName);
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('reflection = %s.', MoccosLog::dumper($parentReflection)));
		foreach ($structure as $alias => $childs) {
			$reflectionProperty = $parentReflection->getMoccosProperty($alias);
			$remoteReflection = MoccosReflectionClass::getInstance($reflectionProperty->remoteClassName);
			if ($reflectionProperty) {
				$arrays[$alias] = array(
					'class' => $reflectionProperty->remoteClassName,
					'type' => $reflectionProperty->type,
					'primaryKey' => $remoteReflection->primaryKey,
					'remote_id' => $reflectionProperty->remote_id,
					'local_id' => $reflectionProperty->local_id,
					'includes' => $this->_getStructure($childs, $reflectionProperty->remoteClassName),
				);
			}
		}
		return $arrays;
	}
}