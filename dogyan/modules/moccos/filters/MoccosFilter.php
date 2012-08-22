<?php
/**
 * 
 * @author Ikezaki
 *
 */
class MoccosFilter {
	
	/**
	 * 
	 * @var unknown_type
	 */
	public $type;
	
	/**
	 * 
	 * @var unknown_type
	 */
	public $args;
	
	/**
	 * 
	 * @param unknown_type $type
	 * @param unknown_type $args
	 */
	public function __construct($type = null, $args = null) {
		$this->type = $type;
		$this->args = $args;
	}
	
	/**
	 * 
	 */
	public function isEmpty() {
		return $this->type === null;
	}
	
	
	/**
	 * 
	 * @param unknown_type $property
	 * @param unknown_type $value
	 */
	public static function not($property, $value) {
		$filter = new MoccosValueFilter('<>', $property, $value);
		return $filter;
	}
	
	/**
	 * 
	 * @param unknown_type $property
	 * @param unknown_type $value
	 */
	public static function equal($property, $value) {
		$filter = new MoccosValueFilter('=', $property, $value);
		return $filter;
	}
	
	/**
	 * 
	 * @param unknown_type $property
	 * @param unknown_type $value
	 */
	public static function over($property, $value) {
		$filter = new MoccosValueFilter('>', $property, $value);
		return $filter;
	}
	
	/**
	 * 
	 * @param unknown_type $property
	 * @param unknown_type $value
	 */
	public static function andOver($property, $value) {
		$filter = new MoccosValueFilter('>=', $property, $value);
		return $filter;
	}
	
	/**
	 * 
	 * @param unknown_type $property
	 * @param unknown_type $value
	 */
	public static function less($property, $value) {
		$filter = new MoccosValueFilter('<', $property, $value);
		return $filter;
	}
	
	/**
	 * 
	 * @param unknown_type $property
	 * @param unknown_type $value
	 */
	public static function andLess($property, $value) {
		$filter = new MoccosValueFilter('<=', $property, $value);
		return $filter;
	}
	
	/**
	 * 
	 * @param unknown_type $property
	 * @param unknown_type $value
	 */
	public static function leftLike($property, $value) {
		$filter = new MoccosValueFilter('%.', $property, $value);
		return $filter;
	}
	
	/**
	 * 
	 * @param unknown_type $property
	 * @param unknown_type $value
	 */
	public static function rightLike($property, $value) {
		$filter = new MoccosValueFilter('.%', $property, $value);
		return $filter;
	}
	
	/**
	 * 
	 * @param unknown_type $property
	 * @param unknown_type $value
	 */
	public static function bothLike($property, $value) {
		$filter = new MoccosValueFilter('%.%', $property, $value);
		return $filter;
	}
	
	/**
	 * 
	 * @param unknown_type $property
	 * @param unknown_type $value
	 */
	public static function in($property, $value) {
		$filter = new MoccosValueFilter('()', $property, $value);
		return $filter;
	}
	
	/**
	 * 
	 * @param unknown_type $filters
	 */
	public static function andLink($filters) {
		$filter = new MoccosLinkFilter('&&', $filters);
		return $filter;
	}
	
	/**
	 * 
	 * @param unknown_type $filters
	 */
	public static function orLink($filters) {
		$filter = new MoccosLinkFilter('||', $filters);
		return $filter;
	}
	
	public static function search($properties, $keywords) {
		$andLinks = array();
		foreach ($keywords as $keyword) {
			$orLinks = array();
			foreach ($properties as $peoperty) {
				$orLinks[] = MoccosFilter::bothLike($peoperty, $keyword);
			}
			$andLinks[] = MoccosFilter::orLink($orLinks);
		}
		return MoccosFilter::andLink($andLinks);
	}
}

/**
 * 
 * @author Ikezaki
 *
 */
class MoccosValueFilter extends MoccosFilter {
	public function __construct($type, $property, $value) {
		parent::__construct($type, array(
			'property' => $property,
			'value' => $value,
		));
	}
}
/**
 * 
 * @author Ikezaki
 *
 */
class MoccosLinkFilter extends MoccosFilter {
	public function __construct($type, $filters) {
		parent::__construct($type, $filters);
	}
}