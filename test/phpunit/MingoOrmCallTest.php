<?php

require_once('MingoTestBase_class.php');

class MingoOrmCallTest extends MingoTestBase {

  /**
   * @dataProvider  getSetVal
   */
  public function testSimpleSetNotMulti($foo,$bar){
  
    // setup...
    $t = new MingoTestOrm();
    
    /* try{
      $t->setFoo();
      $this->fail('setting a blank value did not throw an exception');
    }catch(InvalidArgumentException $e){}//try/catch */

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
    $t = new MingoTestOrm();
    $t->attach(array());
    $t->attach(array());
    
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
    $t = new MingoTestOrm();
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
    $t = new MingoTestOrm();
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
   * @dataProvider  getOrm
   */
  public function testGet($t){
  
    $foo = $t->getFoo();
    $this->assertNotEmpty($foo);
    
    $bar = $t->getField(array('bar','baz'));
    $this->assertNotEmpty($bar);
    
    $bar = $t->getField('bar');
    $this->assertTrue(is_array($bar));
    
    $fake = $t->getField('blah_blah_blah',0);
    $this->assertSame(0,$fake);
    
    $fake = $t->getField('blah_blah_blah');
    $this->assertNull($fake);
  
    $fake = $t->getField('blah_blah_blah','blah_blah_blah');
    $this->assertSame('blah_blah_blah',$fake);
    
    $t2 = $this->getOrm(); $t2 = $t2[0][0];
    $t->attach($t2);
    
    $foo = $t->getFoo();
    $this->assertTrue(is_array($foo));
    $this->assertEquals(2,count($foo));
    
    $t->attach(array());
    $foo = $t->getFoo('adsfsdf');
    $this->assertTrue(is_array($foo));
    $this->assertEquals(3,count($foo));
    $this->assertSame('adsfsdf',$foo[2]);
  
  }//method
  
  /**
   * @dataProvider  getOrm
   */
  public function testHas($t){
  
    $this->assertTrue($t->hasFoo());
    $this->assertTrue($t->hasBar());
    $this->assertTrue($t->hasField(array('bar','baz')));
    
    $this->assertFalse($t->hasField('blah_blah_blah'));
  
    $t2 = $this->getOrm(); $t2 = $t2[0][0];
    $t->attach($t2);
    
    $this->assertTrue($t->hasFoo());
    $this->assertTrue($t->hasBar());
    $this->assertTrue($t->hasField(array('bar','baz')));
    
    $t->setFoo(0);
    $this->assertFalse($t->hasFoo());
    
    $t->attach(array());
    $this->assertFalse($t->hasFoo());
  
  }//method
  
  /**
   * @dataProvider  getOrm
   */
  public function testExists($t){
  
    $this->assertTrue($t->existsFoo());
    $this->assertTrue($t->existsBar());
    $this->assertTrue($t->existsField(array('bar','baz')));
    
    $this->assertFalse($t->existsField('blah_blah_blah'));
    
    $t->setFoo(0);
    $this->assertTrue($t->existsFoo());
  
    $t2 = $this->getOrm(); $t2 = $t2[0][0];
    $t->attach($t2);
    
    $this->assertTrue($t->existsFoo());
    $this->assertTrue($t->existsBar());
    $this->assertTrue($t->existsField(array('bar','baz')));
    
    $t->attach(array());
    $this->assertFalse($t->existsFoo());
  
  }//method
  
  public function testIs(){
  
    $t = new MingoTestOrm();
    
    $t->setFoo(1);
    
    $this->assertTrue($t->isFoo(1));
    $this->assertFalse($t->isFoo(2));
  
    $t->setField(array('bar','baz'),3);
    $this->assertTrue($t->isField(array('bar','baz'),3));
    $this->assertFalse($t->isField(array('bar','baz'),2));
  
    $t->attach(array('foo' => 1));
    $this->assertTrue($t->isFoo(1));
    $this->assertFalse($t->isFoo(2));
    
    $t->attach(array('foo' => 2));
    $this->assertFalse($t->isFoo(1));
    $this->assertFalse($t->isFoo(2));
  
  }//method
  
  public function testIn(){
  
    $t = new MingoTestOrm();
    
    $t->setFoo(1);
    
    $this->assertTrue($t->inFoo(1));
    $this->assertFalse($t->inFoo(2));
  
    $t->setField(array('bar','baz'),3);
    $this->assertTrue($t->inField(array('bar','baz'),3));
    $this->assertFalse($t->inField(array('bar','baz'),2));
    $this->assertFalse($t->inField(array('bar','baz'),1,2));
  
    $t->attach(array('foo' => 4)); // foo: 1,4
    $this->assertTrue($t->inFoo(1));
    $this->assertFalse($t->inFoo(2));
    $this->assertTrue($t->inFoo(1,4));
    $this->assertFalse($t->inFoo(1,2));
    
    $t->attach(array('foo' => 2));  // foo: 1,4,2
    $this->assertTrue($t->inFoo(1));
    $this->assertTrue($t->inFoo(2));
    $this->assertTrue($t->inFoo(1,4,2));
    $this->assertFalse($t->inFoo(3));
    $this->assertFalse($t->inFoo(1,4,3));
    
    // now test searching for in in the array...
    $t = new MingoTestOrm();
    $t->setFoo(array(1,2,3,4)); // foo: array(1,2,3,4)
    $this->assertTrue($t->inFoo(2));
    $this->assertTrue($t->inFoo(2,3));
    $this->assertTrue($t->inFoo($t->getFoo()));
    $this->assertFalse($t->inFoo(5));
    $this->assertFalse($t->inFoo(1,5));
    
    // attach another value...
    $t->attach(array('foo' => 5)); // foo: array(1,2,3,4), 5
    $this->assertTrue($t->inFoo(2));
    $this->assertTrue($t->inFoo(array(1,2,3,4)));
    $this->assertFalse($t->inFoo(6));
    $this->assertFalse($t->inFoo(1,6));
    
    // test an array of arrays...
    $t->attach(array('foo' => array(array(6)))); // foo: array(1,2,3,4), 5, array(array(6))
    $this->assertTrue($t->inFoo(array(6)));
  
  }//method
  
  public function testAttach(){
  
    $t = new MingoTestOrm();
    $t->setFoo(array(1,2,3));
    $t->attachFoo(4);
    $this->assertEquals(array(1,2,3,4),$t->getFoo());
  
    $t->attachFoo(5,6);
    
    $this->assertEquals(array(1,2,3,4,5,6),$t->getFoo());
  
    $t->setBar('foo');
    $t->attachBar('bar');
    $this->assertEquals('foobar',$t->getBar());
    
    // make sure the orm knows it should update itself...
    $list = $t->getList();
    $this->assertTrue($list[0]['modified']);
  
  }//method
  
  public function testClear(){
  
    $t = new MingoTestOrm();
    $t->setFoo(array(1,2,3,4)); // foo: array(1,2,3,4)
    $t->clearFoo(4);
    $this->assertEquals(array(1,2,3),$t->getFoo());
  
     // attach another value...
    $t->attach(array('foo' => 3)); // foo: array(1,2,3), 3
    $t->clearFoo(3);
    $this->assertEquals(array(array(1,2),null),$t->getFoo());
    
    $t->attach(array('foo' => 1)); // foo: array(1,2), null, 1
    $t->attach(array('foo' => 2)); // foo: array(1,2), null, 1, 2
    $t->clearFoo(1,2);
    $this->assertEquals(array(array(),null,null,null),$t->getFoo());
    
    // make sure the orm knows it should update itself...
    $list = $t->getList();
    foreach($list as $map){
      $this->assertTrue($map['modified']);
    }//foreach
  
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

}//class

