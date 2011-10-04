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
 *  how to do auto-increment:
 *    http://shiflett.org/blog/2010/jul/auto-increment-with-mongodb   
 *  
 *  @version 0.5
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 12-09-09
 *  @package mingo 
 ******************************************************************************/
class MingoMongoInterface extends MingoInterface {

  const INDEX_ASC = 1;
  const INDEX_DESC = -1;
  const INDEX_SPATIAL = '2d';

  /**
   *  will contain the table names that have been verified this session
   *
   *  @see  normalizeTable()
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
      $this->con_db = $connection->login($name,$username,$password);

    }else{
    
      $connection = new Mongo($host);
      $this->con_db = $connection->selectDB($name);
      
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
   *  
   *  @param  MingoTable  $table  
   *  @return array
   */
  protected function _getTables(MingoTable $table = null){
  
    $ret_list = array();
    
    if(empty($table)){
    
      $db_name = sprintf('%s.',$this->getName());
    
      $table_list = $this->con_db->listCollections();
      foreach($table_list as $table_name){
        $ret_list[] = str_replace($db_name,'',$table_name);
      }//foreach
      
    }else{
    
      $table_name = $table->getName();
    
      // I personally don't think this is very elegant, but it's all I can come up with...
      $ret_map = $this->con_db->execute(sprintf('db.getCollectionNames().indexOf("%s")',$table_name));
      if(isset($ret_map['retval']) && ($ret_map['retval'] > -1)){ $ret_list[] = $table_name; }//if
    
    }//if/else
    
    return $ret_list;
  
  }//method
  
  /**
   *  @see  getCount()
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  mixed $where_criteria the where criteria ran through {@link normalizeCriteria())      
   *  @return integer the count
   */
  protected function _getCount($table,$where_criteria){
  
    $ret_int = 0;
    
    if(!empty($where_criteria)){
    
      $cursor = $this->getCursor($table,$where_criteria);
      $ret_int = $cursor->count();
    
    }else{
    
      $ret_int = $table['collection']->count();
    
    }//if/else
    
    return $ret_int;
  
  }//method
  
  /**
   *  @see  get()
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  mixed $where_criteria the where criteria ran through {@link normalizeCriteria())      
   *  @return array   
   */
  protected function _get($table,$where_criteria){

    $cursor = $this->getCursor($table,$where_criteria);
   
    ///while($cursor->hasNext()){ $ret_list[] = $cursor->getNext(); }//while
    return array_values(iterator_to_array($cursor));

  }//method
  
  /**
   *  @see  getQuery()
   *  @param  mixed $query  a query the interface can understand
   *  @param  array $options  any options for this query
   *  @return mixed      
   */
  protected function _getQuery($query,array $options = array()){
  
    // canary...
    if(empty($options['table'])){
      throw new InvalidArgumentException('options did not have a table key');
    }//if
  
    $table = array();
    $table['collection'] = $this->normalizeTable(new MingoTable($options['table']));
  
    $where_criteria = array();
    $where_criteria['where_map'] = $query;
    $where_criteria['sort_map'] = empty($options['sort_map']) ? array() : $options['sort_map'];
    $where_criteria['limit'] = empty($options['limit']) ? array() : $options['limit'];
    
    $cursor = $this->getCursor($table,$where_criteria);
   
    ///while($cursor->hasNext()){ $ret_list[] = $cursor->getNext(); }//while
    return array_values(iterator_to_array($cursor));
  
  }//method
  
  /**
   *  @see  kill()
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  mixed $where_criteria the where criteria ran through {@link normalizeCriteria())      
   *  @return boolean
   */
  protected function _kill($table,$where_criteria){
  
    $where_map = $where_criteria['where_map'];
    
    $ret_mixed = $table['collection']->remove($where_map);
    if(is_array($ret_mixed)){
      $ret_mixed = $ret_mixed['ok'];
    }//if
    
    return $ret_mixed;
  
  }//method
  
