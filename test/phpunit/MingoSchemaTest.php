<?php

require_once('MingoTestBase_class.php');

class MingoSchemaTest extends MingoTestBase {

  public function testIndex(){
  
    $arg_list = array();
    $arg_list[] = array(
      'in' => array('foo','bar'),
      'out' => array('foo' => 1,'bar' => 1)
    );
    $arg_list[] = array(
      'in' => array('foo',array('bar' => -1)),
      'out' => array('foo' => 1,'bar' => -1)
    );
    $arg_list[] = array(
      'in' => array(array('foo' => -1),array('bar' => 1)),
      'out' => array('foo' => -1,'bar' => 1)
    );
    $arg_list[] = array(
      'in' => array(array('foo' => 1),'bar'),
      'out' => array('foo' => 1,'bar' => 1)
    );
    $arg_list[] = array(
      'in' => array('foo'),
      'out' => array('foo' => 1)
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
    
      $schema = new MingoSchema();
      $ret = call_user_func_array(array($schema,'addIndex'),$arg_map['in']);
      $this->assertInstanceOf('MingoSchema',$ret);
      
      $index = $schema->getIndexes();
      $this->assertSame($arg_map['out'],current($index));
    
    }//foreach
  
  }//method
  
  public function testRequired(){
  
    $arg_list = array();
    $arg_list[] = array(
      'in' => array('foo' => null,'bar' => null),
      'out' => array('foo' => null,'bar' => null)
    );
    $arg_list[] = array(
      'in' => array('foo' => null,'bar' => 'baz'),
      'out' => array('foo' => null,'bar' => 'baz')
    );
    $arg_list[] = array(
      'in' => array('FOO' => null),
      'out' => array('foo' => null)
    );
    $arg_list[] = array(
      'in' => array('FOO' => 1),
      'out' => array('foo' => 1)
    );
  
    foreach($arg_list as $key => $arg_map){
    
      $schema = new MingoSchema();
      
      foreach($arg_map['in'] as $name => $default_val){
      
        $f = new MingoField($name);
        $f->setDefaultVal($default_val);
        $f->setRequired(true);
        $schema->addField($f);
        
      }//foreach
      
      $fields = $schema->getRequiredFields();
      $this->assertSame($arg_map['out'],$fields);
      
    }//foreach
  
  }//method
  
  public function testGetIndexes(){
  
    $schema = new MingoSchema();
    $schema->addIndex('one','two');
    
    $index_list = $schema->getIndexes();
    $this->assertEquals(1,count($index_list));
    
    $schema->addIndex('one','two');
    $index_list = $schema->getIndexes();
    $this->assertEquals(2,count($index_list));
  
  }//method

}//class
