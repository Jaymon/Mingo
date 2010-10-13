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
 *  @version 0.6
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 12-12-09
 *  @package mingo 
 ******************************************************************************/
abstract class mingo_db_sql extends mingo_db_interface {

  /**
   *  everything is utf-8, I'm not even giving people a choice
   */        
  const CHARSET = 'UTF8';
  const ENGINE = 'InnoDB';
  
  /**
   *  supported SQL databases
   */
  const TYPE_MYSQL = 1;
  const TYPE_SQLITE = 2;
  
  /**
   *  maps certain errors to one namespace (ie, key) so we can group table errors
   *  and handle them all with the same code, even though different dbs (eg, mysql and sqlite)
   *  throw different errors (eg, they have different error codes for "table doesn't exist")      
   *
   *  @var  array   
   */
  protected $error_map = array(
    'no_table' => array(
      'HY000', // sqlite
      '42S02' // mysql
    )
  );
  
  /**
   *  these are directly correlated with mingo_criteria's $where_criteria internal
   *  map that is returned from calling mingo_criteria::get(). These are used in
   *  the {@link getCriteria()} method to change a where_criteria object into usable SQL      
   *
   *  @var  array   
   */
  protected $method_map = array(
    'in' => array('sql' => 'handleListSql', 'symbol' => 'IN'),
    'nin' => array('sql' => 'handleListSql', 'symbol' => 'NOT IN'),
    'is' => array('sql' => 'handleValSql', 'symbol' => '='),
    'not' => array('sql' => 'handleValSql', 'symbol' => '!='),
    'gt' => array('sql' => 'handleValSql', 'symbol' => '>'),
    'gte' => array('sql' => 'handleValSql', 'symbol' => '>='),
    'lt' => array('sql' => 'handleValSql', 'symbol' => '<'),
    'lte' => array('sql' => 'handleValSql', 'symbol' => '<='),
    'sort' => array('sql' => 'handleSortSql')
  );
  
