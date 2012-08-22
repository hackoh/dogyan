<?php
interface MoccosDriverInterface {
	/**
	 * 
	 * @param MoccosFinderInterface $finder
	 * @return array
	 */
	public function select(MoccosFinderInterface $finder);
	public function update(MoccosInterface $moccos);
	public function insert(MoccosInterface $moccos);
	public function delete(MoccosInterface $moccos);
	public function lastInsertId();
	public static function getInstance($className);
}