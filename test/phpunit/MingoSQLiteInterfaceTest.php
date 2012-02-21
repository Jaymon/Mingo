<?php
/**
 *  handle testing of SQLite interface
 *  
 *  @version 0.3
 *  @author Jay Marcyes
 *  @since 3-19-10
 *  @package mingo
 *  @subpackage test
 ******************************************************************************/
require_once('MingoInterfaceTest.php');

class MingoSQLiteInterfaceTest extends MingoInterfaceTest {

  public function createInterface(){ return new MingoSQLiteInterface(); }//method
  
  public function createConfig(){
  
    $path = sys_get_temp_dir();
    if(mb_substr($path,-1) === DIRECTORY_SEPARATOR){
      
      $path = mb_substr($path,0,-1);
    
    }//if
    
    $name = sprintf(
      '%s.sqlite',
      join(DIRECTORY_SEPARATOR,array($path,md5(get_class($this).microtime(true))))
    );
  
    $config = new MingoConfig(
      $name,
      '',
      '',
      '',
      array()
    );
    
    $config->setDebug(true);
    
    return $config;
  
  }//method
  
}//class
