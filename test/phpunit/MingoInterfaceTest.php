<?php
/**
 *  handle common testing of an interface
 *  
 *  when writing a new interface, the tests are actually in a good order to write
 *  the interface (ie, start with connecting, move to creating the table, then to
 *  removing the table, then to inserting, etc.) so you can just use the --filter
 *  command line param to do each test while you're writing the interface. Just extend
 *  this class with an interface specific test class, then define the abstract methods
 *  and you should be good to go
 *  
 *  @version 0.8
 *  @author Jay Marcyes
 *  @since 3-19-10
 *  @package mingo 
 *  @subpackage test 
 ******************************************************************************/
require_once('MingoTestBase.php');

abstract class MingoInterfaceTest extends MingoTestBase {
  
  /**
   *  singleton db object
   */     
  protected static $db = null;
  
  /**
   *  get a new MingoInterface instance
   *  
   *  @since  12-31-11      
   *  @return \MingoInterface
   */
  abstract public function createInterface();
  
  /**
   *  get a new MingoConfig instance
   *  
   *  @since  12-31-11      
   *  @return \MingoConfig
   */
  abstract public function createConfig();
  
  /**
   *  make sure the interface can connect
   *
   *  @since  12-31-11
   */
  public function testConnect(){
  
    $this->getDb();
  
  }//method
  
  /**
   *  make sure the interface can create a table
   *
   *  in order for this to work, MingoInterface killTable(), hasTable(), setTable(),
   *  setIndex(), and getIndex() must be implemented
   *      
   *  @since  1-3-12
   */
  public function testSetTable(){
  
    $table = $this->getTable(__FUNCTION__);
  
  }//method
  
  /**
   *  make sure you can get tables
   *
   *  @since  1-12-12   
   */
  public function testGetTables(){
  
    $t1_name = sprintf('%s1',__FUNCTION__);
    $t1 = $this->getTable($t1_name);
  
    $t2_name = sprintf('%s2',__FUNCTION__);
    $t2 = $this->getTable($t2_name);
  
    $db = $this->getDb();
    $table_list = $db->getTables();
    $this->assertGreaterThanOrEqual(2,count($table_list));
  
    $table_list = $db->getTables($t1);
    $this->assertCount(1,$table_list);
  
    $table_list = $db->getTables($t2);
    $this->assertCount(1,$table_list);
  
  }//method
  
  /**
   *  make sure interface can remove tables
   *  
   *  pretty much needs all the same interface methods as {@link testSetTable()} does
   */
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
   *  make sure interface can add rows to a table
   *
   *  MingoInterface set() needs to be implemented   
   */
  public function testInsert(){
  
    $table = $this->getTable(__FUNCTION__);
    $this->insert($table,1);
  
  }//method
  
  /**
   *  make sure getting one value works
   *  
   *  MingoInterface get() needs to be implemented
   *      
   *  @since  3-3-11
   */
  public function testGetOne(){

    $db = $this->getDb();
    
    // insert 2 records, so we can make sure we only fetch one
    $table = $this->getTable(__FUNCTION__);
    $_id_list = $this->insert($table,2);

    foreach($_id_list as $_id){

      $where_criteria = new MingoCriteria();
      $where_criteria->is_id((string)$_id);
      $map = $db->getOne($table,$where_criteria);
      
      $this->assertArrayHasKey('_id',$map);
        
      // make sure this was an id we wanted...
      $map_id = (string)$map['_id'];
      $this->assertContains($map_id,$_id_list);
    
    }//foreach
  
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
    $table = $this->getTable(__FUNCTION__);
    $_id_list = $this->insert($table,6);
    
    $limit = 5;
    $where_criteria = new MingoCriteria();
    $where_criteria->setBounds($limit,0);
    
    $list = $db->get($table,$where_criteria);
    $this->assertInternalType('array',$list);
    $this->assertLessThanOrEqual($limit,count($list));
  
  }//method
  
  /**
   *  test counting
   *  
   *  requires getCount() to be implemented
   *  
   *  @since  1-4-12
   */
  public function testGetCount(){
  
    $limit = 20;
    $db = $this->getDb();
    $table = $this->getTable(__FUNCTION__);
    $_id_list = $this->insert($table,$limit);
    
    $where_criteria = new MingoCriteria();
    $where_criteria->in_id($_id_list);
    
    $total = $db->getCount($table,$where_criteria);
    $this->assertEquals($limit,$total);
  
  }//method
  
