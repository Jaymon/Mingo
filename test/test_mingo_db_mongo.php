<?php

require_once('test_mingo_db_interface.php');

class test_mingo_db_mongo extends test_mingo_db_interface {

  /**
   *  @return string  the host string (something like server:port
   */
  protected function getDbHost(){ return 'localhost:27017'; }//method

  protected function getDb(){
  
    $db = new mingo_db_mongo();
    return $db;
  
  }//method

}//class
