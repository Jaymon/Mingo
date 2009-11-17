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
  
  /**
   *  holds auto increment information
   *  
   *  @see  setInc(), update(), insert()      
   *  @var  array
   */
  private $inc_map = array();
  
  function __construct($db = '',$host = '',$username = '',$password = ''){
  
    $this->setDb($db);
    $this->setHost($host);
    
    $this->setUsername($username);
    $this->setPassword($password);
    
    $this->inc_map['table'] = array();
  
  }//method
  
  /**
   *  connect to the db
   *  
   *  @param  string  $db the db to use, defaults to {@link getDb()}
   *  @param  string  $host the host to use, defaults to {@link getHost()}. if you want a specific
   *                        port, attach it to host (eg, localhost:27017 or example.com:27017)            
   *  @param  string  $username the username to use, defaults to {@link getUsername()}
   *  @param  string  $password the password to use, defaults to {@link getPassword()}   
   *  @return boolean
   *  @throws mingo_exception   
   */
  function connect($db = '',$host = '',$username = '',$password = ''){
  
    // set all the connection variables...
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
      }else{
        throw new mingo_exception('no $host specified');
      }//if/else
    }else{
      $this->setHost($db);
    }//if/else
    
    if(!empty($username)){
      $this->setUsername($username);
    }//if/else
    if(!empty($password)){
      $this->setPassword($password);
    }//if/else
    
    try{
      
      // do the connecting...
      if($this->hasUsername() && $this->hasPassword()){
        
        $this->con_map['connection'] = new MongoAuth($host);
        $this->con_db = $this->con_map['connection']->login($db,$this->getUsername(),$this->getPassword());
  
      }else{
      
        $this->con_map['connection'] = new Mongo($host);
        $this->con_db = $this->con_map['connection']->selectDB($db);
        
      }//if/else
      
    }catch(MongoConnectionException $e){
    
      throw new mingo_exception(sprintf('trouble finding connection: %s',$e->getMessage()));
      
    }//try/catch
  
    $this->con_map['connected'] = true;
    
    // load up the inc info table...
    $this->inc_map['table'] = sprintf('%s_inc',__CLASS__);
    $this->inc_map['map'] = $this->getOne($this->inc_map['table']);
    
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
  
  /**
   *  set up an auto increment field for a table
   *  
   *  since mongo doesn't natively support auto_increment fields, we do kind of a
   *  hack by creating a distributing table that will distribute the next value
   *  for the increment field on an insert. The one row in the table gets loaded 
   *  on every {@link connnect()} call, so you only really need to call this method
   *  when you are installing, or updating
   *  
   *  @link http://groups.google.com/group/mongodb-user/browse_thread/thread/c2c263c3e9a56a17
   *    I got the idea to use a version field from this link
   *  
   *  @link http://groups.google.com/group/mongodb-user/browse_thread/thread/c2c263c3e9a56a17/4946f83c8f31e9a0?lnk=gst&q=%24inc#4946f83c8f31e9a0         
   *      
   *  @param  string  $table  the table you want to add the auto_increment field to
   *  @param  string  $name the name of the field that will be auto_incremented from now on
   *  @param  integer $start_count  what the start value should be   
   *  @return boolean
   */
  function setInc($table,$name = 'id',$start_count = 0){
  
    // canary...
    if(empty($table)){ return false; }//if
    if($table instanceof MongoCollection){ $table = $table->getName(); }//if
    if(empty($name)){ $name = 'id'; }//if
    
    $inc_table = $this->getTable($this->inc_map['table']);
    $inc_map = $inc_table->findOne();
    $new_table = false;
    if(empty($inc_map)){
    
      $inc_map['inc_version'] = microtime(true);
      $inc_map[$table] = $start_count;
      $inc_map[sprintf('%s_field',$table)] = $name;
      $inc_table->insert($inc_map);
      $new_table = true;
      
    }else{
    
      if(!isset($inc_map[$table])){
        
        $inc_map[$table] = $start_count;
        $inc_map[sprintf('%s_field',$table)] = $name;
        $this->update($inc_table,$inc_map);
        $new_table = true;
        
      }//if
    
    }//if/else
    
    // add an index on the increment field if first time we've seen the table...
    if($new_table){
    
      $db_table = $this->getTable($table);
      $db_table->ensureIndex(array($name => 1));
    
    }//if
    
    //update the inc map table...
    $this->inc_map['map'] = $inc_map;
    
    return true;
  
  }//method
  
  /**
   *  get all the tables (collections) of the currently connected db
   *  
   *  @link http://us2.php.net/manual/en/mongodb.listcollections.php
   *      
   *  @return array a list of table names
   */
  function getTables(){
  
    if(!$this->isConnected()){ throw new mingo_exception('no db connection found'); }//if
  
    $ret_list = array();
    $db_name = sprintf('%s.',$this->getDb());
  
    $table_list = $this->con_db->listCollections();
    foreach($table_list as $table){
      $ret_list[] = str_replace($db_name,'',$table);
    }//foreach
    
    return $ret_list;
  
  }//method
  
  function setOption($val){ $this->option = $val; }//method
  function hasOption($val){ return $val && $this->option; }//method
  
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
  
    $table = $this->getTable($table);
    list($where_map) = $this->getCriteria($where_map);
    $result = $table->find($where_map);
    return empty($result) ? 0 : $result->count();
  
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
   *  @param  array|mingo_criteria  $where_map  if you want sorting power, use mingo_criteria
   *  @param  integer|array $limit  either something like 10, or array($limit,$offset)   
   *  @return array            
   */
  function get($table,$where_map = array(),$limit = 0){
    
    $ret_list = array();
    $table = $this->getTable($table);
    list($where_map,$sort_map) = $this->getCriteria($where_map);
    list($limit,$offset) = $this->getLimit($limit);
    
    $cursor = $table->find($where_map);
    if(!empty($cursor)){
    
      // @todo  right here you can call $cursor->count() to get how many rows were found
    
      // do the sort stuff...
      if(!empty($sort_map)){ $cursor->sort($sort_map); }//if
    
      // do the limit stuff...
      if(!empty($limit)){ $cursor->limit($limit); }//if
      if(!empty($offset)){ $cursor->skip($offset); }//if
    
      while($cursor->hasNext()){ $ret_list[] = $cursor->getNext(); }//while
  
    }//if

    return $ret_list;

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
   *  @throws mingo_exception on any failure               
   */
  function insert($table,$map){
    
    $db_table = $this->getTable($table);
    list($map) = $this->getCriteria($map);
    
    // see if this table has auto_increment stuff...
    if(isset($this->inc_map['map'][$table])){
      
      // increment the table's unique id...
      $inc_field = $this->inc_map['map'][sprintf('%s_field',$table)];
      $inc_table = $this->getTable($this->inc_map['table']);
      $bump_inc_where_map = $inc_where_map = array('_id' => $this->inc_map['map']['_id']);
      $inc_max = 100; // only try to auto-increment 100 times before failure
      $inc_count = 0;
      $c = new mingo_criteria();
      $c->inc($table,1);
      
      // we are going to try and get an increment, we do this in a loop to make sure
      // we get a real increment since mongo doesn't lock anything
      do{
      
        try{
        
          $inc_bool = true;
          $inc_row = $this->getOne($inc_table,$inc_where_map);
          $bump_inc_where_map['inc_version'] = $inc_row['inc_version'];
          $c->set('inc_version',microtime(true));
          
          $this->update($inc_table,$c,$bump_inc_where_map);
          
        }catch(mingo_exception $e){
          
          if($inc_count++ > $inc_max){
            throw new mingo_exception('tried to auto increment too many times');
          }//if
           
          $inc_bool = false;
          
        }//try/catch
        
      }while(!$inc_bool);
      
      $map[$inc_field] = ($inc_row[$table] + 1);
  
    }//if
    
    $db_table->insert($map);
    
    // $error_map has keys: [err], [n], and [ok]...
    $error_map = $this->con_db->lastError();
    if(empty($error_map['err'])){
    }else{
      throw new mingo_exception(sprintf('insert failed with message: %s',$error_map['err']));
    }//if/else
    
    return $map;
  
  }//method
  
  /**
   *  update $map from $table using $where_map as criteria
   *  
   *  @param  string  $table  the table name
   *  @param  array|mingo_criteria  $map  the key/value map that will be added to $table
   *  @param  array|mingo_criteria  $where_map  if empty, $map is checked for '_id'   
   *  @return array the $map that was just saved
   *  @throws mingo_exception on any failure
   */
  function update($table,$map,$where_map = array()){
    
    // canary...
    if(empty($where_map)){
      if(empty($map['_id'])){
        // since there isn't a where map, and no unique id, insert it instead...
        return $this->insert($table,$map);
      }else{
        $where_map = array('_id' => $map['_id']);
      }//if
    }//if
    
    $ret_id = null;
    $table = $this->getTable($table);
    list($map) = $this->getCriteria($map);
    list($where_map) = $this->getCriteria($where_map);
    
    // clean up before updating...
    if(isset($map['_id'])){
      $ret_id = $map['_id'];
      unset($map['_id']);
    }//if
    
    // always returns true, annoying...
    $table->update($where_map,$map);
    
    // $error_map has keys: [err], [updatedExisting], [n], [ok]...
    $error_map = $this->con_db->lastError();
    if(empty($error_map['updatedExisting'])){
      throw new mingo_exception(sprintf('update failed with message: %s',$error_map['err']));
    }else{
      if(empty($ret_id)){
        if(isset($where_map['_id'])){
          $ret_id = $where_map['_id'];
        }//if
      }//if
      if(empty($ret_id)){
      
        // @todo - need to load this map to get the id, but I have no idea how to do that.
      
      }else{
        $map['_id'] = $ret_id;
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
  function drop($table){
    
    $ret_bool = false;
    $table = $this->getTable($table);
    
    // drop is an array with [nIndexesWas], [msg], [ns], and [ok] indexes set...
    $drop = $table->drop();

    if(empty($drop['ok'])){
    
      throw new mingo_exception($drop['msg']);
    
    }else{
    
      $ret_bool = true;
    
    }//if/else
    
    return $ret_bool;
  
  }//method
  
  /**
   *  this loads the table so operations can be performed on it
   *  
   *  @param  string|MongoCollection  $table  the table to connect to      
   *  @return MongoCollection the table connection
   */
  protected function getTable($table){
  
    // canary...
    if(!$this->isConnected()){ throw new mingo_exception('no db connection found'); }//if
    if(empty($table)){ throw new mingo_exception('no $table given'); }//if
    
    return ($table instanceof MongoCollection) ? $table : $this->con_db->selectCollection($table);
  
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
   *  @param  integer|offset  $limit  can be either int (eg, limit=10) or array (eg, array($limit,$offset)
   *  @return array array($limit,$offset)
   */
  protected function getLimit($limit){
  
    // canary...
    if(empty($limit)){ return array(0,0); }//if
  
    $ret_limit = $ret_offset = 0;
  
    if(is_array($limit)){
      $ret_limit = $limit[0];
      if(isset($limit[1])){ $ret_offset = $limit[1]; }//if
    }else{
      $ret_limit = $limit;
    }//if/else
  
    return array($ret_limit,$ret_offset);
  
  }//method
  
}//class     
