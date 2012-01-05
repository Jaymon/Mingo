<?php
/**
 *  handle relational db abstraction for mingo    
 *
 *  this interface attempts to treat a relational db (namely MySql and Sqlite) the
 *  way FriendFeed does: http://bret.appspot.com/entry/how-friendfeed-uses-mysql or
 *  like MongoDb 
 *  
 *  @todo
 *    - extend PDO to do this http://us2.php.net/manual/en/pdo.begintransaction.php#81022
 *      for better transaction support   
 *  
 *  @abstract 
 *  @version 0.8
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 12-12-09
 *  @package mingo 
 ******************************************************************************/
abstract class MingoRDBMSInterface extends MingoPDOInterface {

  const INDEX_ASC = 1;
  const INDEX_DESC = -1;
  const INDEX_SPATIAL = '2d';

  /**
   *  everything is utf-8, I'm not even giving people a choice
   */        
  const CHARSET = 'UTF8';
  
  
  
  ///protected $statement_map = array();
  
  /**
   *  create an index table for the given $table and $index_map      
   *   
   *  @since  10-18-10
   *  @param  string  $table
   *  @param  array $index_map  the index structure 
   */
  abstract protected function createIndexTable($table,array $index_map);
  
  /**
   *  get the indexes for the given table
   *  
   *  this should get all the indexes that are set on the $table
   *         
   *  @since  10-18-10
   *  @see  getIndexes()
   *  @param  string  $table      
   */
  abstract protected function getTableIndexes($table);

  
  /**
   *  true if the $e is for a missing table exception
   *
   *  @since  10-18-10
   *  @see  handleException()         
   *  @param  Exception $e  the thrown exception
   *  @return boolean
   */
  abstract protected function isNoTableException(Exception $e);
  
  /**
   *  gets all the fields in the given table
   *  
   *  @note Mingo doesn't use this for anything, so you can just define it as an empty
   *  method, but it is handy for debugging info, etc.   
   *      
   *  @todo right now it just returns the field names, but it would be easy to
   *        add a detail boolean after table name that would return the entire array
   *        with all the field info, this might be useful in the future            
   *      
   *  @param  MingoTable  $table      
   *  @return array all the field names found, empty array if none found
   */        
  abstract public function getTableFields(MingoTable $table);
  
