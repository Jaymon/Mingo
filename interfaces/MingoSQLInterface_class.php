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
abstract class MingoSQLInterface extends MingoInterface {

  const INDEX_ASC = 1;
  const INDEX_DESC = -1;
  const INDEX_SPATIAL = '2d';

  /**
   *  everything is utf-8, I'm not even giving people a choice
   */        
  const CHARSET = 'UTF8';
  
  /**
   *  these are directly correlated with MingoCriteria's $where_criteria internal
   *  map that is returned from calling MingoCriteria::getWhere(). These are used in
   *  the {@link normalizeCriteria()} method to change a MingoCriteria object into usable SQL      
   *
   *  @var  array   
   */
  protected $method_map = array(
    'in' => array('arg' => 'handleListSql', 'symbol' => 'IN'),
    'nin' => array('arg' => 'handleListSql', 'symbol' => 'NOT IN'),
    'is' => array('arg' => 'handleValSql', 'symbol' => '='),
    'not' => array('arg' => 'handleValSql', 'symbol' => '!='),
    'gt' => array('arg' => 'handleValSql', 'symbol' => '>'),
    'gte' => array('arg' => 'handleValSql', 'symbol' => '>='),
    'lt' => array('arg' => 'handleValSql', 'symbol' => '<'),
    'lte' => array('arg' => 'handleValSql', 'symbol' => '<='),
    'near' => array('args' => 'handleSpatialSql'),
    'sort' => array('arg' => 'handleSortSql')
  );
  
  /**
   *  executes the query and returns the result
   *     
   *  @see  getStatement()   
   *  @param  string  $query  the query to prepare and run
   *  @param  array $val_list the values list for the query, if the query has ?'s then 
   *                          the values should be in this array           
   *  @return mixed array of results if select query, last id if insert, update
   */
  public function getQuery($query,$val_list = array()){
  
    $ret_mixed = false;
  
    // prepare the statement and run the query...
    // http://us2.php.net/manual/en/function.PDO-prepare.php
    $stmt_handler = $this->getStatement($query,$val_list);

    if(preg_match('#^(?:select|show|pragma)#iu',$query)){

      // certain queries should always return an array...
      $ret_mixed = $stmt_handler->fetchAll(PDO::FETCH_ASSOC);
      
    }else{
    
      // all other queries should return whether they were successful, which if no
      // exception was thrown, they were...
      $ret_mixed = true;
      
    }//if/else
    
    $stmt_handler->closeCursor();

    return $ret_mixed;
  
  }//method
  
  /**
   *  create a table
   *   
   *  the table should have atleast an _id (varchar(24)) and a body (blob) field, 
   *  it can also have a row_id (integer), if it doesn't have those then nothing will
   *  work as expected   
   *      
   *  @see  setTable()
   *  @param  string  $table  the table name
   *  @Param  MingoSchema  $schema the table's schema         
   */
  abstract protected function createTable($table,MingoSchema $schema);
  
  /**
   *  create an index table for the given $table and $index_map      
   *   
   *  @since  10-18-10
   *  @param  string  $table
   *  @param  array $index_map  the index structure
   *  @param  MingoSchema  $schema the table schema   
   */
  abstract protected function createIndexTable($table,array $index_map,MingoSchema $schema);
  
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
   *  get the dsn connection string that PDO will use to connect to the backend
   *   
   *  @since  10-18-10
   *  @param  string  $name the database name
   *  @param  string  $host the host
   *  @return string  the dsn         
   */
  abstract protected function getDsn($name,$host);
  