  /**
   *  insert $map into $table
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  array  $map  the key/value map that will be added to $table  
   *  @return array the $map that was just saved, with the _id set               
   */
  protected function insert($table,array $map){
    
    $table['collection']->insert($map);
    
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
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  string  $_id the _id attribute from $map   
   *  @param  array $map  the key/value map that will be added to $table
   *  @return array the $map that was just saved with _id set
   */
  protected function update($table,$_id,array $map){

    // convert the id to something Mongo understands...
    $where_criteria = new MingoCriteria();
    $where_criteria->is_id($_id);
    $where_map = $this->normalizeCriteria($table['table'],$where_criteria);
    $where_map = $where_map['where_map'];
    
    // always returns true, annoying...
    $table['collection']->update($where_map,$map);
    
    // $error_map has keys: [err], [updatedExisting], [n], [ok]...
    $err_map = $this->con_db->lastError();
    if(empty($err_map['updatedExisting'])){
    
      throw new MongoException(sprintf('update failed with message: %s',$err_map['err']));
      
    }//if
    
    return $map;
  
  }//method
  
  /**
   *  @see  setIndex()
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  mixed $index  an index ran through {@link normalizeIndex()}
   *  @return boolean
   */
  protected function _setIndex($table,$index){

    return $table['collection']->ensureIndex($index);
    
  }//method
  
  /**
   *  convert an array index map into something this interface understands
   *
   *  @since  5-2-11
   *  @param  MingoTable  $table 
   *  @param  array $index_map  an index map that is usually in the form of array(field_name => options,...)      
   *  @return mixed whatever this interface will understand
   */
  protected function normalizeIndex(MingoTable $table,array $index_map){
  
    foreach($index_map as $field => $options){
    
      if(empty($options)){
        $index_map[$field] = self::INDEX_ASC;
      }else if(ctype_digit((string)$options)){
        $index_map[$field] = ($options > 0) ? self::INDEX_ASC : self::INDEX_DESC;
      }//if/else if
    
    }//foreach
  
    return $index_map;
  
  }//method
  
  /**
   *  @see  getIndexes()
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}      
   *  @return array
   */
  protected function _getIndexes($table){
  
    $ret_list = array();
    $index_list = $table['collection']->getIndexInfo();
    
    foreach($index_list as $index_map){
    
      $ret_list[$index_map['name']] = $index_map['key'];
    
    }//foreach
    
    return $ret_list;
  
  }//method
  
  /**
   *  @see  killTable()
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}      
   *  @return boolean
   */
  protected function _killTable($table){

    $ret_bool = false;
    
    // drop is an array with [nIndexesWas], [msg], [ns], and [ok] indexes set...
    $drop = $table['collection']->drop();

    if(empty($drop['ok'])){
    
      throw new UnexpectedValueException($drop['msg']);
    
    }else{
    
      $ret_bool = true;
    
    }//if/else
    
    return $ret_bool;
  
  }//method

  /**
   *  @see  setTable()
   *  
   *  http://www.mongodb.org/display/DOCS/Capped+Collections
   *      
   *  @param  MingoTable  $table       
   *  @return boolean
   */
  protected function _setTable(MingoTable $table){

    $this->con_db->createCollection(
      $table->getName(),
      $table->getOption('table.capped',false),
      $table->getOption('table.size',0),
      $table->getOption('table.max',0)
    );
    
    return true;
  
  }//method
  
  /**
   *  @see  handleException()
   *  
   *  @param  MingoTable  $table     
   *  @return boolean false on failure to solve the exception, true if $e was successfully resolved
   */
  protected function _handleException(Exception $e,MingoTable $table){
    
    // canary...
    if(!($e instanceof MongoException)){ return false; }//if
    
    return $this->setTable($table);
    
  }//method
  
  /**
   *  turn the table into something the interface can understand
   *  
   *  @param  MingoTable  $table 
   *  @return mixed return whatever you want, however you want to return it, whatever is easiest for you
   */
  protected function normalizeTable(MingoTable $table){
  
    // canary...
    $table_name = $table->getName();
    
    if(empty($this->table_verified_map[$table_name])){
    
      // create the table and all its indexes if it doesn't already exist...
      if(!$this->hasTable($table)){
        $this->setTable($table);
      }//if
    
      $this->table_verified_map[$table_name] = true;
    
    }//if
    
    return array(
      'table' => $table,
      'collection' => $this->con_db->selectCollection($table_name)
    );
    
  }//method
  
  /**
   *  this should be used to take the generic $where_criteria and turn it into something
   *  the interface can use (eg, for a SQL interface, the $where_criteria would be turned
   *  into a valid SQL string).
   *  
   *  @param  MingoTable  $table    
   *  @param  MingoCriteria $where_criteria   
   *  @return mixed return whatever you want, however you want to return it, whatever is easiest for you
   */
  protected function normalizeCriteria(MingoTable $table,MingoCriteria $where_criteria = null){
  
    $ret_map = array(
      'where_criteria' => $where_criteria,
      'where_map' => array(),
      'sort_map' => array(),
      'limit' => array(0,0)
    );
  
    // canary...
    if($where_criteria === null){ return $ret_map; }//if
  
    $where_map = $where_criteria->getWhere();
    if(isset($where_map['_id'])){
      $where_map['_id'] = $this->normalize_id($where_map['_id']);
    }//if
    
    $ret_map['where_map'] = $where_map;
    $ret_map['sort_map'] = $where_criteria->getSort();
    $ret_map['limit'] = $where_criteria->getBounds();
  
    return $ret_map;
      
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
   *  get a Mongo cursor from a mingo_criteria
   *
   *  @since  10-11-10   
   *  @param  mixed $table  the table ran through {@link normalizeTable()}  
   *  @param  array $where_criteria the criteria returned from {@link normalizeCriteria()}
   *  @return MongoCursor
   */
  protected function getCursor(array $table,array $where_criteria){
  
    $where_map = $where_criteria['where_map'];
    $sort_map = $where_criteria['sort_map'];
    
    $cursor = $table['collection']->find($where_map);
    
    // do the sort stuff...
    // @note  a MongoCursorException can be thrown if skip is larger than the results that can be returned...
    if(!empty($sort_map)){ $cursor->sort($sort_map); }//if
  
    // do the limit stuff...
    $limit = $where_criteria['limit'];
    
    if(!empty($limit[0])){ $cursor->limit($limit[0]); }//if
    if(!empty($limit[1])){ $cursor->skip($limit[1]); }//if
  
    return $cursor;
  
  }//method
  
}//class     

