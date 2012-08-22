<?php
class Bar extends Poyo {
	public function __construct() {
		parent::__construct();
		$this->_autoloads[] = dirname(__FILE__) . '/controllers/';
		$this->_route = array_merge($this->_route, require dirname(__FILE__) . '/configs/route.php');
	}
}