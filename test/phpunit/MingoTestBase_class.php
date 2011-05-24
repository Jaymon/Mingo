<?php

error_reporting(E_ALL | E_STRICT | E_PARSE);
ini_set('display_errors','on');

// declare a mingo autoloader we can use...
include_once(
  join(
    DIRECTORY_SEPARATOR,
    array(
      join(DIRECTORY_SEPARATOR,array(dirname(__FILE__),'..','..')),
      'MingoAutoload_class.php'
    )
  )
);
MingoAutoload::register();

abstract class MingoTestBase extends PHPUnit_Framework_TestCase {

  /**
   *  http://www.phpunit.de/manual/current/en/writing-tests-for-phpunit.html#writing-tests-for-phpunit.data-providers
   */        
  public function getOrm(){
    
    $t = new MingoTestOrm();
    $t->attach(
      array(
        'foo' => rand(0,PHP_INT_MAX),
        'bar' => array(
          'baz' => md5(microtime(true))
        )
      ),
      false
    );
    
    return array(
      array($t)
    );
  }//method
  
  protected function getTable($name = ''){
    
    if(empty($name)){ $name = get_class($this); }//if
    $table = new MingoTable($name);
    $table->setIndex('foo','bar','baz');
    $table->setIndex('bar','baz');
    
    return $table;
    
  }//method

}//class

class MingoTestOrm extends MingoOrm {

  protected function populateTable(MingoTable $table){
  
    ///$table->setIndex('foo','bar','baz');
  
  }//method
  
}//class
