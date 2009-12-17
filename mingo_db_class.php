<?php

/**
 *  handle mingo db connections transparently between the different interfaces, this
 *  class is used to establish the singleton, and then allow the map to interact
 *  with the db layer.
 *
 *  @version 0.1
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 11-08-09
 *  @package mingo 
 ******************************************************************************/
class mingo_db {

  const TYPE_MONGO = 1;
  const TYPE_MYSQL = 2;
  const TYPE_SQLITE = 3;

  /**
   *  holds all the connection information this class used
   *  
   *  @var  array associative array
   */
  private $con_map = array();
  
  /**
   *  holds the actual db connection, established by calling {@link connect()}
   *  @var  MongoDb
   */
  private $con_db = null;
  
  /**
   *  used by {@link getInstance()} to keep a singleton object, the {@link getInstance()} 
   *  method should be the only place this object is ever messed with so if you want to touch it, DON'T!  
   *  @var mingo_db
   */
  private static $instance = null;
  
  function __construct($type = 0,$db = '',$host = '',$username = '',$password = ''){
  
    $this->setType($type);
    $this->setDb($db);
    $this->setHost($host);
    
    $this->setUsername($username);
    $this->setPassword($password);

  }//method
  
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
  function connect($type = 0,$db = '',$host = '',$username = '',$password = ''){
  
    // set all the connection variables...
    if(empty($type)){
      if($this->hasType()){
        $type = $this->getType();
      }else{
        throw new mingo_exception('no $type specified');
      }//if/else
    }else{
      $this->setType($type);
    }//if/else
    
    if(empty($db)){
      if($this->hasDb()){
        $db = $this->getDb();
      }else{
        throw new mingo_exception('no $db specified');
      }//if/else
    }else{
      $this->setDb($db);
    }//if/else
    
    if(empty($host)){
      if($this->hasHost()){
        $host = $this->getHost();
      }//if
    }else{
      $this->setHost($host);
    }//if/else
    
    if(empty($username)){
      if($this->hasUsername()){
        $username = $this->getUsername();
      }//if
    }else{
      $this->setUsername($username);
    }//if/else
    
    if(empty($password)){
      if($this->hasPassword()){
        $password = $this->getPassword();
      }//if
    }else{
      $this->setPassword($password);
    }//if/else
    
    switch($type){
    
      case self::TYPE_MONGO:
      
        $this->con_db = new mingo_db_mongo();
        break;
      
      case self::TYPE_MYSQL:
      case self::TYPE_SQLITE:
      
        $this->con_db = new mingo_db_sql($type);
        break;
        
      default:
      
        throw new mingo_exception(sprintf('Invalid $type (%s) specified',$type));
        break;
    
    }//switch
    
    // actually connect to the db...
    $this->con_map['connected'] = $this->con_db->connect(
      $db,
      $host,
      $username,
      $password
    );
    
    // reset the debug level for the con_db just in case...
    $this->setDebug($this->getDebug());
    
    return $this->con_map['connected'];
  
  }//method
  
  /**
   *  forces dbal to follow the singelton pattern 
   *  
   *  {@link http://en.wikipedia.org/wiki/Singleton_pattern}
   *  and keeps all db classes to one instance, {@link $instance} should only 
   *  be messed with in this function
   *     
   *  @return mingo_db  null on failure
   */
  static function getInstance(){
    if(self::$instance === null){ self::$instance = new self; }//if
    return self::$instance;
  }//method

  function setType($val){ $this->con_map['type'] = $val; }//method
  function getType(){ return $this->hasType() ? $this->con_map['type'] : 0; }//method
  function hasType(){ return !empty($this->con_map['type']); }//method
  function isType($val){ return ((int)$this->getType() === (int)$val); }//method
  
  function setDb($val){ $this->con_map['db'] = $val; }//method
  function getDb(){ return $this->hasDb() ? $this->con_map['db'] : ''; }//method
  function hasDb(){ return !empty($this->con_map['db']); }//method

  function setHost($val){ $this->con_map['host'] = $val; }//method
  function getHost(){ return $this->hasHost() ? $this->con_map['host'] : ''; }//method
  function hasHost(){ return !empty($this->con_map['host']); }//method
  
  function setUsername($val){ $this->con_map['username'] = $val; }//method
  function getUsername(){ return $this->hasUsername() ? $this->con_map['username'] : ''; }//method
  function hasUsername(){ return !empty($this->con_map['username']); }//method
  
  function setPassword($val){ $this->con_map['password'] = $val; }//method
  function getPassword(){ return $this->hasPassword() ? $this->con_map['password'] : ''; }//method
  function hasPassword(){ return !empty($this->con_map['password']); }//method
  
  function setDebug($val){
    $this->con_map['debug'] = $val;
    if($this->isConnected()){ $this->con_db->setDebug($val); }//if
  }//method
  function getDebug(){ return $this->hasDebug(); }//method
  function hasDebug(){ return !empty($this->con_map['debug']); }//method
  
  function isConnected(){ return !empty($this->con_map['connected']); }//method
  
  /**
   *  tell how many records match $where_criteria in $table
   *  
   *  @param  string  $table
   *  @param  mingo_criteria $where_criteria
   *  @return integer the count   
   */
  function getCount($table,mingo_criteria $where_criteria = null){
  
    // canary...
    if(empty($table)){ throw new mingo_exception('no $table specified'); }//if
    return $this->con_db->getCount($table,$where_criteria);
  
  }//method
  
