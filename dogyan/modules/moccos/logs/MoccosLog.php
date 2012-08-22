<?php
/**
 * 
 * @author Ikezaki
 *
 */
class MoccosLog  {
	
	/**
	 * 
	 * @var unknown_type
	 */
	private static $_instance;
	
	/**
	 * 
	 * @var unknown_type
	 */
	private $_dumped = false;
	
	/**
	 * 
	 * @var unknown_type
	 */
	public $_logs = array();
	
	/**
	 * 
	 * @var unknown_type
	 */
	private $_startTime;
	
	/**
	 * 
	 */
	private function __construct() {
		list ($micro, $sec) = explode(' ', microtime());
		$microtime = $sec + $micro;
		$this->_startTime = $microtime;
		$this->_push(MOCCOS_LOG_INFO, 'v ^_^ v  Moccos start.');
	}
	
	/**
	 * 
	 */
	public function __destruct() {
//		if ($this->_dumped === false)
//			$this->_dump();
	}
	
	/**
	 * 
	 */
	private function _dump() {
		$this->_dumped = true;
		$templateFile = MOCCOS_LOGS_DIR.'template/template.html';
		include $templateFile;
	}
	
	/**
	 * 
	 */
	public static function _getInstance() {
		if (self::$_instance === null)
			self::$_instance = new self();
		return self::$_instance;
	}
	
	/**
	 * 
	 * @param unknown_type $level
	 * @param unknown_type $content
	 */
	public function _push($level, $content) {
		if ($level <= MOCCOS_LOG_LEVEL) {
			$backtrace = debug_backtrace();
			list ($micro, $sec) = explode(' ', microtime());
			$microtime = $sec + $micro;
			$record = new MoccosLogRecord();
			$record->lap = $microtime - $this->_startTime;
			$record->class = isset($backtrace[2]['class']) ? $backtrace[2]['class'] : null;
			$record->line = isset($backtrace[1]['line']) ? $backtrace[1]['line'] : null;
			$record->function = isset($backtrace[2]['function']) ? $backtrace[2]['function'] : null;
			$record->content = $content;
			$this->_logs[] = $record;
		}
	}
	
	/**
	 * 
	 * @param unknown_type $level
	 * @param unknown_type $content
	 */
	public static function push($level, $content) {
		self::_getInstance()->_push($level, $content);
	}
	
	/**
	 * 
	 */
	public static function dump() {
		self::_getInstance()->_dump();
	}
	
	/**
	 * 
	 * @param unknown_type $something
	 */
	public static function dumper($something) {
		//ob_start();
		//var_dump($something);
		//return ob_get_clean();
	}
}