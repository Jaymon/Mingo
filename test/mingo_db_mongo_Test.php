<?php

require_once('mingo_db_interface_Test.php');

class mingo_db_mongo_Test extends test_mingo_db_interface {

  /**
   *  @return string  the host string (something like server:port
   */
  public function getDbHost(){ return 'localhost:27017'; }//method

  public function getDbInterface(){
  
    return 'mingo_db_mongo';
  
  }//method
  
  /**
   *  test the auto-creation of the Mongo db table
   *  
   *  Mongo auto-creates tables on the fly, it is not done on an error like the SQL interfaces,
   *  so this just makes sure Mongo sets indexes and stuff correctly if they don't exist   
   *
   *  @since  2-23-11   
   */
  public function testAutoCreate(){

    $db = $this->getDb();
    $table = sprintf('%s_2',$this->getTable());
    
    $ret_bool = $db->killTable($table);
    $this->assertTrue($ret_bool);
    
    $schema = $this->getSchema();
    $this->assertFalse($db->hasTable($table));
    
    // insert something...
    
    $timestamp = time();
    $map['foo'] = $timestamp;
    $map['bar'] = $timestamp;
    $map['baz'] = $timestamp;
    $db->insert($table,$map,$schema);
    
    // test the table was created...
    $this->assertTrue($db->hasTable($table));
  
    $index_list = $db->getIndexes($table);
    
    // the "- 1" is to compensate for the schema not having the _id index... 
    $this->assertEquals(count($schema->getIndexes()),count($index_list) - 1);
  
    $ret_bool = $db->killTable($table);
    $this->assertTrue($ret_bool);
  
  }//method
  
  public function xtestCriteria()
  {
    $c = new mingo_criteria();
    $c->is_id('asdfdsfdsfsafdffdfdf');
    out::e($c->getWhere());
    
    $c = new mingo_criteria();
    $c->gt_id('asdfdsfdsfsafdffdfdf');
    out::e($c->getWhere());
  
    $c = new mingo_criteria();
    $c->in_id(array('asdfdsfdsfsafdffdfdf','asdfdsfdsfsafdffdfdf2'));
    out::e($c->getWhere());
  
  }//method
  
  
  public function xtestLog()
  {
    $db_name = $this->getDbName();
    $host = $this->getDbHost();
    $username = $this->getDbUsername();
    $password = $this->getDbPassword();
    $interface = $this->getDbInterface();
    
    $db = new $interface();
    $db->connect($db_name,$host,$username,$password);
    
    $mongo = $db->getDb();
    
    ///$col = $mongo->selectCollection(md5(microtime(true)));
    ///$obj = array( "title" => "Calvin and Hobbes", "author" => "Bill Watterson" );
    ///$col->insert($obj);
    ///out::e($mongo->listCollections('test_orm'));
    ///out::e($mongo->command(array('getCollectionName' => 'test_orm')));
    ///out::e($mongo->execute(sprintf('"" in db.getCollectionNames()','test_orm')));
    out::e($mongo->execute(sprintf('db.getCollectionNames().indexOf("%s")','test_orm')));
    out::e($mongo->execute(sprintf('db.getCollectionNames().indexOf("%s")','blah')));
    
    ///out::e($mongo->execute('test_mingo_db_interface.system.namespaces{}'));
    
    ///out::e($mongo->command(array('test_mingo_db_interface.system.namespaces' => 1)));
    
    return;
    
    
    $db = mingo_db::getInstance('MingoMongoOrm');
    $db->connect($interface,$db_name,$host,$username,$password);
    
    $log = new Log();
    $log->setDb($db);
    
    $log->load(new mingo_criteria());
    return;
    
    
    $db = new $interface();
    $db->connect($db_name,$host,$username,$password);
    
    ///$db->
    
    
    
    out::h();
  
  
  }//method

}//class