  /**
   *  delete the records that match $where_criteria in $table
   *  
   *  @param  string  $table
   *  @param  mingo_criteria $where_criteria
   *  @return boolean
   */
  function kill($table,mingo_criteria $where_criteria){
  
    // canary...
    if(empty($table)){ throw new mingo_exception('no $table specified'); }//if
    if(empty($where_criteria)){
      throw new mingo_exception('no $where_criteria specified');
    }else{
      if(!$where_criteria->has()){
        throw new mingo_exception('aborting delete because $where_criteria was empty');
      }//if
    }//if/else
  
    return $this->con_db->kill($table,$where_criteria);
  
  }//method
  
  /**
   *  get a list of rows matching $where_criteria
   *  
   *  @param  string  $table
   *  @param  mingo_criteria  $where_criteria
   *  @param  integer|array $limit  either something like 10, or array($limit,$page)   
   *  @return array
   */
  function get($table,mingo_criteria $where_criteria = null,$limit = 0){
    
    // canary...
    if(empty($table)){ throw new mingo_exception('no $table specified'); }//if
    
    list($limit,$offset) = $this->getLimit($limit);
    return $this->con_db->get($table,$where_criteria,array($limit,$offset));

  }//method
  
  /**
   *  get the first found row in $table according to $where_criteria
   *  
   *  @param  string  $table
   *  @param  mingo_criteria  $where_criteria
   *  @return array
   */
  function getOne($table,mingo_criteria $where_criteria = null){
    
    // canary...
    if(empty($table)){ throw new mingo_exception('no $table specified'); }//if
    
    return $this->con_db->getOne($table,$where_map);
    
  }//method
  
  /**
   *  insert $map into $table
   *  
   *  @param  string  $table  the table name
   *  @param  array $map  the key/value map that will be added to $table
   *  @param  mingo_schema  $schema the table schema  
   *  @return array the $map that was just saved with _id set
   *     
   *  @throws mingo_exception on any failure               
   */
  function set($table,$map,mingo_schema $schema){
  
    // canary...
    if(empty($table)){ throw new mingo_exception('no $table specified'); }//if
    if(empty($map)){ throw new mingo_exception('no point in setting an empty $map'); }//if
    if(empty($schema)){ throw new mingo_exception('no $schema specified'); }//if
  
    if(empty($map['_id'])){
      // since there isn't an _id, insert...
      $map = $this->con_db->insert($table,$map,$schema);
    }else{
      $id = $map['_id'];
      unset($map['_id']);
      $map = $this->con_db->update($table,$id,$map,$schema);
    }//if
    
    // make sure _id was set...
    if(empty($map['_id'])){
      throw new mingo_exception('$map returned from either insert or update without _id being set');
    }//if
  
    return $map;
  
  }//method
  
  /**
   *  deletes a table
   *  
   *  @param  string  $table  the table to delete from the db
   *  @return boolean
   */
  function killTable($table){
    
    // canary...
    if(empty($table)){ throw new mingo_exception('no $table given'); }//if
    return $this->con_db->killTable($table);
  
  }//method
  
  /**
   *  get all the tables of the currently connected db
   *
   *  @return array a list of table names
   */
  function getTables(){
  
    if(!$this->isConnected()){ throw new mingo_exception('no db connection found'); }//if
    return $this->con_db->getTables();
  
  }//method
  
  /**
   *  adds a table to the db
   *  
   *  @param  string  $table  the table to add to the db
   *  @param  mingo_schema  a schema object that defines indexes, etc. for this 
   *  @return boolean
   */
  function setTable($table,mingo_schema $schema){
  
    // canary...
    if(empty($table)){ throw new mingo_exception('no $table given'); }//if
    if(empty($schema)){ throw new mingo_exception('$schema must be present'); }//if
    if($this->hasTable($table)){ return true; }//if
    
    return $this->con_db->setTable($table,$schema);
    
  }//method
  
  /**
   *  check to see if a table is in the db
   *  
   *  @param  string  $table  the table to check
   *  @return boolean
   */
  function hasTable($table){
  
    // canary...
    if(empty($table)){ throw new mingo_exception('no $table given'); }//if
    return $this->con_db->hasTable($table);
  
  }//method
  
  /**
   *  set the limit/offset
   *  
   *  @param  integer|array $limit  can be either int (eg, limit=10) or array (eg, array($limit,$page)
   *  @return array array($limit,$page)
   */
  protected function getLimit($limit){
  
    // canary...
    if(empty($limit)){ return array(0,0); }//if
  
    $ret_limit = $ret_offset = 0;
  
    if(is_array($limit)){
      $ret_limit = (int)$limit[0];
      if(isset($limit[1])){
        $limit[1] = (int)$limit[1];
        $ret_offset = (empty($limit[1]) ? 0 : ($limit[1] - 1)) * $ret_limit;
      }//if
    }else{
      $ret_limit = (int)$limit;
    }//if/else
  
    return array($ret_limit,$ret_offset);
  
  }//method
  
  /**
   *  close the db connection so we don't run afoul of anything when serializing this
   *  class   
   *  
   *  http://www.php.net/manual/en/language.oop5.magic.php#language.oop5.magic.sleep
   *  
   *  @return the names of all the variables that should be serialized      
   */
  function __sleep(){
    $this->con_db = null;
    $this->con_map['connected'] = false;
    return array_keys(get_object_vars($this));
  }//method
  
}//class     
