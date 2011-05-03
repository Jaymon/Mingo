<?php

require_once('MingoInterfaceTest.php');

class MingoMongoInterfaceTest extends MingoInterfaceTest {

  /**
   *  @return string  the host string (something like server:port
   */
  public function getDbHost(){ return 'localhost:27017'; }//method

  public function getDbInterface(){
  
    return 'MingoMongoInterface';
  
  }//method
  
  /**
   *  this test currently fails, but it should pass
   *  
   *  the problem is types, setting foo=1 then trying to select foo='1' should work
   *  but doesn't, so you have to be really aware of the type you have and this particular
   *  bug has bit us a couple times so far. I'm just not sure the most elegant way to fix
   *  it yet   
   *  
   *  @since  4-18-11
   */
  public function xtestType(){
  
    $db = $this->getDb();
    $table = $this->getTable(2);
    $db->killTable($table);
    
    $schema = new MingoSchema();
    $schema->setIndex('foo');
    
    $f = new MingoField('foo',MingoField::TYPE_INT);
    $schema->setField($f);
    
    $map = array(
      'foo' => 1
    );
    
    $db->set($table,$map,$schema);
    
    $c = new MingoCriteria();
    $c->isField('foo','1');
    
    $ret_map = $db->getOne($table,$schema,$c);

    $this->assertArrayHasKey('foo',$ret_map);
    $this->assertEquals(1,$ret_map['foo']);
  
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
    $schema = $this->getSchema();
    
    $ret_bool = $db->killTable($table,$schema);
    $this->assertTrue($ret_bool);
    
    $this->assertFalse($db->hasTable($table));
    
    // insert something...
    
    $timestamp = time();
    $map['foo'] = $timestamp;
    $map['bar'] = $timestamp;
    $map['baz'] = $timestamp;
    $db->set($table,$map,$schema);
    
    ///out::e($db->getIndexes($table));
    
    // test the table was created...
    $this->assertTrue($db->hasTable($table));
  
    ///out::e($db->getIndexes($table));
  
    $index_list = $db->getIndexes($table);
    
    // the "- 1" is to compensate for the schema not having the _id index... 
    $this->assertEquals(count($schema->getIndexes()),count($index_list) - 1);
  
    $ret_bool = $db->killTable($table);
    $this->assertTrue($ret_bool);
  
  }//method

}//class
