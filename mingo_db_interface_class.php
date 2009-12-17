<?php

/**
 *  base class for all the interfaces that mingo_db can use   
 *
 *  if you want to make an interface for mingo, just create a class that extends
 *  this class and implement all the functions below. A type and row in the mingo_db::connect()
 *  will also have to be added.
 *  
 *  @note when implementing the interface, you don't really have to worry about error checking
 *        because mingo_db will do all the error checking before calling the function from this
 *        class      
 *  
 *  @link http://www.php.net/manual/en/language.oop5.abstract.php
 *  
 *  @version 0.1
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 12-16-09
 *  @package mingo 
 ******************************************************************************/
abstract class mingo_db_interface {

  /**
   *  holds all the connection information this class used
   *  
   *  @var  array associative array
   */
  protected $con_map = array();
  
  /**
   *  holds the actual db connection, established by calling {@link connect()}
   *  @var  MongoDb
   */
  protected $con_db = null;
  
  /**
   *  turn debugging on or off
   *  
   *  @param  boolean $val
   */        
  function setDebug($val){ $this->con_map['debug'] = !empty($val); }//method
  
  /**
   *  get the currently set debug level
   *  
   *  @return boolean
   */
  function getDebug(){ return $this->hasDebug(); }//method
  
  /**
   *  true if debug is on, false if it's off
   *  
   *  @return boolean
   */
  function hasDebug(){ return !empty($this->con_map['debug']); }//method
  
  /**
   *  returns true if {@link connect()} has been called and returned true
   *  
   *  @return boolean
   */
  function isConnected(){ return !empty($this->con_map['connected']); }//method
  
  /**
   *  connect to the db
   *  
   *  @param  integer $type one of the self::TYPE_* constants   
   *  @param  string  $db the db to use, defaults to {@link getDb()}
   *  @param  string  $host the host to use, defaults to {@link getHost()}. if you want a specific
   *                        port, attach it to host (eg, localhost:27017 or example.com:27017)            
   *  @param  string  $username the username to use, defaults to {@link getUsername()}
   *  @param  string  $password the password to use, defaults to {@link getPassword()}   
   *  @return boolean
   *  @throws mingo_exception   
   */
  abstract public function connect($db,$host,$username,$password);
  
  /**
   *  tell how many records match $where_criteria in $table
   *  
   *  @param  string  $table
   *  @param  mingo_criteria  $where_criteria
   *  @return integer the count
   */
  abstract public function getCount($table,mingo_criteria $where_criteria = null);
  
  /**
   *  delete the records that match $where_criteria in $table
   *  
   *  this method will not delete an entire table's contents, you will have to do
   *  that manually.         
   *  
   *  @param  string  $table
   *  @param  mingo_criteria  $where_criteria
   *  @return boolean
   */
  abstract public function kill($table,mingo_criteria $where_criteria);
  
  /**
   *  get a list of rows matching $where_map
   *  
   *  @param  string  $table
   *  @param  mingo_criteria  $where_map
   *  @param  array $limit  array($limit,$offset)   
   *  @return array   
   */
  abstract public function get($table,mingo_criteria $where_criteria = null,$limit = array());
  
  /**
   *  get the first found row in $table according to $where_map find criteria
   *  
   *  @param  string  $table
   *  @param  mingo_criteria  $where_criteria
   *  @return array
   */
  abstract public function getOne($table,mingo_criteria $where_criteria = null);
  
  /**
   *  insert $map into $table
   *  
   *  @param  string  $table  the table name
   *  @param  array|mingo_criteria  $map  the key/value map that will be added to $table
   *  @param  mingo_schema  $schema the table schema   
   *  @return array the $map that was just saved, with the _id set
   *  @throws mingo_exception on any failure               
   */
  abstract public function insert($table,$map,mingo_schema $schema);
  
  /**
   *  update $map from $table using $_id
   *  
   *  @param  string  $table  the table name
   *  @param  string  $_id the _id attribute from $map   
   *  @param  array $map  the key/value map that will be added to $table
   *  @param  mingo_schema  $schema the table schema      
   *  @return array the $map that was just saved with _id set
   *     
   *  @throws mingo_exception on any failure
   */
  abstract public function update($table,$_id,$map,mingo_schema $schema);
  
  /**
   *  adds an index to $table
   *  
   *  @param  string  $table  the table to add the index to
   *  @param  array $map  usually something like array('field_name' => 1), this isn't need for sql
   *                      but it's the same way to keep compatibility with Mongo   
   *  @return boolean
   */
  abstract public function setIndex($table,$map);
  
  
  /**
   *  deletes a table
   *  
   *  @param  string  $table  the table to delete from the db
   *  @return boolean
   */
  abstract public function killTable($table);
  
  /**
   *  get all the tables of the currently connected db
   *  
   *  @return array a list of table names
   */
  abstract public function getTables($table);
  
  /**
   *  adds a table to the db
   *      
   *  @param  string  $table  the table to add to the db
   *  @param  mingo_schema  $schema the table schema    
   *  @return boolean
   */
  abstract public function setTable($table,mingo_schema $schema);
  
  /**
   *  check to see if a table is in the db
   *  
   *  @param  string  $table  the table to check
   *  @return boolean
   */
  abstract public function hasTable($table);
  
}//class     