  /**
   *  connect to the db
   *  
   *  @param  integer $type one of the self::TYPE_* constants   
   *  @param  string  $db_name  the db to use
   *  @param  string  $host the host to use               
   *  @param  string  $username the username to use
   *  @param  string  $password the password to use   
   *  @return boolean
   *  @throws mingo_exception   
   */
  function connect($db_name,$host,$username,$password){

    $this->con_map['pdo_options'] = array(
      PDO::ERRMODE_EXCEPTION => true,
      // references I can find of the exit code 1 error is here:
      // http://bugs.php.net/bug.php?id=43199
      // it's this bug: http://bugs.php.net/42643 and it only affects CLI on <=5.2.4...
      PDO::ATTR_PERSISTENT => (strncasecmp(PHP_SAPI, 'cli', 3) === 0) ? false : true, 
      PDO::ATTR_EMULATE_PREPARES => true,
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ); 

    $dsn = '';
    $query_charset = '';
    if($this->isMysql()){
    
      if(empty($host)){ throw new mingo_exception('no $host specified'); }//if
      
      $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s',$host,$db_name,self::CHARSET);
      // http://stackoverflow.com/questions/1566602/is-set-character-set-utf8-necessary
      $query_charset = sprintf('SET NAMES %s',self::CHARSET); // another: 'SET CHARACTER SET UTF8';
    
    }else if($this->isSqlite()){
    
      $dsn = sprintf('sqlite:%s',$db_name);
      
      // for sqlite: PRAGMA encoding = "UTF-8"; from http://sqlite.org/pragma.html only good on db creation
      // http://stackoverflow.com/questions/263056/how-to-change-character-encoding-of-a-pdo-sqlite-connection-in-php
    
    }else{
    
      throw new mingo_exception('Unsupported db type, check the mingo_db::TYPE_* constants for supported db types');
    
    }//if/else
    
    try{
      
      $this->con_db = new PDO($dsn,$username,$password,$this->con_map['pdo_options']);
      if(!empty($query_charset)){ $this->getQuery($query_charset); }//if
      $this->con_map['connected'] = true;
      
    }catch(Exception $e){
    
      if($this->hasDebug()){
      
        $e_msg = array();
        
        $con_map_msg = array();
        foreach($this->con_map['pdo_options'] as $key => $val){
          $con_map_msg[] = sprintf('%s => %s',$key,$val);
        }//foreach
        
        $e_msg[] = sprintf(
          'new PDO("%s","%s","%s",array(%s)) failed.',
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
        
        throw new mingo_exception(join("\r\n",$e_msg),$e->getCode(),$e);
        
      }else{
        throw $e;
      }//if/else
    
    }//try/catch
    
    return $this->con_map['connected'];
  
  }//method

  function setType($val){ $this->con_map['type'] = $val; }//method
  function getType(){ return $this->hasType() ? $this->con_map['type'] : 0; }//method
  function hasType(){ return !empty($this->con_map['type']); }//method
  function isType($val){ return ((int)$this->getType() === (int)$val); }//method
  function isSqlite(){ return $this->isType(self::TYPE_SQLITE); }//method
  function isMysql(){ return $this->isType(self::TYPE_MYSQL); }//method
  
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
  
    $ret_bool = false;
    
    try{
    
      $_id_list = array();
      $_id_count = 0;
    
      // ok, load up all the rows we're going to delete...
      $row_list = $this->get($table,$schema,$where_criteria);
      if(!empty($row_list)){
      
        // populate the _id_list...
        foreach($row_list as $row){
          
          if(!empty($row['_id'])){
            $_id_list[] = $row['_id'];
            $_id_count++;
          }//if
        
        }//foreach
        
      }//if
      
      if(!empty($_id_list)){
        
        // begin the delete transaction...
        $this->con_db->beginTransaction();
        
        // delete values from index tables...
        $this->killIndexes($table,$_id_list,$schema);
        
        // delete values from main table...
        $query = sprintf(
          'DELETE FROM %s WHERE _id IN (%s)',
          $table,
          join(',',array_fill(0,$_id_count,'?'))
        );
        $ret_bool = $this->getQuery($query,$_id_list);
        
        // finish the delete transaction...
        $this->con_db->commit();
        
      }//if
        
    }catch(Exception $e){
    
      // get rid of any changes that were made since we failed...
      $this->con_db->rollback();
      throw $e;
    
    }//try/catch
  
    return $ret_bool;
  
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
    $id_list = array();
    $order_map = array();
    $list = array();
    $sort_query = '';
    
    if(($where_criteria instanceof mingo_criteria) && ($where_criteria->hasWhere() || $where_criteria->hasSort())){
      
      // first get the criteria info...
      $where_map = $where_criteria->getWhere();
      $sort_map = $where_criteria->getSort();
      
      list($where_query,$val_list,$sort_query) = $this->getCriteria($where_criteria);
      
      // now, find the right index table to select from...
      $index_table = $this->getIndexTable($table,$where_criteria,$schema);
      
      if(empty($index_table)){
        
        $is_valid = empty($where_map) 
          || ((count($where_map) === 1) && (isset($where_map['_id']) || isset($where_map['row_id'])));
        
        if($is_valid){
        
          if(!empty($sort_map) && ((count($sort_map) > 1) || !isset($sort_map['row_id']))){
            throw new mingo_exception(
              'you can only sort by "row_id" when selecting on nothing, "_id," or "row_id"'
            );
          }//if
        
          if(isset($where_map['_id'])){
            
            // it is a _id query, so the only sort can be row_id...
            $id_list = $val_list;
            
          }else{
          
            // you can directly select on the table using "row_id" also...
            $query = $this->getSelectQuery(
              $table,
              '*',
              $where_query,
              $sort_query,
              $limit
            );
            
            $list = $this->getQuery($query,$val_list);
            $sort_query = '';
          
          }//if/else
          
        }else{
        
          throw new mingo_exception(
            sprintf('could not match fields: [%s] with an index table',join(',',array_keys($where_map)))
          );
        
        }//if/else
         
      }else{
        
        $query = $this->getSelectQuery(
          $index_table,
          '_id',
          $where_query,
          $sort_query,
          $limit
        );
        
        $sort_query = ''; // clear it so it isn't used in the second query
        $limit = array();
        
        $stmt_handler = $this->getStatement($query,$val_list);
        $id_list = $stmt_handler->fetchAll(PDO::FETCH_COLUMN,0);
        if(!empty($id_list)){ $order_map = array_flip($id_list); }//if
      
      }//if/else
      
    }else{
    
      // we're selecting raw, so just load results with no WHERE...
      $query = $this->getSelectQuery(
        $table,
        '*',
        '',
        $sort_query,
        $limit
      );
      $list = $this->getQuery($query,array());
    
    }//if/else
    
    if(!empty($id_list)){
      
      $query = $this->getSelectQuery(
        $table,
        '*',
        sprintf('WHERE _id IN (%s)',join(',',array_fill(0,count($id_list),'?'))),
        $sort_query,
        $limit
      );
      
      $list = $this->getQuery($query,$id_list);
      
    }//if
    
    if(!empty($list)){
    
      foreach($list as $map){
      
        $ret_map = $this->getMap($map['body']);
        $ret_map['_id'] = $map['_id'];
        $ret_map['row_id'] = $map['row_id'];
        
        // put the ret_map in the right place...
        if(isset($order_map[$map['_id']])){
        
          $ret_list[$order_map[$map['_id']]] = $ret_map;
        
        }else{
        
          $ret_list[] = $ret_map;
        
        }//if/else
      
      }//foreach
      
      // sort the list if an order map was set, this is done because the rows
      // returned from the main table are not guarranteed to be in the same order
      // that the index table returned (I'm looking at you MySQL)...
      if(!empty($order_map)){
        ksort($ret_list);
      }//if
    
    }//if

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
    
    $ret_list = $this->get($table,$schema,$where_criteria,array(1,0));
    return empty($ret_list) ? array() : $ret_list[0];

  }//method
  
