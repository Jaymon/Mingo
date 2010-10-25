<?php

error_reporting(E_ALL | E_STRICT | E_PARSE);
ini_set('display_errors','on');

// declare a mingo autoloader we can use...
include_once(
  join(
    DIRECTORY_SEPARATOR,
    array(
      join(DIRECTORY_SEPARATOR,array(dirname(__FILE__),'..')),
      'mingo_autoload_class.php'
    )
  )
);
mingo_autoload::register();

class mingo_test extends PHPUnit_Framework_TestCase {

  /**
   *  http://www.phpunit.de/manual/current/en/writing-tests-for-phpunit.html#writing-tests-for-phpunit.data-providers
   */        
  public function getOrm(){
    
    $t = new test_orm();
    $t->append(
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

}//class
