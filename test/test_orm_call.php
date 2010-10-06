<?php

require('mingo_test_class.php');

class test_orm_call extends mingo_test {

  /**
   * @dataProvider  getSetVal
   */
  public function testSimpleSetNotMulti($foo,$bar){
  
    // setup...
    $t = new test_orm();
    
    try{
      $t->setFoo();
      $this->fail('setting a blank value did not throw an exception');
    }catch(InvalidArgumentException $e){}//try/catch
    
    $t->setFoo($foo);
    
    // test...
    $map = $t->getMap(0);
    $this->assertArrayHasKey('foo',$map);
    $this->assertEquals($foo,$map['foo']);
    
    // make sure the orm knows it should update itself...
    $list = $t->getList();
    $this->assertTrue($list[0]['modified']);
    
    // add one more value and make sure both keys still exist...
    $t->setBar($bar);
    
    $map = $t->getMap(0);
    $this->assertArrayHasKey('foo',$map);
    $this->assertArrayHasKey('bar',$map);
    $this->assertEquals($foo,$map['foo']);
    $this->assertEquals($bar,$map['bar']);
    
  }//method
  
  /**
   * @dataProvider  getSetVal
   */
  public function testSimpleSetMulti($foo,$bar){
  
    // setup...
    $val = 1;
    $t = new test_orm();
    $t->append(array());
    $t->append(array());
    
    $t->setFoo($foo);
    $t->setBar($bar);
    
    $map_list = $t->getMap();
    $this->assertEquals(count($map_list),2);
    
    foreach($map_list as $map){
    
      $this->assertArrayHasKey('foo',$map);
      $this->assertArrayHasKey('bar',$map);
      $this->assertEquals($foo,$map['foo']);
      $this->assertEquals($bar,$map['bar']);
    
    }//foreach
    
  }//method
  
  /**
   * @dataProvider  getSetVal
   */
  public function testDepthSet($foo,$bar){
  
    // setup...
    $t = new test_orm();
    $t->setField(array('foo','baz'),$foo);
    
    // test...
    $map = $t->getMap(0);
    $this->assertArrayHasKey('foo',$map);
    $this->assertArrayHasKey('baz',$map['foo']);
    $this->assertEquals($foo,$map['foo']['baz']);
    
    // now set bar to an array to make sure setting existing works...
    $t->setBar(array('baz' => 'chaz'));
    $t->setField(array('bar','baz'),$bar);
    
    // test...
    $map = $t->getMap(0);
    $this->assertArrayHasKey('bar',$map);
    $this->assertArrayHasKey('baz',$map['bar']);
    $this->assertEquals($bar,$map['bar']['baz']);
    
    // now add a new key to foo...
    $t->setField(array('foo','che'),1);
  
    // test...
    $map = $t->getMap(0);
    $this->assertArrayHasKey('foo',$map);
    $this->assertEquals(2,count($map['foo']));
    $this->assertEquals($foo,$map['foo']['baz']);
    $this->assertEquals(1,$map['foo']['che']);
  
  }//method
  
  public function testBump(){
  
    // setup...
    $val = rand(1,5000);
    $t = new test_orm();
    $t->bumpFoo($val);
    $t->bumpField(array('bar','baz'),($val + 1));
  
    // test...
    $map = $t->getMap(0);
    $this->assertArrayHasKey('foo',$map);
    $this->assertEquals($val,$map['foo']);
    
    $this->assertArrayHasKey('bar',$map);
    $this->assertArrayHasKey('baz',$map['bar']);
    $this->assertEquals(($val + 1),$map['bar']['baz']);
    
    // make sure the orm knows it should update itself...
    $list = $t->getList();
    $this->assertTrue($list[0]['modified']);
    
    // now make sure adding works also...
    $t->bumpFoo(1);
    
    $map = $t->getMap(0);
    $this->assertEquals(($val + 1),$map['foo']);
  
  }//method
  
  /**
   * @dataProvider  getOrm
   */
  public function testKill($t){
  
    // do some killing...
    $t->killFoo();
    
    $map = $t->getMap(0);
    $this->assertArrayNotHasKey('foo',$map);
    
    $list = $t->getList();
    $this->assertTrue($list[0]['modified']);
    
    $t->killField(array('bar','baz'));
    
    $map = $t->getMap(0);
    $this->assertArrayHasKey('bar',$map);
    $this->assertArrayNotHasKey('baz',$map['bar']);
    
    $t->killField('bar');
    
    $map = $t->getMap(0);
    $this->assertArrayNotHasKey('bar',$map);
    $this->assertEmpty($map);
  
  }//method
  
  /**
   *  http://www.phpunit.de/manual/current/en/writing-tests-for-phpunit.html#writing-tests-for-phpunit.data-providers
   *
   */        
  public function getSetVal(){
    return array(
      array(rand(0,PHP_INT_MAX),md5(microtime(true)))
    );
  }//method
  
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
