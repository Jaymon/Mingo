<?php

require_once('mingo_db_interface_Test.php');

class mingo_db_mongo_Test extends test_mingo_db_interface {

  /**
   *  @return string  the host string (something like server:port
   */
  public function getDbHost(){ return 'localhost:27017'; }//method

  public function getDbInterface(){
  
    return 'mingo_db_mongo';
  
  }//method

}//class
