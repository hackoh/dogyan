<?php
/**
 *
 * @author Ikezaki
 * @copyright Copyright &copy; 2011 Ikezaki
 * @version 2.0
 */
class MoccosOciDriver extends MoccosPDODriver {

	/**
	 *
	 * @var string
	 */
	const QUOTE = '`';

	/**
	 *
	 * @access private
	 * @var array
	 */
	private static $_tables = array();
	
	public static $_caches = array();

	/**
	 *
	 * @access private
	 * @var array
	 */
	private static $_types = array(
		Moccos::INT => 'int',
		Moccos::FLOAT => 'float',
		Moccos::TEXT => 'text',
		Moccos::LONGTEXT => 'longtext',
		Moccos::TINYTEXT => 'tinytext',
		Moccos::DATETIME => 'datetime',
		Moccos::BLOB => 'blob',
		Moccos::LONGBLOB => 'longblob',
		Moccos::VARCHAR => 'varchar',
		Moccos::BOOLEAN => 'tinyint(1)',
	);

	/**
	 *
	 * @param Moccos $instance
	 * @return int
	 */
	public function insert(Moccos $instance) {
		$reflection = MoccosReflectionClass::getInstance($instance);
		$properties = $reflection->getMoccosProperties();
		$values = array();
		foreach ($properties as $property) {
			if ($reflection->primaryKey !== $property->name) {
				switch ($property->type) {
					case Moccos::BELONGSTO:
					case Moccos::HASMANY:
					case Moccos::HASONE:
						break;
					case Moccos::BLOB:
					case Moccos::TEXT:
					case Moccos::TINYTEXT:
					case Moccos::LONGBLOB:
					case Moccos::VARCHAR:
						$values[] = $this->_pdo->quote($instance->{$property->name}, PDO::PARAM_STR);
						break;
					case Moccos::BOOLEAN:
						$values[] = $this->_pdo->quote($instance->{$property->name}, PDO::PARAM_BOOL);
						break;
					default:
						$values[] = $this->_pdo->quote($instance->{$property->name}, PDO::PARAM_INT);
						break;
				}
			}
		}
		$query = sprintf('insert into %s (%s) values (%s);',
			self::QUOTE.$this->_tableName.self::QUOTE,
			self::QUOTE.join(self::QUOTE.','.self::QUOTE, $reflection->getSimpleMoccosProperties()).self::QUOTE,
			join(',', $values)
		);
		MoccosLog::push(MOCCOS_LOG_INFO, sprintf('query = %s.', $query));
		return $this->_pdo->exec($query);
	}

	/**
	 *
	 * @param Moccos $instance
	 * @return int
	 */
	public function update(Moccos $instance) {
		$reflection = MoccosReflectionClass::getInstance($instance);
		$properties = $reflection->getMoccosProperties();
		$sets = array();
		foreach ($properties as $property) {
			switch ($property->type) {
				case Moccos::BELONGSTO:
				case Moccos::HASMANY:
				case Moccos::HASONE:
					break;
				case Moccos::BLOB:
				case Moccos::TEXT:
				case Moccos::TINYTEXT:
				case Moccos::LONGBLOB:
				case Moccos::VARCHAR:
					$sets[] = sprintf("%s = %s", self::QUOTE.$property->name.self::QUOTE, $this->_pdo->quote($instance->{$property->name}, PDO::PARAM_STR));
					break;
				case Moccos::BOOLEAN:
					$sets[] = sprintf("%s = %s", self::QUOTE.$property->name.self::QUOTE, $this->_pdo->quote($instance->{$property->name}, PDO::PARAM_BOOL));
					break;
				default:
					$sets[] = sprintf("%s = %s", self::QUOTE.$property->name.self::QUOTE, $this->_pdo->quote($instance->{$property->name}, PDO::PARAM_INT));
					break;
			}
		}
		$query = sprintf('update %s set %s where %s = %s',
			self::QUOTE.$this->_tableName.self::QUOTE,
			join(',', $sets),
			self::QUOTE.$reflection->primaryKey.self::QUOTE,
			$instance->{$reflection->primaryKey}
		);
		MoccosLog::push(MOCCOS_LOG_INFO, sprintf('query = %s.', $query));
		return $this->_pdo->exec($query);
	}

