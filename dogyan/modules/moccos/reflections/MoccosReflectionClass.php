<?php
/**
 * 
 * @author Ikezaki
 *
 */
class MoccosReflectionClass extends ReflectionClass {
	
	/**
	 * 
	 * @var unknown_type
	 */
	private static $_instances = array();
	
	/**
	 * 
	 * @var unknown_type
	 */
	public $className;
	
	/**
	 * 
	 * @var unknown_type
	 */
	public $primaryKey;
	
	/**
	 * 
	 * @param $argument
	 */
	public function __construct($argument) {
		parent::__construct($argument);
		$className = $this->name;
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('%s reflection constructor called.', $className));
		$this->className = get_user_prop($className, '_className');
		if ($this->className === null) $this->className = $className;
		$this->primaryKey = get_user_prop($className, '_primaryKey');
		if ($this->primaryKey === null) $this->primaryKey = 'id';
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('%s reflection dump = %s', $className, MoccosLog::dumper($this)));
	}
	
	/**
	 * 
	 * @param unknown_type $argument
	 */
	public static function getInstance($argument) {
		if (is_object($argument))
			$className = get_class($argument);
		else
			$className = $argument;
		if (! isset(self::$_instances[$className]))
			self::$_instances[$className] = new self($className);
		return self::$_instances[$className];
	}
	
	/**
	 * 
	 */
	public function getMoccosProperties() {
		$moccosReflectionProperties = array();
		foreach (get_class_vars($this->name) as $property => $define) {
			if (is_array($define) && isset($define['type'])) {
			//if (isset($define['type'])) {
				//$moccosReflectionProperties[] = new MoccosReflectionProperty($this->name, $property);
				$moccosReflectionProperties[] = MoccosReflectionProperty::getInstance($this->name, $property);
			}
		}
		return $moccosReflectionProperties;
	}
	
	/**
	 * 
	 * @param $name
	 */
	public function getMoccosProperty($name) {
		$reflectionProperty = MoccosReflectionProperty::getInstance($this->name, $name);
		//$reflectionProperty = new MoccosReflectionProperty($this->name, $name);
		return $reflectionProperty;
	}
	
	/**
	 * 
	 */
	public function getSimpleMoccosProperties() {
		$simpleMoccosReflectionProperties = array();
		foreach ($this->getMoccosProperties() as $moccosReflectionProperty) {
			if ($moccosReflectionProperty->name !== $this->primaryKey) {
				if (! $moccosReflectionProperty->isRelationProperty())
					$simpleMoccosReflectionProperties[] = $moccosReflectionProperty->name;
			}
		}
		return $simpleMoccosReflectionProperties;
	}
}