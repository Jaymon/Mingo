<?php

/**
 *  You can use this class as a template for future interfaces
 *  
 *  @version 0.3
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 12-09-09
 *  @package mingo 
 ******************************************************************************/
class *Interface extends MingoInterface {

  /**
   *  do the actual connecting of the interface
   *
   *  @see  connect()   
   *  @return boolean
   */
  protected function _connect($name,$host,$username,$password,array $options){
  
  }//method
  
  /**
   *  @see  getTables()
   *  @return array
   */
  protected function _getTables($table = ''){
  
  }//method
  
  /**
   *  @see  getCount()   
   *  @return integer the count
   */
  protected function _getCount($table,MingoSchema $schema,MingoCriteria $where_criteria = null,array $limit){
  
  }//method
  
  /**
   *  @see  get()
   *  @return array   
   */
  protected function _get($table,MingoSchema $schema,MingoCriteria $where_criteria = null,array $limit){

  }//method
  
  /**
   *  @see  getOne()
   *  @return array
   */
  protected function _getOne($table,MingoSchema $schema,MingoCriteria $where_criteria = null){
  
  }//method
  
  /**
   *  @see  kill()
   *  @return boolean
   */
  protected function _kill($table,MingoSchema $schema,MingoCriteria $where_criteria){
  
  }//method
  
  /**
   *  insert $map into $table
   *  
   *  @param  string  $table  the table name
   *  @param  array|mingo_criteria  $map  the key/value map that will be added to $table
   *  @param  MingoSchema $schema the table schema   
   *  @return array the $map that was just saved, with the _id set               
   */
  protected function insert($table,array $map,MingoSchema $schema){
    
  }//method
  
  /**
   *  update $map from $table using $_id
   *  
   *  @param  string  $table  the table name
   *  @param  string  $_id the _id attribute from $map   
   *  @param  array $map  the key/value map that will be added to $table
   *  @param  MingoSchema $schema the table schema      
   *  @return array the $map that was just saved with _id set
   */
  protected function update($table,$_id,array $map,MingoSchema $schema){

  }//method
  
  /**
   *  adds an index to $table
   *  
   *  @param  string  $table  the table to add the index to
   *  @param  array $map  the keys are the field names, the values are the definitions for each field
   *  @param  MingoSchema $schema the table schema      
   *  @return boolean
   */
  protected function setIndex($table,array $index_map,MingoSchema $schema){
    
  }//method
  
  /**
   *  @see  getIndexes()
   *  @return array
   */
  protected function _getIndexes($table){
  
  }//method
  
  /**
   *  @see  killTable()
   *  @return boolean
   */
  protected function _killTable($table){

  }//method
  
  /**
   *  adds a table to the db
   *  
   *  http://www.mongodb.org/display/DOCS/Capped+Collections
   *      
   *  @see  setTable()   
   *  @return boolean
   */
  protected function _setTable($table,MingoSchema $schema){

  }//method
  
  /**
   *  @see  handleException()
   *  @return boolean false on failure to solve the exception, true if $e was successfully resolved
   */
  protected function _handleException(Exception $e,$table,MingoSchema $schema){
    
  }//method
  
  /**
   *  this should be used to take the generic $where_criteria and turn it into something
   *  the interface can use (eg, for a SQL interface, the $where_criteria would be turned
   *  into a valid SQL string).
   *  
   *  @param  MingoCriteria $where_criteria
   *  @return mixed return whatever you want, however you want to return it, whatever is easiest for you
   */
  protected function getCriteria(MingoCriteria $where_criteria){
  
  }//method
  
}//class     