  /**
   *  @see  getCount()
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  mixed $where_criteria the where criteria ran through {@link normalizeCriteria())      
   *  @return integer the count
   */
  protected function _getCount($table,$where_criteria){
  
    $ret_int = 0;
    $result = array();
    
    $query = '';
    $val_list = array();
    
    if(empty($where_criteria['_id_list']))
    {
      $query_map = $where_criteria['query_map'];
      $query_map['select'] = $where_criteria['is_index'] ? 'count(DISTINCT _id) AS ct' : 'count(_id) AS ct';
      $query = $this->getSelectQuery($where_criteria['table'],$query_map);
      $val_list = $where_criteria['val_list'];
      
    }else{
    
      $query = $this->getSelectQuery(
        $table,
        array(
          'select' => 'count(*) AS ct',
          'where' => sprintf('WHERE _id IN (%s)',join(',',array_fill(0,count($where_criteria['_id_list']),'?'))),
          'limit' => $where_criteria['query_map']['limit']
        )
      );
      $val_list = $where_criteria['_id_list'];
    
    }//if/else
    
    $result = $this->getQuery($query,$val_list);
    if(isset($result[0]['ct'])){ $ret_int = (int)$result[0]['ct']; }//if
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

    $ret_list = array();
    $_id_list = array();
    $order_map = array();
    $list = array();
    
    if(empty($where_criteria['_id_list'])){
    
      $query_map = $where_criteria['query_map'];
      $query = $this->getSelectQuery($where_criteria['table'],$query_map);
      
      if(empty($where_criteria['is_index'])){
        
        $list = $this->getQuery($query,$where_criteria['val_list']);
      
      }else{
      
        $stmt_handler = $this->getStatement($query,$where_criteria['val_list']);
        $_id_list = $stmt_handler->fetchAll(PDO::FETCH_COLUMN,0);
        
        if(!empty($_id_list)){
          $order_map = array_flip($_id_list);
        }//if

      }//if/else
      
    }else{
    
      $_id_list = $where_criteria['_id_list'];
    
    }//if/else
    
    if(!empty($_id_list)){
      
      $query = $this->getSelectQuery(
        $table,
        array(
          'select' => '*',
          'where' => sprintf('WHERE _id IN (%s)',join(',',array_fill(0,count($_id_list),'?')))
        )
      );
      
      $list = $this->getQuery($query,$_id_list);
      
    }//if
    
    if(!empty($list)){
    
      // sort the list if an order map was set, this is done because the rows
      // returned from the main table are not guarranteed to be in the same order
      // that the index table returned (I'm looking at you MySQL)...
      if(!empty($order_map)){
        $ret_list = array_fill(0,count($list),null);
      }//if

      foreach($list as $key => $map){
      
        $ret_map = $this->getMap($map['body']);
        $ret_map['_id'] = $map['_id'];
        $ret_map['_rowid'] = (int)$map['_rowid'];
        $ret_map['_created'] = (int)$map['_created'];
        
        // put the ret_map in the right place...
        if(isset($order_map[$map['_id']])){
        
          $ret_list[$order_map[$map['_id']]] = $ret_map;
          unset($order_map[$map['_id']]);
        
        }else{
        
          $ret_list[$key] = $ret_map;
        
        }//if/else
      
      }//foreach
      
      // do some self correcting if there were rows in the index tables not in the main table
      // I'm not entirely sure how this happens, but it has cropped up, plus, if a user
      // manually deletes a row from the main table, we want to eventually sync the index
      // tables again...
      if(!empty($order_map)){
      
        $dead_id_list = array();
      
        foreach($order_map as $dead_id => $dead_index){
        
          $dead_id_list[] = $dead_id;
          unset($ret_list[$dead_index]);
        
        }//foreach
      
        // quick check to make sure we don't try and delete a whole table...
        if(!empty($dead_id_list)){
          $this->killIndexes($table,$dead_id_list);
        }//if
      
        // reset keys...
        $ret_list = array_values($ret_list);
      
      }//if
    
    }//if

    return $ret_list;

  }//method
  
  /**
   *  @see  kill()
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  mixed $where_criteria the where criteria ran through {@link normalizeCriteria())      
   *  @return boolean
   */
  protected function _kill($table,$where_criteria){

    $ret_bool = false;
    $limit = 100; // SQLite has a 500 variable IN (...) limit
    $offset = 0;

    try{

      $has_more = false;
    
      do{
      
        // begin the delete transaction, we're going to do this every iteration...
        $this->con_db->beginTransaction();
      
        $_id_list = array();
        
        if(empty($where_criteria['_id_list'])){
        
          $query_map = $where_criteria['query_map'];
          $query_map['select'] = $where_criteria['is_index'] ? 'DISTINCT _id' : '_id';
          $query_map['limit'] = array($limit,0);
          
          $query = $this->getSelectQuery($where_criteria['table'],$query_map);
          
          $stmt_handler = $this->getStatement($query,$where_criteria['val_list']);
          $_id_list = $stmt_handler->fetchAll(PDO::FETCH_COLUMN,0);
        
        }else{
        
          $_id_list = array_slice($where_criteria['_id_list'],$offset,$limit);
        
        }//if/else
        
        if(!empty($_id_list)){
          
          // delete values from index tables...
          $this->killIndexes($table,$_id_list);
          
          // delete values from main table...
          $query = sprintf(
            'DELETE FROM %s WHERE _id IN (%s)',
            $this->handleTableSql($table),
            join(',',array_fill(0,count($_id_list),'?'))
          );
          
          $ret_bool = $this->getQuery($query,$_id_list);
          
        }//if
        
        $has_more = isset($_id_list[$limit - 1]);
        $offset += $limit;
        
        // finish the delete transaction for this iteration...
        $this->con_db->commit();
        
      }while($has_more);

    }catch(Exception $e){

      // get rid of any changes that were made since we failed...
      $this->con_db->rollback();
      
      throw $e;
    
    }//try/catch
  
    return $ret_bool;
  
  }//method
  