  /**
   *  things to do once the connection is established
   *   
   *  @since  10-18-10
   */
  abstract protected function onConnect();
  
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
   *  do the actual connecting of the interface
   *
   *  @see  connect()   
   *  @return boolean
   */
  protected function _connect($name,$host,$username,$password,array $options){
  
    $connected = false;
    $pdo_options = array(
      PDO::ERRMODE_EXCEPTION => true,
      // references I can find of the exit code 1 error is here:
      // http://bugs.php.net/bug.php?id=43199
      // it's this bug: http://bugs.php.net/42643 and it only affects CLI on <=5.2.4...
      ///PDO::ATTR_PERSISTENT => (strncasecmp(PHP_SAPI, 'cli', 3) === 0) ? false : true, 
      PDO::ATTR_EMULATE_PREPARES => true,
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    );
    
    // passed in options take precedence...
    if(isset($options['pdo_options'])){
      $pdo_options = array_merge($pdo_options,$options['pdo_options']);
    }//if

    $dsn = $this->getDsn($name,$host);
    $con_class = empty($options['pdo_class']) ? 'PDO' : $options['pdo_class'];
    
    try{
    
      $this->con_db = new $con_class($dsn,$username,$password,$pdo_options);
      $connected = true;
      $this->onConnect();
      
    }catch(Exception $e){
    
      if($this->hasDebug()){
      
        $e_msg = array();
        
        $con_map_msg = array();
        foreach($pdo_options as $key => $val){
          $con_map_msg[] = sprintf('%s => %s',$key,$val);
        }//foreach
        
        $e_msg[] = sprintf(
          'new %s("%s","%s","%s",array(%s)) failed.',
          $con_class,
          $dsn,
          $username,
          $password,
          join(',',$con_map_msg)
        );
        
        $e_msg[] = '';
        $e_msg[] = sprintf(
          'available drivers if original exception was "could not find driver" exception: [%s]',
          join(',',PDO::getAvailableDrivers())
        );
        
        throw new PDOException(join(PHP_EOL,$e_msg),$e->getCode());
        
      }else{
        throw $e;
      }//if/else
    
    }//try/catch
    
    return $connected;
  
  }//method
  
  /**
   *  @see  getTables()
   *  @return array
   */
  protected function _getTables($table = ''){
  
  }//method
  
  /**
   *  @see  getCount()   
   *  @return integer the count
   */
  protected function _getCount($table,MingoSchema $schema,MingoCriteria $where_criteria = null,array $limit){
  
    $ret_int = 0;
    $result = array();
    
    $query = '';
    $val_list = array();
    $qi_map = $this->getQueryInfo($table,$schema,$where_criteria,$limit);
    
    if(empty($qi_map['_id_list']))
    {
      $query_map = $qi_map['query_map'];
      $query_map['select'] = $qi_map['is_index'] ? 'count(DISTINCT _id) AS ct' : 'count(_id) AS ct';
      $query = $this->getSelectQuery($qi_map['table'],$query_map);
      $val_list = $qi_map['val_list'];
      
    }else{
    
      $query = $this->getSelectQuery(
        $table,
        array(
          'select' => 'count(*) AS ct',
          'where' => sprintf('WHERE _id IN (%s)',join(',',array_fill(0,count($qi_map['_id_list']),'?'))),
          'limit' => $limit
        )
      );
      $val_list = $qi_map['_id_list'];
    
    }//if/else
    
    $result = $this->getQuery($query,$val_list);
    if(isset($result[0]['ct'])){ $ret_int = (int)$result[0]['ct']; }//if
    return $ret_int;
  
  }//method
  
