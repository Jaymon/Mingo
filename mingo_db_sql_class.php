<?php

/**
 *  handle relational db abstraction for mingo    
 *
 *  @version 0.1
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 12-12-09
 *  @package mingo 
 ******************************************************************************/
class mingo_db_sql {

  /**
   *  everything is utf-8, I'm not even giving people a choice
   */        
  const CHARSET = 'UTF8';
  const ENGINE = 'InnoDB';

  /**
   *  holds all the connection information this class used
   *  
   *  @var  array associative array
   */
  private $con_map = array();
  
  /**
   *  holds the actual db connection, established by calling {@link connect()}
   *  @var  MongoDb
   */
  private $con_db = null;
  
  /**
   *  hold all the queries this instance has run
   *  @var  array
   */
  private $query_list = array();
  
  /**
   *  used by {@link getInstance()} to keep a singleton object, the {@link getINstance()} 
   *  method should be the only place this object is ever messed with so if you want to touch it, DON'T!  
   *  @var mingo_db
   */
  private static $instance = null;
  
  function __construct($type){
  
    $this->setType($type);
    
  }//method
  
  /**
   *  connect to the db
   *  
   *  @param  integer $type one of the self::TYPE_* constants   
   *  @param  string  $db the db to use, defaults to {@link getDb()}
   *  @param  string  $host the host to use, defaults to {@link getHost()}. if you want a specific
   *                        port, attach it to host (eg, localhost:27017 or example.com:27017), only required
   *                        for Mysql               
   *  @param  string  $username the username to use, defaults to {@link getUsername()}
   *  @param  string  $password the password to use, defaults to {@link getPassword()}   
   *  @return boolean
   *  @throws mingo_exception   
   */
  function connect($db,$host = '',$username = '',$password = ''){

    try{
    
      $this->con_map['pdo_options'] = array(
        PDO::ERRMODE_EXCEPTION => true,
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_EMULATE_PREPARES => true,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
      );
  
      // alternative to setting charset: $this->query('SET NAMES utf8');
      // another: $this->query('SET CHARACTER SET UTF8'); 
      
      // for sqlite: PRAGMA encoding = "UTF-8"; from http://sqlite.org/pragma.html
    
      $dsn = '';
      $query_charset = '';
      if($this->isMysql()){
      
        if(empty($host)){ throw new mingo_exception('no $host specified'); }//if
        
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s',$host,$db,self::CHARSET);
        $query_charset = sprintf('SET NAMES %s',self::CHARSET); // another: 'SET CHARACTER SET UTF8';
      
      }else if($this->isSqlite()){
      
        $dsn = sprintf('sqlite:%s',$db);
      
      }else{
      
        throw new mingo_exception('Unsupported db type, check the mingo_db::TYPE_* constants for supported db types');
      
      }//if/else
      
      $this->con_db = new PDO($dsn,$username,$password,$this->con_map['pdo_options']);
      $this->con_map['connected'] = true;
      
    }catch(PDOException $e){
    
      $this->error_msg .= 'dbal::connect - db failed connection '.$e->getMessage();
      
    }//try/catch
    
    return $this->con_map['connected'];
  
  }//method

  function setType($val){ $this->con_map['type'] = $val; }//method
  function getType(){ return $this->hasType() ? $this->con_map['type'] : 0; }//method
  function hasType(){ return !empty($this->con_map['type']); }//method
  function isType($val){ return ((int)$this->getType() === (int)$val); }//method
  function isSqlite(){ return $this->isType(mingo_db::TYPE_SQLITE); }//method
  function isMysql(){ return $this->isType(mingo_db::TYPE_MYSQL); }//method
  
  function isConnected(){ return !empty($this->con_map['connected']); }//method
  
  /**
   *  tell how many records match $where_map in $table
   *  
   *  @param  string  $table
   *  @param  array $where_map
   *  @return integer the count   
   */
  function count($table,$where_map = array()){
  
    $ret_int = 0;
    $table = $this->getTable($table);
    list($where_map) = $this->getCriteria($where_map);
    if(empty($where_map)){
      $ret_int = $table->count();
    }else{
      $cursor = $table->find($where_map);
      $ret_int = $cursor->count();
    }//if/else
    return $ret_int;
  
  }//method
  
  /**
   *  delete the records that match $where_map in $table
   *  
   *  @param  string  $table
   *  @param  array $where_map
   *  @return boolean
   */
  function delete($table,$where_map = array()){
  
    $table = $this->getTable($table);
    list($where_map) = $this->getCriteria($where_map);
    return $table->remove($where_map);
  
  }//method
  
