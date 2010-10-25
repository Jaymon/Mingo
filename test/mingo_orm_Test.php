<?php
/**
 *  @todo currently the load and kill only load/kill one row, so we need to test
 *  loading mulitple rows and killing multiple rows and iterating through, and pagination,
 *  and totals   
 */    

require_once('mingo_test_class.php');

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
  
  public function testSet(){
  
    $t = $this->getDbConnectedOrm();
    
    $t->setFoo('one');
    $t->setBar(2);
    
    $this->assertEmpty($t->get_id(null));
    $t->set();
    
    $this->assertOrmStructure($t);
  
    return $t->get_id();
  
  }//method
  
  /**
   *  @depends  testSet
   */
  public function testLoadBy_Id($_id){
  
    $t = $this->getDbConnectedOrm();
    $t->loadBy_id($_id);
    $this->assertOrmStructure($t);
    
    return $_id;
  
  }//method
  
  /**
   *  @depends  testLoadBy_Id
   */
  public function testLoadOne($_id){
  
    $t = $this->getDbConnectedOrm();
    
    $c = new mingo_criteria();
    $c->is_id($_id);
    $t->loadOne($c);
    $this->assertOrmStructure($t);
    
    return $_id;
  
  }//method
  
  /**
   *  @depends  testLoadOne
   */
  public function testLoad($_id){
  
    $t = $this->getDbConnectedOrm();
    
    $c = new mingo_criteria();
    $c->is_id($_id);
    $t->load($c);
    $this->assertOrmStructure($t);
    
    return $_id;
  
  }//method
  
  /**
   *  @depends  testLoad
   */
  public function testKill($_id){
  
    $t = $this->getDbConnectedOrm();
    
    $c = new mingo_criteria();
    $c->is_id($_id);
    $t->loadOne($c);
    $this->assertOrmStructure($t);
    
    $t->kill();
    $this->assertEmpty($t->get_id(null));
    
    // try to load it again...
    $c = new mingo_criteria();
    $c->is_id($_id);
    $this->assertEquals(false,$t->loadOne($c));
    
  }//method
  
  protected function assertOrmStructure($t){
  
    $this->assertNotEmpty($t->get_id(null));
    $this->assertEquals('one',$t->getFoo());
    $this->assertEquals(2,$t->getBar());
    $this->assertNotEmpty($t->getCreated(null));
    $this->assertNotEmpty($t->getUpdated(null));
  
  }//method
  
  protected function getDbConnectedOrm(){
  
    $test_db = new test_mingo_db_sqlite();
    ///$test_db = new test_mingo_db_mongo();
  
    $db = mingo_db::getInstance();
    $db->connect(
      $test_db->getDbInterface(),
      $test_db->getDbName(),
      $test_db->getDbHost(),
      $test_db->getDbUsername(),
      $test_db->getDbPassword()
    );
    
    $t = new test_orm();
    $t->setDb($db);
    return $t;
  
  }//method

}//class
