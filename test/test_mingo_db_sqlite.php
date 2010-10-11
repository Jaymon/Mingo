<?php

require_once('test_mingo_db_interface.php');

class test_mingo_db_sqlite extends test_mingo_db_interface {

  /**
   *  @return string  the database name
   */
  protected function getDbName(){
    
    return sprintf(
      '%s.sqlite',
      join(DIRECTORY_SEPARATOR,array(dirname(__FILE__),__CLASS__))
    );
    
  }//method

  protected function getDb(){
  
    $db = new mingo_db_sqlite();
    return $db;
  
  }//method
  
  /* public static function tearDownAfterClass(){
  
    $that = new self();
    out::e($that->getDbName());
  
    // http://us2.php.net/manual/en/function.unlink.php#98861
    unlink($that->getDbName());
  
  }//method */

}//class