  /**
   *  get a list of rows matching $where_map
   *  
   *  @param  string  $table
   *  @param  mingo_criteria  $where_map
   *  @param  array $limit  array($limit,$offset)   
   *  @return array   
   */
  function get($table,mingo_criteria $where_criteria = null,$limit = array()){
    
    $ret_list = array();
    $id_list = array();
    
    if($where_criteria instanceof mingo_criteria){
      
      // first get the maps...
      list($where_map,$sort_map) = $where_criteria->get();

      if(!empty($where_map)){
        
        // get the table...
        $index_table = sprintf('%s_%s',$table,md5(join(',',array_keys($where_map))));
        
        // build the query...
        $query = 'SELECT _id FROM %s %s';
        $printf_vars = array();
        list($where_query,$val_list) = $where_criteria->getSql();
        
        $printf_vars[] = $table;
        $printf_vars[] = $where_query;
        
        if(!empty($limit[0])){
          $query .= ' LIMIT %d OFFSET %d';
          $printf_vars[] = $limit[0];
          $printf_vars[] = $limit[1];
        }//if
        
        $query = vsprintf($query,$printf_vars);
        
        $id_list = $this->getQuery($query,$val_list);
        out::e($id_list);
        
      }//if
      
    }//if
    
    $printf_vars = array();
    $query = 'SELECT * FROM %s';
    $printf_vars[] = $table;
    
    if(!empty($id_list)){
      
      $query .= ' WHERE _id IN (%s)';
      $printf_vars[] = join(',',array_fill(0,count($id_list),'?'));
    
    }//if
    
    if(!empty($limit[0])){
      $query .= ' LIMIT %d OFFSET %d';
      $printf_vars[] = $limit[0];
      $printf_vars[] = $limit[1];
    }//if
    
    $query = vsprintf($query,$printf_vars);
    
    $list = $this->getQuery($query,$id_list);
    foreach($list as $map){
    
      $ret_map = json_decode(gzuncompress($map['body']));
      $ret_map['_id'] = $map['_id'];
      $ret_map['row_id'] = $map['row_id'];
      $ret_list[] = $ret_map;
    
    }//foreach
    
    out::e($ret_list);
    return $ret_list;

  }//method
  
  /**
   *  get the first found row in $table according to $where_map find criteria
   *  
   *  @param  string  $table
   *  @param  array|mingo_criteria  $where_map
   *  @return array
   */
  function getOne($table,$where_map = array()){
    
    $table = $this->getTable($table);
    list($where_map) = $this->getCriteria($where_map);
    $ret_map = $table->findOne($where_map);
    return empty($ret_map) ? array() : $ret_map;

  }//method
  
  /**
   *  increment $field in $table by $count according to $where_map search criteria
   *  
   *  @param  string  $table
   *  @param  string  $field  the field to increment
   *  @param  array $where_map  the find criteria
   *  @param  integer $count  how many you want to increment $field by
   *  @return boolean
   */
  function bump($table,$field,$where_map,$count = 1){
  
    // canary...
    if(empty($field)){ throw new mingo_exception('no $field specified'); }//if
    if(empty($count)){ return true; }//if
  
    $table = $this->getTable($table);
    list($where_map) = $this->getCriteria($where_map);
    
    $c = new mingo_criteria();
    $c->inc($field,$count);
    
    return $this->update($table,$c,$where_map);
    
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
    $field_map['body'] = gzcompress(json_encode($map));
    
    // insert the saved map into the table...
    $field_name_str = join(',',array_keys($field_map));
    $field_val_str = join(',',array_fill(0,count($field_map),'?'));
    
    $query = sprintf('INSERT INTO %s (%s) VALUES (%s)',$table,$field_name_str,$field_val_str);
    $val_list = array_values($field_map);
    $ret_bool = $this->getQuery($query,$val_list);
    
    if($ret_bool){
    
      // we need to add to all the index tables...
      if($schema->hasIndex()){
      
        foreach($schema->getIndex() as $index_map){
        
          $printf_vars = array();
          $field_list = array_keys($index_map);
          $field_name_str = join(',',$field_list);
          $index_table = sprintf('%s_%s',$table,md5($field_name_str));
          $field_name_str .= ',_id';
          $field_val_str = join(',',array_fill(0,count($field_list) + 1,'?'));
          $val_list = array();
        
          $query = 'INSERT INTO %s (%S) VALUES (%s)';
          $query = sprintf('INSERT INTO %s (%s) VALUES (%s)',$index_table,$field_name_str,$field_val_str);
          
          foreach($index_map as $field => $order){
          
            $val_list[] = empty($map[$field]) ? '' : $map[$field];
          
          }//foreach
          
          $val_list[] = $field_map['_id'];
          
          $ret_bool = $this->getQuery($query,$val_list);
          
        }//foreach
      
      }//if
      
      $map['_id'] = $field_map['_id'];
      
    }//if
    
    return $map;
  
  }//method
  
