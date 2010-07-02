<?php

/**
 *  base class for all the interfaces that mingo_db can use   
 *
 *  if you want to make an interface for mingo, just create a class that extends
 *  this class and implement all the functions below. A type and row in the mingo_db::connect()
 *  will also have to be added.
 *  
 *  @notes
 *    - when implementing the interface, you don't really have to worry about error checking
 *      because mingo_db will do all the error checking before calling the function from this
 *      class
 *    - you don't have to worry about throwing mingo_exceptions either because mingo_db
 *      will catch any exceptions from all the abstract methods and wrap them in a mingo_exception.
 *    - in php 5.3 you can set default values for any of the abstract method params without
 *      an error being thrown, in php <5.3 the implemented method signatures have to match
 *      the abstract signature exactly 
 *    - there are certain reserved rows any implementation will have to deal with:
 *      - _id = the unique id assigned to a newly inserted row, this is a 24 character
 *              randomly created string, if you don't want to make your own, and there
 *              isn't an included one (like mongo) then you can use {@link getUniqueId()}
 *              defined in this class
 *      - row_id = this is an auto increment row, ie, the row number. This technically only
 *                 needs to be generated when mingo_schema::setInc() is used in the ORM's
 *                 __construct() method, sql it gets created regardless though since it's so
 *                 easy to do 
 *  
 *  @link http://www.php.net/manual/en/language.oop5.abstract.php
 *  
 *  @abstract 
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
   *  hold all the queries this instance has run
   *  @var  array
   */
  protected $query_list = array();
  
  /**
   *  create an instance, this method is final to assure that all instance creation
   *  is the same for any class that extends this class
   */
  final function __construct(){
    $this->start();
  }//method
  
  /**
   *  turn debugging on or off
   *  
   *  @param  boolean $val
   */        
  public function setDebug($val){ $this->con_map['debug'] = !empty($val); }//method
  
  /**
   *  get the currently set debug level
   *  
   *  @return boolean
   */
  public function getDebug(){ return $this->hasDebug(); }//method
  
  /**
   *  true if debug is on, false if it's off
   *  
   *  @return boolean
   */
  public function hasDebug(){ return !empty($this->con_map['debug']); }//method
  
  /**
   *  returns true if {@link connect()} has been called and returned true
   *  
   *  @return boolean
   */
  public function isConnected(){ return !empty($this->con_map['connected']); }//method
  
  /**
   *  returns a list of the queries executed  
   *  
   *  the class that implements this interface should track the queries using {@link $query_list}
   *  but that isn't assured. For example, the SQL interfaces only save queries when debug is true   
   *      
   *  @return array a list of queries executed by this db interface instance
   */
  public function getQueries(){ return $this->query_list; }//method
  
  /**
   *  init function that is called when a new instance is created
   */
  abstract protected function start();
  
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
   *  delete the records that match $where_criteria in $table
   *  
   *  this method will not delete an entire table's contents, you will have to do
   *  that manually.         
   *  
   *  @param  string  $table
   *  @param  mingo_schema  $schema the table schema    
   *  @param  mingo_criteria $where_criteria
   *  @return boolean
   */
  abstract public function kill($table,mingo_schema $schema,mingo_criteria $where_criteria);
  
  /**
   *  get a list of rows matching $where_map
   *  
   *  @param  string  $table
   *  @param  mingo_schema  $schema the table schema
   *  @param  mingo_criteria  $where_map
   *  @param  array $limit  array($limit,$offset)   
   *  @return array   
   */
  abstract public function get($table,mingo_schema $schema,mingo_criteria $where_criteria = null,$limit = array());
  
  /**
   *  get the first found row in $table according to $where_map find criteria
   *  
   *  @param  string  $table
   *  @param  mingo_schema  $schema the table schema  
   *  @param  mingo_criteria  $where_criteria
   *  @return array
   */
  abstract public function getOne($table,mingo_schema $schema,mingo_criteria $where_criteria = null);
  
  /**
   *  tell how many records match $where_criteria in $table
   *  
   *  @param  string  $table
   *  @param  mingo_schema  $schema the table schema    
   *  @param  mingo_criteria  $where_criteria
   *  @return integer the count
   */
  abstract public function getCount($table,mingo_schema $schema,mingo_criteria $where_criteria = null);
  
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
  abstract public function getTables($table = '');
  
  /**
   *  adds a table to the db
   *  
   *  this should check to see if the table exists before adding it, checking if the
   *  table existed was done in mingo_db::setTable() but had to be removed so that agile
   *  development could be achieved (adding tables and indexes without calling ->setTable()
   *  explicitely.    
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
  
  /**
   *  hanlde an error state
   *  
   *  this is handy for trying to add tables or indexes if they don't exist so the db
   *  handler can then try the queries that errored out again
   *  
   *  @param  Exception $e  the exception that was raised
   *  @param  string  $table  the table name
   *  @param  mingo_schema  $schema the table schema
   *  @return boolean false on failure to solve the exception, true if $e was successfully resolved
   */
  abstract public function handleException(Exception $e,$table,mingo_schema $schema);
  
  /**
   *  generates a 24 character unique id for the _id of an inserted row
   *
   *  @param  string  $table  the table to be used in the hash
   *  @return string  a 24 character id string   
   */
  protected function getUniqueId($table = ''){
    
    // took out x and b, because 1 id started 0x which made it a hex number, and b just because
    $str = '1234567890acdefghijklmnpqrstuvwyz';
    $id = uniqid(sprintf('%s%s',$str[rand(0,32)],$str[rand(0,32)]),true);
    return str_replace('.','',$id);
    
  }//method
  
}//class     
