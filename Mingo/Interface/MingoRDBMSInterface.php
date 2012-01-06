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
  
  /**
   *  these fields are in the main table so they don't need to be in the body
   *
   *  @var  array   
   */
  protected $non_body_fields = array('_id','_rowid','_created','_updated');
  
  /**
   *  @see  getCount()
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  mixed $where_criteria the where criteria ran through {@link normalizeCriteria())      
   *  @return integer the count
   */
  protected function _getCount($table,$where_criteria){

  
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

  
  }//method
  
  /**
   *  insert $map into $table
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  array  $map  the key/value map that will be added to $table  
   *  @return array the $map that was just saved, with the _id set               
   */
  protected function insert($table,array $map){

    // insert into the main table
    $db = $this->getDb();
    
    // build the field map that will be used to insert into the main table
    $field_map = array();
    $field_map['_id'] = $this->getUniqueId($table);
    $field_map['body'] = $this->getBody($map);
    
    foreach($this->non_body_fields as $field){
      
      if(isset($map[$field])){
        
        $field_map[$field] = $map[$field];
        unset($map[$field]);
        
      }//if
      
    }//foreach
    
    // insert the saved map into the table...
    $field_name_str = join(',',array_map(array($this,'normalizeNameSQL'),array_keys($field_map)));
    $field_val_str = join(',',array_fill(0,count($field_map),'?'));

    try{

      // begin the insert transaction...
      $db->beginTransaction();
   
      $query = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        $this->normalizeTableSQL($table),
        $field_name_str,
        $field_val_str
      );
      
      $val_list = array_values($field_map);
      
      $ret_bool = $this->getQuery($query,$val_list);
      if($ret_bool){
   
        // get the row id...
        $map['_rowid'] = $db->lastInsertId();

        // we need to add to all the index tables...
        if($table->hasIndexes()){
        
          $this->insertIndexes($table,$map);
        
        }//if
        
        // restore the field names
        foreach($this->non_body_fields as $field){
      
          if(isset($field_map[$field])){ $map[$field] = $field_map[$field]; }//if
          
        }//foreach
        
      }//if
    
      // finish the insert transaction...
      $db->commit();
  
    }catch(Exception $e){
    
       // get rid of any changes that were made since we failed...
      $db->rollback();
      throw $e;
    
    }//try/catch

    return $map;
    
  }//method
  
  /**
   *  update the index tables with the new values
   *  
   *  @param  MingoTable  $table
   *  @param  array $map  the key/value pairs found in $table's body field
   *  @return boolean
   */
  protected function insertIndexes(MingoTable $table,array $map){
    
    $ret_bool = false;
    
    foreach($table->getIndexes() as $index){
    
      $index = $this->normalizeIndex($table,$index);
      $ret_bool = $this->insertIndex($table,$map,$index);
      
    }//foreach
    
    return $ret_bool;
    
  }//method
  
  /**
   *  insert into an index table
   *  
   *  @see  recursiveInsertIndex()   
   *  @param  \MingoTable $table  the master table, not the index table
   *  @param  array $map  the key/value pairs found in $table's body field
   *  @param  \MingoIndex $index  the index
   *  @return boolean
   */
  protected function insertIndex(MingoTable $table,array $map,MingoIndex $index){
    
    // canary
    if(!isset($map['_rowid'])){
      throw new InvalidArgumentException('$map did not contain _rowid key');
    }//if
    
    $ret_bool = false;
    $field_name_list = array();
    $field_bind_list = array();
    $val_list = array();
  
    foreach($index->getFieldNames() as $field){
    
      // canary, ignore values that don't exist in the map
      if(!isset($map[$field])){ continue; }//if
      // canary, you can't index an array
      if(is_array($map[$field])){
        throw new RuntimeException('you cannot index an array field');
      }//if
    
      list($sql_name,$sql_val,$sql_bind) = $this->normalizeInsertSql(
        $table->getField($field),
        $map[$field]
      );
      
      $field_name_list[] = $sql_name;
      $val_list[] = $sql_val;
      $field_bind_list[] = $sql_bind;
        
    }//foreach
    
    if(!empty($field_name_list)){
    
      list($sql_name,$sql_val,$sql_bind) = $this->normalizeInsertSql(
        $table->getField('_rowid'),
        $map['_rowid']
      );
      $field_name_list[] = $sql_name;
      $val_list[] = $sql_val;
      $field_bind_list[] = $sql_bind;
      $index_table = $this->getIndexTableName($table,$index);
      
      $query = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        $this->normalizeTableSql($index_table),
        join(',',$field_name_list),
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
   *  @return array array($field,$val,$bind) where $bind is usually a question mark
   */
  protected function normalizeInsertSQL(MingoField $field,$val){
  
    return array($this->normalizeNameSQL($field),$val,'?');
    
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
  
    $ret_bool = $this->setIndexTable($table,$index);

    if($ret_bool){
    
      // if debugging is on it means we're in dev so go ahead and populate the index...
      if($this->hasDebug()){ $this->populateIndex($table,$index); }//if
    
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
  
    // get all the index $tables...
    $table_list = array($table->getName());
    foreach($table->getIndexes() as $index){
    
      $table_list[] = $this->getIndexTableName($table,$index);
    
    }//foreach
        
    foreach($table_list as $table_name){
      
      $index_list = $this->getTableIndexes($table_name);
        
      // only add indexes that haven't been seen before...
      foreach($index_list as $index){
      
        $ret_list[] = $index;
      
      }//foreach
    
    }//foreach
    
    return $ret_list;
  
  }//method
  
  /**
   *  get the indexes for the given table
   *  
   *  this should get all the indexes that are set on the $table
   *         
   *  @since  10-18-10
   *  @see  getIndexes()
   *  @param  string  $table  the table name whose indexes you want
   *  @return array a list of MingoIndex instances   
   */
  abstract protected function getTableIndexes($table);
  
  /**
   *  goes through the master $table and adds the contents to the index table      
   *
   *  @param  \MingoTable $table  the main table, not the index table
   *  @param  \MingoIndex $index  the index that will be used to populate the tables    
   */
  protected function populateIndex($table,MingoIndex $index){
  
    \out::e('tbi');
    return false;
  
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
   *  @see  killTable()
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}      
   *  @return boolean
   */
  protected function _killTable($table){

    // sqlite: http://www.sqlite.org/lang_droptable.html...
    // postgres: http://www.postgresql.org/docs/8.2/static/sql-droptable.html
    $query = sprintf('DROP TABLE IF EXISTS %s',$this->normalizeTableSQL($table));
    $ret_bool = $this->getQuery($query);
    if($ret_bool){ $this->killIndexTables($table); }//if
    
    return $ret_bool;

  }//method
  
  /**
   *  since sql creates a table for each index on the main $table, we need to kill them
   *  sometimes
   *  
   *  this is in a separate method so children can override it if needs be
   *      
   *  @param  string  $table  the main table's name      
   *  @return boolean
   */
  protected function killIndexTables(MingoTable $table){
  
    $ret_bool = false;
    $query_format = 'DROP TABLE IF EXISTS %s';
  
    foreach($table->getIndexes() as $index){
    
      $table_name = $this->getIndexTableName($table,$index);
      $ret_bool = $this->getQuery(sprintf($query_format,$table_name));
    
    }//foreach
    
    return $ret_bool;
    
  }//method
  
  /**
   *  get the index table name
   *  
   *  @param  \MingoTable $table   
   *  @param  \MingoIndex $index
   *  @return string  the index table name
   */
  protected function getIndexTableName(MingoTable $table,MingoIndex $index){
          
    $table_name = sprintf('%s_%s',$table->getName(),$index->getName());
    return $table_name;
  
  }//method
  
  /**
   *  create an index table for the given $table and $index_map      
   *   
   *  @since  10-18-10
   *  @param  \MingoTable $table   
   *  @param  \MingoIndex $index
   */
  abstract protected function setIndexTable(MingoTable $table,MingoIndex $index);
  
  /**
   *  allows customizing the field sql type using the schema's field hints
   *
   *  @since  10-19-10
   *  @param  \MingoTable $table   
   *  @param  string  $field  the field name
   *  @return string
   */
  abstract protected function normalizeSqlType(MingoTable $table,$field);
  
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
  
    $ret_map = parent::normalizeCriteria($table,$where_criteria);
  
    $check_index_tables = false;
    if($where_criteria !== null){
    
      $check_index_tables = ($where_criteria->hasWhere() || $where_criteria->hasSort());
      
    }//if
  
    if(empty($where_criteria)){
    
      // we're selecting raw, so just load results with no WHERE...
      // SELECT * FROM <table>;
      $ret_map['index_table'] = '';
    
    }else{
  
      // first get the criteria info...
      $where_map = $where_criteria->getWhere();
      $sort_map = $where_criteria->getSort();
      
      // now, find the right index table to select from...
      $index_table = $this->findIndexTableName($table,$where_criteria);
      
      if(empty($index_table)){
        
        $ret_map['table'] = $table->getName();
        $ret_map['is_index'] = false;
      
        if(isset($where_map['_id']) && empty($sort_map)){
          
          $ret_map['_id_list'] = $val_list;
          
          // enforce limit on an _id_list also...
          if(!empty($limit[0])){
          
            if(count($ret_map['_id_list']) > $limit[0]){
            
              $ret_map['_id_list'] = array_slice($ret_map['_id_list'],$limit[1],$limit[0]);
            
            }//if
          
          }//if
          
        }else{
        
          // you can directly select on the table using "_rowid" also...
          $ret_map['query_map'] = array(
            'select' => '*',
            'where' => $where_query,
            'sort' => $sort_query
          );
          
          $ret_map['val_list'] = $val_list; 
        
        }//if/else
         
      }else{
        
        $ret_map['table'] = $index_table;
        $ret_map['is_index'] = true;
        $ret_map['query_map'] = array(
          'select' => 'DISTINCT _id',
          'where' => $where_query,
          'sort' => $sort_query
        );
        $ret_map['val_list'] = $val_list; 
        
      }//if/else
      
    }//if/else
  
    return $ret_map;
  
  }//method
  
  /**
   *  find the index table name from the table and the list of fields the index comprises
   *  
   *  @param  MingoTable  $table  the main table's name
   *  @param  MingoCriteria $where_criteria   
   *  @return string  the index table name
   */
  protected function findIndexTableName(MingoTable $table,MingoCriteria $where_criteria){
  
    $ret_str = '';
  
    $where_map = $where_criteria->getWhere();
    $sort_map = $where_criteria->getSort();
    
    // php >= 5.3, use when Mingo is ported to namespaces...
    ///$field_list = array_keys(array_replace($where_map,$sort_map));
    
    // we need to get a list of all the fields used in the order they will be used
    $field_list = array_keys($where_map);
    if(!empty($sort_map)){
    
      $field_list = array_unique(array_merge($field_list,array_keys($sort_map)));
    
    }//if
    
    // now go through the index and see if it matches all the fields...
    foreach($table->getIndexes() as $index){
    
      $field_i = 0;
      $is_match = false;
    
      foreach($index->getFieldNames() as $field){
        
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
        
      }else{
      
        // since we couldn't find an index table, make sure the query can be valid
        // on the main table
        
        // we are selecting on the main table (no index is being used) so we can only
        // select or sort on 4 fields (by default): _id, _rowid, _created, and _updated
        
        foreach($field_list as $field){
        
          // if a field in the where map is not in the main table we've got trouble
          // since an index table couldn't be found
          if(!in_array($field,$this->non_body_fields)){
            
            $e_msg = sprintf(
              'Could not match fields: [%s] sorted by fields: [%s] with an index table.',
              join(',',array_keys($where_map)),
              join(',',array_keys($sort_map))
            );
          
            // list the available index tables if we are in debug mode...
            if($this->hasDebug()){
            
              $e_msg .= ' Indexes available: ';
            
              $index_list = $this->getIndexes($table);
              $e_index_list = array();
              
              foreach($index_list as $index){
              
                $e_index_list = sprintf('[%s]',join(',',array_keys($index->getFieldNames())));
              
              }//foreach
            
              $e_msg .= join(', ',$e_index_list);
            
            }//if
          
            throw new RuntimeException($e_msg);
            
          }//if
        
        }//foreach
      
      }//if/else
      
    }//foreach
    
    return $ret_str;
    
  }//method

  
  /**
   *  get the body that is the key/val pairs that will go in the body field of the table
   *  
   *  I zlib compress: http://www.php.net/manual/en/ref.zlib.php
   *  Not really sure why except that Friendfeed does it, and I don't want to be different         
   *      
   *  @param  array $map  the key/value pairings
   *  @return string  a zlib compressed json encoded string
   */
  protected function getBody(array $map){
  
    // remove fields that don't need to be in the body because they are in the table...
    foreach($this->non_body_fields as $field){
      if(isset($map[$field])){ unset($map[$field]); }//if
    }//foreach
    
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
  
  
  
}//class     