  /**
   *  update $map from $table using $where_map as criteria
   *  
   *  @param  string  $table  the table name
   *  @param  array|mingo_criteria  $map  the key/value map that will be added to $table
   *  @param  array|mingo_criteria  $where_map  if empty, $map is checked for '_id'   
   *  @return array the $map that was just saved
   *  @throws mingo_exception on any failure
   */
  function update($table,$map,$where_map = array()){
    
    // canary...
    if(empty($where_map)){
      if(empty($map['_id'])){
        // since there isn't a where map, and no unique id, insert it instead...
        return $this->insert($table,$map);
      }else{
        $where_map = array('_id' => $map['_id']);
      }//if
    }//if
    
    $ret_id = null;
    $table = $this->getTable($table);
    list($map) = $this->getCriteria($map);
    list($where_map) = $this->getCriteria($where_map);
    
    // clean up before updating...
    if(isset($map['_id'])){
      $ret_id = $map['_id'];
      unset($map['_id']);
    }//if
    
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
  private function killIndexTables($table){
  
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
   *  @param  string  $table  the table to add to the db
   *  @param  mingo_schema  $schema the table schema    
   *  @return boolean
   */
  function setTable($table,mingo_schema $schema){

    $ret_bool = false;
    $query = '';
    
    if($this->isMysql()){
      
      $query = sprintf('CREATE TABLE %s (
          row_id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          _id VARCHAR(24) NOT NULL,
          body LONGBLOB,
          UNIQUE KEY (id),
          KEY (_id)
      ) ENGINE=%s CHARSET=%s',$table,self::ENGINE,self::CHARSET);
      
      $ret_bool = $this->getQuery($query);
      
    }else if($this->isSqlite()){
    
      $query = sprintf('CREATE TABLE %s (
          row_id INTEGER NOT NULL PRIMARY KEY ASC,
          _id TEXT COLLATE NOCASE NOT NULL,
          body BLOB
      )',$table);
    
      $ret_bool = $this->getQuery($query);
      if($ret_bool){
      
        // add the index for _id to the table...
        $this->setIndex($table,array('_id' => 1));
      
      }//if
    
    }//if/else if
    
    if($ret_bool){
      
      // add all the indexes for this table...
      if($schema->hasIndex()){
      
        foreach($schema->getIndex() as $index_map){
        
          $printf_vars = array();
          $field_list = array_keys($index_map);
          $field_list_str = join(',',$field_list);
          $index_table = sprintf('%s_%s',$table,md5($field_list_str));
        
          $query = 'CREATE TABLE %s (';
          $printf_vars[] = $index_table;
          
          foreach($field_list as $field){
          
            $query .= '%s varchar(100) NOT NULL,';
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
   *  @return mixed array of results if select query, last id if insert, update
   */
  function getQuery($query,$val_list = array()){
  
    $ret_mixed = false;
  
    $query = trim($query);
  
    /**
    if($this->db_debug && is_array($val_list)){
      foreach($field_val_list as $key => $val){
        if(!is_numeric($val)){ $val = "'".$val."'"; }//if
        $this->db_last_query = preg_replace('/\?/u',$val,$this->db_last_query,1);
      }//foreach
    }//if
    **/
    $this->query_list[] = $query;
  
    try{
    
      // prepare the statement and run the query...
      // http://us2.php.net/manual/en/function.PDO-prepare.php
      $stmt_handler = $this->con_db->prepare($query);
    
      // execute the query...
      $is_success = empty($val_list) ? $stmt_handler->execute() : $stmt_handler->execute($val_list);
      if($is_success){

        if(mb_stripos($query,'select') === 0){

          // a select statement should always return an array...
          $ret_mixed = $stmt_handler->fetchAll(PDO::FETCH_ASSOC);
          
        }else{
        
          // all other queries should return whether they were successful...
          $ret_mixed = $is_success;
          
        }//if/else
        
      }else{
      
        $err_map = $stmt_handler->errorInfo();
        throw new mingo_exception(sprintf('query "%s" failed execution with error: %s',
          $query,
          print_r($err_map,1)
        ));
        
      }//if/else
        
    }catch(PDOException $e){
    
      throw new mingo_exception(sprintf('query "%s" failed with exception %s: %s',
        $query,
        $e->getCode(),
        $e->getMessage()
      ));
    
    }//try/catch

    return $ret_mixed;
  
  }//method
  
  /**
   *  generates a 24 character unique id for the _id of an inserted row
   *
   *  @param  string  $table  the table to be used in the hash
   *  @return string  a 24 character id string   
   */
  private function getUniqueId($table = ''){
  
    $id = uniqid();
    $hash = mb_substr(md5(microtime(true).$table.rand(0,50000)),0,11);
    return sprintf('%s%s',$id,$hash);
  
  }//method
  
}//class     
