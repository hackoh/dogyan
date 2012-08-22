<?php
class IndexExtensionController extends IndexController {
	public function index() {
		// do something
		$this->viewPath = Dogyan::getPath('Poyo') . '/views';
		$this->view = 'index/index.html';
	}
}