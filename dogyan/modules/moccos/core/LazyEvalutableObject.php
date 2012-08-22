<?php
/**
 * LazyEvalutableObject
 *
 * <code>
 * class Test extends LazyEvalutableObject {
 * 	public $omase = 'san';
 * 	public $tokyo = 'kyoto';
 * 	public $one_time;
 * 	public function __construct() {
 * 		$this->addLazyEvalutionObserver('omase', array($this, 'omase'));
 * 		$this->addLazyEvalutionObserver('tokyo', array($this, 'tokyo'));
 * 		$this->addLazyEvalutionObserver('one_time', array($this, 'one_time'));
 * 	}
 * 	public function omase($property) {
 * 		echo sprintf("hello! property is %s\n", $property);
 * 		return LazyEvalutableObject::DEFAULT_VALUE;
 * 	}
 * 	public function tokyo($property) {
 * 		echo sprintf("hello! property is %s\n", $property);
 * 		return 'osaka';
 * 	}
 * 	public function one_time($property) {
 * 		echo sprintf("hello! property is %s\n", $property);
 * 		$this->one_time = rand(0, 10);
 * 		return $this->one_time;
 * 	}
 * }
 *
 * $test = new Test();
 * echo sprintf("\$test->omase = %s\n", $test->omase);
 * // hello! property is omase
 * // $test->omase = san
 *
 * echo sprintf("\$test->tokyo = %s\n", $test->tokyo);
 * // hello! property is tokyo
 * // $test->tokyo = osaka
 *
 * echo sprintf("\$test->one_time = %s\n", $test->one_time);
 * echo sprintf("\$test->one_time = %s\n", $test->one_time);
 * // hello! property is one_time
 * // $test->one_time = 1
 * // $test->one_time = 1
 * </code>
 *
 * @author Ikezaki
 *
 */
abstract class LazyEvalutableObject {
	/**
	 *
	 * @var string
	 */
	const DEFAULT_VALUE = '___LazyEvalutableObject::DEFAULT_VALUE___';

	/**
	 *
	 * @access private
	 * @var array
	 */
	private static $_events = array();

	/**
	 *
	 * @access private
	 * @var array
	 */
	private static $_defaults = array();
	
	private static $_retains = array();

	/**
	 * unique hash (for  >PHP5.2)
	 * 
	 * @var string
	 */
	private $_hash;
	
	public static function clean() {
		self::$_events = array();
		self::$_defaults = array();
	} 
	
	public function __construct() {
		$this->_hash = self::_getRandomString(15);
	}
	public function __clean() {
		//vd($this->_hash);
		unset(self::$_events[$this->_hash]);
		unset(self::$_defaults[$this->_hash]);
	}
	
	/**
	 *
	 * @param string $property
	 * @param callback $handler
	 * @return void
	 */
	public function addLazyEvalutionObserver($property, $handler) {
		$className = get_class($this);
		$hash = $this->_hash;
		if (property_exists($this, $property)) {
			if (is_callable($handler)) {
				self::$_defaults[$hash][$property] = $this->{$property};
				unset($this->{$property});
				self::$_events[$hash][$property] = $handler;
			} else {
				if (is_array($handler))
					$handler = sprintf('%s::%s', get_class($handler[0]), $handler[1]);
				trigger_error(sprintf('Observer (function) does not exist: %s()', $handler), E_USER_WARNING);
			}
		} else
			trigger_error(sprintf('Can\'t add observer at property: %s::$%s', $className, $property), E_USER_WARNING);
	}
	
	public function removeLazyEvalutionObserver($property) {
		unset(self::$_events[$this->_hash][$property]);
		unset(self::$_defaults[$this->_hash][$property]);
	}

	/**
	 *
	 * @param string $property
	 * @return mixed
	 */
	public function __get($property) {
		$className = get_class($this);
		$hash = $this->_hash;
		//vd($hash);
		if (isset(self::$_defaults[$hash]) && array_key_exists($property, self::$_defaults[$hash])) {
			$returnValue = null;
			if (isset(self::$_events[$hash][$property])) {
				$handler = self::$_events[$hash][$property];
				$returnValue = call_user_func_array($handler, array($property, self::$_defaults[$hash][$property]));
			}
			if (!isset($this->{$property}) && $returnValue === self::DEFAULT_VALUE)
				return self::$_defaults[$hash][$property];
			else
				return $returnValue;
		} else {
			trigger_error(sprintf('Undefined property: %s::$%s', $className, $property), E_USER_NOTICE);
		}
	}
	
	private static function _getRandomString($length = 8){
		$list = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		mt_srand();
		$result = '';
		for ($i = 0; $i < $length; $i++) $result .= $list{mt_rand(0, strlen($list) - 1)};
		return $result;
	}
}