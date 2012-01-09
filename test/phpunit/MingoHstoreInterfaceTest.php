<?php
/**
 *  handle testing of Postgres hstore interface
 *  
 *  @version 0.3
 *  @author Jay Marcyes
 *  @since 12-30-11
 *  @package mingo
 *  @subpackage test
 ******************************************************************************/
require_once('MingoInterfaceTest.php');

class MingoHstoreInterfaceTest extends MingoInterfaceTest {
  
  public function createInterface(){ return new MingoHstoreInterface(); }//method
  
  public function createConfig(){
  
    $config = new MingoConfig(
      'vagrant',
      'localhost:5432',
      'vagrant',
      'vagrant',
      array()
    );
    
    $config->setDebug(true);
    
    return $config;
  
  }//method
  
  /**
   *  we override parent to test sorting on _rowid, because Postgres htable will
   *  only sort correctly with integers on __rowid, everything else will fail   
   *  
   *  a query like:
   *  
   *  SELECT * FROM testsort ORDER BY CASE WHEN body -> 'foo' < 'A' THEN lpad(body -> 'foo',255, '0') ELSE body -> 'foo' END;
   *  
   *  will make it sort correctly, but I just can't help but think that will be massively
   *  slow with larger datasets and it looks seriously ugly also, via:
   *  http://stackoverflow.com/questions/4080787/                  
   *      
   *  @since  5-24-11
   */
  public function testSort(){
  
    $db = $this->getDb();
    $table = $this->getTable(__FUNCTION__);
    $count = 20;
    $_id_list = $this->insert($table,$count);
    
    $where_criteria = new MingoCriteria();
    $where_criteria->desc_rowid();
    
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
   *  Postgres hstore doesn't sort on integers well (it uses string natural sort, so
   *  if you had 1,10,2,20 and you sorted them desc, they would sort as: 20,2,10,1), because
   *  of that, we don't test the sorting         
   */
  public function testSortAdvanced(){ return; }//method
  
}//class