  /**
   *  insert $map into $table
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  array  $map  the key/value map that will be added to $table  
   *  @return array the $map that was just saved, with the _id set               
   */
  protected function insert($table,array $map){

    // insert into the main table, _id and body are all we care about...
    $field_map = array();
    $field_map['_id'] = $this->getUniqueId($table);
    $field_map['body'] = $this->getBody($map);
    $field_map['_created'] = $map['_created'];
    unset($map['_created']);
    
    // insert the saved map into the table...
    $field_name_str = join(',',array_keys($field_map));
    $field_val_str = join(',',array_fill(0,count($field_map),'?'));

    try{

      // begin the insert transaction...
      $this->con_db->beginTransaction();
   
      $query = sprintf('INSERT INTO %s (%s) VALUES (%s)',$table,$field_name_str,$field_val_str);
      $val_list = array_values($field_map);
      $ret_bool = $this->getQuery($query,$val_list);
  
      if($ret_bool){
   
        // get the row id...
        $map['_rowid'] = $this->con_db->lastInsertId();

        // we need to add to all the index tables...
        if($table->hasIndexes()){
        
          $this->setIndexes($table,$field_map['_id'],$map);
        
        }//if
        
        $map['_id'] = $field_map['_id'];
        $map['_created'] = $field_map['_created'];
      
      }//if
    
      // finish the insert transaction...
      $this->con_db->commit();
  
    }catch(Exception $e){
    
       // get rid of any changes that were made since we failed...
      $this->con_db->rollback();
      throw $e;
    
    }//try/catch
  
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

    try{
    
      // we don't need to save the _rowid into the body since it gets reset in get()...
      ///if(isset($map['_rowid'])){ unset($map['_rowid']); }//if
    
      // begin the insert transaction...
      $this->con_db->beginTransaction();
      
      $query = sprintf('UPDATE %s SET body=?,_created=? WHERE _id=?',$table);
      
      // we don't need to save certain fields into the body since it gets reset whenever the
      // map is pulled out from the db (no sense in having it in 2 places)...
      $restore_field_map = array('_rowid' => null,'_created' => null);
      foreach($restore_field_map as $field_name => $field_val){
      
        if(isset($map[$field_name])){
        
          $restore_field_map[$field_name] = $map[$field_name];
          unset($map[$field_name]);
        
        }//if
      
      }//foreach
      
      $val_list = array($this->getBody($map),(int)$restore_field_map['_created'],$_id);
      
      // put the fields back...
      foreach($restore_field_map as $field_name => $field_val){
      
        if($field_val !== null){
        
          $map[$field_name] = $field_val;
        
        }//if
      
      }//foreach
      
      $ret_bool = $this->getQuery($query,$val_list);
      
      if($ret_bool){
        
        // we need to update all the index tables, and it's easier to delete and re-add...
        if($table->hasIndex()){
        
          $this->killIndexes($table,$_id);
          $this->setIndexes($table,$_id,$map);
        
        }//if
        
        // finish the insert transaction...
        $this->con_db->commit();
      
      }//if
      
    }catch(Exception $e){
    
       // get rid of any changes that were made since we failed...
      $this->con_db->rollback();
      throw $e;
    
    }//try/catch
    
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

    // canary, don't bother trying to re-create the index table if it already exists...
    $index_table = $this->getIndexTableName($table,$index);
    if($this->hasTable(new MingoTable($index_table))){ return true; }//if
  
    $ret_bool = $this->createIndexTable($table,$index);

    if($ret_bool){
    
      // if debugging is on it means we're in dev so go ahead and populate the index...
      if($this->hasDebug()){
        $this->populateIndex($table,$index);
      }//if
    
    }//if
    
    return $ret_bool;

  }//method
  
