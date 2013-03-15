<?php

require_once('MingoTestBase.php');
//require_once('/vagrant/out_class.php'); 

class MingoOrmCallTest extends MingoTestBase {

  /**
   *  turns out I was having a runaway __call() created function.
   *  
   *  the fix for this was to add a method_exists() in MingoMagic::__call(), but I'm
   *  not incredibly happy about that as it is now called everytime the __call() method
   *  is called (which is a lot), but I'd rather have it slower and consistent than fast
   *  and buggy
   *
   *  @since  9-12-11
   */
  public function testBadMethodName(){
  
    $this->setExpectedException('BadMethodCallException');
  
    $username = 'Foo';
    $t = new MingoTestOrm();
    $t->loadByUsername($username);
  
  }//method

  public function testSetField(){
  
    // setup...
    $t = new MingoTestOrm();
    $foo = $this->getSetVal();
    
    $t->setFoo($foo);

    // test...
    $map = $t->getFields();

    $this->assertArrayHasKey('foo', $map);
    $this->assertEquals($foo,$map['foo']);
    
    // make sure the orm knows it should update itself...
    $this->assertTrue($t->isModified());
    
    // add one more value and make sure both keys still exist...
    $bar = $this->getSetVal();
    $t->setBar($bar);
    
    $map = $t->getFields();
    $this->assertArrayHasKey('foo',$map);
    $this->assertArrayHasKey('bar',$map);
    $this->assertEquals($foo,$map['foo']);
    $this->assertEquals($bar,$map['bar']);
    
  }//method
  
  public function testDepthSet(){
  
    // setup...
    $t = new MingoTestOrm();
    $foo = $this->getSetVal();
    $bar = $this->getSetVal();
    $t->setField(array('foo','baz'),$foo);
    
    // test...
    $map = $t->getFields();
    $this->assertArrayHasKey('foo',$map);
    $this->assertArrayHasKey('baz',$map['foo']);
    $this->assertEquals($foo,$map['foo']['baz']);
    
    // now set bar to an array to make sure setting existing works...
    $t->setBar(array('baz' => 'chaz'));
    $t->setField(array('bar','baz'),$bar);
    
    // test...
    $map = $t->getFields();
    $this->assertArrayHasKey('bar',$map);
    $this->assertArrayHasKey('baz',$map['bar']);
    $this->assertEquals($bar,$map['bar']['baz']);
    
    // now add a new key to foo...
    $t->setField(array('foo','che'),1);
  
    // test...
    $map = $t->getFields();
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
    $map = $t->getFields();
    $this->assertArrayHasKey('foo',$map);
    $this->assertEquals($val,$map['foo']);
    
    $this->assertArrayHasKey('bar',$map);
    $this->assertArrayHasKey('baz',$map['bar']);
    $this->assertEquals(($val + 1),$map['bar']['baz']);
    
    // make sure the orm knows it should update itself...
    $this->assertTrue($t->isModified());
    
    // now make sure adding works also...
    $t->bumpFoo(1);
    
    $map = $t->getFields();
    $this->assertEquals(($val + 1),$map['foo']);
  
  }//method
  
  public function testKill(){
  
    // do some killing...
    $t = $this->getOrm();
    $t->killFoo();
    
    $map = $t->getFields();
    $this->assertArrayNotHasKey('foo',$map);
    $this->assertTrue($t->isModified());
    
    $t->killField(array('bar','baz'));
    
    $map = $t->getFields();
    $this->assertArrayHasKey('bar',$map);
    $this->assertArrayNotHasKey('baz',$map['bar']);
    
    $t->killField('bar');
    
    $map = $t->getFields();
    $this->assertArrayNotHasKey('bar',$map);
    $this->assertEmpty($map);
  
  }//method
  
