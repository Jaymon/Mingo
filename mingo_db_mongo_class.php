<?php

/**
 *  handle mongo db connections 
 *  
 *  @note mongo db inserts and updates always return true, so you have to check the last
 *        error to see if they were successful, I found this out through this link:
 *        http://markmail.org/message/ghapbonzag2uim2p     
 *
 *  @version 0.1
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 12-09-09
 *  @package mingo 
 ******************************************************************************/
class mingo_db_mongo extends mingo_db_interface {

  /**
   *  holds auto increment information
   *  
   *  @see  setInc(), update(), insert()      
   *  @var  array
   */
  private $inc_map = array();
  
  protected function start(){

    $this->inc_map['table'] = array();
  
  }//method
  
  /**
   *  connect to the mongo db
   *    
   *  @param  string  $db the db to use
   *  @param  string  $host the host to use. if you want a specific port, 
   *                        attach it to host (eg, localhost:27017 or example.com:27017)            
   *  @param  string  $username the username to use
   *  @param  string  $password the password to use   
   *  @return boolean
   *  @throws mingo_exception   
   */
  function connect($db,$host,$username,$password){
    
    if(empty($host)){ throw new mingo_exception('no $host specified'); }//if
    
    try{
      
      // do the connecting...
      if(!empty($username) && !empty($password)){
        
        $this->con_map['connection'] = new MongoAuth($host);
        $this->con_db = $this->con_map['connection']->login($db,$username,$password);
  
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
   *  get all the tables (collections) of the currently connected db
   *  
   *  @link http://us2.php.net/manual/en/mongodb.listcollections.php
   *  
   *  @param  string  $table  doesn't do anything, just here for abstract signature match   
   *  @return array a list of table names
   */
  function getTables($table = ''){
  
    $ret_list = array();
    $db_name = sprintf('%s.',$this->getDb());
  
    $table_list = $this->con_db->listCollections();
    foreach($table_list as $table){
      $ret_list[] = str_replace($db_name,'',$table);
    }//foreach
    
    return $ret_list;
  
  }//method
  
  /**
   *  tell how many records match $where_criteria in $table
   *  
   *  @param  string  $table
   *  @param  mingo_schema  $schema the table schema   
   *  @param  mingo_criteria  $where_criteria
   *  @return integer the count
   */
  function getCount($table,mingo_schema $schema,mingo_criteria $where_criteria = null){
  
    $ret_int = 0;
    $table = $this->getTable($table);
    list($where_map) = $this->getCriteria($where_criteria);
    if(empty($where_map)){
      $ret_int = $table->count();
    }else{
      $cursor = $table->find($where_map);
      $ret_int = $cursor->count();
    }//if/else
    return $ret_int;
  
  }//method
  
  /**
   *  delete the records that match $where_criteria in $table
   *  
   *  this method will not delete an entire table's contents, you will have to do
   *  that manually.         
   *  
   *  @param  string  $table
   *  @param  mingo_schema  $schema the table schema     
   *  @param  mingo_criteria  $where_criteria
   *  @return boolean
   */
  function kill($table,mingo_schema $schema,mingo_criteria $where_criteria){
  
    $table = $this->getTable($table);
    list($where_map) = $this->getCriteria($where_criteria);
    return $table->remove($where_map);
  
  }//method
  
  /**
   *  get a list of rows matching $where_map
   *  
   *  @param  string  $table
   *  @param  mingo_schema  $schema the table schema   
   *  @param  mingo_criteria  $where_map
   *  @param  array $limit  array($limit,$offset)   
   *  @return array   
   */
  function get($table,mingo_schema $schema,mingo_criteria $where_criteria = null,$limit = array()){
    
    $ret_list = array();
    $table = $this->getTable($table);
    list($where_map,$sort_map) = $this->getCriteria($where_criteria);
    
    $cursor = $table->find($where_map);
    
    // @todo  right here you can call $cursor->count() to get how many rows were found
  
    // do the sort stuff...
    if(!empty($sort_map)){ $cursor->sort($sort_map); }//if
  
    // do the limit stuff...
    if(!empty($limit[0])){ $cursor->limit($limit[0]); }//if
    if(!empty($limit[1])){ $cursor->skip($limit[1]); }//if
  
    // @note  a MongoCursorException can be thrown if skip is larger than the results that can be returned... 
    while($cursor->hasNext()){ $ret_list[] = $cursor->getNext(); }//while
      
    return $ret_list;

  }//method
  
  /**
   *  get the first found row in $table according to $where_map find criteria
   *  
   *  @param  string  $table
   *  @param  mingo_schema  $schema the table schema   
   *  @param  mingo_criteria  $where_criteria
   *  @return array
   */
  function getOne($table,mingo_schema $schema,mingo_criteria $where_criteria = null){
    
    $table = $this->getTable($table);
    list($where_map) = $this->getCriteria($where_criteria);
    $ret_map = $table->findOne($where_map);
    return empty($ret_map) ? array() : $ret_map;

  }//method
  
  /**
   *  insert $map into $table
   *  
   *  @param  string  $table  the table name
   *  @param  array|mingo_criteria  $map  the key/value map that will be added to $table
   *  @return array the $map that was just saved
   *  @param  mingo_schema  $schema the table schema   
   *  @throws mingo_exception on any failure               
   */
  function insert($table,$map,mingo_schema $schema){
    
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
      $c->incField($table,1);
      
      // we are going to try and get an increment, we do this in a loop to make sure
      // we get a real increment since mongo doesn't lock anything
      do{
      
        try{
        
          $inc_bool = true;
          $inc_row = $this->getOne($inc_table,$inc_where_map);
          $bump_inc_where_map['inc_version'] = $inc_row['inc_version'];
          $c->setField('inc_version',microtime(true));
          
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
  function update($table,$_id,$map,mingo_schema $schema){
    
    $ret_id = null;
    $table = $this->getTable($table);
    list($map) = $this->getCriteria($map);
    list($where_map) = $this->getCriteria(array('_id' => $_id));
    
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
    if(!$this->hasTable($table)){ return true; }//if
    
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
   *  adds a table to the db
   *  
   *  @param  string  $table  the table to add to the db
   *  @param  mingo_schema  a schema object that defines indexes, etc. for this   
   *  @return boolean
   */
  function setTable($table,mingo_schema $schema){
  
    $this->con_db->createCollection($table);
    
    if(!empty($schema)){
      
      // create an auto increment key if defined...
      if($schema->hasInc()){
      
        $this->setInc($table,$schema->getIncField(),$schema->getIncStart());
        
      }//if
      
      // add all the indexes for this table...
      if($schema->hasIndex()){
      
        foreach($schema->getIndex() as $index_map){
        
          $this->setIndex($$table,$index_map);
        
        }//foreach
      
      }//if
      
    }//if
    
    return true;
  
  }//method
  
  /**
   *  check to see if a table is in the db
   *  
   *  @param  string  $table  the table to check
   *  @return boolean
   */
  function hasTable($table){

    // get all the tables currently in the db...
    $table_list = $this->getTables();
    return in_array($table,$table_list,true);
    
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
   *  this doesn't do anything except return false since Mongo pretty much adds tables and
   *  indexes if they don't already exist. It's needed for interface compatibility though
   */
  protected function handleException(Exception $e,$table,mingo_schema $schema){ return false; }//method
  
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
  private function setInc($table,$name = 'id',$start_count = 0){
  
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
  
}//class     