  /**
   *  tell how many records match $where_criteria in $table
   *  
   *  @param  string  $table
   *  @param  mingo_schema  $schema the table schema    
   *  @param  mingo_criteria  $where_criteria
   *  @param  integer|array $limit  array($limit,$offset)   
   *  @return integer the count
   */
  function getCount($table,mingo_schema $schema,mingo_criteria $where_criteria = null,$limit = array()){
  
    $ret_int = 0;
    $result = array();
    
    if(($where_criteria instanceof mingo_criteria) && $where_criteria->hasWhere()){
      
      $where_map = $where_criteria->getWhere();
      
      list($where_query,$val_list,$sort_query) = $this->getCriteria($where_criteria);
      $index_table = $this->getIndexTable($table,$where_criteria,$schema);

      if(empty($index_table)){
    
        $is_valid = empty($where_map) 
          || ((count($where_map) === 1) && (isset($where_map['_id']) || isset($where_map['row_id'])));
        
        if($is_valid){
        
          $query = $this->getSelectQuery($table,'count(*)',$where_query,'',$limit);
          $result = $this->getQuery($query,$val_list);
          
        }else{
        
          throw new mingo_exception(
            sprintf('could not match fields: [%s] with an index table',join(',',array_keys($where_map)))
          );
        
        }//if/else
        
      }else{
        
        $query = $this->getSelectQuery($index_table,'count(*)',$where_query,'',$limit);
        $result = $this->getQuery($query,$val_list);
        
      }//if/else
      
    }//if
    
    // if another query wasn't run, just run on the main table...
    if(empty($result)){
    
      $query = $this->getSelectQuery($table,'count(*)','','',$limit);
      $result = $this->getQuery($query);
    
    }//if
    
    if(isset($result[0]['count(*)'])){ $ret_int = (int)$result[0]['count(*)']; }//if
    return $ret_int;
  
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
  function insert($table,array $map,mingo_schema $schema){
    
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
        if($schema->hasIndex()){
        
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
   *  @param  mingo_schema  $schema the table schema      
   *  @return array the $map that was just saved with _id set
   *     
   *  @throws mingo_exception on any failure
   */
  function update($table,$_id,array $map,mingo_schema $schema){
    
    try{
    
      // we don't need to save the row_id into the body since it gets reset in get()...
      if(isset($map['row_id'])){ unset($map['row_id']); }//if
    
      // begin the insert transaction...
      $this->con_db->beginTransaction();
      
      $query = sprintf('UPDATE %s SET body=? WHERE _id=?',$table);
      $val_list = array($this->getBody($map),$_id);
      $ret_bool = $this->getQuery($query,$val_list);
      
      if($ret_bool){
        
        // we need to add to all the index tables...
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
   *  adds an index to $table
   *  
   *  @param  string  $table  the table to add the index to
   *  @param  array $map  usually something like array('field_name' => 1), this isn't need for sql
   *                      but it's the same way to keep compatibility with Mongo   
   *  @return boolean
   */
  function setIndex($table,array $map){
    
    // ALTER TABLE table_name`ADD|DROP [FULLTEXT] INDEX(column_name,...);
    // http://www.w3schools.com/sql/sql_alter.asp
    //
    // good info on indexes: http://www.mysqlperformanceblog.com/2006/08/17/duplicate-indexes-and-redundant-indexes/
    //  and: http://www.xaprb.com/blog/2006/08/28/how-to-find-duplicate-and-redundant-indexes-in-mysql/
    //  http://www.sql-server-performance.com/tips/optimizing_indexes_general_p2.aspx
    //  http://www.sql-server-performance.com/articles/per/index_not_equal_p1.aspx
    //  http://www.databasejournal.com/features/mysql/article.php/1382791
    //  Error 1170 when trying to create an index, means you are creating an unlimited index:
    //    http://www.mydigitallife.info/2007/07/09/mysql-error-1170-42000-blobtext-column-used-in-key-specification-without-a-key-length/
    // you can see indexes on the table using this: SHOW INDEX FROM ‘table’
    //
    // SQLite has a different index creation syntax...
    //  http://www.sqlite.org/lang_createindex.html create index [if not exists] name ON table_name (col_one[,col...])
    // Mysql syntax...
    //  http://dev.mysql.com/doc/refman/5.0/en/alter-table.html
    
    // the order bit is ignored for sql, so we just need the keys...
    $field_list = array_keys($map);
    $field_list_str = join(',',$field_list);
    $index_name = 'i'.md5($field_list_str);
    
    if($this->isSqlite()){
      $query = sprintf('CREATE INDEX IF NOT EXISTS %s ON %s (%s)',$index_name,$table,$field_list_str);
    }else if($this->isMysql()){
      $query = sprintf('ALTER TABLE %s ADD INDEX %s (%s)',$table,$index_name,$field_list_str);
    }//if/else
    
    return $this->getQuery($query);
  
  }//method
  
  
  /**
   *  deletes a table
   *  
   *  @param  string  $table  the table to delete from the db
   *  @return boolean
   */
  function killTable($table){
    
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
   *  get all the tables of the currently connected db
   *  
   *  @return array a list of table names
   */
  function getTables($table = ''){
  
    $ret_list = array();
    if($this->isSqlite()){
    
      // thanks: http://us3.php.net/manual/en/ref.sqlite.php#47442
      // query that should check sqlite tables: "SELECT * FROM sqlite_master WHERE type='table' AND name='$mytable'"
      // SELECT name FROM sqlite_master WHERE type = \'table\'' from: http://www.litewebsite.com/?c=49
      $query = 'Select * FROM sqlite_master WHERE type=?';
      $query_vars = array('table');
      if(!empty($table)){
        $query .= ' AND name=?';
        $query_vars[] = $table;
      }//if
      
      $list = $this->getQuery($query,$query_vars);
      if(!empty($list)){
      
        // sqlite gives us tons of stuff like schema and stuff, we just want the names...
        foreach($list as $map){
          $ret_list[] = $map['tbl_name'];
        }//foreach
      
      }//if
    
    }else if($this->isMysql()){
  
      $query = 'SHOW TABLES';
      $query_vars = array();
      if(!empty($table)){
        $query .= ' LIKE ?';
        $query_vars[] = $table;
      }//if
      
      $list = $this->getQuery($query,$query_vars);
      if(!empty($list)){
        
        // for some reason, mysql puts each table in an array with a horrible name: Tables_in_DBNAME
        foreach($list as $table_map){
          $array_keys = array_keys($table_map);
          if(isset($table_map[$array_keys[0]])){ $ret_list[] = $table_map[$array_keys[0]]; }//if
        }//foreach
        
      }//if
    
    }//if/else
    
    return $ret_list;
  
  }//method
  
  /**
   *  adds a table to the db
   *  
   *  http://dev.mysql.com/doc/refman/5.0/en/storage-requirements.html
   *      
   *  @param  string  $table  the table to add to the db
   *  @param  mingo_schema  $schema the table schema    
   *  @return boolean
   */
  function setTable($table,mingo_schema $schema){

    $ret_bool = $this->hasTable($table);
    $query = '';
    
    if(!$ret_bool){
    
      if($this->isMysql()){
        
        $query = sprintf('CREATE TABLE %s (
            row_id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            _id VARCHAR(24) NOT NULL,
            body LONGBLOB,
            UNIQUE KEY (_id)
        ) ENGINE=%s CHARSET=%s',$table,self::ENGINE,self::CHARSET);
        
        $ret_bool = $this->getQuery($query);
        
      }else if($this->isSqlite()){
      
        $query = sprintf('CREATE TABLE %s (
            row_id INTEGER NOT NULL PRIMARY KEY ASC,
            _id VARCHAR(24) COLLATE NOCASE NOT NULL,
            body BLOB
        )',$table);
      
        $ret_bool = $this->getQuery($query);
        if($ret_bool){
        
          // add the index for _id to the table...
          $this->setIndex($table,array('_id' => 1));
        
        }//if
      
      }//if/else if
      
    }//if
    
    if($ret_bool){
      
      // add all the indexes for this table...
      if($schema->hasIndex()){
      
        foreach($schema->getIndex() as $index_map){
        
          $printf_vars = array();
          $field_list = array_keys($index_map);
          $field_list_str = join(',',$field_list);
          $index_table = sprintf('%s_%s',$table,md5($field_list_str));
        
          if(!$this->hasTable($index_table)){
          
            $query = 'CREATE TABLE %s (';
            $printf_vars[] = $index_table;
            
            foreach($field_list as $field){
            
              if($this->isMysql()){
            
                $query .= '%s VARCHAR(100) NOT NULL,';
                
              }else if($this->isSqlite()){
              
                $query .= '%s VARCHAR(100) COLLATE NOCASE NOT NULL,';
              
              }//if/else if
                
              $printf_vars[] = $field;
            
            }//foreach
          
            // it turns out putting NOT NULL UNIQUE creates an index, so when I set the _id index later
            // it makes 2 _id indexes, one unique and one normal
            $query .= '_id VARCHAR(24) NOT NULL, PRIMARY KEY (%s,_id))';
            $printf_vars[] = $field_list_str;
          
            if($this->isMysql()){
            
              $query .= ' ENGINE=%s CHARSET=%s';
              $printf_vars[] = self::ENGINE;
              $printf_vars[] = self::CHARSET;
              
            }//if
            
            $query = vsprintf($query,$printf_vars);
  
            if($this->getQuery($query)){
            
              $this->setIndex($index_table,array('_id' => 1));
              
              // if debugging is on it means we're in dev so go ahead and populate the index...
              if($this->hasDebug()){
                $this->populateIndex($table,$index_map,$schema);
              }//if
            
            }//if
            
          }//if

        }//foreach
      
      }//if
      
    }//if
    
    return $ret_bool;
  
  }//method
  
  /**
   *  check to see if a table is in the db
   *  
   *  @param  string  $table  the table to check
   *  @return boolean
   */
  function hasTable($table){
  
    // get all the tables currently in the db...
    $table_list = $this->getTables($table);
    return !empty($table_list);
    
  }//method
  
  /**
   *  executes the query and returns the result
   *     
   *  @see  getStatement()   
   *  @param  string  $query  the query to prepare and run
   *  @param  array $val_list the values list for the query, if the query has ?'s then 
   *                          the values should be in this array           
   *  @return mixed array of results if select query, last id if insert, update
   */
  function getQuery($query,$val_list = array()){
  
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
      throw new mingo_exception(
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
   *  get all the indexes of $table
   *        
   *  @param  string  $table  the table to get the indexes from
   *  @return array an array in the same format that {@link mingo_schema::getIndexes()} returns
   */
  public function getIndexes($table){
  
    $ret_list = array();
  
    // get just the $table tables...
    $table_list = array();
    foreach($this->getTables() as $table_name){
    
      if(($table_name === $table) || preg_match(sprintf('#^%s_[a-z0-9]{32,}#i',$table),$table_name)){
      
        $table_list[] = $table_name;
      
      }//if
    
    }//foreach
    
    foreach($table_list as $table_name){
      
      if($this->isMysql()){
      
        // http://www.techiegyan.com/?p=209
        
        // see also: http://www.xaprb.com/blog/2006/08/28/how-to-find-duplicate-and-redundant-indexes-in-mysql/
        // for another way to find indexes
        
        // also good reading: http://www.mysqlperformanceblog.com/2006/08/17/duplicate-indexes-and-redundant-indexes/
      
        $query = sprintf('SHOW INDEX FROM %s',$table_name);
        $index_list = $this->getQuery($query);
        
        $ret_map = array();
        $table_index_list = array();
        $last_i = count($index_list) - 1;
        
        foreach($index_list as $i => $index_map){
        
          if($index_map['Seq_in_index'] > 1){
          
            $ret_map[$index_map['Column_name']] = 1;
            
          }else{
          
            $ret_map = array();
            $ret_map[$index_map['Column_name']] = 1;
          
          }//if/else
          
          $next_i = $i + 1;
          $have_index = ($next_i > $last_i) || ($index_list[$next_i]['Seq_in_index'] == 1);
          if($have_index){
          
            // all this is to account for duplicate indexes but also for different tables (ie,
            // tables might have the same index (eg, _id), but we don't want to include
            // any duplicate index more than once, since multiple tables might have the same
            // duplicate indexes)...
            $table_index_list[] = $ret_map;
            $keys = array_keys($table_index_list,$ret_map,true);
            if(empty($keys) || (count($keys) != 2)){
              if(!in_array($ret_map,$ret_list,true)){
                $ret_list[] = $ret_map;
              }//if
            }else{
              $keys = array_keys($ret_list,$ret_map,true);
              if(count($keys) < 2){
                $ret_list[] = $ret_map;
              }//if
            }//if/else
            
            $ret_map = array();
              
          }else{
            $ret_map[$index_map['Column_name']] = 1;
          }//if/else
        
        }//foreach
        
      }else if($this->isSqlite()){
      
        // sqlite: pragma table_info(table_name)
        //  http://www.sqlite.org/pragma.html#schema
        //  http://www.mail-archive.com/sqlite-users@sqlite.org/msg22055.html
        //  http://stackoverflow.com/questions/604939/how-can-i-get-the-list-of-a-columns-in-a-table-for-a-sqlite-database
        $query = sprintf('PRAGMA index_list(%s)',$table_name);
        $index_list = $this->getQuery($query);
        foreach($index_list as $index_map){
        
          $query = sprintf('PRAGMA index_info(%s)',$index_map['name']);
          $field_list = $this->getQuery($query);

          $ret_map = array();
          foreach($field_list as $field_map){
            $ret_map[$field_map['name']] = 1; // all sql indexes sort asc
          }//foreach
          
          if(!in_array($ret_map,$ret_list,true)){
            $ret_list[] = $ret_map;
          }//if
          
        }//foreach
        
      }//if/else if
      
    }//foreach
    
    return $ret_list;
  
  }//method
  
  /**
   *  handle an error state
   *  
   *  this is handy for trying to add tables or indexes if they don't exist so the db
   *  handler can then try the queries that errored out again
   *  
   *  this method will also try and fix any exception that match codes found in {@link $error_map},
   *  if it does successfully resolve the exception, it will return true giving the failed method
   *  a chance to redeem itself.      
   *  
   *  @param  Exception $e  the exception that was raised
   *  @param  string  $table  the table name
   *  @param  mingo_schema  $schema the table schema
   *  @return boolean false on failure to solve the exception, true if $e was successfully resolved
   */
  function handleException(Exception $e,$table,mingo_schema $schema){
  
    $ret_bool = false;
    $e_code = $e->getCode();
    if(!empty($e_code)){
    
      if(in_array($e_code,$this->error_map['no_table'])){
      
        // table was missing, so assure the table and everything...
        $ret_bool = $this->setTable($table,$schema);
      
      }//if
      
    }//if
  
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
   *  @param  mingo_schema  $schema the table schema
   *  @return boolean
   */
  protected function setIndexes($table,$_id,$map,$schema){
        
    $ret_bool = false;
    
    foreach($schema->getIndex() as $index_map){
    
      $ret_bool = $this->insertIndex($table,$_id,$map,$index_map);
      
    }//foreach
    
    return $ret_bool;
    
  }//method
  
  /**
   *  removes all the indexes for a given $_id
   *  
   *  this is called after updating the value and before calling {@link setIndexes()}
   *
   *  @param  string  $table
   *  @param  string|array  $_id  either one _id or many in an array
   *  @param  mingo_schema  $schema
   *  @return boolean
   */
  protected function killIndexes($table,$_id_list,$schema){
  
    // canary...
    if(!is_array($_id_list)){ $_id_list = array($_id_list); }//if
  
    $ret_bool = false;
    $_id_count = count($_id_list);
  
    foreach($schema->getIndex() as $index_map){
    
      $field_list = array_keys($index_map);
      $field_name_str = join(',',$field_list);
      $index_table = sprintf('%s_%s',$table,md5($field_name_str));
      
      if(!empty($index_table)){
      
        $query = sprintf(
          'DELETE FROM %s WHERE _id IN (%s)',
          $index_table,
          join(',',array_fill(0,$_id_count,'?'))
        );
        
        $ret_bool = $this->getQuery($query,$_id_list);
  
      }//if
      
    }//foreach
  
    return $ret_bool;
  
  }//method
  
  /**
   *  get the index table name from the table and the list of fields the index comprises
   *  
   *  @param  string  $table  the main table's name
   *  @param  array $field_list a list of the field names the index encompasses
   *  @return string  the index table name
   */
  protected function getIndexTable($table,mingo_criteria $where_criteria,mingo_schema $schema){
  
    $ret_str = '';
  
    $where_map = $where_criteria->getWhere();
    $sort_map = $where_criteria->getSort();
    
    $field_list = array_keys($where_map);
    if(empty($field_list) && !empty($sort_map)){ $field_list = array_keys($sort_map); }//if
  
    // now go through the index and see if it matches...
    foreach($schema->getIndex() as $index_map){
    
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
        $ret_str = sprintf('%s_%s',$table,md5(join(',',array_keys($index_map))));
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
   *  @param  mingo_schema  $schema just here so get() will work as expected    
   */
  protected function populateIndex($table,$index_map,mingo_schema $schema){
  
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
   *  @param  string  $table  the master table, not the index table
   *  @param  string  $_id the _id of the $table where $map is found
   *  @param  array $map  the key/value pairs found in $table's body field
   *  @param  array $index_map  the map that represents the index
   *  @return boolean
   */
  protected function insertIndex($table,$_id,$map,$index_map){
    
    $field_list = array_keys($index_map);
    $field_name_str = join(',',$field_list);
    $index_table = sprintf('%s_%s',$table,md5($field_name_str));
    $field_name_str .= ',_id';
    $field_val_str = join(',',array_fill(0,count($field_list) + 1,'?'));
    $val_list = array();
  
    $query = 'INSERT INTO %s (%s) VALUES (%s)';
    $query = sprintf('INSERT INTO %s (%s) VALUES (%s)',$index_table,$field_name_str,$field_val_str);
    
    foreach($index_map as $field => $order){
    
      $val = '';
      if(isset($map[$field])){
        $val = $map[$field];
      }//if
    
      $val_list[] = $val;
    
    }//foreach
    
    $val_list[] = $_id;
    
    return $this->getQuery($query,$val_list);
  
  }//method
  
  /**
   *  builds a select query suitable to be passed into {@link getQuery()}
   *  
   *  this function puts all the different parts together
   *  
   *  @param  string  $table  the table   
   *  @param  string  $select_query the fields to select from (usually * or count(*), or _id
   *  @param  string  $where_query  the where part of the string
   *  @param  string  $sort_query the sort part of the string
   *  @param  array $limit  array($limit,$offset)
   *  @return string  the built query         
   */
  protected function getSelectQuery($table,$select_query,$where_query = '',$sort_query = '',$limit = array()){
  
    $printf_vars = array();
        
    // build the query...
    $query = 'SELECT %s FROM %s';
    $printf_vars[] = $select_query;
    $printf_vars[] = $table;
    
    if(!empty($where_query)){
      $query .= ' %s';
      $printf_vars[] = $where_query;
    }//if
    
    // add sort...
    if(!empty($sort_query)){
      $query .= ' '.$sort_query;
    }//if
    
    if(!empty($limit[0])){
      $query .= ' LIMIT %d OFFSET %d';
      $printf_vars[] = (int)$limit[0];
      $printf_vars[] = (int)(empty($limit[1]) ? 0 : $limit[1]);
    }//if
    
    return vsprintf($query,$printf_vars);
    
  }//method
  
  /**
   *  convert the $criteria_where and the $criteria_sort into SQL
   *  
   *  the sql is suitable to be used in PDO, and so the string has ? where each value
   *  should go, the value array will correspond to each of the ?      
   *  
   *  @param  mingo_criteria  $where_criteria   
   *  @return array an array map with 'where_str', 'where_val', and 'sort_str' keys set      
   */
  protected function getCriteria(mingo_criteria $where_criteria){
  
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
              
                if(!empty($this->method_map[$command_bare]['sql'])){
                
                  $callback = $this->method_map[$command_bare]['sql'];
                  list($command_sql,$command_val) = $this->{$callback}(
                    $this->method_map[$command_bare]['symbol'],
                    $name,
                    $map[$command]
                  );
                  
                  list($where_sql,$where_val) = $this->appendSql(
                    'AND',
                    $command_sql,
                    $command_val,
                    $where_sql,
                    $where_val
                  );
                  
                }//if
              
              }//if
            
            }else{
            
              // @todo  throw an error, there shouldn't ever be an array value outside a command
              throw new mingo_exception(
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
  private function isBinaryString($val){
  
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
  
}//class     
