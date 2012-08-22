<?php
/**
 * 
 * @author Ikezaki
 *
 */
class MoccosPDO extends PDO {

	/**
	 * 
	 * @access private
	 * @var array
	 */
	private static $_instances = array();

	/**
	 * 
	 * @param string $alias
	 * @return MoccosPDO
	 */
	public static function getInstance($alias = 'default') {
		$config = MoccosConfig::getConfig($alias);
		if (!isset(self::$_instances[$alias]))
			self::$_instances[$alias] = self::_connect($config);
		return self::$_instances[$alias];
	}

	/**
	 *
	 * @param string $query
	 * @return PDOStatement
	 */
	public function query($query) {
		$result = parent::query($query);
		if (is_null($result))
			throw new MoccosPDOException(join(',', $this->errorInfo()));
		return $result;
	}

	/**
	 *
	 * @param string $query
	 * @return PDOStatement
	 */
	public function exec($query) {
		$result = parent::exec($query);
		if ($this->errorCode() !== '00000') {
			throw new MoccosPDOException(join(',', $this->errorInfo()));
		}
		return $result;
	}

	/**
	 * 
	 * @access private
	 * @param array $config
	 * @return MoccosPDO
	 */
	private static function _connect($config) {
		if ($config["driver"] === 'oci') {
			$dsn = sprintf("%s:dbname=//%s:%s/%s",
				$config["driver"],
				$config["host"],
				$config["port"],
				$config["database"]
			);
		} else {
			$dsn = sprintf("%s:host=%s;port=%s;dbname=%s",
				$config["driver"],
				$config["host"],
				$config["port"],
				$config["database"]
			);
		}
		try {
			$pdo = new self($dsn, $config["user"], $config["password"]);
		} catch (PDOException $e) {
			throw new MoccosPDOException('PDO connect error');
		}
		if ($config["driver"] == 'mysql') {
			//$pdo->exec(sprintf('set max_allowed_packet=%s', $config['max_allowed_packet']));
			$pdo->exec(sprintf('set names %s', $config['charset']));
			$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
		}
		return $pdo;
	}
}