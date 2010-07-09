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
 *  @version 0.4
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
   *  connect to the db
   *  
   *  @param  integer $type one of the self::TYPE_* constants   
   *  @param  string  $db the db to use
   *  @param  string  $host the host to use               
   *  @param  string  $username the username to use
   *  @param  string  $password the password to use   
   *  @return boolean
   *  @throws mingo_exception   
   */
  function connect($db,$host,$username,$password){

    $this->con_map['pdo_options'] = array(
      PDO::ERRMODE_EXCEPTION => true,
      PDO::ATTR_PERSISTENT => true,
      PDO::ATTR_EMULATE_PREPARES => true,
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    );

    $dsn = '';
    $query_charset = '';
    if($this->isMysql()){
    
      if(empty($host)){ throw new mingo_exception('no $host specified'); }//if
      
      $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s',$host,$db,self::CHARSET);
      // http://stackoverflow.com/questions/1566602/is-set-character-set-utf8-necessary
      $query_charset = sprintf('SET NAMES %s',self::CHARSET); // another: 'SET CHARACTER SET UTF8';
    
    }else if($this->isSqlite()){
    
      $dsn = sprintf('sqlite:%s',$db);
      
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
    $sort_query = '';
    $run_main_query = true;
    
    if(($where_criteria instanceof mingo_criteria) && $where_criteria->has()){
      
      // first get the criteria info...
      list($where_map,$sort_map) = $where_criteria->get();
      list($where_query,$val_list,$sort_query) = $where_criteria->getSql();
      
      // now, find the right index table to select from...
      $index_table = $this->getIndexTable($table,$where_criteria,$schema);

      if(!empty($index_table)){
        
        $query = $this->getSelectQuery(
          $index_table,
          '_id',
          $where_query,
          $sort_query,
          $limit
        );
        $row_list = $this->getQuery($query,$val_list);
        $sort_query = ''; // clear it so it isn't used in the second query
        
        if(empty($row_list)){
        
          $run_main_query = false;
        
        }else{
          
          // build the id list...
          foreach($row_list as $key => $row){
            if(isset($row['_id'])){
              $order_map[$row['_id']] = $key;
              $id_list[] = $row['_id'];
            }//if
          }//foreach
          
        }//if
         
      }else{
        
        if((count($where_map) === 1) && isset($where_map['_id'])){
          
          // it is a _id query, so the only sort can be row_id...
          $id_list = $val_list;
          if(!empty($sort_map) && ((count($sort_map) > 1) || !isset($sort_map['row_id']))){
            throw new mingo_exception(
              sprintf('you can only sort by "row_id" when directly selecting on table: %s',$table)
            );
          }//if
          
        }else{
        
          throw new mingo_exception(
            sprintf('could not match fields: [%s] with an index table',join(',',array_keys($where_map)))
          );
        
        }//if/else
      
      }//if/else
      
    }//if
    
    if($run_main_query){
      
      $printf_vars = array();
      $query = 'SELECT * FROM %s';
      $printf_vars[] = $table;
      
      if(!empty($id_list)){
        
        $query .= ' WHERE _id IN (%s)';
        $printf_vars[] = join(',',array_fill(0,count($id_list),'?'));
      
      }//if
      
      // add sort...
      if(!empty($sort_query)){
        $query .= ' '.$sort_query;
      }//if
      
      if(!empty($limit[0])){
        $query .= ' LIMIT %d OFFSET %d';
        $printf_vars[] = $limit[0];
        $printf_vars[] = empty($id_list) ? $limit[1] : 0;
      }//if
      
      $query = vsprintf($query,$printf_vars);
      
      $list = $this->getQuery($query,$id_list);
      foreach($list as $map){
      
        $ret_map = $this->getMap($map['body']);
        $ret_map['_id'] = $map['_id'];
        $ret_map['row_id'] = $map['row_id'];
        
        // put the ret_map in the right place...
        if(isset($order_map[$map['_id']])){
        
          $ret_list[$order_map[$map['_id']]] = $ret_map;
        
        }else{
        
          $ret_list[] = $ret_map;
        
        }
      
      }//foreach
      
      // sort the list if an order map was set...
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
   *  @return integer the count
   */
  function getCount($table,mingo_schema $schema,mingo_criteria $where_criteria = null){
  
    $ret_int = 0;
    $result = array();
    
    if($where_criteria instanceof mingo_criteria){
      
      // first get the maps...
      list($where_map,$sort_map) = $where_criteria->get();
      list($where_query,$val_list,$sort_query) = $where_criteria->getSql();
      $index_table = $this->getIndexTable($table,$where_criteria,$schema);

      if(!empty($index_table)){
        
        $query = $this->getSelectQuery($index_table,'count(*)',$where_query);
        $result = $this->getQuery($query,$val_list);
        
      }else{
      
        if((count($where_map) === 1) && isset($where_map['_id'])){
          
          $printf_vars = array();
          $query = 'SELECT count(*) FROM %s';
          $printf_vars[] = $table;
          
          if(!empty($id_list)){
            
            $query .= ' WHERE _id IN (%s)';
            $printf_vars[] = join(',',array_fill(0,count($id_list),'?'));
          
          }//if
          
          $query = vsprintf($query,$printf_vars);
          $result = $this->getQuery($query,$val_list);
          
        }else{
        
          throw new mingo_exception(
            sprintf('could not match fields: [%s] with an index table',join(',',array_keys($where_map)))
          );
        
        }//if/else
      
      }//if/else
      
    }//if
    
    // if another query wasn't run, just run on the main table...
    if(empty($result)){
    
      $query = sprintf('SELECT count(*) FROM %s',$table);
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
  function insert($table,$map,mingo_schema $schema){
    
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
  function update($table,$_id,$map,mingo_schema $schema){
    
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
  function setIndex($table,$map){
    
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
            UNIQUE KEY (_id),
            KEY (_id)
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
          
            $query .= '_id VARCHAR(24) NOT NULL UNIQUE, PRIMARY KEY (%s,_id))';
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
   *  prepares and executes the query and returns the result
   *  @param  string  $query  the query to prepare and run
   *  @param  array $val_list the values list for the query, if the query has ?'s then 
   *                          the values should be in this array
   *  @param  mingo_schema  $schema the table schema, this doesn't need to be passed in
   *                                but might help if an error is encountered           
   *  @return mixed array of results if select query, last id if insert, update
   */
  function getQuery($query,$val_list = array(),mingo_schema $schema = null){
  
    $ret_mixed = false;
  
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
    if($is_success){

      if(preg_match('#^(?:select|show)#iu',$query)){

        // a select statement should always return an array...
        $ret_mixed = $stmt_handler->fetchAll(PDO::FETCH_ASSOC);
        
      }else{
      
        // all other queries should return whether they were successful...
        $ret_mixed = $is_success;
        
      }//if/else
      
    }else{
    
      $err_map = $stmt_handler->errorInfo();
      throw new mingo_exception(
        sprintf('query "%s" failed execution with error: %s',
          $query,
          print_r($err_map,1)
        ),
        $err_map[0]
      );
      
    }//if/else

    return $ret_mixed;
  
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
  
    // we're interested in the where part of the criteria...
    list($where_map,$sort_map) = $where_criteria->get();
    
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
      $printf_vars[] = $limit[0];
      $printf_vars[] = empty($limit[1]) ? 0 : $limit[1];
    }//if
    
    return vsprintf($query,$printf_vars);
    
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
