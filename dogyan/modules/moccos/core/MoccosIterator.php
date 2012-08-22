<?php
/**
 *
 * @author Ikezaki
 *
 */
class MoccosIterator extends ArrayIterator implements Serializable {
	public $position = 0;
	private $_drops = array();
	private static $_sortProperty = '';

	/**
	 *
	 * @access private
	 * @var string
	 */
	public $_className;
	/**
	 *
	 * @param array $data
	 * @param string $className
	 */
	public function __construct($data = array(), $className) {
		parent::__construct($data);
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('%s iterator constructor called.', $className));
		$this->_className = $className;
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('%s iterator constructor end.', $className));
	}
	public function __clean($nest = 0) {
		foreach ($this as $instance) $instance->__clean($nest + 1);
		//parent::__destruct();
	}
	public function __wakeup() {
		foreach ($this as $instance)
			$instance->__wakeup();
	}
	public function serialize() {
		return serialize(array($this->getArrayCopy(), $this->position, $this->_className, $this->_drops));
	}
	public function unserialize($serialized) {
		list($storage, $this->position, $this->_className, $this->_drops) = unserialize($serialized);
		foreach ($storage as $key => $value) {
			$this->offsetSet($key, $value);
		}
	}

	public function save() {
		foreach ($this as $instance) $instance->save();
		foreach ($this->_drops as $instance) $instance->drop();
		$this->_drops = array();
	}

	public function next() {
		++$this->position;
		parent::next();
	}

	public function rewind() {
		$this->position = 0;
		parent::rewind();
	}

	public function seek($position) {
		$this->position = $position;
		parent::seek($position);
	}

	/**
	 *
	 * @param void
	 * @return Moccos
	 */
	public function current() {
		$value = parent::current();
		if ($value === null) {
			return null;
		}
		if ($value instanceof Moccos)
			return $value;
		//$instance = $this->_toInstance($value);
		$this->offsetSet($this->key(), $this->_toInstance($value));
		return $this->offsetGet($this->key());
	}
	/**
	 *
	 * @param mixed $index
	 * @return Moccos
	 */
	public function offsetGet($index) {
		if ($this->offsetExists($index)) {
			$value = parent::offsetGet($index);
			if ($value instanceof Moccos)
				return $value;
			$instance = $this->_toInstance($value);
			$this->offsetSet($index, $instance);
			return $instance;
		} else {
			//debug_print_backtrace();
			trigger_error(sprintf('undefined offset: %s', $index), E_USER_NOTICE);
			return null;
		}
	}

	/**
	 *
	 * @param string $delimiter
	 * @param string $property
	 * @return string
	 */
	public function join($delimiter, $property) {
		$string = '';
		$count = 0;
		foreach ($this as $index => $instance) {
			if (is_object($instance->{$property}) && method_exists($instance->{$property}, '__toString')) {
				$string .= $instance->{$property}->__toString();
			} else {
				$string .= $instance->{$property};
			}
			if ($count++ < $this->count() - 1)
				$string .= $delimiter;
		}
		return $string;
	}

	/**
	 *
	 * @param string $property
	 * @return array
	 */
	public function values($property) {
		$properties = array();
		foreach ($this as $instance) {
			$properties = $instance->{$property};
		}
		return $properties;
	}

	/**
	 *
	 * @param array $data
	 * @return MoccosIterator
	 */
	public function set($data) {
		foreach ($data as $id => $value) {
			if ($this->offsetExists($id)) {
				$instance = $this->offsetGet($id);
				$instance->set($value);
			}
			else $instance = $this->_create()->set($value);
			$this[$id] = $instance;
		}
		return $this;
	}

	public function sortBy($property) {
		self::$_sortProperty = $property;
		if (PHP_VERSION >= 5.2) $this->uasort(array($this, '_sortBy'));
		else uasort($this, array($this, '_sortBy'));
	}
	public function rsortBy($property) {
		self::$_sortProperty = $property;
		if (PHP_VERSION >= 5.2) $this->uasort(array($this, '_rsortBy'));
		else uasort($this, array($this, '_rsortBy'));
	}

	public function _sortBy($a, $b) {
		$a_value = is_object($a) ? $a->{self::$_sortProperty} : $a[self::$_sortProperty];
		$b_value = is_object($b) ? $b->{self::$_sortProperty} : $b[self::$_sortProperty];
	    if ($a_value == $b_value) return 0;
	    return ($a_value < $b_value) ? -1 : 1;
	}
	public function _rsortBy($a, $b) {
		$a_value = is_object($a) ? $a->{self::$_sortProperty} : $a[self::$_sortProperty];
		$b_value = is_object($b) ? $b->{self::$_sortProperty} : $b[self::$_sortProperty];
	    if ($a_value == $b_value) return 0;
	    return ($a_value > $b_value) ? -1 : 1;
	}
	public function remove($offset) {
		if ($this->offsetExists($offset)) {
			$this->_drops[] = $this->offsetGet($offset);
			$this->offsetUnset($offset);
		}
	}
	public function removeExists() {
		if ($this->count()) {
			//$position = $this->position;
			$offsets = array();
			foreach ($this as $offset => $instance) {
				if ($instance->id !== null) {
					$offsets[] = $offset;
				}
			}
			foreach ($offsets as $offset) $this->remove($offset);
			//$this->seek($position);
		}
	}

	/**
	 *
	 * @param void
	 * @return array
	 */
	public function toAssoc() {
		$array = array();
		foreach ($this as $instance) $array[] = $instance->toAssoc();
		return $array;
	}

	/**
	 *
	 * @access private
	 * @param void
	 * @return Moccos
	 */
	private function _create() {
		$className = $this->_className;
		return new $className();
	}

	/**
	 *
	 * @access private
	 * @param array $datum
	 * @return Moccos
	 */
	private function _toInstance($datum = array()) {
		$className = $this->_className;
		$instance = new $className();
		$instance->set($datum);
		return $instance;
	}

	public function in($property, $value) {
		if (! $this->count()) return false;
		//$position = $this->position;
		$flag = false;
		foreach ($this as $instance) {
			if ($instance->{$property} === $value) {
				$flag = true;
				break;
			}
		}
		//$this->seek($position);
		return $flag;
	}

	public function dropAll() {
		foreach ($this as $instance) $instance->drop();
	}
}