<?php

require('mingo_test_class.php');

class test_mingo_schema extends mingo_test {

  public function testIndex(){
  
    $arg_list = array(
      0 => array('foo','bar'),
      1 => array('foo',array('bar' => -1)),
      2 => array(array('foo' => -1),array('bar' => 1)),
      3 => array(array('foo' => 1),'bar'),
      4 => array('foo'),
      5 => array(array('foo' => -1))
    );
    
    $expected_list = array(
      0 => array('foo' => 1,'bar' => 1),
      1 => array('foo' => 1,'bar' => -1),
      2 => array('foo' => -1,'bar' => 1),
      3 => array('foo' => 1,'bar' => 1),
      4 => array('foo' => 1),
      5 => array('foo' => -1)
    );
  
    foreach($arg_list as $key => $args){
    
      $schema = new mingo_schema('table');
      $ret_bool = call_user_func_array(array($schema,'setIndex'),$args);
      $this->assertTrue($ret_bool);
      
      $index = $schema->getIndex();
      $this->assertSame($expected_list[$key],current($index));
    
    }//foreach
  
  }//method
  
  public function testRequired(){
  
    $arg_list = array(
      0 => array('foo' => null,'bar' => null),
      1 => array('foo' => null,'bar' => 'baz'),
      2 => array('FOO' => null),
      3 => array('FOO' => 1)
    );
    
    $expected_list = array(
      0 => array('foo' => null,'bar' => null),
      1 => array('foo' => null,'bar' => 'baz'),
      2 => array('foo' => null),
      3 => array('foo' => 1)
    );
  
    foreach($arg_list as $key => $args){
    
      $schema = new mingo_schema('table');
      
      foreach($args as $name => $default_val){
        $schema->requireField($name,$default_val);
      }//foreach
      
      $fields = $schema->getRequiredFields();
      $this->assertSame($expected_list[$key],$fields);
      
    }//foreach
  
  }//method
  
  public function testSpatial(){
  
    $arg_list = array(
      'Foo',
      'bar',
      'Baz'
    );
    
    $expected_list = array(
      'foo',
      'bar',
      'baz'
    );
  
    foreach($arg_list as $key => $name){
    
      $schema = new mingo_schema('table');
      $schema->setSpatial($name);
      $this->assertSame($expected_list[$key],$schema->getSpatial());
      
    }//foreach
    
    $schema = new mingo_schema('table');
    
    try{
    
      $schema->setSpatial(mingo_orm::_ID);
      $this->fail('tried setting an invalid field and an exception was not thrown');
      
    }catch(exception $e){}//try/catch
    
  }//method


}//class