  /**
   *  @see  getIndexes()
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}      
   *  @return array
   */
  protected function _getIndexes($table){
  
    $ret_list = array();
  
    // get just the $table index tables...
    $table_list = array();
    foreach($this->getTables() as $table_name){
    
      if(($table_name === $table) || preg_match(sprintf('#^%s_[a-z0-9]{32,}#i',$table),$table_name)){
      
        $table_list[] = $table_name;
      
      }//if
    
    }//foreach
    
    foreach($table_list as $table_name){
      
      $index_list = $this->getTableIndexes($table_name);
      
      // only add indexes that haven't been seen before...
      foreach($index_list as $index_map){
      
        if(!in_array($index_map,$ret_list,true)){
          $ret_list[] = $index_map;
        }//if
      
      }//foreach
      
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

    // sqlite: http://www.sqlite.org/lang_droptable.html...
    $query = sprintf('DROP TABLE IF EXISTS %s',$table);
    $ret_bool = $this->getQuery($query);
    if($ret_bool){
    
      $this->killIndexTables($table);
    
    }//if
    
    return $ret_bool;

  }//method
  
  /**
   *  @see  handleException()
   *  
   *  @param  MingoTable  $table     
   *  @return boolean false on failure to solve the exception, true if $e was successfully resolved
   */
  protected function _handleException(Exception $e,MingoTable $table){
    
    $ret_bool = false;
    if($this->isNoTableException($e)){
    
      // table was missing, so assure the table and everything...
      $ret_bool = $this->setTable($table);
    
    }//if
      
    return $ret_bool;
    
  }//method
  
  
  
  /**
   *  return true if a string is binary
   *   
   *  this method is a cross between http://bytes.com/topic/php/answers/432633-how-tell-if-file-binary
   *  and this: http://groups.google.co.uk/group/comp.lang.php/msg/144637f2a611020c?dmode=source
   *  but I'm still not completely satisfied that it is 100% accurate, though it seems to be
   *  accurate for my purposes.
   *  
   *  @param  string  $val  the val to check to see if contains binary characters
   *  @return boolean true if binary, false if not
   */
  protected function isBinaryString($val){
  
    $val = (string)$val;
    $ret_bool = false;
    $not_printable_count = 0;
    for($i = 0, $max = strlen($val); $i < $max ;$i++){
      if(ord($val[$i]) === 0){ $ret_bool = true; break; }//if
      if(!ctype_print($val[$i])){
        if(++$not_printable_count > 5){ $ret_bool = true; break; }//if
      }//if 
    }//for
    
    return $ret_bool;
  
  }//method
  
  /**
   *  get info about the index
   *  
   *  @param  string  $table   
   *  @param  array $index
   *  @return string  the index table name
   */
  protected function getIndexTableName($table,array $index){
          
    $field_list = array_keys($index);
    $field_list_str = join(',',$field_list);
    $index_table = sprintf('%s_%s',$table,md5($field_list_str));
    return $index_table;
  
  }//method
  
  /**
   *  goes through the master $table and adds the contents to the index table      
   *
   *  @param  string  $table  the main table (not the index table)
   *  @param  array $index_map  an index map, usually retrieved from the table schema    
   */
  protected function populateIndex($table,array $index){
  
    $ret_bool = false;
    $where_criteria = new MingoCriteria();
    
    $limit = 100;
    $offset = 0;
    $where_criteria->setLimit($limit);
    $where_criteria->setOffset($offset);
  
    // get results from the table and add them to the index...
    while($map_list = $this->get($table,$where_criteria)){
    
      foreach($map_list as $map){
      
        $ret_bool = $this->insertIndex($table,$map['_id'],$map,$index);
      
      }//foreach
    
      $offset += $limit;
      $where_criteria->setOffset($offset);
      
    }//while
    
    return $ret_bool;
  
  }//method
  
  /**
   *  insert into an index table
   *  
   *  @see  recursiveInsertIndex()   
   *  @param  string  $table  the master table, not the index table
   *  @param  string  $_id the _id of the $table where $map is found
   *  @param  array $map  the key/value pairs found in $table's body field
   *  @param  array $index  the map that represents the index
   *  @return boolean
   */
  protected function insertIndex($table,$_id,array $map,array $index){
    
    // canary, if this is a spatial index and the spatial field isn't set, then don't insert...
    foreach($index as $field => $index_type){
      if($this->isSpatialIndexType($index_type)){
        if(!isset($map[$field])){
          return false;
        }//if
      }//if
    }//foreach
    
    $index_table = $this->getIndexTableName($table,$index);
    
    $state = new StdClass();
    $state->depth = 0;
    $state->arrField = '';
    
    return $this->recursiveInsertIndex(
      $index_table,
      $_id,
      $map,
      $index,
      $state
    );
  
  }//method
  
  /**
   *  actually do the inserting into an index table
   *  
   *  @recursive   
   *  @see  insertIndex   
   *  @param  string  $table  the master table, not the index table
   *  @param  string  $_id the _id of the $table where $map is found
   *  @param  array $map  the key/value pairs found in $table's body field
   *  @param  array $index_map  the map that represents the index
   *  @param  StdClass  $state  the current state of the recursion   
   *  @return boolean
   */
  protected function recursiveInsertIndex($index_table,$_id,$map,array $index_map,StdClass $state){
  
    $ret_bool = false;
    $field_list = array();
    $field_bind_list = array();
    $val_list = array();
    $run_query = true;
  
    foreach($index_map as $field => $index_type){
    
      // canary...
      if(!$run_query){ break; }//if
      if(!isset($map[$field])){ continue; }//if
      
      if(is_array($map[$field]) && !$this->isSpatialIndexType($index_type)){
      
        if(!empty($state->arrField)){
        
          throw new DomainException(
            sprintf('Cannot index parallel arrays [%s] [%s]',$state->arrField,$field)
          );
        
        }//if
      
        $state->arrField = $field;
        $state->depth++;
        
        $sql_name = $sql_val = $sql_bind = '';
        $run_query = false;
        $list = $map[$field];
        foreach($list as $key => $val){
        
          // filter out nulls...
          if(isset($list[$key])){
          
            $o = new StdClass();
            $o->_is_recurse_ = true;
            $o->key = $key;
            $o->val = $val;
            $map[$field] = $o;
          
            // recursively go through the individual array values...
            $ret_bool = $this->recursiveInsertIndex($index_table,$_id,$map,$index_map,$state);
            
          }//if
        
        }//foreach
        
        $state->arrField = '';
        $state->depth--;
        
        $map[$field] = $list;
      
      }else if(($map[$field] instanceof StdClass) && isset($map[$field]->_is_recurse_)){
      
        // canary...
        if(is_array($map[$field]->val)){
        
          throw new DomainException(
            sprintf(
              'field [%s] is a multi-dimensional array which is not currently supported with this interface',
              $field
            )
          );
        
        }//if
      
        ///out::i($map[$field]);  
        list($sql_name,$sql_val,$sql_bind) = $this->handleInsertSql($field,$map[$field]->val,$index_type);
      
      }else{
      
        list($sql_name,$sql_val,$sql_bind) = $this->handleInsertSql($field,$map[$field],$index_type);
        
      }//if/else
      
      $field_list[] = $sql_name;
      $val_list[] = $sql_val;
      $field_bind_list[] = $sql_bind;
        
    }//foreach
    
    if($run_query){
    
      list($sql_name,$sql_val,$sql_bind) = $this->handleInsertSql('_id',$_id);
      $field_list[] = $sql_name;
      $val_list[] = $sql_val;
      $field_bind_list[] = $sql_bind;
      
      $query = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        $this->handleTableSql($index_table),
        join(',',$field_list),
        join(',',$field_bind_list)
      );
      
      $ret_bool = $this->getQuery($query,$val_list);
      
    }//if
      
    return $ret_bool;
  
  }//method
  
