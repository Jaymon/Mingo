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
abstract class MingoRDBMSInterface extends MingoPDOInterface {
  
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
  protected function _setIndex($table,$index){
  
  
  }//method
  
  /**
   *  @see  getIndexes()
   *  
   *  since postgres hstore has an index on the whole thing, we can just return
   *  the indexes the MingoTable thinks we have, since they are technically indexed      
   *      
   *  @link http://stackoverflow.com/questions/2204058/
   *  
   *  other links:
   *    http://stackoverflow.com/questions/2204058/show-which-columns-an-index-is-on-in-postgresql
   *    http://www.postgresql.org/docs/current/static/catalog-pg-index.html
   *    http://www.manniwood.com/postgresql_stuff/index.html
   *    http://archives.postgresql.org/pgsql-php/2005-09/msg00011.php                  
   *      
   *  @param  mixed $table  the table ran through {@link normalizeTable()}      
   *  @return array
   */
  protected function _getIndexes($table){
  
  
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


  }//method

  /**
   *  @see  setTable()
   *      
   *  @param  MingoTable  $table       
   *  @return boolean
   */
  protected function _setTable(MingoTable $table){


  }//method


  
}//class     

