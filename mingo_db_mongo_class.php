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
  public function connect($db_name,$host,$username,$password){
    
    // canary, make sure certain things exist...
    if(empty($host)){ throw new UnexpectedValueException('$host cannot be empty'); }//if
    
    $this->con_map['db_name'] = $db_name;
    $this->con_map['host'] = $host;
    $this->con_map['username'] = $username;
    $this->con_map['password'] = $password;
    
    // do the connecting...
    if(!empty($username) && !empty($password)){
      
      $this->con_map['connection'] = new MongoAuth($host);
      $this->con_db = $this->con_map['connection']->login($db_name,$username,$password);

    }else{
    
      $this->con_map['connection'] = new Mongo($host);
      $this->con_db = $this->con_map['connection']->selectDB($db_name);
      
    }//if/else
  
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
  public function getTables($table = ''){
  
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
   *  @param  array $limit  array($limit,$offset)
   *  @return integer the count
   */
  public function getCount($table,mingo_schema $schema,mingo_criteria $where_criteria = null,$limit = array()){
  
    $ret_int = 0;
    $table = $this->getTable($table);
    
    if($where_criteria->hasWhere()){
    
      $cursor = $this->getCursor($table,$where_criteria,$limit);
      $ret_int = $cursor->count();
    
    }else{
    
      $ret_int = $table->count();
    
    }//if/else
    
    return $ret_int;
  
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
  public function get($table,mingo_schema $schema,mingo_criteria $where_criteria = null,$limit = array()){
    
    $table = $this->getTable($table);
    $cursor = $this->getCursor($table,$where_criteria,$limit);
   
    ///while($cursor->hasNext()){ $ret_list[] = $cursor->getNext(); }//while
    return array_values(iterator_to_array($cursor));

  }//method
  
  /**
   *  get the first found row in $table according to $where_map find criteria
   *  
   *  @param  string  $table
   *  @param  mingo_schema  $schema the table schema   
   *  @param  mingo_criteria  $where_criteria
   *  @return array
   */
  public function getOne($table,mingo_schema $schema,mingo_criteria $where_criteria = null){
    
    $list = $this->get($table,$schema,$where_criteria,array(1,0));
    return empty($list[0]) ? array() : $list[0];
    
    /**
    using the findOne it doesn't look like you can sort it, which would be nice...    
    $table = $this->getTable($table);
    list($where_map,$sort_map) = $this->getCriteria($where_criteria);
    $ret_map = $table->findOne($where_map);
    return empty($ret_map) ? array() : $ret_map;
    */

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
    
    $ret_mixed = $table->remove($where_map);
    if(is_array($ret_mixed)){
      $ret_mixed = $ret_mixed['ok'];
    }//if
    
    return $ret_mixed;
  
  }//method
  
  /**
   *  insert $map into $table
   *  
   *  @param  string  $table  the table name
   *  @param  array $map  the key/value map that will be added to $table
   *  @param  mingo_schema  $schema the table schema
   *  @return array the $map that was just saved              
   */
  public function insert($table,array $map,mingo_schema $schema){
    
    $db_table = $this->getTable($table);
    $map = $this->getMap($map);
    
    $db_table->insert($map);
    
    // $error_map has keys: [err], [n], and [ok]...
    $error_map = $this->con_db->lastError();
    if(!empty($error_map['err'])){
      throw new MongoException(sprintf('insert failed with message: %s',$error_map['err']));
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
   *  @throws MongoException on any failure
   */
  public function update($table,$_id,array $map,mingo_schema $schema){

    $table = $this->getTable($table);
    $map = $this->getMap($map);
    $where_criteria = new mingo_criteria();
    $where_criteria->is_id($_id);
    
    // always returns true, annoying...
    $table->update($where_criteria->getWhere(),$map);
    
    // $error_map has keys: [err], [updatedExisting], [n], [ok]...
    $err_map = $this->con_db->lastError();
    if(empty($err_map['updatedExisting'])){
    
      throw new MongoException(sprintf('update failed with message: %s',$err_map['err']));
      
    }else{
    
      $map['_id'] = $_id;
      
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
  public function setIndex($table,array $index_map,mingo_schema $schema){
    
    // canary...
    if(empty($index_map)){ throw new mingo_exception('$index_map cannot be empty'); }//if
    
    $table = $this->getTable($table);
    return $table->ensureIndex($index_map);
  
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
  
    $ret_list = array();
    $table = $this->getTable($table);
    $index_list = $table->getIndexInfo();
    
    foreach($index_list as $index_map){
    
      $ret_list[$index_map['name']] = $index_map['key'];
    
    }//foreach
    
    return $ret_list;
  
  }//method
  
  /**
   *  deletes a table
   *  
   *  @param  string  $table  the table to delete from the db
   *  @return boolean
   */
  public function killTable($table){
    
    // canary...
    if(!$this->hasTable($table)){ return true; }//if
    
    $ret_bool = false;
    $table = $this->getTable($table);
    
    // drop is an array with [nIndexesWas], [msg], [ns], and [ok] indexes set...
    $drop = $table->drop();

    if(empty($drop['ok'])){
    
      throw new UnexpectedValueException($drop['msg']);
    
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
  public function setTable($table,mingo_schema $schema){
  
    $this->con_db->createCollection($table);
    
    if(!empty($schema)){
      
      // add all the indexes for this table...
      if($schema->hasIndexes()){
      
        foreach($schema->getIndexes() as $index_map){
        
          $this->setIndex($table,$index_map,$schema);
        
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
  public function hasTable($table){

    // get all the tables currently in the db...
    $table_list = $this->getTables();
    return in_array($table,$table_list,true);
    
  }//method
  
  /**
   *  currently, all mongo does to error recovery is set the table, in case an index was missed
   *  or something   
   */
  public function handleException(Exception $e,$table,mingo_schema $schema){
    
    // canary, check for infinite recursion...
    $traces = $e->getTrace();
    foreach($traces as $trace){
      if($trace['function'] === __FUNCTION__){ return false; }//if
    }//foreach
    
    return $this->setTable($table,$schema);
    
  }//method
  
  /**
   *  this loads the table so operations can be performed on it
   *  
   *  @param  string|MongoCollection  $table  the table to connect to      
   *  @return MongoCollection the table connection
   */
  protected function getTable($table){
  
    // canary...
    if(!$this->isConnected()){ throw new UnexpectedValueException('no db connection found'); }//if
    if(empty($table)){ throw new InvalidArgumentException('no $table given'); }//if
    
    return ($table instanceof MongoCollection) ? $table : $this->con_db->selectCollection($table);
  
  }//method
  
  /**
   *  assures a $where_map contains the right information to make a call against a table
   *  
   *  @param  mingo_criteria  $where_criteria
   *  @return array array($where_map,$sort_map), the $where_map and $sort_map are arrays 
   *                with their values assured
   */
  protected function getCriteria(mingo_criteria $where_criteria){
  
    return array($where_criteria->getWhere(),$where_criteria->getSort());
      
  }//method
  
  /**
   *  convert the $map passed into insert/delete into a proerly formatted map for mongo
   *    
   *  @since  10-11-10    
   *  @param  array|mingo_criteria  $map
   *  @return array      
   */
  protected function getMap($map){
  
    $ret_map = array();
  
    if($map instanceof mingo_criteria){
    
      $ret_map = array_merge(
        $where_criteria->getOperations(),
        $where_criteria->getWhere()
      );
      
    }else{
    
      $ret_map = $map;
      
    }//if/else
  
    return $ret_map;
  
  }//method
  
  /**
   *  get a Mongo cursor from a mingo_criteria
   *
   *  @since  10-11-10   
   *  @param  MongoCollection $table   
   *  @param  mingo_criteria  $where_criteria
   *  @param  array $limit  array($limit,$offset)   
   *  @return MongoCursor
   */
  protected function getCursor(MongoCollection $table,mingo_criteria $where_criteria,$limit){
  
    list($where_map,$sort_map) = $this->getCriteria($where_criteria);
    
    $cursor = $table->find($where_map);
    
    // do the sort stuff...
    // @note  a MongoCursorException can be thrown if skip is larger than the results that can be returned...
    if(!empty($sort_map)){ $cursor->sort($sort_map); }//if
  
    // do the limit stuff...
    if(!empty($limit[0])){ $cursor->limit($limit[0]); }//if
    if(!empty($limit[1])){ $cursor->skip($limit[1]); }//if
  
    return $cursor;
  
  }//method
  
}//class     
