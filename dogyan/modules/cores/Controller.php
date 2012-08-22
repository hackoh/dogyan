<?php
class Controller {
	public $view;
	public $viewPath;
	private $_vars = array();
	public function __construct() {
		if ($this->viewPath === null) {
			$reflection = new ReflectionClass($this);
			$this->viewPath = dirname(dirname($reflection->getFileName())) . '/views';
		}
	}
	public function doAction($action, $arguments) {
		$result = call_user_func_array(array($this, $action), $arguments);
		if ($result !== false) {
			if ($this->view === null) $this->view = str_replace('controller', '', strtolower(get_class($this))) . '/' . $action . '.html';
			if (file_exists($this->viewPath . '/' . $this->view)) echo View::render($this->viewPath . '/' . $this->view, $this->_vars);
			else throw new Exception();
		}
	}
	public function set($key, $value) {
		$this->_vars[$key] = $value;
	}
}