  /**
   *  formats the insert sql for index tables
   *  
   *  @since  10-21-10
   *  @param  string  $field  the field name
   *  @param  mixed $val  the field value
   *  @param  mixed $index_type the index type for $field
   *  @return array array($field,$val,$bind) where $bind is usually a question mark
   */
  protected function handleInsertSQL($field,$val,$index_type = null){
    return array($field,$val,'?');
  }//method
  
  /**
   *  returns true if the passed in $index_type is spatial
   *  
   *  @since  10-18-10
   *  @param  string  $index_type
   *  @return boolean
   */
  protected function isSpatialIndexType($index_type){
    return $index_type === self::INDEX_SPATIAL;
  }//method
  
  
  
  /**
   *  builds a select query suitable to be passed into {@link getQuery()}
   *  
   *  this function puts all the different parts together
   *      
   *  @param  string  $table  the table
   *  @param  array $query_map  can have a number of keys:
   *                            'select' - string - the fields to select from (usually * or count(*), or _id)
   *                            'where' - string - the where part of the string (starts with WHERE ...)
   *                            'sort' - string - the sort part of the string
   *                            'limit' - array - array($limit,$offset)   
   *  @return string  the built query         
   */
  protected function getSelectQuery($table,array $query_map){
  
    $query = 'SELECT';
    $printf_vars = array();
        
    // build the query...
    
    if(empty($query_map['select'])){
    
      $query .= ' *';
    
    }else{
    
      $query .= ' %s';
      $printf_vars[] = $query_map['select'];
    
    }//if/else
    
    $query .= ' FROM %s';
    $printf_vars[] = $this->handleTableSql($table);
    
    if(!empty($query_map['where'])){
    
      $query .= ' %s';
      $printf_vars[] = $query_map['where'];
    
    }//if
    
    // add sort...
    if(!empty($query_map['sort'])){
    
      $query .= ' '.$query_map['sort'];
    
    }//if
    
    // add limit...
    if(!empty($query_map['limit'][0])){
      $query .= ' LIMIT %d OFFSET %d';
      $printf_vars[] = (int)$query_map['limit'][0];
      $printf_vars[] = (empty($query_map['limit'][1]) ? 0 : (int)$query_map['limit'][1]);
    }//if

    return vsprintf($query,$printf_vars);
    
  }//method
  
