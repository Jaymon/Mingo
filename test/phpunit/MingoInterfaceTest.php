<?php

require_once('MingoTestBase.php');

abstract class MingoInterfaceTest extends MingoTestBase {
  
  /**
   *  singleton db object
   */     
  protected static $db = null;
  
  /**
   *  holds true for each table name that it made sure the table was "set"
   *
   *  @since  5-24-11   
   *  @var  array table/boolean pairs   
   */
  protected static $table_map = array();
  
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
    ///$this->setTable($table);
    return self::$db;
  
  }//method
  
  protected function getTable($name = ''){
    
    $table = parent::getTable($name);
    $name = $table->getName();
    if(empty(self::$table_map[$name])){ $this->setTable($table); }//if
    self::$table_map[$name] = $name;
    
    return $table;
    
  }//method
  
  /**
   *  I wanted to see if it was feasible to move the serialization over to json
   *  instead of PHP's proprietary serialization format, but it doesn't look like
   *  it will be possible since php's json doesn't support any php objects and instead
   *  converts them to StdClass or arrays.
   *
   *  @link http://us2.php.net/manual/en/function.json-encode.php
   *  @link http://us2.php.net/json_decode      
   *  @since  11-2-11
   */
  public function xtestStructure(){
  
    $map = array();
    $map['foo'] = new StdClass();
    $map['foo']->one = 1;
    $map['foo']->two = 2;
    $map['foo']->three = 'this is a string';
  
    $e = new \RuntimeException('this is the message');
    
    $map['e'] = $e;
    
    $map['map'] = array('baz' => 'string','bar' => 234);
    $map['list'] = array(1,2,3,4,'string');
    
    $json = json_encode($map);
    
    $ret = json_decode($json,false);
  
    \out::e($ret);
  
  }//method
  
  /**
   *  make sure an Interface can be serialized and unserialized and work
   *      
   *  @since  10-3-11
   */
  public function testSerialize(){
  
    $db = $this->getDb();
    $table = $this->getTable();
    $_id_list = $this->addRows($db,$table,1);
    
    $sdb = serialize($db);
    $this->assertInternalType('string',$sdb);
    
    $db2 = unserialize($sdb);
    
    $where_criteria = new MingoCriteria();
    $where_criteria->in_Id($_id_list);
    
    $list = $db2->get($table,$where_criteria);
    $this->assertNotEmpty($list);
    
    foreach($list as $map){
    
      $this->assertContains($map['_id'],$_id_list);
    
    }//foreach
  
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
  
  /**
   *  test adding to a table when one of the index values is actually an array of
   *  values
   */    
  public function testIndexArrayInsert()
  {
    $db = $this->getDb();
    $table = $this->getTable();

    /*
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
    */
    
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
      $this->assertLessThanOrEqual(24,mb_strlen($map['_id']));
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
  
  /**
   *  test to make sure you can force a table to delete all rows
   *
   *  @since  9-29-11   
   */        
  public function testKillAll(){
  
    $db = $this->getDb();
    $table = $this->getTable();
    $_id_list = $this->addRows($db,$table,10);
  
    $where_criteria = new MingoCriteria();
    
    $e_thrown = false;
    try{
  
      $db->kill($table,$where_criteria,false);
      
    }catch(UnexpectedValueException $e){
    
      $e_thrown = true;
    
    }//try/catch
    
    if(!$e_thrown){
      $this->fail(
        'UnexpectedValueException was not thrown when an empty criteria was passed to unforced kill()'
      );
    }//if
    
    $db->kill($table,$where_criteria,true);
  
    $result = $db->getOne($table,$where_criteria);
    $this->assertEmpty($result);
    
  }//method
  
  public function testKillTable(){
  
    $db = $this->getDb();
    $table = new MingoTable(__FUNCTION__);
    $ret_bool = $db->killTable($table);
    $this->assertTrue($ret_bool);
    
    $this->assertFalse($db->hasTable($table));
    
    $table_list = $db->getTables($table);
    $this->assertNotContains($table,$table_list);
    
    $this->assertTrue($db->setTable($table));
    $this->assertTrue($db->hasTable($table));
    $ret_bool = $db->killTable($table);
    $this->assertTrue($ret_bool);
    $this->assertFalse($db->hasTable($table));
    
  }//method
  
  /**
   *  @since  5-24-11
   */
  public function testCriteriaIn(){
  
    $db = $this->getDb();
    $table = $this->getTable(__FUNCTION__);
    
    $_id_list = $this->addRows($db,$table,5);
    
    $where_criteria = new MingoCriteria();
    $where_criteria->inFoo(1,2);
    
    $list = $db->get($table,$where_criteria);
    $this->assertEquals(2,count($list));
    
    $this->assertSubset($list,$_id_list);
  
  }//method
  
  /**
   *  @since  5-24-11
   */
  public function testCriteriaNin(){
  
    $db = $this->getDb();
    $table = $this->getTable(__FUNCTION__);
    
    $_id_list = $this->addRows($db,$table,5);
    
    $where_criteria = new MingoCriteria();
    $where_criteria->ninFoo(1,2);
    
    $list = $db->get($table,$where_criteria);
    $this->assertEquals(3,count($list));
    
    $this->assertSubset($list,$_id_list);
  
  }//method
  
  /**
   *  make sure the interface is sorting returned results right
   *  
   *  basically, add some rows and then get them back in reverse order
   *  
   *  @since  5-24-11
   */
  public function testSort(){
  
    $db = $this->getDb();
    $table = $this->getTable(__FUNCTION__);
    $count = 20;
    
    $_id_list = $this->addRows($db,$table,$count);
    
    $where_criteria = new MingoCriteria();
    ///$where_criteria->in_id($_id_list);
    $where_criteria->descFoo();
    ///$where_criteria->ascFoo();
    
    $list = $db->get($table,$where_criteria);
    ///out::e($list);
    $this->assertEquals($count,count($list));
    $this->assertSubset($list,$_id_list);
    
    $last_val = $count;
    foreach($list as $map){
    
      $this->assertEquals($map['foo'],$last_val - 1);
      $last_val = $map['foo'];
    
    }//foreach
    
    $this->assertSubset($list,$_id_list);
  
  }//method
  
  /**
   *  this goes into a more advanced sorting problem to make sure stuff is being
   *  sorted correctly
   *  
   *  @since  9-12-11
   */
  public function testSortAdvanced(){
  
    $row_count = 20;
    $db = $this->getDb();
    $user_list = array(1,2,3);
  
    // create a more advanced table...
    $table = new MingoTable(__FUNCTION__);
    $table->setField('userId',MingoField::TYPE_INT);
    $table->setField('url',MingoField::TYPE_STR);
    
    // set defaults like the MingoOrm getTable() would...
    $table->setField(MingoOrm::_ROWID,MingoField::TYPE_INT);
    $table->setField(MingoOrm::_CREATED,MingoField::TYPE_INT);
    $table->setField(MingoOrm::_UPDATED,MingoField::TYPE_INT);
    
    $table->setIndex(MingoOrm::_CREATED);
    $table->setIndex('url','userId');
    $table->setIndex('userId', MingoOrm::_ROWID);
    $this->setTable($table);
    
    // add some rows...
    for($i = 0; $i < $row_count ;$i++){
    
      $map = array(
        'userid' => $user_list[array_rand($user_list,1)],
        'url' => sprintf('http://%s.com',md5(microtime(true)))
      );
      
      $map = $db->set($table,$map);
      $this->assertArrayHasKey('_id',$map);
      
      ///out::e($map);
    
    }//for
  
    // test sort...
    foreach($user_list as $user_id){
      
      $where_criteria = new MingoCriteria();
      $where_criteria->setUserId($user_id);
      $where_criteria->desc_RowId();
      
      ///out::i($where_criteria);
      
      $list = $db->get($table,$where_criteria);
      
      $last_row_id = null;
      foreach($list as $map){
      
        $this->assertSame($user_id,$map['userid']);
        if($last_row_id !== null){
        
          $this->assertLessThan($last_row_id,(int)$map['_rowid']);
        
        }//if/else
        
        $last_row_id = (int)$map['_rowid'];
      
      }//foreach
      
    }//foreach
  
  }//method
  
  /**
   *  assure right index is queried
   *  
   *  with 2 similar indexes using SQLite (and I assume MySQL) the interface's
   *  index table selector would mess up because it would choose the first table
   *  since the where would match and the sort was never taken into account, so a
   *  PDOException would be thrown:
   *  
   *  PDOException: SQLSTATE[HY000]: General error: 1 no such column: che
   *  
   *  this test is here to make sure that is fixed
   *  
   *  @since  9-2-11
   */
  public function testSimilarIndexes(){
  
    $db = $this->getDb();
    $table = $this->getTable(__FUNCTION__);
  
    // create 2 similar indexes...
    $table->setIndex('foo','bar');
    $table->setIndex('foo','che');
  
    // now try and query the second index...
    $where_criteria = new MingoCriteria();
    $where_criteria->isFoo(__FUNCTION__);
    $where_criteria->descChe();
    
    // no errors should be thrown...
    $list = $db->get($table,$where_criteria);
    $this->assertEmpty($list);
    
  }//method
  
  protected function assertSubset(array $list,array $_id_list){
  
    foreach($list as $map){
    
      $this->assertArrayHasKey('_id',$map);
      $this->assertContains($map['_id'],$_id_list);
    
    }//foreach
  
  }//method
  
  protected function addRows(MingoInterface $db,MingoTable $table,$count){
  
    $_id_list = array();
  
    for($i = 0; $i < $count ;$i++){
    
      $map = array(
        'foo' => $i
      );
      
      $map = $db->set($table,$map);
      
      $this->assertArrayHasKey('foo',$map);
      $this->assertArrayHasKey('_id',$map);
      $this->assertLessThanOrEqual(24,mb_strlen($map['_id']));
      $this->assertNotContains($map['_id'],$_id_list);
      $_id_list[] = (string)$map['_id'];
    
    }//for
  
    return $_id_list;
  
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

  protected function setTable(MingoTable $table){
  
    $db = $this->getDb();
  
    // make sure the table doesn't exist before creating it...
    $db->killTable($table);
  
    $ret_bool = $db->setTable($table);
    $this->assertTrue($ret_bool);
    $this->assertTrue($db->hasTable($table));
  
    // make sure the table exists...
    ///$table_list = $db->getTables();
    ///$this->assertContains($table->getName(),$table_list);
  
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
