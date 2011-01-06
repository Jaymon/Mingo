<?php

require_once('mingo_db_interface_Test.php');

class mingo_db_mysql_Test extends test_mingo_db_interface {
  
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
  
    return 'mingo_db_mysql';
  
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
    
    $ret_map = $db->insert($table,$map,$schema);
    
    for($i = 0; $i < 5; $i++){
      $db->insert($table,$map,$schema);
    }//for
    
    $pdo = $db->getDb();
    $stmt_handler = $pdo->prepare(sprintf('DELETE FROM %s WHERE _id=?',$table));
    $ret_bool = $stmt_handler->execute(array($ret_map['_id']));
    $stmt_handler->closeCursor();
    $this->assertTrue($ret_bool);
    
    $where_criteria = new mingo_criteria();
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
