<?php

require('mingo_test_class.php');

class test_mingo_orm extends mingo_test {

  /**
   * @dataProvider  getOrm
   */
  public function testAppend($t){
  
    $this->assertFalse($t->isMulti());
    $this->assertSame(1,$t->getCount());
    $this->assertTrue($t->isCount(1));
  
    // now test append and multi support...
    $t2 = $this->getOrm(); $t2 = $t2[0][0];
    $t->append($t2);
    $t->append(array('foo' => 1,'bar' => array('baz' => 2)));
    
    $list = $t->getList();
    $this->assertSame(3,count($list));
    $this->assertTrue($t->isMulti());
    
    // make sure the structure of each list row is correct...
    foreach($list as $map){
      
      $this->assertArrayHasKey('modified',$map);
      $this->assertInternalType('boolean',$map['modified']);
      
      $this->assertArrayHasKey('map',$map);
      $this->assertInternalType('array',$map['map']);
      
      // make sure the structure is correct of the map...
      $this->assertArrayHasKey('foo',$map['map']);
      $this->assertArrayHasKey('bar',$map['map']);
      $this->assertArrayHasKey('baz',$map['map']['bar']);
    
    }//foreach
  
    return $t;
  
  }//method

  /**
   *  @dataProvider  getOrm
   */
  public function testGet($t){
  
    // $t should have 3 objects...
    
    $t2 = $t->get(0);
    
    $this->assertInstanceOf('test_orm',$t2);
    $this->assertFalse($t->isMulti());
    $this->assertSame(1,$t->getCount());
    $this->assertTrue($t->isCount(1));
    
  }//method

}//class
