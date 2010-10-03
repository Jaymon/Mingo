<?php

/**
 *  handle mongo db connections 
 *  
 *  @note mongo db inserts and updates always return true, so you have to check the last
 *        error to see if they were successful, I found this out through this link:
 *        http://markmail.org/message/ghapbonzag2uim2p     
 *
 *  @link http://us2.php.net/mongo
 *  
 *  @version 0.1
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 12-09-09
 *  @package mingo 
 ******************************************************************************/
class mingo_db_mongo extends mingo_db_interface {

  protected function start(){}//method
  
  /**
   *  connect to the mongo db
   *    
   *  @param  string  $db_name  the db to use
   *  @param  string  $host the host to use. if you want a specific port, 
   *                        attach it to host (eg, localhost:27017 or example.com:27017)            
   *  @param  string  $username the username to use
   *  @param  string  $password the password to use   
   *  @return boolean
   *  @throws mingo_exception   
   */
  function connect($db_name,$host,$username,$password){
    
    if(empty($host)){ throw new mingo_exception('no $host specified'); }//if
    
    $this->con_map['db_name'] = $db_name;
    $this->con_map['host'] = $host;
    $this->con_map['username'] = $username;
    $this->con_map['password'] = $password;
    
    try{
      
      // do the connecting...
      if(!empty($username) && !empty($password)){
        
        $this->con_map['connection'] = new MongoAuth($host);
        $this->con_db = $this->con_map['connection']->login($db_name,$username,$password);
  
      }else{
      
        $this->con_map['connection'] = new Mongo($host);
        $this->con_db = $this->con_map['connection']->selectDB($db_name);
        
      }//if/else
      
    }catch(MongoConnectionException $e){
    
      throw new mingo_exception(sprintf('trouble finding connection: %s',$e->getMessage()));
      
    }//try/catch
  
    $this->con_map['connected'] = true;
    
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
    $db_name = sprintf('%s.',$this->con_map['db_name']);
  
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
   *  @param  integer|array $limit  either something like 10, or array($limit,$offset)   
   *  @return integer the count
   */
  function getCount($table,mingo_schema $schema,mingo_criteria $where_criteria = null,$limit = array()){
  
    $ret_int = 0;
    $table = $this->getTable($table);
    list($where_map) = $this->getCriteria($where_criteria);
    if(empty($where_map)){
      $ret_int = $table->count();
    }else{
      $cursor = $table->find($where_map);
      
      // do the limit stuff...
      if(!empty($limit[0])){ $cursor->limit($limit[0]); }//if
      if(!empty($limit[1])){ $cursor->skip($limit[1]); }//if
      
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
   *  get all the indexes of $table
   *
   *  @link http://us2.php.net/manual/en/mongocollection.getindexinfo.php
   *                
   *  @param  string  $table  the table to get the indexes from
   *  @return array an array in the same format that {@link mingo_schema::getIndexes()} returns
   */
  public function getIndexes($table){
  
    $table = $this->getTable($table);
    return $table->getIndexInfo();
  
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
        
          $this->setIndex($table,$index_map);
          
        
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
   *  this doesn't do anything except return false since Mongo pretty much adds tables and
   *  indexes if they don't already exist. It's needed for interface compatibility though
   */
  public function handleException(Exception $e,$table,mingo_schema $schema){ return false; }//method
  
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
  protected function getCriteria($where_criteria){
  
    // canary...
    if(empty($where_criteria)){ return array(array(),array()); }//if
    if(!is_array($where_criteria) && !is_object($where_criteria)){
      throw new mingo_exception('$where_criteria is not an associative array or mingo_criteria instance');
    }//if
  
    
    $where_map = $sort_map = array();
    
    if($where_criteria instanceof mingo_criteria){
      $where_map = array_merge(
        $where_criteria->getOperations(),
        $where_criteria->getWhere()
      );
      $sort_map = $where_criteria->getSort();
    }else{
      $where_map = $where_criteria;
    }//if/else
  
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
          $c->inField(mingo_orm::_ID,$where_map['_id']);
          list($new_where_map) = $c->getWhere();
          $where_map['_id'] = $new_where_map['_id'];
          
        }else{
      
          $where_map['_id'] = new MongoId($where_map['_id']);  
        
        }//if/else
      
      }//if
    }//if
    
    return array($where_map,$sort_map);
      
  }//method
  
}//class     
