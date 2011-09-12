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
class MingoSQLiteInterface extends MingoSQLInterface {
  
  /**
   *  gets all the fields in the given table
   *  
   *  @todo right now it just returns the field names, but it would be easy to
   *        add a detail boolean after table name that would return the entire array
   *        with all the field info, this might be useful in the future            
   *      
   *  @param  MingoTable  $table      
   *  @return array all the field names found, empty array if none found
   */        
  public function getTableFields(MingoTable $table){
    
    $ret_list = array();
    
    // sqlite: pragma table_info(table_name)
    //  http://www.sqlite.org/pragma.html#schema
    //  http://www.mail-archive.com/sqlite-users@sqlite.org/msg22055.html
    $query = sprintf('PRAGMA table_info(%s)',$table);
    $field_index = 'name';
    
    if($result_list = $this->_getQuery($query)){
    
      foreach($result_list as $result_map){
        if(isset($result_map[$field_index])){ $ret_list[] = $result_map[$field_index]; }//if
      }//foreach
      
    }//if
  
    return $ret_list;
  
  }//method
  
  /**
   *  @see  getTables()
   *  
   *  @param  MingoTable  $table  
   *  @return array
   */
  protected function _getTables(MingoTable $table = null){
  
    $ret_list = array();
    
    // thanks: http://us3.php.net/manual/en/ref.sqlite.php#47442
    // query that should check sqlite tables: "SELECT * FROM sqlite_master WHERE type='table' AND name='$mytable'"
    // SELECT name FROM sqlite_master WHERE type = \'table\'' from: http://www.litewebsite.com/?c=49
    $query = 'Select * FROM sqlite_master WHERE type=?';
    $query_vars = array('table');
    if($table !== null){
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
   *  @param  string  $name the database name
   *  @param  string  $host the host
   *  @return string  the dsn         
   */
  protected function getDsn($name,$host){
  
    // for sqlite: PRAGMA encoding = "UTF-8"; from http://sqlite.org/pragma.html only good on db creation
    // http://stackoverflow.com/questions/263056/how-to-change-character-encoding-of-a-pdo-sqlite-connection-in-php
  
    return sprintf('sqlite:%s',$name);
  
  }//method
  
  /**
   *  things to do once the connection is established
   *   
   *  @since  10-18-10
   */
  protected function onConnect(){}//method
  
  /**
   *  @see  setTable()
   *  @param  MingoTable  $table       
   *  @return boolean
   */
  protected function _setTable(MingoTable $table){
  
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
  
  protected function createIndex($table,array $index){
  
    // SQLite has a different index creation syntax...
    //  http://www.sqlite.org/lang_createindex.html create index [if not exists] name ON table_name (col_one[,col...])
    
    // the order bit is ignored for sql, so we just need the keys...
    $field_list = array_keys($index);
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
   *  @param  array $index  the index structure
   */
  protected function createIndexTable($table,array $index){
  
    $index_table = $this->getIndexTableName($table,$index);
    $field_list = array();
    $printf_vars = array();
    $query = array();
    $query[] = 'CREATE TABLE %s (';
    $printf_vars[] = $index_table;
    
    foreach($index as $field => $index_type){
    
      if($this->isSpatialIndexType($index_type)){
        throw new RuntimeException('SPATIAL indexes are currently unsupported in SQLite');
      }//if
    
      $query[] = '%s %s,';
      
      $field_list[] = $field;
      $printf_vars[] = $field;
      $printf_vars[] = $this->getSqlType($table,$field);
    
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
        $ret_map[$field_map['name']] = self::INDEX_ASC; // all sql indexes sort asc
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
  
    $ret_bool = ($e->getCode() == 'HY000') && preg_match('#no\s+such\s+table#i',$e->getMessage());
    return $ret_bool;
    
  }//method
  
  /**
   *  allows customizing the field sql type using the schema's field hints
   *
   *  @since  10-19-10
   *  @param  string  $field  the field name
   *  @param  MingoSchema $schema the schema for the table         
   *  @return string
   */
  protected function getSqlType(MingoTable $table,$field){
  
    $ret_str = '';
    $field_instance = $table->getField($field);
  
    switch($field_instance->getType()){
    
      case MingoField::TYPE_INT:
      case MingoField::TYPE_BOOL:
        $ret_str = 'INTEGER';
        break;
      
      case MingoField::TYPE_FLOAT:
      
        $ret_str = 'REAL';
        break;
      
      case MingoField::TYPE_POINT:
      case MingoField::TYPE_STR:
      case MingoField::TYPE_LIST:
      case MingoField::TYPE_MAP:
      case MingoField::TYPE_OBJ:
      case MingoField::TYPE_DEFAULT:
      default:
        
        if($field_instance->hasRange())
        {
          if($field_instance->isFixedSize())
          {
            $ret_str = sprintf('CHAR(%s) COLLATE NOCASE',$field_instance->getMaxSize());
          }else{
            $ret_str = sprintf('VARCHAR(%s) COLLATE NOCASE',$field_instance->getMaxSize());
          }//if/else
        
        }else{
          $ret_str = 'VARCHAR(100) COLLATE NOCASE';
        }//if/else
        
        break;
    
    }//switch
  
    return $ret_str;
  
  }//method
  
}//class     
