<?php
require_once('MingoTestBase.php');
//include('/vagrant/out_class.php');

class MingoOrmTest extends MingoTestBase {

  /**
   *  make sure an Interface can be serialized and unserialized and work
   *      
   *  for some reason, the private db var started trying to be serialized (I swear
   *  serialize used to ignore private vars), this has never been a problem until
   *  now, so this test was added to make sure the orm can be serialized in the future         
   *      
   *  @since  10-3-11
   */
  public function testSerialize(){
  
    $orm = $this->getDbConnectedOrm();
    ///$orm = new MingoIterateTestOrm();
    
    $orm->setFoo('one');
    $orm->setBar(2);
    
    $this->assertEmpty($orm->get_id());
    $orm->set();
    $this->assertNotEmpty($orm->get_id());
    
    $sorm = serialize($orm);
    
    $this->assertInternalType('string',$sorm);
    $orm2 = unserialize($sorm);
    
    $this->assertSame('one',$orm2->getFoo());
    $this->assertSame(2,$orm2->getBar());
    $this->assertSame($orm->get_id(),$orm2->get_id());
    
  }//method

  public function testSet(){
  
    $t = $this->getDbConnectedOrm();
    
    $t->setFoo('one');
    $t->setBar(2);
    
    $this->assertEmpty($t->get_id(null));
    $t->set();
    
    $this->assertOrmStructure($t);
  
    return $t->get_id();
  
  }//method

  public function testKill(){
    $t = $this->getSetOrm();
    $this->assertTrue($t->has_id());

    $t->kill();

    $this->assertFalse($t->has_id());
    $this->assertFalse($t->has_created());
    $this->assertFalse($t->has_updated());

  }//method
  
  protected function assertOrmStructure($t){
  
    $this->assertNotEmpty($t->get_id(null));
    $this->assertEquals('one',$t->getFoo());
    $this->assertEquals(2,$t->getBar());
    $this->assertNotEmpty($t->get_Created(null));
    $this->assertNotEmpty($t->get_Updated(null));
  
  }//method
  
}//class

