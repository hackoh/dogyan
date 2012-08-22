<?php
/**
 * 
 * @author Ikezaki
 *
 */
class MoccosReflectionProperty extends ReflectionProperty {
	
	/**
	 * 
	 * @var unknown_type
	 */
	private static $_instances = array();
	/**
	 * プロパティの型
	 * 
	 * @var int
	 */
	public $type;
	
	/**
	 * プロパティのデフォルト値
	 * 
	 * @var mixed
	 */
	public $default;
	
	/**
	 * プロパティの最大長
	 * 
	 * @var int
	 */
	public $length;
	
	/**
	 * プロパティがプライマリキーかどうか
	 * 
	 * @var bool
	 */
	public $primary = false;
	
	/**
	 * プロパティが新規保存される度
	 * 内部シーケンスがインクリメントされるかどうか
	 * 
	 * @var bool
	 */
	public $auto_increment = false;
	
	/**
	 * プロパティがNULLを許容するかどうか
	 * 
	 * @var bool
	 */
	public $not_null = false;
	
	/**
	 * 関係プロパティの場合、
	 * 紐付けに使用される自クラスのプロパティ名
	 * 
	 * @var string
	 */
	public $local_id;
	
	/**
	 * 関係プロパティの場合、
	 * 紐付けに使用される相手クラスのプロパティ名
	 * 
	 * @var string
	 */
	public $remote_id;
	
	public $order;
	
	/**
	 * 関係プロパティの場合、
	 * 紐付けられる相手クラス名
	 * 
	 * @var string
	 */
	public $remoteClassName;
	
	/**
	 * コンストラクタ
	 * 
	 * 引数はMoccosReflectionPropertyと同じ
	 * 
	 * @param mixed $class
	 * @param string $name
	 */
	public function __construct($class, $name) {
		parent::__construct($class, $name);
		$define = get_user_prop($this->class, $this->name);
		if (is_array($define) && isset($define['type'])) {
			foreach ($define as $key => $value) {
				if ($key === 'class') $this->remoteClassName = $value;
				else $this->{$key} = $value;
			}
		}
	}
	
	/**
	 * 
	 * @param unknown_type $class
	 * @param unknown_type $name
	 */
	public static function getInstance($class, $name) {
		if (is_object($class))
			$className = get_class($class);
		else
			$className = $class;
		if (! isset(self::$_instances[$className][$name]))
			self::$_instances[$className][$name] = new self($className, $name);
		return self::$_instances[$className][$name];
	}
	
	/**
	 * 
	 */
	public function isRelationProperty() {
		return $this->type === Moccos::HASMANY || $this->type === Moccos::BELONGSTO || $this->type === Moccos::HASONE;
	}
	
	public function getBelongsTo() {
		$reflection = MoccosReflectionClass::getInstance($this->class);
		$reflectionProperties = $reflection->getMoccosProperties();
		foreach ($reflectionProperties as $reflectionProperty) {
			if ($reflectionProperty->type === Moccos::BELONGSTO && $reflectionProperty->local_id === $this->name) {
				return $reflectionProperty;
			}
		}
		return null;
	}
}