<?php

require_once('mingo_db_interface_Test.php');

class mingo_db_mysql_Test extends test_mingo_db_interface {
  
  /**
   *  @return string  the database name
   */
  public function getDbName(){ return 'happy'; }//method
  
  /**
   *  @return string  the host string (something like server:port
   */
  public function getDbHost(){ return 'localhost'; }//method
  
  /**
   *  @return string  the username used to connect
   */
  public function getDbUsername(){ return 'root'; }//method
  
  /**
   *  @return string  the password used to connect
   */
  public function getDbPassword(){ return ''; }//method

  public function getDbInterface(){
  
    return 'mingo_db_mysql';
  
  }//method
  
  protected function getSchema(){
  
    $ret_schema = parent::getSchema();
    
    // add a spatial index also...
    ///$ret_schema->setSpatial('location','bar','baz');
    
    return $ret_schema;
  
  }//method

}//class
