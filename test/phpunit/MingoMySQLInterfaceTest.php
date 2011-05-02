<?php

require_once('MingoInterfaceTest.php');

class MingoMySQLInterfaceTest extends MingoInterfaceTest {
  
  /**
   *  @return string  the database name
   */
  public function getDbName(){ return 'happy'; }//method
  
  /**
   *  @return string  the host string (something like server:port
   */
  public function getDbHost(){ return 'localhost'; }//method
  
  /**
   *  @return string  the username used to connect
   */
  public function getDbUsername(){ return 'root'; }//method
  
  /**
   *  @return string  the password used to connect
   */
  public function getDbPassword(){ return ''; }//method

  public function getDbInterface(){
  
    return 'MingoMySQLInterface';
  
  }//method
  
  /**
   *  we were having a problem with a Schema that had index [foo] and then another index
   *  [foo,bar] where when you tried to select on foo=? AND bar=? it would fail with
   *  a "bar doesn't exist" error, but I can't seem to duplicate it      
   *
   *  @since  4-18-11
   */
  public function xtestBadIndex(){
  
    $db = $this->getDb();
    $table = $this->getTable(2);
    $db->killTable($table);
    ///$schema = $this->getSchema();
    
    $schema = new mingo_schema();
    $schema->setIndex('foo');
    $schema->setIndex('bar','baz');
    $schema->setIndex('foo','baz');
    
    $db->setTable($table,$schema);
    
    $c = new mingo_criteria();
    $c->isFoo(1);
    $c->isBaz(2);
    
    try{
    
      $db->getOne($table,$schema,$c);
      $this->fail(sprintf('$table %s exists and should not exist',$table));
      
    }catch(Exception $e){}//try/catch
  
    $db->setTable($table,$schema);
  
  }//method
  
  /**
   *  a bad kill is described as a kill where the main table has the row removed, but
   *  the sub tables don't have it removed   
   *      
   *  @since  1-5-11
   */
  public function testBadKill(){
  
    $db = $this->getDb();
    $table = $this->getTable();
    $schema = $this->getSchema();
    $timestamp = time();
    
    $map = array();
    $map['foo'] = $timestamp;
    $map['bar'] = $timestamp;
    $map['baz'] = $timestamp;
    
    $ret_map = $db->set($table,$map,$schema);
    
    for($i = 0; $i < 5; $i++){
      unset($map['_id']);
      $db->set($table,$map,$schema);
    }//for
    
    $pdo = $db->getDb();
    $stmt_handler = $pdo->prepare(sprintf('DELETE FROM %s WHERE _id=?',$table));
    $ret_bool = $stmt_handler->execute(array($ret_map['_id']));
    $stmt_handler->closeCursor();
    $this->assertTrue($ret_bool);
    
    $where_criteria = new MingoCriteria();
    $where_criteria->isFoo($map['foo']);
    
    $ret_list = $db->get($table,$schema,$where_criteria);
    $this->assertEquals(5,count($ret_list));
  
  
  
  }//method
  
  protected function getSchema(){
  
    $ret_schema = parent::getSchema();
    
    // add a spatial index also...
    ///$ret_schema->setSpatial('location','bar','baz');
    
    return $ret_schema;
  
  }//method

}//class
