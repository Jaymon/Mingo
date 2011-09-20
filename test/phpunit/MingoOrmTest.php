<?php
/**
 *  @todo currently the load and kill only load/kill one row, so we need to test
 *  loading mulitple rows and killing multiple rows and iterating through, and pagination,
 *  and totals   
 */    

require_once('MingoTestBase_class.php');

class MingoOrmTest extends MingoTestBase {

  /**
   *  makes sure the cloning in {@link MingoOrm::detach()} works as expected
   *  
   *  basically, I changed the detach method to clone the current instance instead
   *  of creating a whole new object, this allows the children objects to inherit any
   *  unknowable values of the parent with minimal fuss, it also means the child doesn't
   *  have to recreate the table or db objects      
   *      
   *  @since  9-19-11
   */
  public function testIterate(){
  
    $orms = new MingoIterateTestOrm();
    $orms->attach(array('foo' => 1));
    $orms->attach(array('foo' => 2));
    $orms->attach(array('foo' => 3));
    
    foreach($orms as $orm){
    
      $this->assertEquals(1,$orm->common->count);
      $this->assertEquals('che',$orm->name);
    
    }//foreach
    
    $orms->common->count = 2;
    
    foreach($orms as $orm){
    
      $this->assertEquals(2,$orm->common->count);
      $this->assertEquals('che',$orm->name);
    
    }//foreach
  
  }//method

  /**
   * @dataProvider  getOrm
   */
  public function testAttach($t){
  
    $this->assertFalse($t->isMulti());
    $this->assertSame(1,$t->getCount());
    $this->assertTrue($t->isCount(1));
  
    // now test attach and multi support...
    $t2 = $this->getOrm(); $t2 = $t2[0][0];
    $t->attach($t2);
    $t->attach(array('foo' => 1,'bar' => array('baz' => 2)));
    
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
    
    $this->assertInstanceOf('MingoTestOrm',$t2);
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
    
    $c = new MingoCriteria();
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
    
    $c = new MingoCriteria();
    $c->is_id($_id);
    $t->load($c);
    $this->assertOrmStructure($t);
    
    return $_id;
  
  }//method
  
  /**
   *  load a whole bunch and make sure hasMore and the like work
   *  
   *  @since  5-9-11
   */
  public function testLoadMany(){
  
    $total = 10;

    $table = $this->getTable(sprintf('%s_%s',__FUNCTION__,rand(0,9)));    
    for($i = 0; $i < $total ;$i++){
    
      $t = $this->getDbConnectedOrm();
      $t->setTable($table);
      $this->assertTrue($t->set());
    
    }//for
    ///$table = new MingoTable('testloadmany_1');
    
    $c = new MingoCriteria();
    $t = $this->getDbConnectedOrm();
    
    $total_load = $t->loadTotal($c);
    $this->assertGreaterThanOrEqual($total,$total_load);
    
    $limit = 5;
    
    $c->setLimit($limit);
    $count = $t->load($c);
    $this->assertEquals($limit,$count);
    $this->assertTrue($t->hasMore());
  
  }//method
  
  /**
   *  @depends  testLoad
   */
  public function testKill($_id){
  
    $t = $this->getDbConnectedOrm();
    
    $c = new MingoCriteria();
    $c->is_id($_id);
    $t->loadOne($c);
    $this->assertOrmStructure($t);
    
    $t->kill();
    $this->assertEmpty($t->get_id(null));
    
    // try to load it again...
    $c = new MingoCriteria();
    $c->is_id($_id);
    $this->assertEquals(false,$t->loadOne($c));
    
  }//method
  
  /**
   *  test to make sure the load() and loadOne() works as expected
   *   
   *  @since  6-1-11
   */        
  public function testMulti(){
  
    $t1 = $this->getDbConnectedOrm();
    $t1->setFoo(1);
    $t1->set();
    $this->assertTrue($t1->has_id());
    
    $t2 = $this->getDbConnectedOrm();
    $t2->setFoo(2);
    $t2->set();
    $this->assertTrue($t2->has_id());
    
    $c = new MingoCriteria();
    $c->is_id($t1->get_id());
    $t3 = $this->getDbConnectedOrm();
    $this->assertTrue($t3->loadOne($c));
    $this->assertFalse($t3->isMulti());
    
    $this->assertType('int',$t3->getFoo());
    $this->assertSame($t1->getFoo(),$t3->getFoo());
    
    // now do a multi load...
    $c = new MingoCriteria();
    $c->in_id($t1->get_id(),$t2->get_id());
    $t4 = $this->getDbConnectedOrm();
    $this->assertSame(2,$t4->load($c));
    $this->assertTrue($t4->isMulti());
    
    $this->assertType('array',$t4->getFoo());
    $this->assertEquals(array($t1->getFoo(),$t2->getFoo()),$t4->getFoo());
    
    $c = new MingoCriteria();
    $c->in_id($t1->get_id());
    $t5 = $this->getDbConnectedOrm();
    $this->assertSame(1,$t5->load($c));
    $this->assertTrue($t5->isMulti());
    
    $this->assertType('array',$t5->getFoo());
    $this->assertEquals(array($t1->getFoo()),$t5->getFoo());
  
  }//method
  
  protected function assertOrmStructure($t){
  
    $this->assertNotEmpty($t->get_id(null));
    $this->assertEquals('one',$t->getFoo());
    $this->assertEquals(2,$t->getBar());
    $this->assertNotEmpty($t->getCreated(null));
    $this->assertNotEmpty($t->getUpdated(null));
  
  }//method
  
  protected function getDbConnectedOrm(){
  
    $test_db = new MingoSQLiteInterfaceTest();
    ///$test_db = new test_mingo_db_mongo();
  
    $db = $test_db->getDb();
    /* $db->connect(
      $test_db->getDbInterface(),
      $test_db->getDbName(),
      $test_db->getDbHost(),
      $test_db->getDbUsername(),
      $test_db->getDbPassword()
    ); */
    
    $t = new MingoTestOrm();
    $t->setDb($db);
    return $t;
  
  }//method

}//class

/**
 *  this is specfic to the {@link MingoOrmTest::testIterate()} method
 */
class MingoIterateTestOrm extends MingoTestOrm {

  public $common = null;
  
  public $name = 'che';

  public function __construct(){
  
    $this->common = new StdClass();
    $this->common->count = 1;
  
  }//method
  
}//class
