<?php
/**
 *  handle testing of PostgreSQL interface
 *  
 *  @version 0.1
 *  @author Jay Marcyes
 *  @since 1-7-12
 *  @package mingo
 *  @subpackage test
 ******************************************************************************/
require_once('MingoInterfaceTest.php');
require_once('/vagrant/out_class.php');

class MingoPostgreSQLInterfaceTest extends MingoInterfaceTest {
  
  public function createInterface(){ return new MingoPostgreSQLInterface(); }//method
  
  public function createConfig(){
  
    $config = new MingoConfig(
      'vagrant',
      'localhost:5432',
      'vagrant',
      'vagrant',
      array()
    );
    
    $config->setDebug(true);
    
    return $config;
  
  }//method
  
}//class
