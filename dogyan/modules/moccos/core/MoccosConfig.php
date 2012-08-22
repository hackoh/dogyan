<?php
//define('MOCCOS_LOG_NONE', 0);
//define('MOCCOS_LOG_INFO', 1);
//define('MOCCOS_LOG_DEBUG', 2);
//define('MOCCOS_LOG_TRACE', 3);
// log level setting
define('MOCCOS_LOG_LEVEL', MOCCOS_LOG_INFO);

/**
 * 
 * @author Ikezaki
 *
 */
class MoccosConfig {
	
	/**
	 * 
	 * @access private
	 * @var string
	 */
	private static $_switch = 'develop';
	
	/**
	 * 
	 * @access private
	 * @var array
	 */
	private static $_config = array(
		'develop' => array(
			"driver" => "mysql",
			"host" => "impv.net",
			"port" => "3306",
			"database" => "moccos_test",
			"user" => "root",
			"password" => "ahensensou",
			"charset" => "utf8",
			"create" => true,
			"alter" => true,
		),
	);

	/**
	 * 
	 * @param string $key
	 * @return array
	 */
	public static function getConfig($key = null) {
		if ($key === null)
			return self::$_config[self::$_switch];
		return self::$_config[$key];
	}

	/**
	 * 
	 * @param string $switch
	 * @return array
	 */
	public static function switchTo($switch = 'develop') {
		self::$_switch = $switch;
	}

	/**
	 * 
	 * @param string $name
	 * @param array $config
	 * @param boolean $useThis
	 */
	public static function append($name, $config, $useThis = false) {
		self::$_config[$name] = $config;
		if ($useThis)
			self::switchTo($name);
	}
}