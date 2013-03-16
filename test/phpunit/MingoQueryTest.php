<?php
require_once('MingoTestBase.php');
///include('/vagrant/out_class.php');

class MingoQueryTest extends MingoTestBase {

  public function testGetOne(){
  
    $t = $this->getSetOrm();
    $query = $t->getQuery();
    $t2 = $query->is_id($t->get_id())->getOne();
    $this->assertEquals($t->get_id(), $t2->get_id());
  
  }//method

  public function testGet(){
  
    // $t should have 3 objects...
    $t = $this->getSetOrm();
    $t2 = $this->getSetOrm();
    $t3 = $this->getSetOrm();
    
    $_ids = array($t->get_id(), $t2->get_id(), $t3->get_id());
    $query = $t->getQuery();
    $iter = $query->in_id($_ids)->get();
    $this->assertEquals(3, count($iter));
    foreach($iter as $ti){
      $this->assertTrue(in_array($ti->get_id(), $_ids));
    }//foreach

  }//method
  
  /**
   *  load a whole bunch and make sure hasMore and the like work
   *  
   *  @since  5-9-11
   */
  public function testGetMany(){
  
    $total = 10;

    $t = null;
    $table = $this->getTable(sprintf('%s_%s',__FUNCTION__,rand(0,9)));    
    for($i = 0; $i < $total ;$i++){
    
      $t = $this->getDbConnectedOrm();
      $t->setTable($table);
      $this->assertEquals(true, $t->set());
    
    }//for

    $query = $t->getQuery();
    
    $total_count = count($query); // this causes a SELECT count(*) type query
    $this->assertGreaterThanOrEqual($total, $total_count);
    
    $limit = $total / 2;
    $query->setLimit($limit);

    $iter = $query->get();
    $count = count($iter); // causes just the total loaded rows to be counted
    $this->assertEquals($limit, $count);
    $this->assertTrue($iter->hasMore());
  
  }//method
  
  public function testKill(){
  
    $t = $this->getSetOrm();
    $query = $t->getQuery();
    $_id = $t->get_id();
    
    $ret_bool = $query->is_id($_id)->kill();
    $this->assertTrue($ret_bool);
    
    // try to load it again...
    $query = $t->getQuery();
    $orm = $query->is_id($_id)->getOne();
    $this->assertNull($orm);

  }//method

}//class

