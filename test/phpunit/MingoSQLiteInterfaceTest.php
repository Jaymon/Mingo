<?php

require_once('MingoSQLInterfaceTest.php');

class MingoSQLiteInterfaceTest extends MingoSQLInterfaceTest {

  /**
   *  @return string  the database name
   */
  public function getDbName(){
    
    $path = sys_get_temp_dir();
    if(mb_substr($path,-1) === DIRECTORY_SEPARATOR){
      
      $path = mb_substr($path,0,-1);
    
    }//if
    
    return sprintf(
      '%s.sqlite',
      join(DIRECTORY_SEPARATOR,array($path,md5(__CLASS__.microtime(true))))
    );
    
  }//method

  public function getDbInterface(){
  
    return 'MingoSQLiteInterface';
  
  }//method
  
  public function xtestBlah()
  {
    $db = $this->getDb();
    $table = $this->getTable();
    
    $c = new MingoCriteria();
    $c->inFoo(1,2,3,4);
    $c->setBounds(10,1);
    
    $db->get($table,$c);
  
  
  
  }//method
  
  /* public static function tearDownAfterClass(){
  
    $that = new self();
    
    
    
    out::e($that->getDbName());
  
    // http://us2.php.net/manual/en/function.unlink.php#98861
    unlink($that->getDbName());
  
  }//method */

}//class
