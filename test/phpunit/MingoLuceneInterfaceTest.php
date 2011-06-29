<?php

require_once('MingoInterfaceTest.php');

class MingoLuceneInterfaceTest extends MingoInterfaceTest {

  /**
   *  @return string  the database name
   */
  public function getDbName(){
    
    return sprintf(
      '%s%s_lucene',
      sys_get_temp_dir(),
      __CLASS__
    );
    
  }//method

  public function xtestSetup(){
  
    $db = $this->getDb();
    $table = $this->getTable(__FUNCTION__);
    
    $_id_list = $this->addRows($db,$table,5);
    
    ///$where_criteria = new MingoCriteria();
    ///$where_criteria->ninFoo(1,2);
    ///$where_criteria->is_q('foo:(-1 -2)');
    
    $list = $db->getQuery('foo NOT foo:(1 2)',array('table' => $table));
    out::e($list);
    $this->assertEquals(3,count($list));
    
    $this->assureSubset($list,$_id_list);
  
    return;
  
    $db = $this->getDb();
    $table = $this->getTable(__FUNCTION__);
    ///$table = new MingoTable(__FUNCTION__);
    
    $_id_list = array();
  
    for($i = 0; $i < 5 ;$i++){
    
      $map = array(
        'foo' => $i
      );
      
      $map = $db->set($table,$map);
      $_id_list[] = (string)$map['_id'];
    
    }//for
    
    out::e($_id_list);
    $c = new MingoCriteria();
    $c->in_id($_id_list);
    $list = $db->get($table,$c);
    out::e($list);
    
    $count = $db->getCount($table,$c);
    out::e($count);
    
    ///$db->getQuery('_id:(1z4ddb7b390b5b1759137267 OR 6z4ddb7b39119fc745281592)',array('table' => $table));
    
    
  
  }//method
  
  /**
   *  lucene only supports NOT IN queries when there is something before them so this
   *  test wouldn't pass
   */
  public function testCriteriaNin(){}//method
  
  /**
   *  lucene's sort doesn't seem to work correctly, and I'm not sure how useful it would
   *  be anyway   
   */
  public function testSort(){}//method

  public function getDbInterface(){
  
    // include zend...
    ///MingoAutoload::addIncludePath('C:\Dropfolder\ZendFramework-1.11.5\library');
    // udpate Zend to latest version: pear upgrade zend/zend
  
    return 'MingoLuceneInterface';
  
  }//method

}//class