  /**
   *  update the index tables with the new values
   *  
   *  @param  MingoTable  $table
   *  @param  string  $_id the _id of the $table where $map is found
   *  @param  array $map  the key/value pairs found in $table's body field
   *  @return boolean
   */
  protected function setIndexes(MingoTable $table,$_id,$map){
        
    $ret_bool = false;
    
    foreach($table->getIndexes() as $index_map){
    
      $index = $this->normalizeIndex($table,$index_map);
      $ret_bool = $this->insertIndex($table,$_id,$map,$index);
      
    }//foreach
    
    return $ret_bool;
    
  }//method
  
  /**
   *  removes all the indexes for a given $_id
   *  
   *  this is called after updating the value and before calling {@link setIndexes()}
   *
   *  @param  MingoTable  $table
   *  @param  string|array  $_id  either one _id or many in an array
   *  @return boolean
   */
  protected function killIndexes(MingoTable $table,$_id_list){
  
    $ret_bool = false;
    $_id_list = (array)$_id_list;
    $_id_sub_list = join(',',array_fill(0,count($_id_list),'?'));
  
    foreach($table->getIndexes() as $index_map){
      
      $index = $this->normalizeIndex($table,$index_map);
      $index_table = $this->getIndexTableName($table,$index);
      if(!empty($index_table)){
      
        $query = sprintf(
          'DELETE FROM %s WHERE _id IN (%s)',
          $index_table,
          $_id_sub_list
        );
        
        $ret_bool = $this->getQuery($query,$_id_list);
  
      }//if
      
    }//foreach
  
    return $ret_bool;
  
  }//method
  
