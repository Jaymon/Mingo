<?php

require_once('MingoTestBase_class.php');

abstract class MingoInterfaceTest extends MingoTestBase {
  
  /**
   *  singleton db object
   */     
  protected static $db = null;
  
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
   *  @return string  the database connection options
   */
  public function getDbOptions(){ return array(); }//method
  
  /**
   *  this should create a mingo_db_interface object, then return it
   *  
   *  @return mingo_db_interface
   */
  public function getDb(){
  
    // canary...
    if(self::$db !== null){ return self::$db; }//if
  
    $interface = $this->getDbInterface();
    self::$db = new $interface();
    self::$db = $this->connect(self::$db);
    $this->setTable();
    return self::$db;
  
  }//method
  
  /**
   *  test the ability of the interface to autocreate the table
   *
   *  @since  5-9-11   
   */        
  public function testAutoCreateTable(){
  
    $db = $this->getDb();
    $table = $this->getTable(sprintf('%s_%s',__FUNCTION__,rand(0,9)));
    $db->killTable($table);
    
    $this->assertFalse($db->hasTable($table));
    
    $where_criteria = new MingoCriteria();
    $where_criteria->isFoo(1);
    
    $db->get($table,$where_criteria);
    
    $this->assertTrue($db->hasTable($table));
  
  }//method
  
  public function testInsertIndexArray()
  {
    $db = $this->getDb();
    $table = $this->getTable();

    // a map with 2 arrays can't be indexed (as per mongo), so an exception should
    // be thrown...
    try{
      
      $map = array(
        'foo' => 'che',
        'bar' => range(1,3),
        'baz' => range(1,2)
      );
      
      $map = $db->set($table,$map);
      $this->fail('indexing 2 arrays should not currently work');
    
    }catch(PHPUnit_Framework_AssertionFailedError $e){
      throw $e;
    }catch(Exception $e){}//try/catch
    
    $map = array(
      'foo' => 'che',
      'bar' => range(1,3),
      'baz' => time()
    );
 
    $map = $db->set($table,$map);
    $this->assertInternalType('array',$map);

    $where_criteria = new MingoCriteria();
    $where_criteria->isBar(1);
    $list = $db->get($table,$where_criteria);
    $this->assertInternalType('array',$list);

    $_id_list = array();
    foreach($list as $list_map){
    
      $this->assertEquals($map['bar'],$list_map['bar']);
      $this->assertEquals($map['baz'],$list_map['baz']);
      $_id_list[] = $map['_id'];
    
    }//method

    // delete them...
    $where_criteria = new MingoCriteria();
    $where_criteria->in_id($_id_list);
 
    $bool = $db->kill($table,$where_criteria);
    $this->assertTrue($bool);
    
  }//method
  
  public function testGetIndexArray(){
  
    $db = $this->getDb();
    $table = $this->getTable();
  
    $map = array(
      'foo' => 'che',
      'bar' => array(1,2,3),
      'baz' => time()
    );
    
    // insert it twice because we want atleast 2 rows...
    $db->set($table,$map);
    
    // somehow mongo can set the id of the $map even though I don't do $map = $db->set...
    if(isset($map['_id'])){ unset($map['_id']); }//if
    
    $db->set($table,$map);
  
    $where_criteria = new MingoCriteria();
    $where_criteria->inField('bar',1,2);
    
    // get the count...
    $count = $db->getCount($table,$where_criteria);
    $this->assertSame(2,$count);
    
    $where_criteria->setLimit(2);
    
    $list = $db->get($table,$where_criteria,$where_criteria->getBounds());
    
    $this->assertSame(2,count($list));
    $this->assertNotEquals((string)$list[0]['_id'],(string)$list[1]['_id']);
  
  }//method
  