	/**
	 *
	 * @param MoccosFinder $finder
	 * @return array
	 */
	public function select(MoccosFinder $finder) {
		$cacheKey = md5(serialize($finder));
		if (isset(self::$_caches[$cacheKey])) {
			return self::$_caches[$cacheKey];
		}
		$structure = $finder->getStructure();
		$reflection = MoccosReflectionClass::getInstance($finder->self);
		$properties = $reflection->getMoccosProperties();
		$selects = $this->_getSelects($finder->getStructure(), $finder->eagerLoad);
		$simpleSelects = $this->_getSimpleSelects($finder->getStructure());
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('finder = %s.', MoccosLog::dumper($finder)));
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('selects = %s.', MoccosLog::dumper($selects)));
		$froms = $this->_getFroms($finder->getStructure());
		//vd($froms);exit();
		$finder->group = self::QUOTE.$this->_tableName.self::QUOTE.'.'.$reflection->primaryKey;
		$subqueries[] = sprintf("select %s from %s", join(",\n", $simpleSelects), join("\n", $froms));
		if ($finder->filter->isEmpty() === false)
			$subqueries[] = sprintf('where %s', self::_renderFilter($finder->filter));
		if ($finder->group !== null && count($finder->includes))
			$subqueries[] = sprintf('group by %s', $finder->group);
		if ($finder->order !== null)
			$subqueries[] = sprintf('order by %s', $finder->order);
		if ($finder->desc === true)
			$subqueries[] = 'desc';
		if ($finder->limit !== null)
			$subqueries[] = sprintf('limit %s', $finder->limit);
		if ($finder->offset !== null)
			$subqueries[] = sprintf('offset %s', $finder->offset);
		if (count($finder->includes) && $finder->eagerLoad) {
			$froms[0] = sprintf('(%s) as %s', join(' ', $subqueries), self::QUOTE.$this->_tableName.self::QUOTE);
			$queries[] = sprintf("select %s from %s", join(",\n", $selects), join("\n", $froms));
			$query = join(' ', $queries);
		} else {
			$subqueries[0] = sprintf("select %s from %s", join(",\n", $selects), join("\n", $froms));
			$query = join(' ', $subqueries);
		}
		MoccosLog::push(MOCCOS_LOG_INFO, sprintf('query = %s.', $query));
		$stmt = $this->_pdo->query($query);
		if ($stmt !== false) {
			$result = MoccosPDODriver::translate($structure, $stmt);
		} else {
			$result = array();
		}
		self::$_caches[$cacheKey] = $result;
		return self::$_caches[$cacheKey];
		return $result;
	}

	/**
	 *
	 * @param MoccosFinder $finder
	 * @return int
	 */
	public function count(MoccosFinder $finder) {
		$structure = $finder->getStructure();
		$reflection = MoccosReflectionClass::getInstance($finder->self);
		$properties = $reflection->getMoccosProperties();
		$selects = $this->_getSelects($finder->getStructure(), $finder->eagerLoad);
		$simpleSelects = $this->_getSimpleSelects($finder->getStructure());
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('finder = %s.', MoccosLog::dumper($finder)));
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('selects = %s.', MoccosLog::dumper($selects)));
		$froms = $this->_getFroms($finder->getStructure());
		$finder->group = self::QUOTE.$this->_tableName.self::QUOTE.'.'.$reflection->primaryKey;
		$subqueries[] = sprintf("select %s from %s", join(",\n", $simpleSelects), join("\n", $froms));
		if ($finder->filter->isEmpty() === false)
			$subqueries[] = sprintf('where %s', self::_renderFilter($finder->filter));
		if ($finder->group !== null && count($finder->includes))
			$subqueries[] = sprintf('group by %s', $finder->group);
		if ($finder->order !== null)
			$subqueries[] = sprintf('order by %s', $finder->order);
		if ($finder->desc === true)
			$subqueries[] = 'desc';
		if ($finder->limit !== null)
			$subqueries[] = sprintf('limit %s', $finder->limit);
		if ($finder->offset !== null)
			$subqueries[] = sprintf('offset %s', $finder->offset);
		$subqueries[0] = sprintf("select %s from %s", join(",\n", $selects), join("\n", $froms));
		$query = join(' ', $subqueries);
		MoccosLog::push(MOCCOS_LOG_INFO, sprintf('query = %s.', $query));
		$stmt = $this->_pdo->query($query);
		if ($stmt !== false)
			return $stmt->rowCount();
		return 0;
	}
	/**
	 *
	 * @param Moccos $instance
	 * @return int
	 */
	public function delete(Moccos $instance) {
		$reflection = MoccosReflectionClass::getInstance($this);
		return $this->_pdo->exec(sprintf('delete from %s where %s = %s;',
			self::QUOTE.$this->_tableName.self::QUOTE,
			$reflection->primaryKey,
			$this->_pdo->quote($instance->{$reflection->primaryKey})
		));
	}
	/**
	 *
	 * @access private
	 * @param MoccosFilter $filter
	 * @return string
	 */
	private function _renderFilter($filter) {
		switch ($filter->type) {
			case '&&':
				$renders = array();
				foreach ($filter->args as $_filter_) {
					$renders[] = $this->_renderFilter($_filter_);
				}
				return sprintf('(%s)', join(' and ', $renders));
			case '||':
				$renders = array();
				foreach ($filter->args as $_filter_) {
					$renders[] = $this->_renderFilter($_filter_);
				}
				return sprintf('(%s)', join(' or ', $renders));
			case '()':
				return sprintf('%s in (%s)', $filter->args['property'], join(',', $filter->args['value']));
			case '.%':
				return sprintf("%s like '%s%%'", $filter->args['property'], $filter->args['value']);
			case '%.':
				return sprintf("%s like '%%%s'", $filter->args['property'], $filter->args['value']);
			case '%.%':
				return sprintf("%s like '%%%s%%'", $filter->args['property'], $filter->args['value']);
			default:
				return sprintf('%s %s %s', $filter->args['property'], $filter->type, $this->_pdo->quote($filter->args['value']));
		}
	}
	/**
	 *
	 * @access private
	 * @param array $structure
	 * @param boolean $eagerLoad
	 * @return array
	 */
	private function _getSelects($structure, $eagerLoad) {
		$selects = array();
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('structure = ', MoccosLog::dumper($structure)));
		foreach ($structure as $alias => $__structure__) {
			MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('__structure__ = ', MoccosLog::dumper($__structure__)));
			$reflection = MoccosReflectionClass::getInstance($__structure__['class']);
			foreach ($reflection->getMoccosProperties() as $reflectionProperty) {
				if (! $reflectionProperty->remoteClassName)
					$selects[] = sprintf('%s.%s as %s__%s', $alias, $reflectionProperty->name, $alias, $reflectionProperty->name);
			}
			if ($eagerLoad) {
				foreach ($this->_getSelects($__structure__['includes'], $eagerLoad) as $__select__) {
					list($prefix, $suffix) = sscanf($__select__, '%s as %s');
					$selects[] = sprintf('%s__%s as %s__%s', $alias, $prefix, $alias, $suffix);
				}
			}
		}
		return $selects;
	}
	/**
	 *
	 * @access private
	 * @param array $structure
	 * @return array
	 */
	private function _getSimpleSelects($structure) {
		$selects = array();
		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('structure = ', MoccosLog::dumper($structure)));
		foreach ($structure as $alias => $__structure__) {
			MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('__structure__ = ', MoccosLog::dumper($__structure__)));
			$reflection = MoccosReflectionClass::getInstance($__structure__['class']);
			foreach ($reflection->getMoccosProperties() as $reflectionProperty) {
				if (! $reflectionProperty->remoteClassName)
					$selects[] = sprintf('%s.%s', $alias, $reflectionProperty->name);
			}
		}
		return $selects;
	}
	/**
	 *
	 * @access private
	 * @param array $structure
	 * @param string $path
	 * @return array
	 */
	private function _getFroms(array $structure, $path = '') {
		$froms = array();
		list ($alias, $currentStructure) = each($structure);
		$tableName = snake_case($currentStructure['class']);
		switch ($currentStructure['type']) {
			case Moccos::BELONGSTO:
				$froms[] = sprintf('left join %s as %s on %s.%s = %s.%s', $tableName, $path.$alias, rtrim($path, '__'), $currentStructure['local_id'], $path.$alias, $currentStructure['remote_id']);
				break;
			case Moccos::HASONE:
				$froms[] = sprintf('left join %s as %s on %s.%s = %s.%s', $tableName, $path.$alias, rtrim($path, '__'), $currentStructure['local_id'], $path.$alias, $currentStructure['remote_id']);
				break;
			case Moccos::HASMANY:
				$froms[] = sprintf('left outer join %s as %s on %s.%s = %s.%s', $tableName, $path.$alias, rtrim($path, '__'), $currentStructure['local_id'], $path.$alias, $currentStructure['remote_id']);
				break;
			default:
				$froms[] = sprintf('%s as %s', $tableName, $alias);
		}
		$childStructures = $currentStructure['includes'];
		foreach ($childStructures as $childAlias => $childStructure)
			$froms = array_merge($froms, $this->_getFroms(array($childAlias => $childStructure), $path.$alias.'__'));
		return $froms;
	}