  public function testGet(){
  
    $t = $this->getOrm();
    $foo = $t->getFoo();
    $this->assertNotEmpty($foo);
    
    $bar = $t->getField(array('bar','baz'));
    $this->assertNotEmpty($bar);
    
    $bar = $t->getField('bar');
    $this->assertTrue(is_array($bar));
    
    $fake = $t->getField('blah_blah_blah', 0);
    $this->assertSame(0, $fake);
    
    $fake = $t->getField('blah_blah_blah');
    $this->assertNull($fake);
  
    $fake = $t->getField('blah_blah_blah','blah_blah_blah');
    $this->assertSame('blah_blah_blah',$fake);
    
    $table = $t->getTable();
    $table->setField('kasfjdkkads', MingoField::TYPE_INT);
  
    // make sure if field's have types then those types are returned...
    $t->setField('kasfjdkkads', '1003');
    $row_id = $t->getField('kasfjdkkads');
    $this->assertType('int', $row_id);
  
  }//method
  
  public function testHas(){
  
    $t = $this->getOrm();
    $this->assertTrue($t->hasFoo());
    $this->assertTrue($t->hasBar());
    $this->assertTrue($t->hasField(array('bar','baz')));
    
    $this->assertFalse($t->hasField('blah_blah_blah'));
  
    $this->assertTrue($t->hasFoo());
    $this->assertTrue($t->hasBar());
    $this->assertTrue($t->hasField(array('bar','baz')));
    
    $t->setFoo(0);
    $this->assertFalse($t->hasFoo());
  
  }//method
  
  public function testExists(){
  
    $t = $this->getOrm();
    $this->assertTrue($t->existsFoo());
    $this->assertTrue($t->existsBar());
    $this->assertTrue($t->existsField(array('bar','baz')));
    
    $this->assertFalse($t->existsField('blah_blah_blah'));
    
    $t->setFoo(0);
    $this->assertTrue($t->existsFoo());
  
  }//method
  
  public function testIs(){
  
    $t = $this->getOrm();
    $t->setFoo(0);
    
    $this->assertFalse($t->isFoo('blah'),'testing string word against integer 0');
    
    $t = new MingoTestOrm();
    $t->setFoo(1);
    
    $this->assertTrue($t->isFoo('1'),'testing string 1 against integer 1');
    $this->assertFalse($t->isFoo('2'),'testing string 2 against integer 1');
    
    $t = new MingoTestOrm();
    $t->setFoo('1');
    
    $this->assertTrue($t->isFoo(1),'testing int 1 against string 1');
    $this->assertFalse($t->isFoo(2),'testing integer 2 against string 1');
    
    $t = new MingoTestOrm();
    
    $t->setFoo(1);
    
    $this->assertTrue($t->isFoo(1),'testing int 1 against int 1');
    $this->assertFalse($t->isFoo(2),'testing int 1 agains int 2');
  
    $t->setField(array('bar','baz'),3);
    $this->assertTrue($t->isField(array('bar','baz'),3),'testing nested bar.baz with matching value');
    $this->assertFalse($t->isField(array('bar','baz'),2),'testing nested bar.baz with incorrect value');
  
  }//method
  
  public function testIn(){
  
    $t = $this->getOrm();
    $t->setFoo(1);
    
    $this->assertTrue($t->inFoo(1));
    $this->assertFalse($t->inFoo(2));
  
    $t->setField(array('bar','baz'),3);
    $this->assertTrue($t->inField(array('bar','baz'),3));
    $this->assertFalse($t->inField(array('bar','baz'),2));
    $this->assertFalse($t->inField(array('bar','baz'),1,2));
  
    // now test searching for in in the array...
    $t = new MingoTestOrm();
    $t->setFoo(array(1,2,3,4)); // foo: array(1,2,3,4)
    $this->assertTrue($t->inFoo(2));
    $this->assertTrue($t->inFoo(2,3));
    $this->assertTrue($t->inFoo($t->getFoo()));
    $this->assertFalse($t->inFoo(5));
    $this->assertFalse($t->inFoo(1,5));
  
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
    $this->assertTrue($t->isModified());
  
  }//method
  
  public function testClear(){
  
    $t = $this->getOrm();
    $t->setFoo(array(1,2,3,4)); // foo: array(1,2,3,4)
    $t->clearFoo(4);
    $this->assertEquals(array(1,2,3),$t->getFoo());
    $this->assertTrue($t->isModified());
  
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

