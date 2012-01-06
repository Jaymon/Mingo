<?php
/**
 *  handle testing the MingoTable
 *  
 *  I still had a MingoSchema test even though the schema was removed like a year
 *  ago, so I've gone ahead and updated the MingoSchemaTest to this class 
 *  
 *  @version 0.1
 *  @author Jay Marcyes
 *  @since 1-5-12
 *  @package mingo 
 *  @subpackage test 
 ******************************************************************************/
require_once('MingoTestBase.php');

class MingoTableTest extends MingoTestBase {

  public function testSetIndex(){
  
    $arg_list = array();
    $arg_list[] = array(
      'in' => array('foo','bar'),
      'out' => array('foo' => '','bar' => '')
    );
    $arg_list[] = array(
      'in' => array('foo',array('bar' => -1)),
      'out' => array('foo' => '','bar' => -1)
    );
    $arg_list[] = array(
      'in' => array(array('foo' => -1),array('bar' => 1)),
      'out' => array('foo' => -1,'bar' => 1)
    );
    $arg_list[] = array(
      'in' => array(array('foo' => 1),'bar'),
      'out' => array('foo' => 1,'bar' => '')
    );
    $arg_list[] = array(
      'in' => array('foo'),
      'out' => array('foo' => '')
    );
    $arg_list[] = array(
      'in' => array(array('Foo' => -1)),
      'out' => array('foo' => -1)
    );
    $arg_list[] = array(
      'in' => array(array('foo' => '2d')),
      'out' => array('foo' => '2d')
    );
    $arg_list[] = array(
      'in' => array(array('foo' => 'some code or something else')),
      'out' => array('foo' => 'some code or something else')
    );
  
    foreach($arg_list as $key => $arg_map){
    
      $index_name = sprintf('index%s',$key);
      $table = new MingoTable('tablename');
      
      $ret = $table->setIndex($index_name,$arg_map['in']);
      $this->assertInstanceOf('MingoTable',$ret);
      
      $index = $table->getIndex($index_name)->getFields();
      $this->assertSame($arg_map['out'],$index);
    
    }//foreach
  
  }//method
  
  public function testGetIndexes(){
  
    $table = new MingoTable('tablename');
    $table->setIndex('index1',array('one','two'));
    
    $index_list = $table->getIndexes();
    $this->assertEquals(1,count($index_list));
    
    $table->setIndex('index2',array('two','three'));
    $index_list = $table->getIndexes();
    $this->assertEquals(2,count($index_list));
  
  }//method

}//class
