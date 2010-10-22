<?php

require_once('mingo_test_class.php');

abstract class test_mingo_db_interface extends mingo_test {
  
  abstract public function getDbInterface();
  
  /**
   *  @return string  the host string (something like server:port
   */
  public function getDbHost(){ return ''; }//method
  
  /**
   *  @return string  the username used to connect
   */
  public function getDbUsername(){ return ''; }//method
  
  /**
   *  @return string  the password used to connect
   */
  public function getDbPassword(){ return ''; }//method

  /**
   *  @return string  the database name
   */
  public function getDbName(){ return __CLASS__; }//method
  
  /**
   *  this should create a mingo_db_interface object, then return it
   *  
   *  @return mingo_db_interface
   */
  public function getDb(){
  
    $interface = $this->getDbInterface();
    $db = new $interface();
    return $db;
  
  }//method

  ///public function setUp(){}//method

  public function testConnect(){
  
    $db = $this->getDb();
    $db_name = $this->getDbName();
    $host = $this->getDbHost();
    $username = $this->getDbUsername();
    $password = $this->getDbPassword();
    
    try{
    
      $ret_bool = $db->connect($db_name,$host,$username,$password);
      $this->assertTrue($ret_bool);

    }catch(Exception $e){
      
      $this->fail(
        sprintf('connecting failed with exception %s "%s"',get_class($e),$e->getMessage())
      );
      
    }//try/catch
  
    return $db;
  
  }//method

  /**
   *  @depends  testConnect
   */
  public function testSetTable($db){
  
    $table = $this->getTable();
    $schema = $this->getSchema();
  
    // make sure the table doesn't exist before creating it...
    $db->killTable($table,$schema);
  
    $ret_bool = $db->setTable($table,$schema);
    $this->assertTrue($ret_bool);
    $this->assertTrue($db->hasTable($table));
  
    // make sure the table exists...
    $table_list = $db->getTables();
    $this->assertContains($table,$table_list);
  
    // make sure index exists...
    $index_list = $db->getIndexes($table);
    $this->assertGreaterThanOrEqual(count($schema->getIndexes()),count($index_list));
    
    return $db;
    
  }//method
  
  /**
   *  @depends  testSetTable
   */
  public function testInsertIndexArray($db){
  
    $table = $this->getTable();
    $schema = $this->getSchema();
    
    // a map with 2 arrays can't be indexed (as per mongo), so an exception should
    // be thrown...
    try{
      
      $map = array(
        'foo' => 'che',
        'bar' => range(1,3),
        'baz' => range(1,2)
      );
      
      $map = $db->insert($table,$map,$schema);
      $this->fail('indexing 2 arrays should not currently work');
    
    }catch(PHPUnit_Framework_AssertionFailedError $e){
      throw $e;
    }catch(Exception $e){}//try/catch
    
    $map = array(
      'foo' => 'che',
      'bar' => range(1,3),
      'baz' => time()
    );
    
    $map = $db->insert($table,$map,$schema);
    $this->assertInternalType('array',$map);
    
    $where_criteria = new mingo_criteria();
    $where_criteria->isBar(1);
    $list = $db->get($table,$schema,$where_criteria);
    $this->assertInternalType('array',$list);
    
    $_id_list = array();
    foreach($list as $list_map){
    
      $this->assertEquals($map['bar'],$list_map['bar']);
      $this->assertEquals($map['baz'],$list_map['baz']);
      $_id_list[] = $map['_id'];
    
    }//method
    
    // delete them...
    $where_criteria = new mingo_criteria();
    $where_criteria->in_id($_id_list);
    
    $bool = $db->kill($table,$schema,$where_criteria);
    $this->assertTrue($bool);
    
    /**
    $map = array(
      'foo' => 'che',
      'bar' => array('cells' => array('foo','bar'),'categories' => array('one','two')),
      'baz' => time()
    );
    
    $map = $db->insert($table,$map,$schema);
    $where_criteria = new mingo_criteria();
    $where_criteria->inField('bar.cells',array('foo','happy'));
    ///$where_criteria->inField('bar.categories','one');
    $where_criteria->gteField('baz',time() - 100000);
    $list = $db->get($table,$schema,$where_criteria);
    out::e($list);
    out::x();
    
    $map = array(
      'foo' => 'che',
      'bar' => array(0 => array('testing' => 'happy',1 => 'sad'),1 => 'nothing'),
      'baz' => time()
    );
    
    $map = $db->insert($table,$map,$schema);
    $this->assertInternalType('array',$map);
    
    $where_criteria = new mingo_criteria();
    ///$where_criteria->isField('bar.testing','happy'); // equivalent to bar.0.testing
    $where_criteria->isField('bar','nothing'); // equivalent to bar.1.nothing
    $list = $db->get($table,$schema,$where_criteria);
    out::e($list);
    out::x();
    // **/
    
    
  }//method
  
