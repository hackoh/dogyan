<?php
class MoccosValidateException extends MoccosException {
	public $result;
	public function __construct($result) {
		$this->result = $result;
	}
}