<?php

/**
 *  handle relational db abstraction for mingo for sqlite    
 *
 *  @version 0.2
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 3-19-10
 *  @package mingo 
 ******************************************************************************/
class mingo_db_sqlite extends mingo_db_sql {

  protected function start(){
  
    $this->setType(self::TYPE_SQLITE);
    
  }//method
  
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
  
  protected function createIndex($table,$map){
  
    // SQLite has a different index creation syntax...
    //  http://www.sqlite.org/lang_createindex.html create index [if not exists] name ON table_name (col_one[,col...])
    
    // the order bit is ignored for sql, so we just need the keys...
    $field_list = array_keys($map);
    $field_list_str = join(',',$field_list);
    $index_name = 'i'.md5($field_list_str);
    
    $query = sprintf('CREATE INDEX IF NOT EXISTS %s ON %s (%s)',$index_name,$table,$field_list_str);
    return $this->getQuery($query);
  
  }//method
  
  protected function setIndex($table,array $map){
  
  
  
  }//method
  
}//class     
