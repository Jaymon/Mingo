<?php

error_reporting(E_ALL | E_STRICT | E_PARSE);
ini_set('display_errors','on');

// declare a mingo autoloader we can use...
include_once(
  join(
    DIRECTORY_SEPARATOR,
    array(
      join(DIRECTORY_SEPARATOR,array(dirname(__FILE__),'..','..','Mingo')),
      'MingoAutoload.php'
    )
  )
);
MingoAutoload::register();

abstract class MingoTestBase extends PHPUnit_Framework_TestCase {

  /**
   * looks like assertType was removed from PHPUnit, this just adds it back
   */
  public function assertType($expected, $actual, $message = ''){
    return $this->assertInternalType($expected, $actual, $message);
  }//method

  /**
   * get an orm, but one that can write to a SQLite db so we can test db stuff
   *
   * returns the same orm as getOrm(), but also has a live db connection
   *
   * @since 2013-3-14
   * @return  MingoOrm
   */
  protected function getDbConnectedOrm(){
  
    $test_db = new MingoSQLiteInterfaceTest();
    $db = $test_db->getDb();
    $t = $this->getOrm();
    $t->setDb($db);
    return $t;
  
  }//method

  /**
   * same as all the other get orm methods, but makes sure the orm has also been
   * saved into the db before returning it
   *
   * @since 2013-3-15
   * @return  MingoOrm
   */
  protected function getSetOrm(){
  
    $t = $this->getDbConnectedOrm();
    $this->assertEmpty($t->get_id());
    $t->set();
    $this->assertNotEmpty($t->get_id());
    return $t;
  
  }//method

  /**
   *  http://www.phpunit.de/manual/current/en/writing-tests-for-phpunit.html#writing-tests-for-phpunit.data-providers
   */        
  public function getOrm(){
    
    $t = new MingoTestOrm();
    $t->setFields(
      array(
        'foo' => rand(0,PHP_INT_MAX),
        'bar' => array(
          'baz' => md5(microtime(true))
        )
      )
    );
    
    return $t;

  }//method
  
  protected function getTable($name = ''){
    
    if(empty($name)){ $name = get_class($this); }//if
    $table = new MingoTable($name);
    $table->setIndex('foobarbaz',array('foo','bar','baz'));
    $table->setField('foo',MingoField::TYPE_INT);
    $table->setIndex('barbaz',array('bar','baz'));
    
    return $table;
    
  }//method

}//class

class MingoTestOrm extends MingoOrm {

  protected function populateTable(MingoTable $table){
  
    ///$table->setIndex('foo','bar','baz');
  
  }//method
  
}//class
