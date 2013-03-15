<?php

require_once('MingoTestBase.php');

class MingoCriteriaTest extends MingoTestBase {

  public function testNin(){
  
    $test_map = array(
      'foo' => array(
        '$nin' => array(1,2,3,4)
      )
    );
  
    $c = new MingoCriteria();
    $c->ninField('foo',1,2,3,4);
    $this->assertTrue($c->hasWhere());
    $where_map = $c->getWhere();
    $this->assertSame($test_map,$where_map);
    
    $c = new MingoCriteria();
    $c->ninField('foo',array(1,2,3,4));
    $this->assertTrue($c->hasWhere());
    $where_map = $c->getWhere();
    $this->assertSame($test_map,$where_map);
    
  }//method
  
  public function testNear(){
  
    $c = new MingoCriteria();
    
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
  
  /**
   *  test the criteria ->has* magic method
   *  
   *  @since  11-8-10
   */
  public function testHas(){
  
    $c = new MingoCriteria();
    
    $this->assertFalse($c->hasFoo());
    $c->isFoo(1);
    $this->assertTrue($c->hasFoo());
    
    $this->assertFalse($c->hasBar());
    $c->gteBar(2);
    $this->assertTrue($c->hasBar());
    $this->assertTrue($c->hasFoo());
  
  }//method

}//class
