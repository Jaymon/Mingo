<?php

/**
 *  handle relational db abstraction for mingo for sqlite    
 *
 *  SQLite has a limit of 500 values in an IN (...) query, just something to be
 *  aware of, see #7: http://www.sqlite.org/limits.html 
 *  
 *  @version 0.2
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 3-19-10
 *  @package mingo 
 ******************************************************************************/
class mingo_db_sqlite extends mingo_db_sql {

  protected function start(){}//method
  
  /**
   *  get all the tables of the currently connected db
   *  
   *  @return array a list of table names
   */
  function getTables($table = ''){
  
    $ret_list = array();
    
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
    
    return $ret_list;
  
  }//method
  
  /**
   *  get the dsn connection string that PDO will use to connect to the backend
   *   
   *  @since  10-18-10
   *  @param  string  $db_name  the database name
   *  @param  string  $host the host
   *  @return string  the dsn         
   */
  protected function getDsn($db_name,$host){
  
    // for sqlite: PRAGMA encoding = "UTF-8"; from http://sqlite.org/pragma.html only good on db creation
    // http://stackoverflow.com/questions/263056/how-to-change-character-encoding-of-a-pdo-sqlite-connection-in-php
  
    return sprintf('sqlite:%s',$db_name);
  
  }//method
  
  /**
   *  things to do once the connection is established
   *   
   *  @since  10-18-10
   */
  protected function onConnect(){}//method
  
  protected function createTable($table,mingo_schema $schema){
  
    $query = sprintf('CREATE TABLE %s (
        row_id INTEGER NOT NULL PRIMARY KEY ASC,
        _id VARCHAR(24) COLLATE NOCASE NOT NULL,
        body BLOB
    )',$table);
  
    $ret_bool = $this->getQuery($query);
    if($ret_bool){
    
      // add the index for _id to the table...
      $ret_bool = $this->createIndex($table,array('_id' => 1));
    
    }//if
    
    return $ret_bool;
  
  }//method
  
  protected function createIndex($table,array $index_map){
  
    // SQLite has a different index creation syntax...
    //  http://www.sqlite.org/lang_createindex.html create index [if not exists] name ON table_name (col_one[,col...])
    
    // the order bit is ignored for sql, so we just need the keys...
    $field_list = array_keys($index_map);
    $field_list_str = join(',',$field_list);
    $index_name = 'i'.md5($field_list_str);
    
    $query = sprintf('CREATE INDEX IF NOT EXISTS %s ON %s (%s)',$index_name,$table,$field_list_str);
    return $this->getQuery($query);
  
  }//method
  
  /**
   *  create an index table for the given $table and $index_map      
   *  
   *  http://www.sqlite.org/syntaxdiagrams.html#column-constraint
   *      
   *  @since  10-18-10
   *  @param  string  $table
   *  @param  array $index_map  the index structure
   *  @param  mingo_schema  $schema the table schema   
   */
  protected function createIndexTable($table,array $index_map,mingo_schema $schema){
  
    $index_table = $this->getIndexTableName($table,$index_map);
    $field_list = array();
    $printf_vars = array();
    $query = array();
    $query[] = 'CREATE TABLE %s (';
    $printf_vars[] = $index_table;
    
    foreach($index_map as $field => $index_type){
    
      if($this->isSpatialIndexType($index_type)){
        throw new RuntimeException('SPATIAL indexes are currently unsupported in SQLite');
      }//if
    
      $query[] = '%s %s,';
      
      $field_list[] = $field;
      $printf_vars[] = $field;
      $printf_vars[] = $this->getSqlType($field,$schema);
    
    }//foreach

    $query[] = '_id VARCHAR(24) NOT NULL, PRIMARY KEY (%s,_id))';
    $printf_vars[] = join(',',$field_list);
    
    $query = vsprintf(join(PHP_EOL,$query),$printf_vars);
    $ret_bool = $this->getQuery($query);
    if($ret_bool){
    
      $this->createIndex($index_table,array('_id' => 1));
      
    }//if
      
    return $ret_bool;
  
  }//method
  
  protected function getTableIndexes($table){
  
    $ret_list = array();
  
    // sqlite: pragma table_info(table_name)
    //  http://www.sqlite.org/pragma.html#schema
    //  http://www.mail-archive.com/sqlite-users@sqlite.org/msg22055.html
    //  http://stackoverflow.com/questions/604939/how-can-i-get-the-list-of-a-columns-in-a-table-for-a-sqlite-database
    $query = sprintf('PRAGMA index_list(%s)',$table);
    $index_list = $this->getQuery($query);
    foreach($index_list as $index_map){
    
      $query = sprintf('PRAGMA index_info(%s)',$index_map['name']);
      $field_list = $this->getQuery($query);

      $ret_map = array();
      foreach($field_list as $field_map){
        $ret_map[$field_map['name']] = 1; // all sql indexes sort asc
      }//foreach
      
      $ret_list[] = $ret_map;
      
    }//foreach

    return $ret_list;
  
  }//method
  
  /**
   *  true if the $e is for a missing table exception
   *
   *  @since  10-18-10
   *  @see  handleException()         
   *  @param  Exception $e  the thrown exception
   *  @return boolean
   */
  protected function isNoTableException(Exception $e){
  
    $ret_bool = false;
  
    $e_code = $e->getCode();
    if(!empty($e_code)){
    
      $ret_bool = ($e_code == 'HY000');
    
    }//if
    
    return $ret_bool;
    
  }//method
  
  /**
   *  allows customizing the field sql type using the schema's field hints
   *
   *  @since  10-19-10
   *  @param  string  $field  the field name
   *  @param  mingo_schema  $schema the schema for the table         
   *  @return string
   */
  protected function getSqlType($field,mingo_schema $schema){
  
    $ret_str = '';
    $field_instance = $schema->getField($field);
  
    switch($field_instance->getType()){
    
      case mingo_field::TYPE_INT:
      case mingo_field::TYPE_BOOL:
        $ret_str = 'INTEGER';
        break;
      
      case mingo_field::TYPE_FLOAT:
      
        $ret_str = 'REAL';
        break;
      
      case mingo_field::TYPE_POINT:
      case mingo_field::TYPE_STR:
      case mingo_field::TYPE_LIST:
      case mingo_field::TYPE_MAP:
      case mingo_field::TYPE_OBJ:
      case mingo_field::TYPE_DEFAULT:
      default:
        
        $ret_str = 'VARCHAR(100) COLLATE NOCASE';
        break;
    
    }//switch
  
    return $ret_str;
  
  }//method
  
}//class     
