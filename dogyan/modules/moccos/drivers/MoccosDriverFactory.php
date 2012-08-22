<?php
/**
 * 
 * @author Ikezaki
 *
 */
class MoccosDriverFactory {
	/**
	 * 
	 * @access private
	 * @var array
	 */
	private static $_instances = array();

	/**
	 * 
	 * @param string $className
	 * @return MoccosDriverInterface
	 */
	public static function getInstance($className) {
		if (!isset(self::$_instances[$className])) {
			$configKey = get_user_prop($className, '_configKey');
			$config = MoccosConfig::getConfig($configKey);
			$driverName = 'Moccos' . ucfirst($config['driver']) . 'Driver';
			self::$_instances[$className] =  new $driverName($className);
		}
		return self::$_instances[$className];
	}
}