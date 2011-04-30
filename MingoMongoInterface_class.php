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
 *  @version 0.3
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 12-09-09
 *  @package mingo 
 ******************************************************************************/
class MingoMongoInterface extends MingoInterface {

  /**
   *  will contain the table names that have been verified this session
   *
   *  @see  getTable()
   *  @since  2-23-11
   *  @var  array keys are table names, values are booleans      
   */
  protected $table_verified_map = array();

  /**
   *  do the actual connecting of the interface
   *
   *  @see  connect()   
   *  @return boolean
   */
  protected function _connect($name,$host,$username,$password,array $options){
    
    // canary, make sure certain things exist...
    if(empty($host)){ throw new InvalidArgumentException('$host cannot be empty'); }//if
    
    $connected = false;
    $connection = null;
    
    // do the connecting...
    if(!empty($username) && !empty($password)){
      
      $connection = new MongoAuth($host);
      $this->con_db = $connection->login($db_name,$username,$password);

    }else{
    
      $connection = new Mongo($host);
      $this->con_db = $connection->selectDB($db_name);
      
    }//if/else
  
    $connected = true;
    $this->setField('connected',$connected);
    $this->setField('connection',$connection);
    
    // turn off timeouts, if the user wants to do a long query, more power to them...
    MongoCursor::$timeout = -1;
    
    return $connected;
  
  }//method
  
  /**
   *  @see  getTables()
   *  @return array
   */
  protected function _getTables($table = ''){
  
    $ret_list = array();
    
    if(empty($table)){
    
      $name = sprintf('%s.',$this->getName());
    
      $table_list = $this->con_db->listCollections();
      foreach($table_list as $table){
        $ret_list[] = str_replace($name,'',$table);
      }//foreach
      
    }else{
    
      // I personally don't think this is very elegant, but it's all I can come up with...
      $ret_map = $this->con_db->execute(sprintf('db.getCollectionNames().indexOf("%s")',$table));
      if(isset($ret_map['retval']) && ($ret_map['retval'] > -1)){ $ret_list[] = $table; }//if
    
    }//if/else
    
    return $ret_list;
  
  }//method
  
  /**
   *  @see  getCount()   
   *  @return integer the count
   */
  protected function _getCount($table,MingoSchema $schema,MingoCriteria $where_criteria = null,array $limit){
  
    $ret_int = 0;
    $table = $this->getTable($table,$schema);
    
    if(!empty($where_criteria) && $where_criteria->hasWhere()){
    
      $cursor = $this->getCursor($table,$where_criteria,$limit);
      $ret_int = $cursor->count();
    
    }else{
    
      $ret_int = $table->count();
    
    }//if/else
    
    return $ret_int;
  
  }//method
  
  /**
   *  @see  get()
   *  @return array   
   */
  protected function _get($table,MingoSchema $schema,MingoCriteria $where_criteria = null,array $limit){
    
    $table = $this->getTable($table,$schema);
    $cursor = $this->getCursor($table,$where_criteria,$limit);
   
    ///while($cursor->hasNext()){ $ret_list[] = $cursor->getNext(); }//while
    return array_values(iterator_to_array($cursor));

  }//method
  
