<?php
require_once('MingoTestBase.php');
include('/vagrant/out_class.php');

class MingoIteratorTest extends MingoTestBase {

  public function testHasMore(){
    $t = $this->getOrm();
    $iter = new MingoIterator(array(), new MingoQuery(get_class($t)));
    
    $this->assertFalse($iter->hasMore());
    $iter->setMore(true);
    $this->assertTrue($iter->hasMore());

  }//method

  public function testIterate(){
    $t = $this->getOrm();
    $iter = new MingoIterator(
      array(
        array(
          '_id' => 1,
          'bar' => 'constant'
        ),
        array(
          '_id' => 2,
          'bar' => 'constant'
        ),
        array(
          '_id' => 3,
          'bar' => 'constant'
        )
      ),
      new MingoQuery(get_class($t))
    );

    $_ids = array(1, 2, 3);
    foreach($iter as $key => $ti){
      $this->assertInstanceOf(get_class($t), $ti);
      $this->assertEquals($_ids[$key], $ti->get_id());
    }//forach

  }//metho

  public function testCount(){
    $t = $this->getOrm();
    $iter = new MingoIterator(
      array(
        array(
          '_id' => 1,
        ),
        array(
          '_id' => 2,
        ),
        array(
          '_id' => 3,
        )
      ),
      new MingoQuery(get_class($t))
    );
  
    $this->assertEquals(3, count($iter));
  
  }//method


}//class

