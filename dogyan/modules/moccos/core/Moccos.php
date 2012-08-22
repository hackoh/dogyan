<?php

define('MOCCOS_CORE_DIR', dirname(__FILE__).'/');
define('MOCCOS_INTERFACES_DIR', MOCCOS_CORE_DIR.'../interfaces/');
define('MOCCOS_DRIVERS_DIR', MOCCOS_CORE_DIR.'../drivers/');
define('MOCCOS_EXCEPTIONS_DIR', MOCCOS_CORE_DIR.'../exceptions/');
define('MOCCOS_FILTERS_DIR', MOCCOS_CORE_DIR.'../filters/');
define('MOCCOS_FINDERS_DIR', MOCCOS_CORE_DIR.'../finders/');
define('MOCCOS_REFLECTIONS_DIR', MOCCOS_CORE_DIR.'../reflections/');
define('MOCCOS_LOGS_DIR', MOCCOS_CORE_DIR.'../logs/');

define('MOCCOS_LOG_NONE', 0);
define('MOCCOS_LOG_INFO', 1);
define('MOCCOS_LOG_DEBUG', 2);
define('MOCCOS_LOG_TRACE', 3);

// interfaces
require MOCCOS_INTERFACES_DIR.'private/MoccosDriverInterface.php';
require MOCCOS_INTERFACES_DIR.'private/MoccosFilterInterface.php';
require MOCCOS_INTERFACES_DIR.'private/MoccosFinderInterface.php';
require MOCCOS_INTERFACES_DIR.'public/MoccosInterface.php';
require MOCCOS_INTERFACES_DIR.'public/MoccosIteratorInterface.php';

// cores
require MOCCOS_CORE_DIR.'LazyEvalutableObject.php';
require MOCCOS_CORE_DIR.'MoccosConfig.php';
require MOCCOS_CORE_DIR.'MoccosIterator.php';

// drivers
require MOCCOS_DRIVERS_DIR.'MoccosDriverFactory.php';
require MOCCOS_DRIVERS_DIR.'pdo/MoccosPDO.php';
require MOCCOS_DRIVERS_DIR.'pdo/MoccosPDODriver.php';
require MOCCOS_DRIVERS_DIR.'pdo/mysql/MoccosMysqlDriver.php';
require MOCCOS_DRIVERS_DIR.'pdo/oci/MoccosOciDriver.php';

// exceptions
require MOCCOS_EXCEPTIONS_DIR.'MoccosException.php';
require MOCCOS_EXCEPTIONS_DIR.'MoccosPDOException.php';
require MOCCOS_EXCEPTIONS_DIR.'MoccosValidateException.php';

// filters
require MOCCOS_FILTERS_DIR.'MoccosFilter.php';

// finders
require MOCCOS_FINDERS_DIR.'MoccosFinder.php';

// reflections
require MOCCOS_REFLECTIONS_DIR.'MoccosReflectionClass.php';
require MOCCOS_REFLECTIONS_DIR.'MoccosReflectionProperty.php';

// logs
require MOCCOS_LOGS_DIR.'MoccosLog.php';
require MOCCOS_LOGS_DIR.'MoccosLogRecord.php';
/**
 *
 * @author Ikezaki
 *
 */
abstract class Moccos extends LazyEvalutableObject {

	const INT = 1;
	const VARCHAR = 2;
	const TEXT = 3;
	const LONGTEXT = 4;
	const FLOAT = 5;
	const TINYTEXT = 6;
	const DATETIME = 7;
	const BLOB = 8;
	const LONGBLOB = 9;
	const BOOLEAN = 10;

	const HASMANY = 101;
	const BELONGSTO = 102;
	const HASONE = 103;
	/**
	 *
	 * @var string
	 */
	private static $_cacheDir = null;
	/**
	 *
	 * @var array
	 */
	private static $_filters = array();

	/**
	 *
	 * @var string
	 */
	public $_primaryKey = 'id';

	/**
	 *
	 * @var string
	 */
	public $_className = null;