  /**
   *  test get() with a criteria
   *  
   *  requires get() to be implemented      
   */
  public function testGet(){
  
    $limit = 10;
    $db = $this->getDb();
    $table = $this->getTable(__FUNCTION__);
    $_id_list = $this->insert($table,$limit);
    
    $where_criteria = new MingoCriteria();
    $where_criteria->in_id($_id_list);
    $where_criteria->setLimit($limit);
    
    $_id_seen_list = array();
    
    for($offset = 0,$max = count($_id_list); $offset < $max ;$offset += $limit){
      
      $where_criteria->setOffset($offset);
      $list = $db->get($table,$where_criteria);
      $this->assertInternalType('array',$list);
      $this->assertEquals($limit,count($list));
      
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
    
    // make sure counts and results are right when we have mutltiple ids that are the same...
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
  
  }//method
  
  /**
   *  test updating maps
   *  
   *  requires update() and getOne() to be implemented 
   */
  public function testUpdate(){
  
    $limit = 10;
    $db = $this->getDb();
    $table = $this->getTable(__FUNCTION__);
    $_id_list = $this->insert($table,$limit);
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
      
      // now pull from db to make sure it really did get updated...
      $where_criteria = new MingoCriteria();
      $where_criteria->is_id($_id);
      $map = $db->getOne($table,$where_criteria);
      $this->assertInternalType('array',$map);
      $this->assertArrayHasKey('_id',$map);
      $this->assertArrayHasKey('bar',$map);
      // for some reason, casting the 'bar' to a double didn't work to make the types match
      $this->assertEquals((string)$time,(string)$map['bar']);
    
    }//foreach
    
  }//method
  
  /**
   *  test removing from the db
   *  
   *  requires kill() and get() to be implemented      
   */
  public function testKill(){
  
    $limit = 10;
    $db = $this->getDb();
    $table = $this->getTable(__FUNCTION__);
    $_id_list = $this->insert($table,$limit);
  
    $where_criteria = new MingoCriteria();
    $where_criteria->in_id($_id_list);
    
    $ret_bool = $db->kill($table,$where_criteria);
    $this->assertTrue($ret_bool);
    
    $list = $db->get($table,$where_criteria);
    $this->assertEmpty($list);
    
    return $db;
  
  }//method
  
