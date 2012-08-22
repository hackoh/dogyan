<?php
class Dogyan {
	protected $_autoloads = array();
	protected $_route = array();
	public function __construct() {
		$this->_autoloads = array(
			dirname(__FILE__) . '/modules/cores/',
		);
		require dirname(__FILE__) . '/modules/moccos/core/Moccos.php';
		spl_autoload_register(array($this, 'autoload'));
	}
	public function autoload($className) {
		foreach (array_reverse($this->_autoloads) as $folder) {
			$file = $folder . $className . '.php';
			if (is_file($file)) {
				require $file;
				return true;
			}
		}
	}
	public function run($request = null) {
		$parsed = Router::dispatch($this->_route, $request);
		list ($controllerName, $actionName) = explode('/', $parsed['target']);
		$controllerName = pascalize(ucfirst($controllerName)) . 'Controller';
		var_dump($controllerName);
		$controller = new $controllerName();
		$controller->doAction($actionName, $parsed['arguments']);
	}
	public static function getPath($appClassName) {
		$reflection = new ReflectionClass($appClassName);
		return dirname($reflection->getFileName());
	}
}