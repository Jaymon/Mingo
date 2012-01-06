<?php

/**
 *  handle relational db abstraction for mingo for sqlite    
 *
 *  SQLite has a limit of 500 values in an IN (...) query, just something to be
 *  aware of, see #7: http://www.sqlite.org/limits.html 
 *  
 *  @requires SQLite > 3.6.19 because it uses foreign keys
 *  
 *  @version 0.4
 *  @author Jay Marcyes
 *  @since 3-19-10
 *  @package mingo 
 ******************************************************************************/
class MingoSQLiteInterface extends MingoRDBMSInterface {
  
  /**
   *  get the dsn connection string that PDO will use to connect to the backend
   *  
   *  @link http://us3.php.net/manual/en/ref.pdo-sqlite.php   
   *  @since  10-18-10
   *  @param  string  $name the database name
   *  @param  string  $host the host
   *  @return string  the dsn         
   */
  protected function getDsn(MingoConfig $config){
  
    // canary
    if(!$config->hasName()){ throw new InvalidArgumentException('no name specified'); }//if
  
    // for sqlite: PRAGMA encoding = "UTF-8"; from http://sqlite.org/pragma.html only good on db creation
    // http://stackoverflow.com/questions/263056/how-to-change-character-encoding-of-a-pdo-sqlite-connection-in-php
  
    return sprintf('sqlite:%s',$config->getName());
  
  }//method
  
  /**
   *  things to do once the connection is established
   *   
   *  @since  10-18-10
   */
  protected function onConnect(){
  
    // turn on foreign keys...
    // http://www.sqlite.org/foreignkeys.html
    $this->getQuery('PRAGMA foreign_keys = ON');
    
  }//method
  
  /**
   *  @see  handleException()
   *  
   *  @param  MingoTable  $table
   *  @return boolean false on failure to solve the exception, true if $e was successfully resolved
   */
  protected function canHandleException(Exception $e){
    
    $ret_bool = ($e->getCode() == 'HY000') && preg_match('#no\s+such\s+table#i',$e->getMessage());
    return $ret_bool;
    
  }//method
  
  /**
   *  @see  setTable()
   *  @param  MingoTable  $table       
   *  @return boolean
   */
  protected function _setTable(MingoTable $table){
  
    $query = sprintf(
      'CREATE TABLE %s (
        _rowid INTEGER NOT NULL PRIMARY KEY ASC,
        _id VARCHAR(24) UNIQUE COLLATE NOCASE NOT NULL,
        _created INTEGER NOT NULL,
        _updated INTEGER NOT NULL,
        body BLOB
      )',
      $this->normalizeTableSQL($table)
    );
  
    $ret_bool = $this->getQuery($query);
  
    if($ret_bool){
    
      // I can't decide if it is a good idea to include these columns as default
      // indexes, section "10.2 Move large columns into another table" of this
      // link: http://web.utk.edu/~jplyon/sqlite/SQLite_optimization_FAQ.html says
      // If you have a table with a column containing a large amount of (e.g. binary) data, 
      // you can split that column into another table which references the original table as a 
      // FOREIGN KEY. This will prevent the data from being loaded unless that column is 
      // actually needed (used in a query). This also helps keep the rest of the table 
      // together in the database file.
      //
      // Though I don't think the blob will ever be that big, I'm not going to worry
      // about it right now, but maybe in the future
    
      $ret_bool = $this->createIndex(
        $table,
        new MingoIndex('index_created',array('_created'))
      );
    
      $ret_bool = $this->createIndex(
        $table,
        new MingoIndex('index_updated',array('_updated'))
      );
    
    }//if
    
    return $ret_bool;
  
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
  protected function setIndexTable(MingoTable $table,MingoIndex $index){
  
    $index_table = $this->getIndexTableName($table,$index);
    $format_vars = array();
    $format_query = array();
    $format_query[] = 'CREATE TABLE %s (';
    $format_vars[] = $this->normalizeTableSQL($index_table);
    $field_list = $index->getFieldNames();
    
    foreach($field_list as $field){
    
      $format_query[] = '%s %s,';
      
      $format_vars[] = $field;
      $format_vars[] = $this->normalizeSqlType($table,$field);
    
    }//foreach

    // this will be the foreign key to the main blob table
    $format_query[] = '_rowid INTEGER UNIQUE NOT NULL,';
    
    // primary key is all the fields in the index + foreign key
    $format_query[] = 'PRIMARY KEY (%s,_rowid),';
    $format_vars[] = join(',',$field_list);
    
    // _rowid will be our binding to the master table
    // http://www.sqlite.org/foreignkeys.html
    $format_query[] = 'FOREIGN KEY(_rowid) REFERENCES %s(_rowid)';
    $format_vars[] = $this->normalizeTableSQL($table);
    
    $format_query[] = ')';
    
    $query = vsprintf(join(PHP_EOL,$format_query),$format_vars);
    $ret_bool = $this->getQuery($query);
      
    return $ret_bool;
  
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
  protected function getTableIndexes($table){
  
    $ret_list = array();
  
    // sqlite: pragma table_info(table_name)
    //  http://www.sqlite.org/pragma.html#schema
    //  http://www.mail-archive.com/sqlite-users@sqlite.org/msg22055.html
    //  http://stackoverflow.com/questions/604939/how-can-i-get-the-list-of-a-columns-in-a-table-for-a-sqlite-database
    $query = sprintf('PRAGMA index_list(%s)',$table);
    $index_list = $this->getQuery($query);
    
    if(is_array($index_list)){
      
      foreach($index_list as $index_map){
      
        $fields = array();
      
        $query = sprintf('PRAGMA index_info(%s)',$index_map['name']);
        $field_map_list = $this->getQuery($query);
  
        $ret_map = array();
        foreach($field_map_list as $field_map){ $fields[] = $field_map['name']; }//foreach
        
        $ret_list[] = new MingoIndex($index_map['name'],$fields);
        
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
    // SELECT name FROM sqlite_master WHERE type = 'table' from: http://www.litewebsite.com/?c=49
    $query = 'Select tbl_name FROM sqlite_master WHERE type=?';
    $query_vars = array('table');
    if($table !== null){
      $query .= ' AND name=?';
      $query_vars[] = $table;
    }//if
    
    $ret_list = $this->getQuery($query,$query_vars);
    
    return $ret_list;
  
  }//method
  
  /**
   *  this is here because each rdbms is a little different in how they create indexes
   *  
   *  it is not in the _setIndex because _setIndex creates a table for the index, and
   *  we need a way to set an index on an existing table   
   *
   *  why, oh why? http://stackoverflow.com/questions/1676448/
   *      
   *  @param  string  $table
   *  @param  \MingoIndex $index   
   *  @return boolean  
   */
  protected function createIndex($table,MingoIndex $index){
  
    // SQLite has a different index creation syntax...
    //  http://www.sqlite.org/lang_createindex.html 
    //  create index [if not exists] name ON table_name (col_one[,col...])
    
    $query = sprintf(
      'CREATE INDEX IF NOT EXISTS %s ON %s (%s)',
      $index->getName(),
      $this->normalizeTableSQL($table),
      join(',',$index->getFieldNames())
    );

    return $this->getQuery($query);
  
  }//method
  
  /**
   *  allows customizing the field sql type using the schema's field hints
   *
   *  @since  10-19-10
   *  @param  \MingoTable $table   
   *  @param  string  $field  the field name
   *  @return string
   */
  protected function normalizeSqlType(MingoTable $table,$field){
  
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
