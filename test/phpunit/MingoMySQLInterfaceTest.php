<?php
/**
 *  handle testing of MySQL interface
 *  
 *  @version 0.3
 *  @author Jay Marcyes
 *  @since 3-19-10
 *  @package mingo
 *  @subpackage test
 ******************************************************************************/
require_once('MingoInterfaceTest.php');

class MingoMySQLInterfaceTest extends MingoInterfaceTest {
  
  public function createInterface(){ return new MingoMySQLInterface(); }//method
  
  public function createConfig(){
  
    $config = new MingoConfig(
      'vagrant',
      'localhost:3306',
      'vagrant',
      'vagrant',
      array()
    );
    
    $config->setDebug(true);
    
    return $config;
  
  }//method

}//class
