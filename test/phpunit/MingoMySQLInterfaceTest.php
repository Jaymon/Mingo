<?php

require_once('MingoSQLInterfaceTest.php');

class MingoMySQLInterfaceTest extends MingoSQLInterfaceTest {
  
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
    $table = $this->getTable(sprintf('%s_%s_2',__CLASS__,__FUNCTION__));
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

}//class
