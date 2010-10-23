<?php

require('mingo_test_class.php');

class test_mingo_criteria extends mingo_test {

  public function testNin(){
  
    $test_map = array(
      'foo' => array(
        '$nin' => array(1,2,3,4)
      )
    );
  
    $c = new mingo_criteria();
    $c->ninField('foo',1,2,3,4);
    $this->assertTrue($c->hasWhere());
    $where_map = $c->getWhere();
    $this->assertSame($test_map,$where_map);
    
    $c = new mingo_criteria();
    $c->ninField('foo',array(1,2,3,4));
    $this->assertTrue($c->hasWhere());
    $where_map = $c->getWhere();
    $this->assertSame($test_map,$where_map);
    
  }//method
  
  public function testNear(){
  
    $c = new mingo_criteria();
    
    // sf...
    $lat = 37.775;
    $long = -122.4183333;
    $c->nearField('foo',array($lat,$long),50);
    
    $test_map = array(
      'foo' => array(
        '$near' => array($lat,$long),
        '$maxDistance' => 50
      )
    );
    
    $this->assertTrue($c->hasWhere());
    $where_map = $c->getWhere();
    $this->assertSame($test_map,$where_map);
  
  }//method 

}//class
