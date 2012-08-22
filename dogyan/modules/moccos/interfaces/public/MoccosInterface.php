<?php
interface MoccosInterface {
	/**
	 * 
	 * @return bool
	 */
	public function save();

	/**
	 * 
	 * @return bool
	 */
	public function drop();
	
	/**
	 * 
	 * @return string
	 */
	public function toJSON();
	
	/**
	 * 
	 * @param array
	 * @return MoccosIteratorInterface
	 */
	public static function find(array $options);
	
	/**
	 * 
	 * @param array
	 * @return MoccosInterface
	 */
	public static function findFirst(array $options);
	
	/**
	 * 
	 * @param ConditionInterface
	 * @return int
	 */
	public static function count(array $options);
	
	public function getClassName();
	public function getPrimaryKey();
}