<?php
class Poyo extends Dogyan {
	public function __construct() {
		parent::__construct();
		$this->_autoloads[] = dirname(__FILE__) . '/controllers/';
	}
}