	/**
	 *
	 */
	public function __construct() {
		parent::__construct();
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('%s constructor called.', get_class($this)));
		if ($this->_className === null) {
			$reflection = MoccosReflectionClass::getInstance(get_class($this));
			$this->_className = $reflection->className;
		}
		$this->_initializeProperties();
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('%s constructor end.', get_class($this)));
	}
	
	public function __clean($nest = 0) {
		parent::__clean();
		if ($nest > 3) {
			return;
		}
		$reflection = MoccosReflectionClass::getInstance(get_class($this));
		$properties = $reflection->getMoccosProperties();
		foreach ($properties as $property) {
			if (isset($this->{$property->name}) && ($this->{$property->name} instanceof Moccos || $this->{$property->name} instanceof MoccosIterator)) {
				$this->{$property->name}->__clean($nest + 1);
			} else {
				//vd('test');
			}
		}
	}

	public function __wakeup() {
		$reflection = MoccosReflectionClass::getInstance(get_class($this));
		foreach ($reflection->getMoccosProperties() as $reflectionProperty) {
			if ($reflectionProperty->isRelationProperty()) {
				if (! $this->{$reflectionProperty->name} instanceof Moccos && ! $this->{$reflectionProperty->name} instanceof MoccosIterator) {
					$this->addLazyEvalutionObserver($reflectionProperty->name, array($this, '_getAndWakeup'));

					//$this->{$reflectionProperty->name}->__wakeup();
				}
			} else {
				if ($this->{$reflectionProperty->name} === null)
					$this->{$reflectionProperty->name} = $reflectionProperty->default;
			}
		}
	}

	/**
	 *
	 * @return Moccos
	 * @param array $datum
	 */
	public function set(array $datum) {
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('%s set called.', $this->_className));
		foreach ($datum as $property => $value) {
			if (in_array($property, array_keys(get_class_vars(get_class($this))))) {
				$propertyReflection = MoccosReflectionProperty::getInstance(get_class($this), $property);
				if ($propertyReflection) {
					switch ($propertyReflection->type) {
						case Moccos::HASMANY:
							if (isset($this->{$property})) {
								$this->{$property}->set($value);
							} else {
								$remoteClassName = $propertyReflection->remoteClassName;
								$remoteInstance = new MoccosIterator($value, $remoteClassName);
								$this->{$property} = $remoteInstance;
							}
							break;
						case Moccos::BELONGSTO:
						//	break;
						case Moccos::HASONE:
							$class = $propertyReflection->remoteClassName;
							$this->{$property} = new $class(false);
							$this->{$property}->set($value);
							break;
						case Moccos::INT:
							$this->{$property} = (int)$value;
							//$belongsToPropertyReflection = $propertyReflection->getBelongsTo();
							//if ($belongsToPropertyReflection !== null) {
								//$instance = call_user_func(array($belongsToPropertyReflection->remoteClassName, 'find'), $this->{$property});
								//$this->{$belongsToPropertyReflection->name} = $instance;
							//}
							break;
						case Moccos::FLOAT:
							$this->{$property} = (float)$value;
							break;
						case Moccos::BOOLEAN:
							$this->{$property} = (bool)$value;
							break;
						default:
							$this->{$property} = $value;
							break;
					}
				}
			}
			
		}
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('%s set end.', get_class($this)));
		return $this;
	}

	/**
	 *
	 * @param void
	 * @return void
	 */
	public function save() {
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('%s save called.', get_class($this)));
		$driver = MoccosDriverFactory::getInstance($this->_className);
		$reflection = MoccosReflectionClass::getInstance(get_class($this));
		$time = time();
		$properties = $reflection->getMoccosProperties();
		//$driver->beginTransaction();
		foreach ($properties as $reflectionProperty) {
			if ($reflectionProperty->type === Moccos::BELONGSTO && isset($this->{$reflectionProperty->name})) {
				if ($this->{$reflectionProperty->name} !== null && $this->{$reflectionProperty->name} instanceof Moccos) {
					$this->{$reflectionProperty->local_id} = $this->{$reflectionProperty->name}->{$reflectionProperty->remote_id};
				}
			}
		}
		if ($reflection->hasProperty('update_date')) $this->update_date = $time;
		if ($this->{$this->_primaryKey} === null) {
			if ($reflection->hasProperty('post_date')) $this->post_date = $time;
			$result = $driver->insert($this);
			$this->{$this->_primaryKey} = $driver->lastInsertId();
		} else {
			$result = $driver->update($this);
		}
		foreach ($properties as $reflectionProperty) {
			if ($reflectionProperty->type === Moccos::HASMANY && isset($this->{$reflectionProperty->name})) {
				foreach ($this->{$reflectionProperty->name} as $instance)
					$instance->{$reflectionProperty->remote_id} = $this->{$reflectionProperty->local_id};
				$this->{$reflectionProperty->name}->save();
			} elseif ($reflectionProperty->type === Moccos::HASONE && isset($this->{$reflectionProperty->name})) {
				$instance = $this->{$reflectionProperty->name};
				$instance->{$reflectionProperty->remote_id} = $this->{$reflectionProperty->local_id};
				$instance->save();
			}
		}
		//$driver->commit();
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('%s save end.', get_class($this)));
		return $result;
	}

	public function drop() {
		$driver = MoccosDriverFactory::getInstance($this->_className);
		$driver->delete($this);
	}

	/**
	 *
	 * @param void
	 * @return array
	 */
	public function toAssoc() {
		$array = array();
		$reflection = MoccosReflectionClass::getInstance(get_class($this));
		foreach ($reflection->getMoccosProperties() as $reflectionProperty) {
			if (! $reflectionProperty->isRelationProperty())
				$array[$reflectionProperty->name] = $this->{$reflectionProperty->name};
		}
		return $array;
	}
	
	public function toCopyableAssoc() {
		$array = array();
		$reflection = MoccosReflectionClass::getInstance(get_class($this));
		foreach ($reflection->getMoccosProperties() as $reflectionProperty) {
			if (! $reflectionProperty->isRelationProperty() && ! $reflectionProperty->primary)
				$array[$reflectionProperty->name] = $this->{$reflectionProperty->name};
		}
		return $array;
	}

	/**
	 *
	 * @param string $property
	 * @param MoccosFilter $filter
	 * @return void
	 */
	public function setFilter($property, $filter) {
		self::$_filters[$this->_className][$property] = $filter;
	}
	private static function _getCache($className, $options) {
		$cacheDir = self::getCacheDir();
		$filePath = $cacheDir . '/' . $className . '/' . md5(serialize($options));
		if (file_exists($filePath)) return unserialize(file_get_contents($filePath));
		else return null;
	}
	private static function _setCache($className, $options, $data) {
		$cacheDir = self::getCacheDir();
		$dir = $cacheDir . '/' . $className;
		$filePath = $dir . '/' . md5(serialize($options));
		if (! file_exists($dir)) mkdir($dir, 0755);
		file_put_contents($filePath, serialize($data));
	}
	private static function _dropCache($className) {
		$cacheDir = self::getCacheDir();
		$dir = $cacheDir . '/' . $className;
		if (file_exists($dir)) {
			foreach (scandir($dir) as $filename) {
				if ($filename !== '.' && $filename !== '..')
					unlink($dir . '/' . $filename);
			}
		}
	}
	public static function getDriver() {
		$className = get_called_class();
		$reflection = MoccosReflectionClass::getInstance($className);
		return MoccosDriverFactory::getInstance($reflection->className);
	}
	/**
	 *
	 * @param array $options
	 * @return mixed
	 */
	public static function find($options = array()) {
		$className = get_called_class();
		$cacheDir = self::getCacheDir();
		if ($cacheDir !== null && !isset($options['includes'])) {
			$result = self::_getCache($className, $options);
			if ($result !== null) {
				return $result;
			}
		}
		$reflection = MoccosReflectionClass::getInstance($className);
		$driver = MoccosDriverFactory::getInstance($reflection->className);
		if (is_numeric($options)) {
			$finder = new MoccosFinder(array(
				'filter' => MoccosFilter::equal($reflection->primaryKey, $options),
				'limit' => 1,
				'self' => $reflection->className,
			));
			$data = $driver->select($finder);
			$iterator = new MoccosIterator($data, $className);
			if ($iterator->count()) return $iterator->current();
			else return null;
		}
		$finder = new MoccosFinder($options);
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('%s find called.', $className));
		$finder->self = $reflection->className;
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('%s driver select start.', $className));
		$data = $driver->select($finder);
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('%s driver select end.', $className));
		$iterator = new MoccosIterator($data, $className);
		if ($cacheDir !== null && !isset($options['includes'])) {
			self::_setCache($className, $options, $iterator);
		}
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('%s find end.', $className));
		return $iterator;
	}
	
	public static function findFirst($options = array()) {
		$className = get_called_class();
		$reflection = MoccosReflectionClass::getInstance($className);
		$driver = MoccosDriverFactory::getInstance($reflection->className);
		if (is_numeric($options)) trigger_error('findFirst argument is must be array, numeric given.', E_USER_ERROR);
		$options['limit'] = 1;
		$finder = new MoccosFinder($options);
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('%s find called.', $className));
		$finder->self = $reflection->className;
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('%s driver select start.', $className));
		$data = $driver->select($finder);
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('%s driver select end.', $className));
		$iterator = new MoccosIterator($data, $className);
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('%s find end.', $className));
		if ($iterator->count()) return $iterator->current();
		return null;
	}

	/**
	 *
	 * @param array $options
	 * @return mixed
	 */
	public static function count($options = array()) {
		$className = get_called_class();
		$reflection = MoccosReflectionClass::getInstance($className);
		$driver = MoccosDriverFactory::getInstance($reflection->className);
		$finder = new MoccosFinder($options);
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('%s find called.', $className));
		$finder->self = $reflection->className;
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('%s driver count start.', $className));
		//$data = $driver->select($finder);
		$count = $driver->count($finder);
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('%s driver count end. count = %s', $className, $count));
		//$iterator = new MoccosIterator($data, $className);
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('%s find end.', $className));
		return $count;
	}

	/**
	 *
	 * @param string $method
	 * @param mixed $arguments
	 */
	public static function __callStatic($method, $arguments) {
		$className = get_called_class();
		$captures = sscanf($method, 'findFirstBy%s');
		if (isset($captures[0])) {
			$property = $captures[0];
			$reflection = MoccosReflectionClass::getInstance($className);
			$instance = call_user_func(array($reflection->className, 'findFirst'), array(
				'filter' => MoccosFilter::equal(snake_case($property), $arguments[0]),
				'limit' => 1,
			));
			return $instance;
		}
		$captures = sscanf($method, 'findBy%s');
		if (isset($captures[0])) {
			$property = $captures[0];
			$reflection = MoccosReflectionClass::getInstance($className);
			return call_user_func(array($reflection->className, 'find'), array(
				'filter' => MoccosFilter::equal(snake_case($property), $arguments[0])
			));
		} else {
			trigger_error(sprintf('Call to undefined method %s::%s()', $className, $method), E_USER_ERROR);
		}
	}

	/**
	 *
	 * @access private
	 * @param void
	 * @return void
	 */
	private function _initializeProperties() {
		$reflection = MoccosReflectionClass::getInstance(get_class($this));
		//$reflection = new MoccosReflectionClass($this);
		foreach ($reflection->getMoccosProperties() as $reflectionProperty) {
			if ($reflectionProperty->isRelationProperty()) {
				$this->addLazyEvalutionObserver($reflectionProperty->name, array($this, '_get'));
			} else {
				$this->{$reflectionProperty->name} = $reflectionProperty->default;
			}
		}
	}


	/**
	 *
	 * @access protected
	 * @param string $property
	 * @param array $define
	 */
	protected function _get($property, $define) {
		if ($define['type'] === self::BELONGSTO) {
			if ($this->{$define['local_id']} === null) {
				$this->{$property} = null;
				return null;
			} else {
				$instance = call_user_func(array($define['class'], 'find'), $this->{$define['local_id']});
				$this->{$property} = $instance;
				return $instance;
			}
		} else if ($define['type'] === self::HASONE) {
			if ($this->{$define['local_id']} === null) {
				$this->{$property} = null;
				return null;
			} else {
				$filter = MoccosFilter::equal($define['remote_id'], $this->{$this->_primaryKey});
				if (isset(self::$_filters[$this->_className][$property]))
					$filter = MoccosFilter::andLink(array(self::$_filters[$this->_className][$property], $filter));
				$iterator = call_user_func(array($define['class'], 'find'), array('filter' => $filter, 'limit' => 1));
				$instance = $iterator->current();
				$this->{$property} = $instance;
				return $instance;
			}
		} else if ($define['type'] === self::HASMANY) {
			if ($this->{$define['local_id']} === null) {
				$iterator = new MoccosIterator(array(), $define['class']);
				$this->{$property} = $iterator;
				return $iterator;
			} else {
				$filter = MoccosFilter::equal($define['remote_id'], $this->{$define['local_id']});
				if (isset(self::$_filters[$this->_className][$property]))
					$filter = MoccosFilter::andLink(array(self::$_filters[$this->_className][$property], $filter));
				$info = array('filter' => $filter);
				if (isset($define['order']))
					$info['order'] = $define['order'];
				if (isset($define['includes']))
					$info['includes'] = $define['includes'];
				$iterator = call_user_func(array($define['class'], 'find'), $info);
				$this->{$property} = $iterator;
				return $iterator;
			}
		} else {
			if (isset($define['default'])) {
				$this->{$property} = $define['default'];
				return $define['default'];
			} else return null;
		}
	}

	public function _getAndWakeup($property, $define) {
		$result = $this->_get($property, $define);
		if ($result !== null) {
			$result->__wakeup();
		}
		return $result;
	}
	public static function getCacheDir() {
		return self::$_cacheDir;
	}
	public static function setCacheDir($dir) {
		$dir = preg_replace('#/$#', '', $dir);
		self::$_cacheDir = $dir;
	}
}
//Moccos::setCacheDir('caches');
/**
 *
 * @param string $className
 * @param string $property
 */
