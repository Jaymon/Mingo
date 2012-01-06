<?php

/**
 *  holds an index
 *
 *  @version 0.1
 *  @author Jay Marcyes
 *  @since 1-5-12
 *  @package mingo 
 ******************************************************************************/
class MingoIndex extends MingoMagic implements Countable {
  
  const TYPE_DEFAULT = 0;
  const TYPE_ASC = 1;
  const TYPE_DESC = -1;
  const TYPE_SPATIAL = '2d';
  
  /**
   *  the name of the index
   *
   *  @var  string   
   */        
  protected $name = '';
  
  /**
   *  the fields of the index (in order from left to right)
   *  
   *  @var  array      
   */
  protected $fields = array();
  
  /**
   *  the type of the index
   *  
   *  @var  integer      
   */
  protected $type = self::TYPE_DEFAULT;
  
  /**
   *  used to store index specific options
   *  
   *  this is here because I can't anticipate all the different ways an interface
   *  might want to define indexes and this lets interface specific definitions
   *  sneak through without too much code change            
   *
   *  @var  array
   */
  protected $option_map = array();

  /**
   *  create an index instance
   *
   *  @param  string  $name the name of the index
   *  @param  array $fields the fields of the index
   *  @param  integer $type an index type         
   */
  public function __construct($name,array $fields,$type = self::TYPE_DEFAULT){
  
    $this->name = $this->normalizeName($name);
    $this->fields = $this->normalizeFields($fields);
    $this->type = $type;
    
  }//method
  
  public function __toString(){
  
    return sprintf('%s (%s)',$this->getName(),join(', ',$this->getFieldNames()));
    
  }//method
  
  public function count(){ return count($this->fields); }//method
  
  public function getType(){ return $this->type; }//method
  
  /**
   *  true if the field is the passed in $type
   *
   *  @return boolean   
   */
  public function isType($type){
    
    return ($this->type === $type);
  
  }//method
  
  public function getName(){ return $this->name; }//method
  
  /**
   *  true if the index contains field $name
   *
   *  @since  1-5-12
   *  @param  string  $name
   *  @return boolean         
   */
  public function hasField($name){
  
    // canary
    if(empty($name)){ throw new InvalidArgumentException('$name was empty'); }//if
  
    $field_name = $this->normalizeName($name);
  
    $fields = $this->getFields();
    return isset($fields[$field_name]);
  
  }//method
  
  /**
   *  return all the fields in the index
   *  
   *  @return array
   */
  public function getFields(){ return $this->fields; }//method
  
  /**
   *  return all the field names in this index
   *  
   *  @return array a list of field names
   */
  public function getFieldNames(){ return array_keys($this->fields); }//method
  
  /**
   *  return all the options
   *  
   *  @return array
   */
  public function getOptions(){ return $this->option_map; }//method
  
  /**
   *  get an option field, or $default_val if the option field isn't set
   *  
   *  @param  string  $name the option field name   
   *  @return mixed
   */
  public function getOption($name,$default_val = null){
    return isset($this->option_map[$name]) ? $this->option_map[$name] : $default_val;
  }//method
  
  /**
   *  set an option field
   *   
   *  @param  string  $name the field name
   *  @param  mixed $val  the value the $name should be set to      
   */
  public function setOption($name,$val){
  
    // canary...
    if(empty($name)){ throw new InvalidArgumentException('$name cannot be empty'); }//if
    $this->option_map[$name] = $val;
    
  }//method
  
  /**
   *  normalizes all the index fields
   *  
   *  this converts an array like array('field1','field2',...) into something like
   *  array('field1' => ...,'field2' => ...) which is what Mingo understands
   *      
   *  @param  array $fields these are the fields that will be in the index
   *  @return array an array with field name keys and type values
   */
  protected function normalizeFields(array $fields){
  
    $index_map = array();
    
    foreach($fields as $field){
    
      if(is_array($field)){
      
        foreach($field as $field_name => $type){
        
          $field_name = $this->normalizeName($field_name);
          $index_map[$field_name] = $type;
        
        }//foreach
      
      }else{
      
        $field = $this->normalizeName($field);
        $index_map[$field] = '';
      
      }//if/else
    
    }//foreach
  
    return $index_map;
  
  }//method
  
}//class     
