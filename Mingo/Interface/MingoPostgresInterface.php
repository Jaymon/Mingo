<?php

/**
 *  handle Postgres connection using hstore
 *  
 *  @version 0.1
 *  @author Jay Marcyes
 *  @since 12-31-2011
 *  @package mingo
 ******************************************************************************/
class MingoPostgresInterface extends MingoPDOInterface {
  
  /**
   *  get the dsn connection string that PDO will use to connect to the backend
   *   
   *  @since  10-18-10
   *  @param  \MingoConfig  $config
   *  @return string  the dsn   
   */
  protected function getDsn(MingoConfig $config){
  
    // canary...
    if(!$config->hasHost()){ throw new InvalidArgumentException('no host specified'); }//if
    if(!$config->hasName()){ throw new InvalidArgumentException('no name specified'); }//if
  
    return sprintf(
      'pgsql:dbname=%s;host=%s;port=%s',
      $config->getName(),
      $config->getHost(),
      $config->getPort(5432)
    );
  
  }//method
  
  /**
   *  @see  getTables()
   *  
   *  @link http://stackoverflow.com/questions/435424/
   *  @link http://stackoverflow.com/questions/1766046/
   *  @link http://www.peterbe.com/plog/pg_class      
   *      
   *  @param  MingoTable  $table  
   *  @return array
   */
  protected function _getTables(MingoTable $table = null){
  
    $query = '';
    $val_list = array();
  
    if(empty($table)){
    
      $query = 'SELECT tablename FROM pg_tables';
    
    }else{
    
      $query = 'SELECT tablename FROM pg_tables WHERE tablename = ?';
      $val_list = array($table->getName());
      
    }//if/else
  
    $ret_list = $this->_getQuery($query,$val_list);
    return $ret_list;
  
  }//method
  
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

    $select_query = $this->getSelectQuery($table,$where_criteria);
    
    return $this->getQuery(
      $select_query,
      isset($where_criteria['where_params']) ? $where_criteria['where_params'] : array()
    );

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
   *  Postgres sets all the indexes on the table when {@link setTable()} is called, it
   *  doesn't need to set any other indexes   
   *      
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  mixed $index  an index ran through {@link normalizeIndex()}
   *  @return boolean
   */
  protected function _setIndex($table,$index){ return false; }//method
  
  /**
   *  convert an array index map into something this interface understands
   *
   *  @since  5-2-11
   *  @param  MingoTable  $table 
   *  @param  array $index_map  an index map that is usually in the form of array(field_name => options,...)      
   *  @return mixed whatever this interface will understand
   */
  protected function normalizeIndex(MingoTable $table,array $index_map){
  
  }//method
  
  /**
   *  @see  getIndexes()
   *  
   *  since postgres hstore has an index on the whole thing, we can just return
   *  the indexes the MingoTable thinks we have, since they are technically indexed      
   *      
   *  @link http://stackoverflow.com/questions/2204058/
   *      
   *  @param  mixed $table  the table ran through {@link normalizeTable()}      
   *  @return array
   */
  protected function _getIndexes($table){
  
    return $table->getIndexes();
  
  }//method
  
  /**
   *  @see  killTable()
   *  
   *  @link http://www.postgresql.org/docs/7.4/interactive/sql-droptable.html
   *        
   *  @param  mixed $table  the table ran through {@link normalizeTable()}      
   *  @return boolean
   */
  protected function _killTable($table){

    $query = sprintf(
      'DROP TABLE %s CASCADE',
      $table
    );
    
    return $this->getQuery($query);

  }//method

  /**
   *  @see  setTable()
   *      
   *  @param  MingoTable  $table       
   *  @return boolean
   */
  protected function _setTable(MingoTable $table){

    $query = sprintf('CREATE TABLE %s (
      _rowid SERIAL PRIMARY KEY,
      _id VARCHAR(24) NOT NULL,
      body hstore
    )',$table);
  
    $ret_bool = $this->getQuery($query);
    
    if($ret_bool){
    
      // add some indexes to the table...
    
      $query = sprintf(
        'CREATE INDEX %s0 ON %s USING BTREE (_id)',
        $table,
        $table
      );
      $ret_bool = $this->getQuery($query);
    
      $query = sprintf(
        'CREATE INDEX %s2 ON %s USING GIST (body)',
        $table,
        $table
      );
      $ret_bool = $this->getQuery($query);
    
      $query = sprintf(
        'CREATE INDEX %s1 ON %s USING BTREE (body)',
        $table,
        $table
      );
      $ret_bool = $this->getQuery($query);
      
    }//if
    
    return $ret_bool;

  }//method
  
  /**
   *  @see  handleException()
   *  
   *  @param  MingoTable  $table
   *  @return boolean false on failure to solve the exception, true if $e was successfully resolved
   */
  protected function canHandleException(Exception $e){
    
    $ret_bool = false;
    
    if($e->getCode() === '42P01'){
    
      // SQLSTATE[42P01]: Undefined table: 7 ERROR: relation "<table_name>" does not exist 
      $ret_bool = true;
    
    }//if
    
    return $ret_bool;
    
  }//method
  
  /**
   *  if you want to do anything special with the field's name, override this method
   *  
   *  for example, mysql might want to wrap teh name in `, so foo would become `foo`      
   *  
   *  @since  1-2-12
   *  @param  string  $name
   *  @return string  the $name, formatted
   */
  protected function normalizeNameSQL($name){
  
    return sprintf('body -> \'%s\'',$name);
    
  }//method
  
}//class     

