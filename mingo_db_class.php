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
   *  maps certain errors to one namespace (ie, key) so we can group table errors
   *  and handle them all with the same code, even though different dbs (eg, mysql and sqlite)
   *  throw different errors (eg, they have different error codes for "table doesn't exist")      
   *
   *  @var  array   
   */
  private $error_map = array();
  
  /**
   *  used by {@link getInstance()} to keep a singleton object, the {@link getInstance()} 
   *  method should be the only place this object is ever messed with so if you want to touch it, DON'T!  
   *  @var mingo_db
   */
  private static $instance = null;
  
  function __construct($type = 0,$db = '',$host = '',$username = '',$password = ''){
  
    $this->error_map['no_table'] = array(/* sqlite */ 'HY000',/* mysql */ '42S02');
  
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
      
        throw new mingo_exception(
          sprintf('Invalid $type (%s) specified, check %s::TYPE_* constants for valid types',$type,__CLASS__)
        );
        break;
    
    }//switch
    
    try{
      
      // reset the debug level for the con_db just in case...
      $this->setDebug($this->getDebug());
      
      // actually connect to the db...
      $this->con_map['connected'] = $this->con_db->connect(
        $db,
        $host,
        $username,
        $password
      );
      
      if($this->hasDebug()){
        if(!is_bool($this->con_map['connected'])){
          throw new mingo_exception(
            sprintf('%s is not the expected return type of boolean',
              gettype($this->con_map['connected'])
            )
          );
        }//if
      }//if
      
    }catch(Exception $e){
      $this->handleException($e);
    }//try/catch
    
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
    if($this->con_db !== null){ $this->con_db->setDebug($val); }//if
  }//method
  function getDebug(){ return $this->hasDebug(); }//method
  function hasDebug(){ return !empty($this->con_map['debug']); }//method
  
  function isConnected(){ return !empty($this->con_map['connected']); }//method
  
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
  
    $ret_bool = false;
  
    try{
    
      $ret_bool = $this->con_db->kill($table,$where_criteria);
      
      if($this->hasDebug()){
        if(!is_bool($ret_bool)){
          throw new mingo_exception(sprintf('%s is not the expected return type of boolean',gettype($ret_bool)));
        }//if
      }//if
    
    }catch(Exception $e){
    
      if($this->handleException($e,$table)){
        $ret_bool = $this->kill($table,$where_criteria);
      }//if
    
    }//try/catch
  
    return $ret_bool;
  
  }//method
  
  /**
   *  get a list of rows matching $where_criteria
   *  
   *  @param  string  $table
   *  @param  mingo_schema  $schema the table schema   
   *  @param  mingo_criteria  $where_criteria
   *  @param  integer|array $limit  either something like 10, or array($limit,$page)   
   *  @return array
   */
  function get($table,mingo_schema $schema,mingo_criteria $where_criteria = null,$limit = 0){
    
    // canary...
    if(empty($table)){ throw new mingo_exception('no $table specified'); }//if
    if($this->hasDebug()){ $this->setTable($table,$schema); }//if
    
    $ret_list = array();
    list($limit,$offset) = $this->getLimit($limit);
    
    try{
    
      $ret_list = $this->con_db->get($table,$schema,$where_criteria,array($limit,$offset));
      
      if($this->hasDebug()){
        if(!is_array($ret_list)){
          throw new mingo_exception(sprintf('%s is not the expected return type of array',gettype($ret_list)));
        }//if
      }//if
    
    }catch(Exception $e){
    
      if($this->handleException($e,$table,$schema)){
        $ret_list = $this->con_db->get($table,$schema,$where_criteria,array($limit,$offset));
      }//if
    
    }//try/catch
    
    return $ret_list;

  }//method
  
  /**
   *  get the first found row in $table according to $where_criteria
   *  
   *  @param  string  $table
   *  @param  mingo_schema  $schema the table schema   
   *  @param  mingo_criteria  $where_criteria
   *  @return array
   */
  function getOne($table,mingo_schema $schema,mingo_criteria $where_criteria = null){
    
    // canary...
    if(empty($table)){ throw new mingo_exception('no $table specified'); }//if
    if($this->hasDebug()){ $this->setTable($table,$schema); }//if
    
    $ret_map = array();
    
    try{
    
      $ret_map = $this->con_db->getOne($table,$where_map);
      
      if($this->hasDebug()){
        if(!is_array($ret_map)){
          throw new mingo_exception(sprintf('%s is not the expected return type of array',gettype($ret_map)));
        }//if
      }//if
    
    }catch(Exception $e){
    
      if($this->handleException($e,$table,$schema)){
        $ret_map = $this->con_db->getOne($table,$where_map);
      }//if
    
    }//try/catch
    
    
    return $ret_map;
    
  }//method
  
  /**
   *  tell how many records match $where_criteria in $table
   *  
   *  @param  string  $table
   *  @param  mingo_schema  $schema the table schema    
   *  @param  mingo_criteria $where_criteria
   *  @return integer the count   
   */
  function getCount($table,mingo_schema $schema,mingo_criteria $where_criteria = null){
  
    // canary...
    if(empty($table)){ throw new mingo_exception('no $table specified'); }//if
    if($this->hasDebug()){ $this->setTable($table,$schema); }//if
    
    $ret_int = 0;
    
    try{
    
      $ret_int = $this->con_db->getCount($table,$schema,$where_criteria);
      
      if($this->hasDebug()){
        if(!is_int($ret_int)){
          throw new mingo_exception(sprintf('%s is not the expected return type of integer',gettype($ret_int)));
        }//if
      }//if
    
    }catch(Exception $e){
    
      if($this->handleException($e,$table,$schema)){
        $ret_int = $this->con_db->getCount($table,$schema,$where_criteria);
      }//if
    
    }//try/catch
    
    
    return $ret_int;
  
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
    if($this->hasDebug()){ $this->setTable($table,$schema); }//if
  
    if(empty($map['_id'])){
    
      try{
    
        // since there isn't an _id, insert...
        $map = $this->con_db->insert($table,$map,$schema);
        
      }catch(Exception $e){
        if($this->handleException($e,$table,$schema)){
          $map = $this->con_db->insert($table,$map,$schema);
        }//if
      }//try/catch
        
    }else{
    
      try{
      
        $id = $map['_id'];
        unset($map['_id']);
        $map = $this->con_db->update($table,$id,$map,$schema);
        
      }catch(Exception $e){
        if($this->handleException($e,$table,$schema)){
          $map = $this->con_db->update($table,$id,$map,$schema);
        }//if
      }//try/catch
        
    }//if
    
    if(is_array($map)){
      
      // make sure _id was set...
      if(empty($map['_id'])){
        throw new mingo_exception('$map returned from either insert or update without _id being set');
      }//if
      
    }else{
    
      if($this->hasDebug()){
        throw new mingo_exception(sprintf('%s is not the expected return type of array',gettype($map)));
      }//if
      
    
    }//if/else
  
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
    
    $ret_bool = false;
    try{
    
      $ret_bool = $this->con_db->killTable($table);
      if($this->hasDebug()){
        if(!is_bool($ret_bool)){
          throw new mingo_exception(sprintf('%s is not the expected return type of boolean',gettype($ret_bool)));
        }//if
      }//if
      
    }catch(Exception $e){
      $this->handleException($e,$table);
    }//try/catch
      
    return $ret_bool;
  
  }//method
  
  /**
   *  get all the tables of the currently connected db
   *
   *  @return array a list of table names
   */
  function getTables(){
  
    if(!$this->isConnected()){ throw new mingo_exception('no db connection found'); }//if
    
    $ret_list = array();
    try{
    
      $ret_list = $this->con_db->getTables();
      if($this->hasDebug()){
        if(!is_array($ret_list)){
          throw new mingo_exception(sprintf('%s is not the expected return type of array',gettype($ret_list)));
        }//if
      }//if
      
    }catch(Exception $e){
      $this->handleException($e);
    }//try/catch
      
    return $ret_list;
  
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
    
    $ret_bool = false;
    try{
    
      $ret_bool = $this->con_db->setTable($table,$schema);
      if($this->hasDebug()){
        if(!is_bool($ret_bool)){
          throw new mingo_exception(sprintf('%s is not the expected return type of boolean',gettype($ret_bool)));
        }//if
      }//if
      
    }catch(Exception $e){
      $this->handleException($e,$table);
    }//try/catch
      
    return $ret_bool;
    
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
    
    $ret_bool = false;
    try{
    
      $ret_bool = $this->con_db->hasTable($table);
      if($this->hasDebug()){
        if(!is_bool($ret_bool)){
          throw new mingo_exception(sprintf('%s is not the expected return type of boolean',gettype($ret_bool)));
        }//if
      }//if
      
    }catch(Exception $e){
      $this->handleException($e,$table);
    }//try/catch
      
    return $ret_bool;
    
  }//method
  
  /**
   *  set the limit/page
   *  
   *  this basically normalizes the limit and the page so you don't have to worry about one or the other
   *  not being present in the implemenations         
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
  
  /**
   *  takes any exception and maps it to a mingo_exception
   *  
   *  this method will also try and fix any exception that match codes found in {@link $error_map},
   *  if it does successfully resolve the exception, it will return true giving the failed method
   *  a chance to redeem itself.
   *  
   *  @param  Exception $e  any exception
   *  @param  string  $table  the table the exception was encountered on
   *  @param  mingo_schema  $table's schema
   *  @return boolean true if the exception was resolved, false if it wasn't
   *  @throws mingo_exception all exceptions get re-thrown as mingo_exception if not resolved
   */
  private function handleException(Exception $e,$table = '',mingo_schema $schema = null){
  
    $e_resolved = false;
  
    // only try and resolve an exception if we have some meta data...
    if(!empty($table) && ($schema !== null)){
    
      $e_code = $e->getCode();
      if(!empty($e_code)){
      
        if(in_array($e_code,$this->error_map['no_table'])){
        
          // table was missing, so assure the table and everything...
          $e_resolved = $this->setTable($table,$schema);
        
        }//if
        
      }//if
      
    }//if
    
    if(!$e_resolved){
    
      if($e instanceof mingo_exception){
      
        // just pass a mingo_exception on up the chain...
        throw $e;
      
      }else{
      
        // map the caught exception to a mingo_exception and pass it on up the chain...
        throw new mingo_exception(
          sprintf(
            '%s %s: "%s" originally thrown in %s:%s',
            get_class($e),
            $e->getCode(),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
          ),
          $e->getCode()
        );
        
      }//if/else
      
    }//if
  
    return $e_resolved;
  
  }//method
  
}//class     
