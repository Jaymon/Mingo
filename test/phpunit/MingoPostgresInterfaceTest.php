<?php

require_once('MingoInterfaceTest.php');
require_once('/vagrant/out_class.php');

class MingoPostgresInterfaceTest extends MingoInterfaceTest {
  
  public function createInterface(){ return new MingoPostgresInterface(); }//method
  
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
