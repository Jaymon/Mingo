<?php
/**
 *  @todo currently the load and kill only load/kill one row, so we need to test
 *  loading mulitple rows and killing multiple rows and iterating through, and pagination,
 *  and totals   
 */    

require('mingo_test_class.php');

class test_mingo_orm extends mingo_test {

  public function testSetSpatial(){
  
    $t = $this->getDbConnectedOrm();
    
    $t->setPt($this->getPoint());
    
    $t->setType(100);
    $t->setFoo('blah');
    
    $this->assertTrue($t->set());
    
    try{
    
      // now update point...
      $t->setPt('blah');
    
      $t->set();
      $this->fail('Pt was the wrong format, an exception should have been generated');
    
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
  
    ///$test_db = new test_mingo_db_sqlite();
    $test_db = new test_mingo_db_mysql();
  
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
