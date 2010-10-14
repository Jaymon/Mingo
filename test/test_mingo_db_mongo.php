<?php

require_once('test_mingo_db_interface.php');

class test_mingo_db_mongo extends test_mingo_db_interface {

  /**
   *  @return string  the host string (something like server:port
   */
  public function getDbHost(){ return 'localhost:27017'; }//method

  public function getDbInterface(){
  
    return 'mingo_db_mongo';
  
  }//method

}//class