  /**
   *  make sure you can do an arbitrary where criteria and have it delete all the rows
   *   
   *  @since  1-11-12
   */        
  public function testKillCriteria(){
  
    $db = $this->getDb();
    
    $table = $this->getTable();
    $timestamp = time();
    $foo_list = array();

    foreach(array(1,2) as $foo){
  
      $foo_list[$foo] = array();
  
      for($i = 0; $i < 10 ;$i++){
      
        $timestamp += 1;
      
        $map = array();
        $map['foo'] = $foo;
        $map['bar'] = $timestamp;
        $map = $db->set($table,$map);

        $foo_list[$foo][] = $map['_id'];
      
      }//for
      
    }//foreach

    foreach($foo_list as $foo => $_id_list){
      
      // kill all the rows with the given foo...
      $where_criteria = new MingoCriteria();
      $where_criteria->isFoo($foo);

      $list = $db->get($table, $where_criteria);
      
      $ret_bool = $db->kill($table,$where_criteria);
      $this->assertTrue($ret_bool);
    
      // see if there are any foo=1 rows left (hint, there shouldn't be)
      $list = $db->get($table,$where_criteria);
      $this->assertEmpty($list);
      
      // now make sure they are gone via _id check
      $where_criteria = new MingoCriteria();
      $where_criteria->in_id($_id_list);
      $list = $db->get($table,$where_criteria);
      $this->assertEmpty($list);
      
    }//foreach
  
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
    $foo = 43;
    $timestamp = time();

    for($i = 0; $i < 2000 ;$i++){
    
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
  
  /**
   *  make sure removing lots of rows using just the _ids works   
   */        
  public function testKillLots2(){
  
    $db = $this->getDb();
    $table = $this->getTable();
    $foo = 44;
    $timestamp = time();
  
    // now let's make sure _id deletion works also...
    $_id_list = array();
    for($i = 0; $i < 201 ;$i++){
    
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
   *  by default, Mingo won't let you just delete all the rows from a table, this is
   *  to protect from a developer passing in a bad criteria that they didn't mean to
   *  pass, you have to pass in true as the third param to have kill() remove all 
   *  the tables         
   *      
   *  @since  9-29-11   
   */
  public function testKillAll(){
  
    $db = $this->getDb();
    $table = $this->getTable();
    $_id_list = $this->insert($table,10);
  
    $where_criteria = new MingoCriteria();
    
    $e_thrown = false;
    try{
  
      $db->kill($table,$where_criteria,false);
      
    }catch(UnexpectedValueException $e){
    
      $e_thrown = true;
    
    }//try/catch
    
    $this->assertTrue(
      $e_thrown,
      'UnexpectedValueException was not thrown when an empty criteria was passed to unforced kill()'
    );
    
    $db->kill($table,$where_criteria,true);
  
    $result = $db->getOne($table,$where_criteria);
    $this->assertEmpty($result);
    
  }//method
  
  /**
   *  test the ability of the interface to autocreate the table
   *
   *  @since  5-9-11   
   */        
  public function testAutoCreateTable(){
  
    $db = $this->getDb();
    $table = parent::getTable(__FUNCTION__);
  
    // get rid of the table
    $db->killTable($table);
  
    $ret_bool = $db->hasTable($table);
    $this->assertFalse($ret_bool);
    
    $where_criteria = new MingoCriteria();
    $where_criteria->isFoo(1);
    
    $db->get($table,$where_criteria);
    
    $this->assertTrue($db->hasTable($table));
  
  }//method
  
  /**
   *  @since  5-24-11
   */
  public function testCriteriaIn(){
  
    $db = $this->getDb();
    $table = $this->getTable(__FUNCTION__);
    $_id_list = $this->insert($table,5);
    
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
    $_id_list = $this->insert($table,5);
    
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
    $_id_list = $this->insert($table,$count);
    
    $where_criteria = new MingoCriteria();
    $where_criteria->descFoo();
    
    $list = $db->get($table,$where_criteria);
    $this->assertEquals($count,count($list));
    $this->assertSubset($list,$_id_list);
    
    $last_val = $count;
    foreach($list as $map){
    
      $this->assertEquals($map['foo'],$last_val);
      $last_val--;
    
    }//foreach
  
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
    $table->setField(MingoOrm::_CREATED,MingoField::TYPE_INT);
    $table->setField(MingoOrm::_UPDATED,MingoField::TYPE_INT);
    
    ///$table->setIndex('created_index',array(MingoOrm::_CREATED));
    $table->setIndex('url_and_user',array('url','userId'));
    $table->setIndex('user_and_created',array('userId',MingoOrm::_CREATED));
    
    $this->setTable($table);
    
    // add some rows...
    for($i = 0; $i < $row_count ;$i++){
    
      $map = array(
        'userid' => $user_list[array_rand($user_list,1)],
        'url' => sprintf('http://%s.com',md5(microtime(true)))
      );
      
      $map = $db->set($table,$map);
      $this->assertArrayHasKey('_id',$map);

    }//for
  
    // test sort...
    foreach($user_list as $user_id){
      
      $where_criteria = new MingoCriteria();
      $where_criteria->setUserId($user_id);
      $where_criteria->desc_created();

      $list = $db->get($table,$where_criteria);
      
      $last_created = time() + 500;
      foreach($list as $map){
      
        $this->assertSame($user_id,$map['userid']);
        $this->assertLessThanOrEqual($last_created,(int)$map['_created']);
        $last_created = (int)$map['_created'];
      
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
    
    // create a more advanced table...
    $table = new MingoTable(__FUNCTION__);
    $table->setField('foo',MingoField::TYPE_STR);
    $table->setField('bar',MingoField::TYPE_STR);
    $table->setField('che',MingoField::TYPE_STR);
    
    // create 2 similar indexes...
    $table->setIndex('foo_and_bar',array('foo','bar'));
    $table->setIndex('foo_and_che',array('foo','che'));
  
    // make sure the table exists in the db
    $this->setTable($table);
  
    // now try and query the second index...
    $where_criteria = new MingoCriteria();
    $where_criteria->isFoo(__FUNCTION__);
    $where_criteria->descChe();
    
    // no errors should be thrown...
    $list = $db->get($table,$where_criteria);
    $this->assertEmpty($list);
    
  }//method

  /**
   *  make sure required fields work as expected
   *  
   *  @since  12-9-11
   */
  public function testRequiredField(){
  
    $db = $this->getDb();
    $table = $this->getTable(__FUNCTION__);
    $foo = $table->getField('foo');
    $foo->setRequired(true);
  
    $foo = $table->getField('foo');
    $this->assertTrue($foo->isRequired());
    
    $map = array('baz' => 'string','bar' => 234);
    
    $foo->setDefaultVal(1234);
    $rmap = $db->set($table,$map);
    $this->assertEquals(1234,$rmap['foo']);
    
    $this->setExpectedException('DomainException');
    $foo->setDefaultVal(null);
    $db->set($table,$map);
  
  }//method
  
  /**
   *  make sure unique fields work as expected
   *  
   *  @since  12-9-11
   */
  public function testUniqueField(){
  
    $db = $this->getDb();
    $table = $this->getTable();
    $foo = $table->getField('foo');
    $foo->setUnique(true);
  
    $foo = $table->getField('foo');
    $this->assertTrue($foo->isUnique());
    
    $timestamp = time() * -1;
    $map = array('foo' => $timestamp,'baz' => 'string','bar' => 234);
    
    $rmap = $db->set($table,$map);
    $this->assertEquals($timestamp,$rmap['foo']);
    
    $this->setExpectedException('DomainException');
    $db->set($table,$map);
    
  }//method
  
  /**
   *  make sure an Interface can be serialized and unserialized and work
   *      
   *  @since  10-3-11
   */
  public function testSerialize(){
  
    $db = $this->getDb();
    $table = $this->getTable(__FUNCTION__);
    $_id_list = $this->insert($table,1);
    
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
   *  returns a singleton connected \MingoInterface instance
   *  
   *  @return \MingoInterface
   */
  public function getDb(){
  
    // canary...
    if(self::$db !== null){ return self::$db; }//if
  
    self::$db = $this->createInterface();
    self::$db = $this->connect(self::$db);
    
    return self::$db;
  
  }//method
  
  /**
   *  get a table and make sure it also exists in the db
   *  
   *  @return MingoTable
   */
  protected function getTable($name = ''){
    
    $table = $this->createTable($name);
    $db = $this->getDb();
    
    $db->setTable($table);
    $this->assertTable($table);
    
    return $table;
    
  }//method
  
  /**
   *  get a fresh table that hasn't been created in the db yet
   *
   *  this will remove the table from the db to make sure it really isn't in the db
   *      
   *  @since  1-3-12
   *  @return MingoTable
   */
  protected function createTable($name = ''){
    
    $db = $this->getDb();
    $table = parent::getTable($name);
    
    // get rid of the table
    $db->killTable($table);
  
    $ret_bool = $db->hasTable($table);
    $this->assertFalse($ret_bool);
    
    return $table;
    
  }//method
  
  /**
   *  @since  1-10-12
   */
  protected function setTable(MingoTable $table){
  
    $db = $this->getDb();
  
    // get rid of the table and then re-add it
    $db->killTable($table);
    $db->setTable($table);
    $this->assertTable($table);
    
    return $table;
  
  }//method
  
  /**
   *  connect to the db
   *  
   *  @param  \MingoInterface $db
   *  @return \MingoInterface         
   */
  protected function connect(MingoInterface $db){
    
    $config = $this->createConfig();
    $ret_bool = $db->connect($config);
    $this->assertTrue($ret_bool);

    return $db;
  
  }//method
  
  /**
   *  insert $count rows into the db $table
   *
   *  @since  1-4-12
   *  @return array a list of _ids   
   */
  public function insert(MingoTable $table,$count){
  
    $db = $this->getDb();
    $_id_list = array();
  
    for($i = 1; $i <= $count ;$i++){
    
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
   *  make sure the table exists and its structure in the db matches its MingoTable structure
   *
   *  @since  1-3-12  a variation of this method was called setTable()    
   */
  protected function assertTable(MingoTable $table){
  
    $db = $this->getDb();
  
    $this->assertTrue($db->hasTable($table));
  
    // make sure index exists...
    $index_list = $db->getIndexes($table);
    
    $this->assertGreaterThanOrEqual(count($table->getIndexes()),count($index_list));
    
    return true;
  
  }//method
  
  /**
   *  make sure that all the maps in $list have _ids that are in $_id_list
   */
  protected function assertSubset(array $list,array $_id_list){
  
    foreach($list as $map){
    
      $this->assertArrayHasKey('_id',$map);
      $this->assertContains($map['_id'],$_id_list);
    
    }//foreach
  
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