  /**
   *  @see  get()
   *  @return array   
   */
  protected function _get($table,MingoSchema $schema,MingoCriteria $where_criteria = null,array $limit){

    $ret_list = array();
    $_id_list = array();
    $order_map = array();
    $list = array();
    $qi_map = $this->getQueryInfo($table,$schema,$where_criteria,$limit);
    
    if(empty($qi_map['_id_list'])){
    
      $query_map = $qi_map['query_map'];
      $query = $this->getSelectQuery($qi_map['table'],$query_map);
      
      if(empty($qi_map['is_index'])){
        
        $list = $this->getQuery($query,$qi_map['val_list']);
      
      }else{
      
        $stmt_handler = $this->getStatement($query,$qi_map['val_list']);
        $_id_list = $stmt_handler->fetchAll(PDO::FETCH_COLUMN,0);
        
        if(!empty($_id_list)){
          $order_map = array_flip($_id_list);
        }//if
      
      }//if/else
      
    }else{
    
      $_id_list = $qi_map['_id_list'];
    
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
        $ret_map['row_id'] = $map['row_id'];
        
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
          $this->killIndexes($table,$dead_id_list,$schema);
        }//if
      
        // reset keys...
        $ret_list = array_values($ret_list);
      
      }//if
    
    }//if

    return $ret_list;

  }//method
  
  /**
   *  @see  getOne()
   *  @return array
   */
  protected function _getOne($table,MingoSchema $schema,MingoCriteria $where_criteria = null){
  
    $ret_list = $this->get($table,$schema,$where_criteria,array(1,0));
    return empty($ret_list) ? array() : $ret_list[0];
  
  }//method
  
  /**
   *  @see  kill()
   *  @return boolean
   */
  protected function _kill($table,MingoSchema $schema,MingoCriteria $where_criteria){
  
    $ret_bool = false;
    $limit = 100; // SQLite has a 500 variable IN (...) limit
    
    try{
    
      $has_more = false;
    
      do{
      
        // begin the delete transaction, we're going to do this every iteration...
        $this->con_db->beginTransaction();
      
        $_id_list = array();
        $map = $this->getQueryInfo($table,$schema,$where_criteria,array($limit,0));
        
        if(empty($map['_id_list'])){
        
          $query_map = $map['query_map'];
          $query_map['select'] = $map['is_index'] ? 'DISTINCT _id' : '_id';
          
          $query = $this->getSelectQuery($map['table'],$query_map);
          
          $stmt_handler = $this->getStatement($query,$map['val_list']);
          $_id_list = $stmt_handler->fetchAll(PDO::FETCH_COLUMN,0);
        
        }else{
        
          $_id_list = $map['_id_list'];
        
        }//if/else
        
        if(!empty($_id_list)){
          
          // delete values from index tables...
          $this->killIndexes($table,$_id_list,$schema);
          
          // delete values from main table...
          $query = sprintf(
            'DELETE FROM %s WHERE _id IN (%s)',
            $this->handleTableSql($table),
            join(',',array_fill(0,count($_id_list),'?'))
          );
          
          $ret_bool = $this->getQuery($query,$_id_list);
          
        }//if
        
        $has_more = isset($_id_list[$limit - 1]);
        
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
   *  @param  string  $table  the table name
   *  @param  array|mingo_criteria  $map  the key/value map that will be added to $table
   *  @param  MingoSchema $schema the table schema   
   *  @return array the $map that was just saved, with the _id set               
   */
  protected function insert($table,array $map,MingoSchema $schema){
  
    // insert into the main table, _id and body are all we care about...
    $field_map = array();
    $field_map['_id'] = $this->getUniqueId($table);
    $field_map['body'] = $this->getBody($map);
    
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
        $map['row_id'] = $this->con_db->lastInsertId();
      
        // we need to add to all the index tables...
        if($schema->hasIndexes()){
        
          $this->setIndexes($table,$field_map['_id'],$map,$schema);
        
        }//if
        
        $map['_id'] = $field_map['_id'];
        
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
   *  update $map from $table using $_id
   *  
   *  @param  string  $table  the table name
   *  @param  string  $_id the _id attribute from $map   
   *  @param  array $map  the key/value map that will be added to $table
   *  @param  MingoSchema $schema the table schema      
   *  @return array the $map that was just saved with _id set
   */
  protected function update($table,$_id,array $map,MingoSchema $schema){

    try{
    
      // we don't need to save the row_id into the body since it gets reset in get()...
      if(isset($map['row_id'])){ unset($map['row_id']); }//if
    
      // begin the insert transaction...
      $this->con_db->beginTransaction();
      
      $query = sprintf('UPDATE %s SET body=? WHERE _id=?',$table);
      $val_list = array($this->getBody($map),$_id);
      $ret_bool = $this->getQuery($query,$val_list);
      
      if($ret_bool){
        
        // we need to update all the index tables, and it's easier to delete and re-add...
        if($schema->hasIndex()){
        
          $this->killIndexes($table,$_id,$schema);
          $this->setIndexes($table,$_id,$map,$schema);
        
        }//if
        
        $map['_id'] = $_id;
        
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
   *  @param  string  $table
   *  @param  mixed $index  an index this interface understands
   *  @param  MingoSchema $schema   
   *  @return boolean
   */
  protected function _setIndex($table,$index,MingoSchema $schema){
    
    // canary, don't bother trying to re-create the index table if it already exists...
    $index_table = $this->getIndexTableName($table,$index);
    if($this->hasTable($index_table)){ return true; }//if
  
    $ret_bool = $this->createIndexTable($table,$index,$schema);

    if($ret_bool){
    
      // if debugging is on it means we're in dev so go ahead and populate the index...
      if($this->hasDebug()){
        $this->populateIndex($table,$index_map,$schema);
      }//if
    
    }//if
    
    return $ret_bool;
    
  }//method
  
  /**
   *  convert an array index map into something this interface understands
   *
   *  @since  5-2-11
   *  @return mixed whatever this interface will understand
   */
  protected function normalizeIndex(array $index_map,MingoSchema $schema){
    return $index_map;
  }//method
  
  /**
   *  @see  getIndexes()
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
   *  removes all the indexes for a given $_id
   *  
   *  this is called after updating the value and before calling {@link setIndexes()}
   *
   *  @param  string  $table
   *  @param  string|array  $_id  either one _id or many in an array
   *  @param  MingoSchema  $schema
   *  @return boolean
   */
  protected function killIndexes($table,$_id_list,MingoSchema $schema){
  
    $ret_bool = false;
    $_id_list = (array)$_id_list;
    $_id_sub_list = join(',',array_fill(0,count($_id_list),'?'));
  
    foreach($schema->getIndexes() as $index_map){
      
      $index_table = $this->getIndexTableName($table,$index_map);
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
   *  @see  killTable()
   *  @return boolean
   */
  protected function _killTable($table){

    $ret_bool = true;
    
    if($this->hasTable($table)){
    
      // sqlite: http://www.sqlite.org/lang_droptable.html...
      $query = sprintf('DROP TABLE IF EXISTS %s',$table);
      $ret_bool = $this->getQuery($query);
      if($ret_bool){
      
        $this->killIndexTables($table);
      
      }//if
    
    }//if
    
    return $ret_bool;

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
  
  /**
   *  adds a table to the db
   *  
   *  http://www.mongodb.org/display/DOCS/Capped+Collections
   *      
   *  @see  setTable()   
   *  @return boolean
   */
  protected function _setTable($table,MingoSchema $schema){

    // canary...
    if($this->hasTable($table)){ return true; }//if

    $ret_bool = false;

    if($this->createTable($table,$schema)){
      
      $ret_bool = true;
      
      // add all the indexes for this table...
      if($schema->hasIndexes()){
      
        foreach($schema->getIndexes() as $index_map){

          $this->setIndex($table,$index_map,$schema);
        
        }//foreach
      
      }//if
      
    }//if
    
    return $ret_bool;

  }//method
  
  /**
   *  @see  handleException()
   *  @return boolean false on failure to solve the exception, true if $e was successfully resolved
   */
  protected function _handleException(Exception $e,$table,MingoSchema $schema){
    
    $ret_bool = false;
    if($this->isNoTableException($e)){
    
      // table was missing, so assure the table and everything...
      $ret_bool = $this->setTable($table,$schema);
    
    }//if
      
    return $ret_bool;
    
  }//method
  
  /**
   *  this should be used to take the generic $where_criteria and turn it into something
   *  the interface can use (eg, for a SQL interface, the $where_criteria would be turned
   *  into a valid SQL string).
   *  
   *  @param  MingoCriteria $where_criteria
   *  @return mixed return whatever you want, however you want to return it, whatever is easiest for you
   */
  protected function normalizeCriteria(MingoCriteria $where_criteria){
  
    $ret_map = array();
    $ret_map['where_str'] = ''; $ret_map[0] = &$ret_map['where_str'];
    $ret_map['where_val'] = array(); $ret_map[1] = &$ret_map['where_val'];
    $ret_map['sort_str'] = array(); $ret_map[2] = &$ret_map['sort_str'];
  
    $ret_where = $ret_sort = '';
  
    $criteria_where = $where_criteria->getWhere();
    $criteria_sort = $where_criteria->getSort();
    
    $command_symbol = $where_criteria->getCommandSymbol();
  
    foreach($criteria_where as $name => $map){
    
      // we only deal with non-command names right now for sql...
      if($name[0] != $command_symbol){
      
        $where_sql = '';
        $where_val = array();
      
        if(is_array($map)){
        
          $total_map = count($map);
        
          // go through each map val and append it to the sql string...
          foreach($map as $command => $val){
    
            if($command[0] == $command_symbol){
            
              $command_bare = mb_substr($command,1);
              $command_sql = '';
              $command_val = array();
            
              // build the sql...
              if(isset($this->method_map[$command_bare])){
              
                $symbol = empty($this->method_map[$command_bare]['symbol'])
                  ? ''
                  : $this->method_map[$command_bare]['symbol'];
              
                if(!empty($this->method_map[$command_bare]['arg'])){
                
                  $callback = $this->method_map[$command_bare]['arg'];
                  list($command_sql,$command_val) = $this->{$callback}(
                    $symbol,
                    $name,
                    $map[$command]
                  );
                  
                }else if(!empty($this->method_map[$command_bare]['args'])){
                
                  $callback = $this->method_map[$command_bare]['args'];
                  list($command_sql,$command_val) = $this->{$callback}(
                    $symbol,
                    $name,
                    $map
                  );
                
                
                }//if/else
              
                list($where_sql,$where_val) = $this->appendSql(
                  'AND',
                  $command_sql,
                  $command_val,
                  $where_sql,
                  $where_val
                );
              
              }//if
            
            }else{
            
              // @todo  throw an error, there shouldn't ever be an array value outside a command
              throw new UnexpectedValueException(
                'there is an error in your criteria, this happens when you pass in an array to '
                .'the constructor, maybe try generating your criteria using the object\'s methods '
                .'and not passing in an array.'
              );
            
            }//if/else
            
          }//foreach
          
          if($total_map > 1){ $where_sql = sprintf(' (%s)',trim($where_sql)); }//if
        
        }else{
        
          // we have a NAME=VAL (an is* method call)...
          list($where_sql,$where_val) = $this->handleValSql('=',$name,$map);
        
        }//if/else
        
        list($ret_map['where_str'],$ret_map['where_val']) = $this->appendSql(
          'AND',
          $where_sql,
          $where_val,
          $ret_map['where_str'],
          $ret_map['where_val']
        );
      
      }//if
    
    }//foreach
  
    if(!empty($ret_map['where_val'])){
      $ret_map['where_str'] = sprintf('WHERE%s',$ret_map['where_str']);
    }//if
    
    // build the sort sql...
    foreach($criteria_sort as $name => $direction){
    
      $dir_sql = ($direction > 0) ? 'ASC' : 'DESC';
      if(empty($ret_map['sort_sql'])){
        $ret_map['sort_str'] = sprintf('ORDER BY %s %s',$name,$dir_sql);
      }else{
        $ret_map['sort_str'] = sprintf('%s,%s %s',$ret_map['sort_sql'],$name,$dir_sql);
      }//if/else
    
    }//foreach

    return $ret_map;
  
  }//method
  
  /**
   *  prepares and executes the query and returns the PDOStatement instance
   *  
   *  @param  string  $query  the query to prepare and run
   *  @param  array $val_list the values list for the query, if the query has ?'s then 
   *                          the values should be in this array      
   *  @return PDOStatement
   */
  public function getStatement($query,$val_list = array()){
  
    $query = trim($query);
  
    // debugging stuff...
    if($this->hasDebug()){
      if(is_array($val_list)){
        $debug_query = $query;
        foreach($val_list as $key => $val){
          if(!is_numeric($val)){
            $val = $this->isBinaryString($val) ? "'[:BINARY STRING:]'" : sprintf("'%s'",$val); 
          }//if
          $debug_query = preg_replace('/\?/u',$val,$debug_query,1);
        }//foreach
        $this->query_list[] = $debug_query;
      }else{
        $this->query_list[] = $query;
      }//if/else
    }//if
  
    // prepare the statement and run the query...
    // http://us2.php.net/manual/en/function.PDO-prepare.php
    $stmt_handler = $this->con_db->prepare($query);
  
    // execute the query...
    $is_success = empty($val_list) ? $stmt_handler->execute() : $stmt_handler->execute($val_list);

    if(!$is_success){
    
      $err_map = $stmt_handler->errorInfo();
      
      $stmt_handler->closeCursor();
      
      // we use the string version of the error code ($err_map[0]) because that is 
      // what handleException expects because that is what a PDOException would have
      // but we can't use PDOException because it won't take a string for the code,
      // no idea why PHP gets away with that natively 
      throw new UnexpectedValueException(
        sprintf('query "%s" failed execution with error: %s',
          $query,
          print_r($err_map,1)
        ),
        $err_map[0]
      );
    
    }//if
  
    return $stmt_handler;
  
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
    if(isset($map['row_id'])){ unset($map['row_id']); }//if
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
   *  update the index tables with the new values
   *  
   *  @param  string  $table
   *  @param  string  $_id the _id of the $table where $map is found
   *  @param  array $map  the key/value pairs found in $table's body field
   *  @param  MingoSchema  $schema the table schema
   *  @return boolean
   */
  protected function setIndexes($table,$_id,$map,MingoSchema $schema){
        
    $ret_bool = false;
    
    foreach($schema->getIndexes() as $index_map){
    
      $ret_bool = $this->insertIndex($table,$_id,$map,$index_map);
      
    }//foreach
    
    return $ret_bool;
    
  }//method
  
  /**
   *  get the index table name from the table and the list of fields the index comprises
   *  
   *  @param  string  $table  the main table's name
   *  @param  MingoCriteria $where_criteria
   *  @param  MingoSchema $schema   
   *  @return string  the index table name
   */
  protected function getIndexTable($table,MingoCriteria $where_criteria,MingoSchema $schema){
  
    $ret_str = '';
  
    $where_map = $where_criteria->getWhere();
    $sort_map = $where_criteria->getSort();
    
    $field_list = array_keys($where_map);
    if(empty($field_list) && !empty($sort_map)){ $field_list = array_keys($sort_map); }//if
  
    // now go through the index and see if it matches...
    foreach($schema->getIndexes() as $index_map){
    
      $field_i = 0;
      $is_match = false;
    
      foreach($index_map as $field => $order){
        
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
        $ret_str = $this->getIndexTableName($table,$index_map);
        break;
        
      }//if
      
    }//foreach
    
    return $ret_str;
    
  }//method
  
   /**
   *  goes through the master $table and adds the contents to the index table      
   *
   *  @param  string  $table  the main table (not the index table)
   *  @param  array $index_map  an index map, usually retrieved from the table schema
   *  @param  MingoSchema  $schema just here so get() will work as expected    
   */
  protected function populateIndex($table,array $index_map,MingoSchema $schema){
  
    $ret_bool = false;
    $limit = 100;
    $offset = 0;
  
    // get results from the table and add them to the index...
    while($map_list = $this->get($table,$schema,null,array($limit,$offset))){
    
      foreach($map_list as $map){
      
        $ret_bool = $this->insertIndex($table,$map['_id'],$map,$index_map);
      
      }//foreach
    
      $offset += $limit;
      
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
   *  @param  array $index_map  the map that represents the index
   *  @return boolean
   */
  protected function insertIndex($table,$_id,array $map,array $index_map){
    
    // canary, if this is a spatial index and the spatial field isn't set, then don't insert...
    foreach($index_map as $field => $index_type){
      if($this->isSpatialIndexType($index_type)){
        if(!isset($map[$field])){
          return false;
        }//if
      }//if
    }//foreach
    
    $index_table = $this->getIndexTableName($table,$index_map);
    
    $state = new StdClass();
    $state->depth = 0;
    $state->arrField = '';
    
    return $this->recursiveInsertIndex(
      $index_table,
      $_id,
      $map,
      $index_map,
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
   *  get info about the index
   *  
   *  @param  string  $table   
   *  @param  array $index_map
   *  @return string  the index table name
   */
  protected function getIndexTableName($table,array $index_map){
          
    $field_list = array_keys($index_map);
    $field_list_str = join(',',$field_list);
    $index_table = sprintf('%s_%s',$table,md5($field_list_str));
    return $index_table;
  
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
    
    if(!empty($query_map['limit'][0])){
      $query .= ' LIMIT %d OFFSET %d';
      $printf_vars[] = (int)$query_map['limit'][0];
      $printf_vars[] = (empty($query_map['limit'][1]) ? 0 : (int)$query_map['limit'][1]);
    }//if
    
    return vsprintf($query,$printf_vars);
    
  }//method
  
  /**
   *  handle sql'ing a generic list: NAME SYMBOL (...)
   *  
   *  @param  string  $symbol the symbol to use in the sQL string
   *  @param  string  $name the name of the field      
   *  @param  array $args a list of values that $name will be in         
   *  @return array array($sql,$val_list);
   */
  protected function handleListSql($symbol,$name,$args){
  
    $ret_str = sprintf(' %s %s (%s)',$name,$symbol,join(',',array_fill(0,count($args),'?')));
    return array($ret_str,$args);
  
  }//method
  
  /**
   *  handle sql'ing a generic val: NAME SYMBOL ?
   *  
   *  @param  string  $symbol the symbol to use in the sQL string
   *  @param  string  $name the name of the field      
   *  @param  array $arg  the argument         
   *  @return array array($sql,$val);
   */
  protected function handleValSql($symbol,$name,$arg){
  
    $ret_str = sprintf(' %s %s ?',$name,$symbol);
    return array($ret_str,$arg);
  
  }//method
  
  /**
   *  handle sql'ing a spatial point
   *  
   *  @since  10-18-10   
   *  @param  string  $symbol the symbol to use in the sQL string
   *  @param  string  $name the name of the field      
   *  @param  array $args the entire map under the $name key         
   *  @return array array($sql,$val);
   */
  protected function handleSpatialSql($symbol,$name,$args){
    throw new BadMethodCallException('the given SQL interface does not support spatial indexing');
  }//method
  
  /**
   *  handle appending to a sql string
   *  
   *  @param  string  $separator  something like 'AND' or 'OR'
   *  @param  string  $new_sql  the sql that will be appended to $old_sql
   *  @param  array $new_val  if $new_sql has any ?'s then their values need to be in $new_val
   *  @param  string  $old_sql  the original sql that will have $new_sql appended to it using $separator
   *  @param  array $old_val  all the old values that will be merged with $new_val
   *  @return array array($sql,$val)
   */
  protected function appendSql($separator,$new_sql,$new_val,$old_sql,$old_val){
  
    // sanity...
    if(empty($new_sql)){ return array($old_sql,$old_val); }//if
  
    // build the separator...
    if(empty($old_sql)){
      $separator = '';
    }else{
      $separator = ' '.trim($separator);
    }//if
          
    $old_sql = sprintf('%s%s%s',$old_sql,$separator,$new_sql);
    
    if(is_array($new_val)){
      $old_val = array_merge($old_val,$new_val);
    }else{
      $old_val[] = $new_val;
    }//if/else
  
    return array($old_sql,$old_val);
  
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
   *  allows customizing the field sql type using the schema's field hints
   *
   *  @since  10-19-10
   *  @param  string  $field  the field name
   *  @param  MingoSchema  $schema the schema for the table         
   *  @return string
   */
  protected function getSqlType($field,MingoSchema $schema){
    $ret_str = 'VARCHAR(100)';
    return $ret_str;
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
  protected function handleInsertSql($field,$val,$index_type = null){
    return array($field,$val,'?');
  }//method
  
  /**
   *  if you want to do anything special with the table's name, override this method
   *  
   *  @since  10-21-10   
   *  @param  string  $table
   *  @return string  $table, formatted
   */
  protected function handleTableSql($table){ return $table; }//method
  
  /**
   *  this gets all kinds of info about the kind of query you need to run to get the rows you
   *  want back
   *  
   *  @since  12-20-10
   *  @param  string  $table  the table that is being queried on
   *  @param  MingoSchema  $schema the table's schema
   *  @param  MingoCriteria  the selection criteria to query the table
   *  @param  array $limit   
   *  @return array a map with all kinds of info about the query that should be run
   */
  protected function getQueryInfo($table,MingoSchema $schema,MingoCriteria $where_criteria = null,array $limit = array())
  {
    $ret_map = array();
  
    $check_index_tables = ($where_criteria !== null)
      && ($where_criteria->hasWhere() || $where_criteria->hasSort());
  
    if($check_index_tables){
      
      // first get the criteria info...
      $where_map = $where_criteria->getWhere();
      $sort_map = $where_criteria->getSort();
      
      list($where_query,$val_list,$sort_query) = $this->normalizeCriteria($where_criteria);
      
      // now, find the right index table to select from...
      $index_table = $this->getIndexTable($table,$where_criteria,$schema);
      
      if(empty($index_table)){
        
        $is_valid = empty($where_map) 
          || ((count($where_map) === 1) && (isset($where_map['_id']) || isset($where_map['row_id'])));
        
        if(!$is_valid){
        
          throw new RuntimeException(
            sprintf('could not match fields: [%s] with an index table',join(',',array_keys($where_map)))
          );
          
        }//if
        
        if(!empty($sort_map) && ((count($sort_map) > 1) || !isset($sort_map['row_id']))){
          throw new RuntimeException(
            'you can only sort by "row_id" when selecting on nothing, "_id," or "row_id"'
          );
        }//if
        
        $ret_map['table'] = $table;
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
        
          // you can directly select on the table using "row_id" also...
          $ret_map['query_map'] = array(
            'select' => '*',
            'where' => $where_query,
            'sort' => $sort_query,
            'limit' => $limit
          );
          
          $ret_map['val_list'] = $val_list; 
        
        }//if/else
         
      }else{
        
        $ret_map['table'] = $index_table;
        $ret_map['is_index'] = true;
        $ret_map['query_map'] = array(
          'select' => 'DISTINCT _id',
          'where' => $where_query,
          'sort' => $sort_query,
          'limit' => $limit
        );
        $ret_map['val_list'] = $val_list; 
        
      }//if/else
      
    }else{
    
      // we're selecting raw, so just load results with no WHERE...
      $ret_map['table'] = $table;
      $ret_map['is_index'] = false;
      $ret_map['query_map'] = array(
        'select' => '*',
        'limit' => $limit
      );
      $ret_map['val_list'] = array();
    
    }//if/else
  
    return $ret_map;
  
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
  
}//class     