//	private function _getFroms($structure, $path = '') {
//		MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('path = %s', MoccosLog::dumper($path)));
//		$froms = array();
//		foreach ($structure as $alias => $__structure__) {
//			$new_path = $path.$alias.'__';
//			$froms[] = sprintf('%s as %s', snake_case($__structure__['class']), $alias);
//			foreach ($this->_getFroms($__structure__['includes'], $new_path) as $__from__) {
//				$paths = explode('__', $new_path);
//				array_pop($paths);
//				$oldPath = join('__', $paths);
//				if (preg_match('/^(.*?) as (\S+)$/', $__from__, $matches)) {
//					list ($match, $__tableName__, $__alias__) = $matches;
//					MoccosLog::push(MOCCOS_LOG_TRACE, sprintf('__alias__ = %s', MoccosLog::dumper($__alias__)));
//					$childStructure = $__structure__['includes'][$__alias__];
//					if ($childStructure['type'] === Moccos::BELONGSTO) {
//						$froms[] = sprintf('left join %s as %s on %s.%s = %s.%s', $__tableName__, $new_path.$__alias__, $oldPath, $childStructure['local_id'], $new_path.$__alias__, $childStructure['remote_id']);
//					} else if($childStructure['type'] === Moccos::HASONE) {
//						$froms[] = sprintf('left join %s as %s on %s.%s = %s.%s', $__tableName__, $new_path.$__alias__, $oldPath, $childStructure['local_id'], $new_path.$__alias__, $childStructure['remote_id']);
//					} else if($childStructure['type'] === Moccos::HASMANY) {
//						$froms[] = sprintf('left outer join %s as %s on %s.%s = %s.%s', $__tableName__, $new_path.$__alias__, $oldPath, $childStructure['local_id'], $new_path.$__alias__, $childStructure['remote_id']);
//					}
//				} else {
//					$froms[] = $__from__;
//				}
//			}
//		}
//		return $froms;
//	}

	/**
	 *
	 * @access protected
	 * @param void
	 * @return boolean
	 */
	protected function _tableExists() {
		if (count(self::$_tables) && in_array($this->_tableName, self::$_tables))
			return true;
		//$stmt = $this->_pdo->query(sprintf('desc test;', $this->_tableName));
		
		$stmt = $this->_pdo->query(sprintf('select * from test;'));
		vd($stmt);exit();
		if (count(self::$_tables) && in_array($this->_tableName, self::$_tables))
			return true;
		return false;
	}

	/**
	 *
	 * @access protected
	 * @param void
	 * @return void
	 */
	protected function _createTable() {
		$this->_pdo->exec(sprintf('
CREATE SEQUENCE %s_seq
START WITH 1
INCREMENT BY 1
NOMAXVALUE;', $this->_tableName));
		$this->_pdo->exec(sprintf('
CREATE TRIGGER %s_trigger
BEFORE INSERT ON %s
FOR EACH ROW
BEGIN
SELECT %s_seq.nextval INTO :new.id FROM dual;
END;', $this->_tableName, $this->_tableName, $this->_tableName));
		$reflection = MoccosReflectionClass::getInstance(pascalize($this->_tableName));
		$queries = array(sprintf('create table %s (', self::QUOTE.$this->_tableName.self::QUOTE));
		$properties = $reflection->getMoccosProperties();
		$columns = array();
		foreach ($properties as $property) {
			if (! $property->isRelationProperty()) {
				$columns[] = sprintf('%s %s %s %s',
					self::QUOTE.$property->name.self::QUOTE,
					self::_getType($property)
					//$property->auto_increment ? 'auto_increment' : null,
					//$property->primary ? 'primary key' : null
					);
			}
		}
		$queries[] = join(',', $columns);
		$queries[] = ');';
		$query = join("\n", $queries);
		return $this->_pdo->exec($query);
	}

	/**
	 *
	 * @access private
	 * @param MoccosReflectionProperty $property
	 * @return string
	 */
	private static function _getType(MoccosReflectionProperty $property) {
		if ($property->length === null) return self::$_types[$property->type];
		switch ($property->type) {
			case Moccos::INT:
			case Moccos::VARCHAR:
			case Moccos::FLOAT:
				return sprintf('%s(%s)', self::$_types[$property->type], $property->length);
			default:
				return self::$_types[$property->type];
		}
	}

	/**
	 *
	 * @access protected
	 * @param vpid
	 * @return void
	 */
	protected function _redefine() {
		return true;
		$reflection = MoccosReflectionClass::getInstance($this->_className);
		$describes = $this->_pdo->query(sprintf(
			'desc %s;',
			self::QUOTE.$this->_tableName.self::QUOTE
		))->fetchAll(PDO::FETCH_ASSOC);
		foreach ($reflection->getMoccosProperties() as $reflectionProperty) {
			if (! $reflectionProperty->isRelationProperty()) {
				foreach ($describes as $describe) {
					if ($describe["Field"] === $reflectionProperty->name) {
						continue 2;
					}
				}
				$this->_addColumn($reflectionProperty);
			}
		}
//		foreach ($describes as $describe) {
//			foreach ($reflection->getMoccosProperties() as $reflectionProperty) {
//				if ($describe["Field"] === $reflectionProperty->name) continue 2;
//			}
//			$this->_dropColumn($describe["Field"]);
//		}
	}

	/**
	 *
	 * @access private
	 * @param MoccosReflectionProperty $reflectionProperty
	 * @return int
	 */
	private function _addColumn(MoccosReflectionProperty $reflectionProperty) {
		$query = sprintf(
			'alter table %s add column %s %s %s %s;',
			self::QUOTE.$this->_tableName.self::QUOTE,
			self::QUOTE.$reflectionProperty->name.self::QUOTE,
			self::_getType($reflectionProperty),
			$reflectionProperty->not_null ? 'not null' : null,
			$reflectionProperty->default !== null ? sprintf('default %s', self::_toSQLValue($reflectionProperty->default)) : null
		);
		return $this->_pdo->exec($query);
	}

	/**
	 *
	 * @access private
	 * @param MoccosReflectionProperty $reflectionProperty
	 * @return int
	 */
	private function _modifyColumn(MoccosReflectionProperty $reflectionProperty) {
		$query = sprintf(
			'alter table %s modify %s %s %s %s;',
			self::QUOTE.$this->_tableName.self::QUOTE,
			self::QUOTE.$reflectionProperty->name.self::QUOTE,
			self::_getType($reflectionProperty),
			$reflectionProperty->not_null ? 'not null' : null,
			$reflectionProperty->default !== null ? sprintf('default %s', self::_toSQLValue($reflectionProperty->default)) : null
		);
		return $this->_pdo->exec($query);
	}

	/**
	 *
	 * @access private
	 * @param string $field
	 * @return int
	 */
	private function _dropColumn($field) {
		$query = sprintf(
			'alter table %s drop column %s;',
			self::QUOTE.$this->_tableName.self::QUOTE,
			self::QUOTE.$field.self::QUOTE
		);
		return $this->_pdo->exec($query);
	}

	/**
	 *
	 * @param $value
	 */
	private static function _toSQLValue($value = null) {
		if ($value === false)
			return '0';
		if ($value === true)
			return '1';
		if ($value === null)
			return 'null';
		return $value;
	}
}