  /**
   *  @depends  testSetTable
   */
  public function testInsert($db){
  
    $table = $this->getTable();
    $schema = $this->getSchema();
    $_id_list = array();
  
    for($i = 0; $i < 20 ;$i++){
    
      $map = array(
        'foo' => $i
      );
      
      $map = $db->insert($table,$map,$schema);
      
      $this->assertArrayHasKey('foo',$map);
      $this->assertArrayHasKey('_id',$map);
      $this->assertEquals(24,mb_strlen($map['_id']));
      $this->assertNotContains($map['_id'],$_id_list);
      $_id_list[] = $map['_id'];
    
    }//for
    
    return array('db' => $db,'_id_list' => $_id_list);
  
  }//method
  
  /**
   *  @depends  testInsert
   */
  public function testGet($db_map){
  
    $db = $db_map['db'];
    $_id_list = $db_map['_id_list'];
    $table = $this->getTable();
    $schema = $this->getSchema();
    
    $where_criteria = new mingo_criteria();
    $where_criteria->in_id($_id_list);
    
    $total = $db->getCount($table,$schema,$where_criteria);
    $this->assertEquals(20,$total);
    
    for($page = 0,$max = count($_id_list); $page < $max ;$page += 10){
      
      $list = $db->get($table,$schema,$where_criteria,array(10,$page));
      $this->assertInternalType('array',$list);
      $this->assertEquals(10,count($list));
      
      foreach($list as $map){
      
        $this->assertArrayHasKey('_id',$map);
        $this->assertContains((string)$map['_id'],$_id_list);
      
      }//foreach
      
    }//for
    
    $map = $db->getOne($table,$schema,$where_criteria);
    $this->assertArrayHasKey('_id',$map);
    $this->assertContains((string)$map['_id'],$_id_list);
  
    return $db_map;
  
  }//method
  
  /**
   *  @depends  testGet
   */
  public function testUpdate($db_map){
  
    $db = $db_map['db'];
    $_id_list = $db_map['_id_list'];
    $table = $this->getTable();
    $schema = $this->getSchema();
    $time = microtime(true);
  
    foreach($_id_list as $_id){
    
      $map = array(
        'bar' => $time
      );
    
      $map = $db->update($table,$_id,$map,$schema);
      $this->assertInternalType('array',$map);
      $this->assertArrayHasKey('_id',$map);
      $this->assertArrayHasKey('bar',$map);
      $this->assertEquals($time,$map['bar']);
      
      // now pull to make sure it really did get updated...
      $where_criteria = new mingo_criteria();
      $where_criteria->is_id($_id);
      $map = $db->getOne($table,$schema,$where_criteria);
      $this->assertInternalType('array',$map);
      $this->assertArrayHasKey('_id',$map);
      $this->assertArrayHasKey('bar',$map);
      $this->assertEquals($time,$map['bar']);
    
    }//foreach
    
    return $db_map;
  
  }//method
  
  /**
   *  @depends  testUpdate
   */
  public function testKill($db_map){
  
    $db = $db_map['db'];
    $_id_list = $db_map['_id_list'];
    $table = $this->getTable();
    $schema = $this->getSchema();
    
    $where_criteria = new mingo_criteria();
    $where_criteria->in_id($_id_list);
    $ret_bool = $db->kill($table,$schema,$where_criteria);
    $this->assertTrue($ret_bool);
    
    $list = $db->get($table,$schema,$where_criteria);
    $this->assertEmpty($list);
    
    return $db;
  
  }//method
  
  /**
   *  @depends  testKill
   */
  public function testKillTable($db){
  
    $table = $this->getTable();
    $ret_bool = $db->killTable($table);
    $this->assertTrue($ret_bool);
    
    $table_list = $db->getTables($table);
    $this->assertNotContains($table,$table_list);
    
  }//method
  
  protected function getSchema(){
  
    $ret_schema = new mingo_schema($this->getTable());
    $ret_schema->setIndex('foo','bar','baz');
    $ret_schema->setIndex('bar','baz');
    return $ret_schema;
  
  }//method
  
  protected function getTable(){
    return sprintf('%s_test',__CLASS__);
  }//method

}//class
