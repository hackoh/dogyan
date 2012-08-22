<?php
/**
 * 
 * @author Ikezaki
 *
 */
abstract class MoccosPDODriver {

	/**
	 * 
	 * @access private
	 * @var int
	 */
	private static $_transactionNest = 0;
	
	/**
	 * 
	 * @access protected
	 * @var MoccosPDO
	 */
	protected $_pdo;
	
	/**
	 * 
	 * @access protected
	 * @var string
	 */
	protected $_tableName;
	
	/**
	 * 
	 * @access protected
	 * @var string
	 */
	protected $_className;
	
	/**
	 * 
	 * @access protected
	 * @var array
	 */
	protected $_config;
	
	/**
	 * 
	 * @param Moccos $instance
	 * @return int
	 */
	abstract public function insert(Moccos $instance);
	
	/**
	 * 
	 * @param Moccos $instance
	 * @return int
	 */
	abstract public function update(Moccos $instance);
	
	/**
	 * 
	 * @param Moccos $instance
	 * @return int
	 */
	abstract public function delete(Moccos $instance);
	
	/**
	 * 
	 * @param MoccosFinder $finder
	 * @return array
	 */
	abstract public function select(MoccosFinder $finder);

	/**
	 * 
	 * @param string $className
	 */
	public function __construct($className) {
		$this->_className = $className;
		$this->_tableName = snake_case($this->_className);
		$this->_config = MoccosConfig::getConfig(get_user_prop($this->_className, '_configKey'));
		$this->_pdo = MoccosPDO::getInstance(get_user_prop($this->_className, '_configKey'));
		if (isset($this->_config['create']) && $this->_config['create']) {
			if (! $this->_tableExists()) {
				$this->_createTable();
				$this->initialize();
			} else $this->_redefine();
		}
	}

	/**
	 * 
	 * @param void
	 * @return void
	 */
	public function initialize() {
		if (isset($this->_config['inserts'])) {
			$initializeFilePath = $this->_config['inserts'].$this->_tableName.'.sql';
			if (file_exists($initializeFilePath)) {
				foreach (explode(';', file_get_contents($initializeFilePath)) as $line) {
					if (!preg_match('/^\s*\r*\n*\s*$/', $line)) $this->_pdo->exec($line);
				}
			}
		}
	}

	/**
	 * 
	 */
	public function beginTransaction() {
		if (!self::$_transactionNest)
			$result = $this->_pdo->beginTransaction();
		++self::$_transactionNest;
		return isset($result) ? $result : null;
	}

	/**
	 * 
	 */
	public function commit() {
		if (self::$_transactionNest === 1)
			$result = $this->_pdo->commit();
		--self::$_transactionNest;
		return isset($result) ? $result : null;
	}

	/**
	 * 
	 */
	public function rollback() {
		if (self::$_transactionNest === 1)
			$result = $this->_pdo->rollback();
		--self::$_transactionNest;
		return isset($result) ? $result : null;
	}

	/**
	 * 
	 * @param void
	 * @return int
	 */
	public function lastInsertId() {
		return $this->_pdo->lastInsertId();
	}
	
	/**
	 * 
	 * @access protected
	 * @param array $structure
	 * @param PDOStatement $stmt
	 * @return array
	 */
	public static function translate($structure, $stmt) {
		MoccosLog::push(MOCCOS_LOG_TRACE, 'translate start.');
		$return = array();
		$rootColumn = key($structure);
		while ($column = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$columnArray = array();
			$id = null;
			foreach ($column as $key => $value) {
				//MoccosLog::push(MOCCOS_LOG_TRACE, 'lap.');
				$k = explode('__', $key);
				$kColumn = array_pop($k);
				$tmpK = &$columnArray;
				$currentDefineTarget = &$structure;
				foreach ($k as $kk) {
					//MoccosLog::push(MOCCOS_LOG_TRACE, 'lap2.');
					if ($kk === $rootColumn)
						$tmpDefineTarget = &$structure[$rootColumn];
					else
						$tmpDefineTarget = &$tmpDefineTarget['includes'][$kk];
					if ($tmpDefineTarget['type'] == Moccos::HASMANY) {
						if (!isset($tmpK[$kk])) {
							$id = $value;
							$tmpK[$kk] = array();
							$tmpK[$kk][$id] = array();
						}
						$tmpK = &$tmpK[$kk][$id];
					} else {
						if (!isset($tmpK[$kk]))
							$tmpK[$kk] = array();
						$tmpK = &$tmpK[$kk];
					}
				}
				$tmpK[$kColumn] = $value;
			}
			$columnArray = current($columnArray);
			$return = self::_push($return, $columnArray, $structure[$rootColumn]['class']);
		}
		MoccosLog::push(MOCCOS_LOG_TRACE, 'translate end.');
		return $return;
	}
	
	/**
	 * 
	 * @access protected
	 * @param array $array1
	 * @param array $array2
	 * @param string $className
	 * @return array
	 */
	private static function _push($array1, $array2, $className) {
		$reflection = MoccosReflectionClass::getInstance($className);
		$primaryKey = $reflection->primaryKey;
		if ($array2[$primaryKey] !== null) {
			if (isset($array1[$array2[$primaryKey]])) {
				$array1[$array2[$primaryKey]] = self::_set($array1[$array2[$primaryKey]], $array2, $reflection);
			} else {
				$array2 = self::_chomp($array2);
				$array1[$array2[$primaryKey]] = $array2;
			}
		} else {
			
		}
		return $array1;
	}
	
	/**
	 * 
	 * @access protected
	 * @param array $array1
	 * @param array $array2
	 * @param MoccosReflectionClass $reflection
	 * @return array
	 */
	private static function _set($array1, $array2, $reflection) {
		foreach ($array2 as $key => $value) {
			$property = $reflection->getMoccosProperty($key);
			if ($property->type === Moccos::HASMANY) {
				$array1[$key] = self::_push($array1[$key], array_shift($array2[$key]), $property->remoteClassName);
			} elseif ($property->type === Moccos::BELONGSTO || $property->type === Moccos::HASONE) {
				$array1[$key] = self::_set($array1[$key], $array2[$key], MoccosReflectionClass::getInstance($property->remoteClassName));
			}
		}
		return $array1;
	}
	
	/**
	 * 
	 * @param array $array
	 * @return array
	 */
	private static function _chomp($array) {
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				unset($value[null]);
				$array[$key] = self::_chomp($value);
			}
		}
		return $array;
	}
}