  /**
   *  @see  getOne()
   *  @return array
   */
  protected function _getOne($table,MingoSchema $schema,MingoCriteria $where_criteria = null){
    
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
   *  @see  kill()
   *  @return boolean
   */
  protected function _kill($table,MingoSchema $schema,MingoCriteria $where_criteria){
  
    $table = $this->getTable($table,$schema);
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
   *  @param  array|mingo_criteria  $map  the key/value map that will be added to $table
   *  @param  MingoSchema $schema the table schema   
   *  @return array the $map that was just saved, with the _id set               
   */
  protected function insert($table,array $map,MingoSchema $schema){
    
    $db_table = $this->getTable($table,$schema);
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
   *  @param  MingoSchema $schema the table schema      
   *  @return array the $map that was just saved with _id set
   */
  protected function update($table,$_id,array $map,MingoSchema $schema){

    $table = $this->getTable($table,$schema);
    $map = $this->getMap($map);
    
    // convert the id to something Mongo understands...
    $where_criteria = new MingoCriteria();
    $where_criteria->is_id($_id);
    list($where_map) = $this->getCriteria($where_criteria);
    
    // always returns true, annoying...
    $table->update($where_map,$map);
    
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
   *  @param  string  $table  the table to add the index to
   *  @param  array $map  the keys are the field names, the values are the definitions for each field
   *  @param  MingoSchema $schema the table schema      
   *  @return boolean
   */
  protected function setIndex($table,array $index_map,MingoSchema $schema){
    
    // canary...
    if(empty($index_map)){ throw new InvalidArgumentException('$index_map cannot be empty'); }//if
    
    $table = $this->getTable($table,$schema);
    return $table->ensureIndex($index_map);
  
  }//method
  
  /**
   *  @see  getIndexes()
   *  @return array
   */
  protected function _getIndexes($table){
  
    $ret_list = array();
    $table = $this->getTable($table);
    $index_list = $table->getIndexInfo();
    
    foreach($index_list as $index_map){
    
      $ret_list[$index_map['name']] = $index_map['key'];
    
    }//foreach
    
    return $ret_list;
  
  }//method
  
  /**
   *  @see  killTable()
   *  @return boolean
   */
  protected function _killTable($table)){

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
   *  http://www.mongodb.org/display/DOCS/Capped+Collections
   *      
   *  @param  string  $table  the table to add to the db
   *  @param  mingo_schema  a schema object that defines indexes, etc. for this   
   *  @return boolean
   */
  public function setTable($table,mingo_schema $schema){

    $this->con_db->createCollection(
      $table,
      $schema->getOption('table.capped',false),
      $schema->getOption('table.size',0),
      $schema->getOption('table.max',0)
    );
    
    return true;
  
  }//method
  
  /**
   *  @see  handleException()
   *  @return boolean false on failure to solve the exception, true if $e was successfully resolved
   */
  protected function _handleException(Exception $e,$table,MingoSchema $schema){
    
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
  protected function getTable($table,mingo_schema $schema = null){
  
    // canary...
    if($table instanceof MongoCollection){ return $table; }//if
    $this->assure($table);
    if(empty($this->table_verified_map[$table]) && !empty($schema)){
    
      // create the table and all its indexes if it doesn't already exist...
      $table_list = $this->getTables($table);
      if(empty($table_list)){
        $this->setTable($table,$schema);
      }//if
    
      $this->table_verified_map[$table] = true;
    
    }//if
    
    return $this->con_db->selectCollection($table);
  
  }//method
  
  /**
   *  this should be used to take the generic $where_criteria and turn it into something
   *  the interface can use (eg, for a SQL interface, the $where_criteria would be turned
   *  into a valid SQL string).
   *  
   *  @param  MingoCriteria $where_criteria
   *  @return mixed return whatever you want, however you want to return it, whatever is easiest for you
   */
  protected function getCriteria(MingoCriteria $where_criteria){
  
    $where_map = $where_criteria->getWhere();
    if(isset($where_map['_id'])){
      $where_map['_id'] = $this->normalize_id($where_map['_id']);
    }//if
  
    return array($where_map,$where_criteria->getSort());
      
  }//method
  
  /**
   *  normalize the _id so that it becomes an _id mongo can work with
   *
   *  @since  3-6-11   
   *  @param  mixed $val   
   */
  protected function normalize_id($val){
  
    if(is_array($val)){
      foreach($val as $key => $v){
        $val[$key] = $this->normalize_id($v);
      }//foreach
    }else{
      if(!($val instanceof MongoId)){
        $val = new MongoId($val);
      }//if
    }//if/else
  
    return $val;
  
  }//method
  
  /**
   *  convert the $map passed into insert/delete into a properly formatted map for mongo
   *    
   *  @since  10-11-10    
   *  @param  array|MingoCriteria $map
   *  @return array      
   */
  protected function getMap($map){
  
    $ret_map = array();
  
    if($map instanceof MingoCriteria){
    
      $ret_map = $where_criteria->getWhere();
      
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
   *  @param  MingoCriteria $where_criteria
   *  @param  array $limit  array($limit,$offset)   
   *  @return MongoCursor
   */
  protected function getCursor(MongoCollection $table,MingoCriteria $where_criteria,$limit){
  
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

