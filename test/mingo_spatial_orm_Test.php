<?php

require_once('mingo_test_class.php');

class test_mingo_spatial_orm extends mingo_test {

  public function testSetSpatial(){
  
    $t = $this->getDbConnectedOrm();
    
    $t->setPt($this->getPoint());
    
    $t->setType(100);
    $t->setFoo('blah');
    
    $this->assertTrue($t->set());
    
    try{
    
      $t->reset();
    
      // now update point to something not valid...
      $t->setPt('blah');
      $t->setType(99);
      $t->setFoo('roh');
    
      $t->set();
      $this->fail('Pt was the wrong format, an exception should have been generated');
    
    }catch(PHPUnit_Framework_AssertionFailedError $e){
      throw $e;
    }catch(Exception $e){}//try/catch
  
  }//method
  
  public function testLoadSpatial(){
  
    $t = $this->getDbConnectedOrm();
    
    $c = new mingo_criteria();
    $c->nearPt($this->getPoint(),50);
    
    $loaded = $t->load($c);
    $this->assertGreaterThanOrEqual(1,$loaded);
    
    // now grab on something else also...
    $c->isType(100);
    
    $loaded = $t->load($c);
    $this->assertGreaterThanOrEqual(1,$loaded);
  
  }//method

  protected function getDbConnectedOrm(){
  
    ///$test_db = new test_mingo_db_sqlite_Test();
    $test_db = new mingo_db_mysql_Test();
    ///$test_db = new test_mingo_db_mongo_Test();
  
    $db = mingo_db::getInstance();
    $db->connect(
      $test_db->getDbInterface(),
      $test_db->getDbName(),
      $test_db->getDbHost(),
      $test_db->getDbUsername(),
      $test_db->getDbPassword()
    );
    
    $t = new test_spatial_orm();
    $t->setDb($db);
    return $t;
  
  }//method
  
  protected function getPoint(){
  
    // sf...
    $lat = 37.775;
    $long = -122.4183333;
    return array($lat,$long);
  
  }//method
  
  public static function tearDownAfterClass(){
  
    $that = new self();
    $t = $that->getDbConnectedOrm();
    
    $db = $t->getDb();
    $db->killTable($t->getTable());
    
  }//method

}//class