function get_user_prop($className, $property) {
	$vars = get_class_vars($className);
	return isset($vars[$property]) ? $vars[$property] : null;
}
/**
 *
 * @param string $string
 * @return string
 */
function pascalize($string) {
	$string = strtolower($string);
	$string = str_replace('_', ' ', $string);
	$string = ucwords($string);
	$string = str_replace(' ', '', $string);
	return $string;
}

/**
 *
 * @param string $string
 * @return string
 */
function camelize($string) {
	$string = pascalize($string);
	$string[0] = strtolower($string[0]);
	return $string;
}

/**
 *
 * @param string $string
 * @return string
 */
function snake_case($string) {
	$string = preg_replace('/([A-Z])/', '_$1', $string);
	$string = strtolower($string);
	return ltrim($string, '_');
}

if (! function_exists('get_called_class')) {
	/**
	 *
	 * @param void
	 * @return string
	 */
	function get_called_class() {
		$traces = debug_backtrace();
		$caller = $traces[1];
		if (! isset($caller['file'])) {
			if (isset($traces[2]['args'][0][0])) return $traces[2]['args'][0][0];
			trigger_error('get_called_class() can not find a class', E_USER_WARNING);
		} else {
			$file = file($caller['file']);
			$pattern = sprintf('/([a-zA-Z\_0-9]+)::%s\s*?\(/', $caller['function']);
			for ($line = $caller['line'] - 1; $line > 0; --$line) {
				if (preg_match($pattern, $file[$line], $matches)) {
					if ($matches[1] === 'self') $pattern = '/class\s+([a-zA-Z\_0-9]+)\s+/';
					else return $matches[1];
				}
			}
			trigger_error('get_called_class() can not find a class', E_USER_WARNING);
		}
	}
}