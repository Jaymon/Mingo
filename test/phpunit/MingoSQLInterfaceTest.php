<?php

require_once('MingoInterfaceTest.php');

abstract class MingoSQLInterfaceTest extends MingoInterfaceTest {
  
  /**
   *  make sure row_id persists in an update
   *  
   *  sadly, this bug has been around forever and I just caught it, shows how much
   *  I actually used the row id         
   *  
   *  @since  9-23-11
   */
  public function testRowId(){
  
    $db = $this->getDb();
    $table = $this->getTable();
    
    $map = array();
    $map['foo'] = 1;
    
    $ret_map = $db->set($table,$map);
    
    $this->assertArrayHasKey('_id',$ret_map);
    $this->assertArrayHasKey('_rowid',$ret_map);
    $this->assertEquals(1,$ret_map['foo']);
    
    $ret_map['foo'] = 2;
    
    $ret_map = $db->set($table,$ret_map);
    
    $this->assertArrayHasKey('_id',$ret_map);
    $this->assertArrayHasKey('_rowid',$ret_map);
    $this->assertEquals(2,$ret_map['foo']);
    
  }//method
  
  /**
   *  a bad kill is described as a kill where the main table has the row removed, but
   *  the sub tables don't have it removed   
   *  
   *  this was previously just in the MySQLInterface test
   *      
   *  @since  1-5-11
   */
  public function testBadKill(){
  
    $db = $this->getDb();
    $table = $this->getTable();
    $timestamp = time();
    
    $map = array();
    $map['foo'] = $timestamp;
    $map['bar'] = $timestamp;
    $map['baz'] = $timestamp;
    
    $ret_map = $db->set($table,$map);
    
    for($i = 0; $i < 5; $i++){
      unset($map['_id']);
      $db->set($table,$map);
    }//for
    
    $pdo = $db->getDb();
    $stmt_handler = $pdo->prepare(sprintf('DELETE FROM %s WHERE _id=?',$table));
    $ret_bool = $stmt_handler->execute(array($ret_map['_id']));
    $stmt_handler->closeCursor();
    $this->assertTrue($ret_bool);
    
    $where_criteria = new MingoCriteria();
    $where_criteria->isFoo($map['foo']);
    
    $ret_list = $db->get($table,$where_criteria);
    $this->assertEquals(5,count($ret_list));
  
  }//method

}//class
