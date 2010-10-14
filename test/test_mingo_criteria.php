<?php

require('mingo_test_class.php');

class test_mingo_criteria extends mingo_test {

  /**
   * @dataProvider  getOrm
   */
  public function testNin($t){
  
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

}//class
