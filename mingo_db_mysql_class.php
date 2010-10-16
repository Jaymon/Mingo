<?php

/**
 *  handle relational db abstraction for mingo for MySQL   
 *
 *  @version 0.2
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 3-19-10
 *  @package mingo 
 ******************************************************************************/
class mingo_db_mysql extends mingo_db_sql {

  protected function start(){
  
    $this->setType(self::TYPE_MYSQL);
    
  }//method
  
  protected function createTable($table,mingo_schema $schema){
  
    $query = sprintf('CREATE TABLE %s (
        row_id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        _id VARCHAR(24) NOT NULL,
        body LONGBLOB,
        UNIQUE KEY (_id)
    ) ENGINE=%s CHARSET=%s',$table,self::ENGINE,self::CHARSET);
    
    return $this->getQuery($query);
  
  }//method
  
  
  protected function setIndex($table,array $map,mingo_schema $schema){
  
    list($field_list,$field_list_str,$index_table) = $this->getIndexInfo($index_map);
  
    // canary...
    if($this->hasTable($index_table)){ return true; }//if
  
    $printf_vars = array();
  
    $query = array();
    $query[] = 'CREATE TABLE %s (';
    $printf_vars[] = $index_table;
    
    foreach($field_list as $field){
    
      $query[] = '%s VARCHAR(100) NOT NULL,';
      $printf_vars[] = $field;
    
    }//foreach
  
    $query[] = '_id VARCHAR(24) NOT NULL UNIQUE,';
    
    $query[] = 'PRIMARY KEY (%s,_id)';
    $printf_vars[] = $field_list_str;
    
    $query[] = ') ENGINE=%s CHARSET=%s';
    $printf_vars[] = self::ENGINE;
    $printf_vars[] = self::CHARSET;
    
    $query = vsprintf(join(PHP_EOL,$query),$printf_vars);

    $ret_bool = $this->getQuery($query);

    if($ret_bool){
    
      // if debugging is on it means we're in dev so go ahead and populate the index...
      if($this->hasDebug()){
        $this->populateIndex($table,$index_map,$schema);
      }//if
    
    }//if
      
  
  
  
  
  }//method
  
  
  /**
   *  adds an index to $table
   *  
   *  @param  string  $table  the table to add the index to
   *  @param  array $map  usually something like array('field_name' => 1), this isn't need for sql
   *                      but it's the same way to keep compatibility with Mongo   
   *  @return boolean
   */
  /* function setIndex($table,array $map){
    
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
  
  }//method */
  
}//class     