  public function testInsert(){
  
    $db = $this->getDb();
    $table = $this->getTable();
    $_id_list = array();
  
    for($i = 0; $i < 20 ;$i++){
    
      $map = array(
        'foo' => $i
      );
      
      $map = $db->set($table,$map);
      
      $this->assertArrayHasKey('foo',$map);
      $this->assertArrayHasKey('_id',$map);
      $this->assertEquals(24,mb_strlen($map['_id']));
      $this->assertNotContains($map['_id'],$_id_list);
      $_id_list[] = (string)$map['_id'];
    
    }//for
    
    return array('db' => $db,'_id_list' => $_id_list);
  
  }//method
  
  /**
   *  this is just to make sure there are no problems raised when the criteria has nothing set
   *  
   *  I noticed a NOTICE was getting raised when doing this using the mysql interface
   *      
   *  @since  1-6-11      
   */
  public function testGetWithNoWhere(){
  
    $db = $this->getDb();
    $table = $this->getTable();
    $limit = 5;
    $where_criteria = new MingoCriteria();
    $where_criteria->setBounds($limit,0);
    
    $list = $db->get($table,$where_criteria);
    $this->assertInternalType('array',$list);
    $this->assertLessThanOrEqual($limit,count($list));
  
  }//method
  
  /**
   *  @depends  testInsert
   *  
   *  @since  3-3-11      
   */
  public function testGetOne($db_map){

    $db = $db_map['db'];
    $_id_list = $db_map['_id_list'];
    $table = $this->getTable();

    foreach($_id_list as $_id){

      $where_criteria = new MingoCriteria();
      $where_criteria->is_id((string)$_id);
      $map = $db->getOne(
        $table,
        $where_criteria
      );
      
      $this->assertArrayHasKey('_id',$map);
        
      // make sure this was an id we wanted...
      $map_id = (string)$map['_id'];
      $this->assertContains($map_id,$_id_list);
    
    }//foreach
  
  }//method
  
  /**
   *  @depends  testInsert
   */
  public function testGet($db_map){
  
    $db = $db_map['db'];
    $_id_list = $db_map['_id_list'];
    $table = $this->getTable();
    
    $where_criteria = new MingoCriteria();
    $where_criteria->in_id($_id_list);
    
    $total = $db->getCount($table,$where_criteria);
    $this->assertEquals(20,$total);
    
    $where_criteria->setLimit(10);
    $_id_seen_list = array();
    
    for($offset = 0,$max = count($_id_list); $offset < $max ;$offset += 10){
      
      $where_criteria->setOffset($offset);
      $list = $db->get($table,$where_criteria);
      $this->assertInternalType('array',$list);
      $this->assertEquals(10,count($list));
      
      foreach($list as $map){
      
        $this->assertArrayHasKey('_id',$map);
        
        // make sure this was an id we wanted...
        $_id = (string)$map['_id'];
        $this->assertContains($_id,$_id_list);
        
        // make sure we aren't getting any dupes...
        $this->assertNotContains($_id,$_id_seen_list);
        $_id_seen_list[] = $_id;
      
      }//foreach
      
    }//for
    
    $map = $db->getOne($table,$where_criteria);
    $this->assertArrayHasKey('_id',$map);
    $this->assertContains((string)$map['_id'],$_id_list);
    
    // make sure counts and results are right when we have mutltiple ids...
    $where_criteria = new MingoCriteria();
    $where_criteria->in_id(
      array(
        $_id_list[0],
        $_id_list[1],
        $_id_list[0]
      )
    );
    $list = $db->get($table,$where_criteria);
    $this->assertEquals(2,count($list));
    
    $count = $db->getCount($table,$where_criteria);
    $this->assertEquals(2,$count);
  
    return $db_map;
  
  }//method
  
