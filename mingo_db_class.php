<?php

/**
 *  handle mongo db connections 
 *  
 *
 *  @note mongo db inserts and updates always return true, so you have to check the last
 *        error to see if they were successful, I found this out through this link:
 *        http://markmail.org/message/ghapbonzag2uim2p     
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
   *  used by {@link getInstance()} to keep a singleton object, the {@link getINstance()} 
   *  method should be the only place this object is ever messed with so if you want to touch it, DON'T!  
   *  @var mingo_db
   */
  private static $instance = null;
  
  function __construct($type = self::TYPE_MONGO,$db = '',$host = '',$username = '',$password = ''){
  
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
  function connect($type = self::TYPE_MONGO,$db = '',$host = '',$username = '',$password = ''){
  
    // set all the connection variables...
    if(empty($type)){
      if($this->hasType()){
        $type = $this->getType();
      }else{
        throw new mingo_exception('no $type specified');
      }//if/else
    }else{
      $this->setDb($type);
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
  
  function setOption($val){ $this->option = $val; }//method
  function hasOption($val){ return $val && $this->option; }//method
  
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
  
  function isConnected(){ return !empty($this->con_map['connected']); }//method
  
  /**
   *  tell how many records match $where_map in $table
   *  
   *  @param  string  $table
   *  @param  array $where_map
   *  @return integer the count   
   */
  function count($table,$where_map = array()){
  
    $ret_int = 0;
    $table = $this->getTable($table);
    list($where_map) = $this->getCriteria($where_map);
    if(empty($where_map)){
      $ret_int = $table->count();
    }else{
      $cursor = $table->find($where_map);
      $ret_int = $cursor->count();
    }//if/else
    return $ret_int;
  
  }//method
  
  /**
   *  delete the records that match $where_map in $table
   *  
   *  @param  string  $table
   *  @param  array $where_map
   *  @return boolean
   */
  function delete($table,$where_map = array()){
  
    $table = $this->getTable($table);
    list($where_map) = $this->getCriteria($where_map);
    return $table->remove($where_map);
  
  }//method
  
  /**
   *  get a list of rows matching $where_map
   *  
   *  @param  string  $table
   *  @param  mingo_criteria  $where_criteria if you want sorting power, use mingo_criteria
   *  @param  integer|array $limit  either something like 10, or array($limit,$page)   
   *  @return array
   */
  function get($table,mingo_criteria $where_criteria = null,$limit = 0){
    
    $ret_list = array();
    list($limit,$offset) = $this->getLimit($limit);
    return $this->con_db->get($table,$where_criteria,array($limit,$offset));

  }//method
  
  /**
   *  get the first found row in $table according to $where_map find criteria
   *  
   *  @param  string  $table
   *  @param  array|mingo_criteria  $where_map
   *  @return array
   */
  function getOne($table,$where_map = array()){
    
    $table = $this->getTable($table);
    list($where_map) = $this->getCriteria($where_map);
    $ret_map = $table->findOne($where_map);
    return empty($ret_map) ? array() : $ret_map;

  }//method
  
  /**
   *  increment $field in $table by $count according to $where_map search criteria
   *  
   *  @param  string  $table
   *  @param  string  $field  the field to increment
   *  @param  array $where_map  the find criteria
   *  @param  integer $count  how many you want to increment $field by
   *  @return boolean
   */
  function bump($table,$field,$where_map,$count = 1){
  
    // canary...
    if(empty($field)){ throw new mingo_exception('no $field specified'); }//if
    if(empty($count)){ return true; }//if
  
    $table = $this->getTable($table);
    list($where_map) = $this->getCriteria($where_map);
    
    $c = new mingo_criteria();
    $c->inc($field,$count);
    
    return $this->update($table,$c,$where_map);
    
  }//method
  
  /**
   *  insert $map into $table
   *  
   *  @param  string  $table  the table name
   *  @param  array|mingo_criteria  $map  the key/value map that will be added to $table
   *  @return array the $map that was just saved
   *  @param  mingo_schema  $schema the table schema, not really needed for Mongo, but important
   *                                for sql   
   *  @throws mingo_exception on any failure               
   */
  function insert($table,$map,mingo_schema $schema){
  
    if(empty($table)){ throw new mingo_exception('no $table specified'); }//if
    if(empty($map)){ throw new mingo_exception('no point in inserting an empty $map'); }//if
    if(empty($schema)){ throw new mingo_exception('no $schema specified'); }//if
  
    return $this->con_db->insert($table,$map,$schema);
    
  }//method
  
  /**
   *  update $map from $table using $where_map as criteria
   *  
   *  @param  string  $table  the table name
   *  @param  array $map  the key/value map that will be added to $table
   *  @param  mingo_criteria  $where_map  if empty, $map is checked for '_id'
   *  @param  mingo_schema  $schema the table schema, not really needed for Mongo, but important
   *                                for sql         
   *  @return array the $map that was just saved
   *  @throws mingo_exception on any failure
   */
  function update($table,$map,$where_map = array(),mingo_schema $schema){
    
    // canary...
    if(empty($schema)){ throw new mingo_exception('no $schema specified'); }//if
    if(empty($where_map)){
      if(empty($map['_id'])){
        // since there isn't a where map, and no unique id, insert it instead...
        return $this->insert($table,$map,$schema);
      }else{
        $where_map = array('_id' => $map['_id']);
      }//if
    }//if
    
    // clean up before updating...
    if(isset($map['_id'])){
      $ret_id = $map['_id'];
      unset($map['_id']);
    }//if
    
    return $this->con_db->update($table,$map,$where_map);
  
  }//method
  
  /**
   *  adds an index to $table
   *  
   *  @link http://www.mongodb.org/display/DOCS/Indexes
   *      
   *  @param  string  $table  the table to add the index to
   *  @param  array $map  usually something like array('field_name' => 1)
   *  @return boolean
   */
  function setIndex($table,$map){
    
    // canary...
    if(empty($map)){ throw new mingo_exception('no $map given'); }//if
    
    $table = $this->getTable($table);
    return $table->ensureIndex($map);
  
  }//method
  
  
  /**
   *  deletes a table
   *  
   *  @param  string  $table  the table to delete from the db
   *  @return boolean
   */
  function killTable($table){
    
    // canary...
    if(empty($table)){ throw new mingo_exception('you are killing an empty $table'); }//if
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
    if(empty($schema)){ throw new mingo_exception('if using a sql db, $schema must be present'); }//if
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
   *  assures a $where_map contains the right information to make a call against a table
   *  
   *  @param  array|mingo_criteria  $where_map      
   *  @return array array($where_map,$sort_map), the $where_map and $sort_map with values assured
   */
  protected function getCriteria($where_map){
  
    // canary...
    if(empty($where_map)){ return array(array(),array()); }//if
    if(!is_array($where_map) && !is_object($where_map)){
      throw new mingo_exception('$where_map is not an associative array or mingo_criteria instance');
    }//if
  
    $sort_map = array();
    
    if($where_map instanceof mingo_criteria){
      list($where_map,$sort_map) = $where_map->get();
    }//if
  
    // assure the _id field is the right type...
    if(isset($where_map['_id'])){
      if(!($where_map['_id'] instanceof MongoId)){
      
        if(is_array($where_map['_id'])){
        
          // make sure the whole list contains the right id type...
          foreach($where_map['_id'] as $key => $id){
            if(!($id instanceof MongoId)){
              $where_map['_id'][$key] = new MongoId($id);
            }//if
          }//foreach
          
          // build an in query for all the _ids...
          $c = new mingo_criteria();
          $c->in_id($where_map['_id']);
          list($new_where_map) = $c->get();
          $where_map['_id'] = $new_where_map['_id'];
          
        }else{
      
          $where_map['_id'] = new MongoId($where_map['_id']);  
        
        }//if/else
      
      }//if
    }//if
    
    return array($where_map,$sort_map);
      
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
  
}//class     
