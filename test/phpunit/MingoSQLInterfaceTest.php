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
    $this->assertArrayHasKey('row_id',$ret_map);
    $this->assertEquals(1,$ret_map['foo']);
    
    $ret_map['foo'] = 2;
    
    $ret_map = $db->set($table,$ret_map);
    
    $this->assertArrayHasKey('_id',$ret_map);
    $this->assertArrayHasKey('row_id',$ret_map);
    $this->assertEquals(2,$ret_map['foo']);
    
  }//method

}//class