  /**
   *  get the body that is the key/val pairs that will go in the body field of the table
   *  
   *  I zlib compress: http://www.php.net/manual/en/ref.zlib.php
   *  Not really sure why except that Friendfeed does it, and I don't want to be different         
   *
   *  between version .1 and .2 this changed from json to serialize because of the
   *  associative arrays becoming stdObjects problem
   *      
   *  @param  array $map  the key/value pairings
   *  @return string  a zlib compressed json encoded string
   */
  protected function getBody($map){
  
    // get rid of table stuff...
    if(isset($map['_rowid'])){ unset($map['_rowid']); }//if
    if(isset($map['_id'])){ unset($map['_id']); }//if
    
    return gzcompress(serialize($map));
  
  }//method
  
  /**
   *  opposite of {@link getBody()}
   *  
   *  between version .1 and .2 this changed from json to serialize because of the
   *  associative arrays becoming stdObjects problem   
   *      
   *  @param  string  $body the getBody() compressed string, probably returned from a db call
   *  @return array the key/value pairs restored to their former glory
   */
  protected function getMap($body){
    return unserialize(gzuncompress($body));
  }//method
  
  /**
   *  get the index table name from the table and the list of fields the index comprises
   *  
   *  @param  MingoTable  $table  the main table's name
   *  @param  MingoCriteria $where_criteria   
   *  @return string  the index table name
   */
  protected function getIndexTable(MingoTable $table,MingoCriteria $where_criteria){
  
    $ret_str = '';
  
    $where_map = $where_criteria->getWhere();
    $sort_map = $where_criteria->getSort();
    
    // php >= 5.3, use when Mingo is ported to namespaces...
    ///$field_list = array_keys(array_replace($where_map,$sort_map));
    
    $field_list = array_keys($where_map);
    if(!empty($sort_map)){
    
      $field_list = array_unique(array_merge($field_list,array_keys($sort_map)));
    
    }//if
    
    // now go through the index and see if it matches...
    foreach($table->getIndexes() as $index_map){
    
      $index = $this->normalizeIndex($table,$index_map);
      $field_i = 0;
      $is_match = false;
    
      foreach($index as $field => $order){
        
        if(isset($field_list[$field_i])){
        
          if($field === $field_list[$field_i]){
            $is_match = true;
            $field_i++;
          }else{
            $is_match = false;
            break;
          }//if/else
        
        }else{
        
          break;
          
        }//if/else
      
      }//foreach
      
      if($is_match){
      
        // we're done, we found a match...
        $ret_str = $this->getIndexTableName($table,$index);
        break;
        
      }//if
      
    }//foreach
    
    return $ret_str;
    
  }//method
  
  /**
   *  since sql creates a table for each index on the main $table, we need to kill them
   *  sometimes
   *  
   *  this function matches any table and deletes it that is in the format:
   *  main table name UNDERSCORE md5hash   
   *      
   *  @param  string  $table  the main table's name      
   *  @return boolean
   */
  protected function killIndexTables($table){
  
    $ret_bool = false;
    $query_drop = 'DROP TABLE IF EXISTS %s';
    
    // any tables matching this form will be dropped...
    $regex_drop = sprintf('/%s_[a-z0-9]{32}/u',preg_quote($table));
    $table_list = $this->getTables();
      
    // no go through and get rid of any index tables...
    foreach($table_list as $table_index){
    
      if(preg_match($regex_drop,$table_index)){
        $ret_bool = $this->getQuery(sprintf($query_drop,$table_index));
      }//if
    
    }//foreach
    
    return $ret_bool;
    
  }//method
  
}//class     