  /**
   *  @depends  testGet
   */
  public function testUpdate($db_map){
  
    $db = $db_map['db'];
    $_id_list = $db_map['_id_list'];
    $table = $this->getTable();
    $time = microtime(true);
  
    foreach($_id_list as $_id){
    
      $map = array(
        '_id' => $_id,
        'bar' => $time
      );
    
      $map = $db->set($table,$map);
      $this->assertInternalType('array',$map);
      $this->assertArrayHasKey('_id',$map);
      $this->assertArrayHasKey('bar',$map);
      $this->assertEquals($time,$map['bar']);
      
      // now pull to make sure it really did get updated...
      $where_criteria = new MingoCriteria();
      $where_criteria->is_id($_id);
      $map = $db->getOne($table,$where_criteria);
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
    
    $where_criteria = new MingoCriteria();
    $where_criteria->in_id($_id_list);
    $ret_bool = $db->kill($table,$where_criteria);
    $this->assertTrue($ret_bool);
    
    $list = $db->get($table,$where_criteria);
    $this->assertEmpty($list);
    
    return $db;
  
  }//method
  
  /**
   *  load up the table with 2000 rows, and then delete them
   *   
   *  @since  12-19-10
   */
  public function testKillLots1(){
  
    $db = $this->getDb();
    ///$db->setDebug(false);
    
    $table = $this->getTable();
    $foo = 'foo';
    $timestamp = time();

    for($i = 0; $i < 2000 ;$i++)
    ///for($i = 0; $i < 201 ;$i++)
    {
      $timestamp += 1;
    
      $map = array();
      $map['foo'] = $foo;
      $map['bar'] = $timestamp;
      $map['baz'] = $timestamp;
      $db->set($table,$map);
    
    }//for */

    $where_criteria = new MingoCriteria();
    $where_criteria->isFoo($foo);

    $db->kill($table,$where_criteria);
 
    $result = $db->getOne($table,$where_criteria);
    $this->assertEmpty($result);
  
  }//method
  
  public function testKillLots2(){
  
    $db = $this->getDb();
    $table = $this->getTable();
    $foo = 'foo2';
    $timestamp = time();
  
    // now let's make sure _id deletion works also...
    $_id_list = array();
    for($i = 0; $i < 201 ;$i++)
    {
      $timestamp += 1;
    
      $map = array();
      $map['foo'] = $foo;
      $map['bar'] = $timestamp;
      $map['baz'] = $timestamp;
      $ret_map = $db->set($table,$map);
      $_id_list[] = $ret_map['_id'];
    
    }//for */
  
    $where_criteria = new MingoCriteria();
    $where_criteria->in_id($_id_list);
  
    $db->kill($table,$where_criteria);
  
    $result = $db->getOne($table,$where_criteria);
    $this->assertEmpty($result);
  
  
  
  }//method
  
  public function testKillTable(){
  
    $db = $this->getDb();
    $table = $this->getTable();
    $ret_bool = $db->killTable($table);
    $this->assertTrue($ret_bool);
    
    $table_list = $db->getTables($table);
    $this->assertNotContains($table,$table_list);
    
  }//method
  
  /**
   *  connect to the db
   */
  protected function connect($db){
  
    $name = $this->getDbName();
    $host = $this->getDbHost();
    $username = $this->getDbUsername();
    $password = $this->getDbPassword();
    $options = $this->getDbOptions();
    
    ///out::e($name,$host,$username,$password,$options);
    
    $ret_bool = $db->connect($name,$host,$username,$password,$options);
    $this->assertTrue($ret_bool);

    return $db;
  
  }//method

  protected function setTable(){
  
    $db = $this->getDb();
    $table = $this->getTable();
  
    // make sure the table doesn't exist before creating it...
    $db->killTable($table);
  
    $ret_bool = $db->setTable($table);
    $this->assertTrue($ret_bool);
    $this->assertTrue($db->hasTable($table));
  
    // make sure the table exists...
    $table_list = $db->getTables();
    $this->assertContains($table->getName(),$table_list);
  
    // make sure index exists...
    $index_list = $db->getIndexes($table);
    $this->assertGreaterThanOrEqual(count($table->getIndexes()),count($index_list));
    
  }//method
  
  /**
   *  this will be called after the class is done running tests, so override if you want
   *  to do global test specific finish work
   *  
   *  @link http://www.phpunit.de/manual/current/en/fixtures.html#fixtures.more-setup-than-teardown
   */
  public static function tearDownAfterClass(){
    self::$db = null;
  }//method

}//class
