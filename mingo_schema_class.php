<?php

/**
 *  allows you to define some stuff about how a mingo_orm should be set up (eg, indexes
 *  and the like)  
 *
 *  @version 0.3
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 11-18-09
 *  @package mingo 
 ******************************************************************************/
class mingo_schema extends mingo_base {

  const INDEX_ASC = 1;
  const INDEX_DESC = -1;
  const INDEX_SPATIAL = '2d';
  
  const FIELD_DEFAULT = 0;
  const FIELD_INT = 1;
  const FIELD_STR = 2;
  const FIELD_POINT = 3;
  const FIELD_LIST = 4;
  const FIELD_MAP = 5;
  const FIELD_OBJ = 6;
  const FIELD_BOOL = 7;

  /**
   *  hold the table name this schema represents
   *  @var  string
   */
  protected $table = '';

  /**
   *  hold all the indexes
   *  @var  array
   */
  protected $index_map = array();
  
  /**
   *  hold a spatial index field name if defined
   *  
   *  @since  10-15-10   
   *  @var  array
   */
  protected $spatial_index = array();
  
  /**
   *  handle the type hints
   *  
   *  uses the FIELD_* constants to allow type hints
   *      
   *  @see  setType()   
   *  @since  10-19-10   
   *  @var  array
   */        
  protected $field_map = array();
  
  /**
   *  used to set fields that should be there when the db is set
   *     
   *  @see  requireField()
   *  @var  array()   
   */        
  protected $required_map = array();

  public function __construct($table){
  
    $this->table = $table;
  
  }//method

  /**
   *  get all the indexes (of any type) this schema contains
   *
   *  @since  10-15-10   
   *  @return array   
   */
  public function getIndexes(){
  
    $ret_list = array();
    if($this->hasSpatial()){
      $ret_list[] = $this->getSpatial();
    }//if
    
    if($this->hasIndex()){
    
      $ret_list = array_merge($ret_list,$this->getIndex());
    
    }//if
  
    return $ret_list;
    
  }//method

  /**
   *  true if this schema has indexes of any type
   *  
   *  @since  10-15-10   
   *  @return boolean
   */
  public function hasIndexes(){
    return $this->hasIndex() || $this->hasSpatial();
  }//method

  /**
   *  set an index on the table this schema represents
   *
   *  @param  string|array  $field,...
   *                          1 - pass in a bunch of strings, each one representing
   *                              a field name: setIndex('field_1','field_2',...);
   *                          2 - pass in an array with this structure: array('field_name' => direction,...)
   *                              where the field's name is the key and the value is usually a direction
   *                              of either 1 (ASC) or -1 (DESC), or some other direction that the chosen 
   *                              interface can accept   
   *  @return boolean   
   */
  public function setIndex(){
  
    $args = func_get_args();
    
    // canary...
    if(empty($args)){ throw new InvalidArgumentException('no fields specified for the index'); }//if
    
    $field_list = array();
    $index_map = $this->normalizeIndex($args);
    
    // canary...
    if(isset($index_map[mingo_orm::_ID])){
      throw new UnexpectedValueException(
        sprintf('a table index cannot include the %s field',mingo_orm::_ID)
      );
    }//if
    
    $this->index_map[] = $index_map;
    
    return true;
  
  }//method
  
  /**
   *  return the index map
   *     
   *  @return array
   */
  public function getIndex(){ return $this->index_map; }//method
  public function hasIndex(){ return !empty($this->index_map); }//method
  
  /**
   *  set a spatial index for this schema, this has the same argument list as {@link setIndex()}
   *  except the first argument has to be a field name (string) that will be the point   
   *
   *  @see  setIndex()      
   */
  public function setSpatial(){
  
    $args = func_get_args();
  
    // canary...
    if(empty($args)){ throw new InvalidArgumentException('no fields specified for the index'); }//if
    if($this->hasSpatial()){
      throw new OverflowException('only one spatial index per table is allowed');
    }//if
    if(!is_string($args[0])){
      throw new UnexpectedValueException('the first arg for a spatial index must be a field name (string)');
    }//if
    
    $args[0] = $this->normalizeField($args[0]);
  
    // canary, certain field names are reserved...
    $invalid_field_list = array(
      mingo_orm::_ID,
      mingo_orm::ROW_ID,
      mingo_orm::CREATED,
      mingo_orm::UPDATED
    );
    
    foreach($invalid_field_list as $invalid_name){
    
      if($args[0] === $invalid_name){
      
        throw new UnexpectedValueException(
          sprintf('a spatial index cannot be set on the %s field',$invalid_name)
        );
      
      }//if
      
    }//foreach
    
    $args[0] = array($args[0] => self::INDEX_SPATIAL);
    $this->spatial_index = $this->normalizeIndex($args);
    return true;
  
  }//method
  
  public function getSpatial(){ return $this->spatial_index; }//method
  public function hasSpatial(){ return !empty($this->spatial_index); }//method
  
  public function setField(mingo_field $field){
  
    // canary...
    if(!$field->hasName()){
      throw new InvalidArgumentException('$field must have a name set');
    }//if
  
    $name = $field->getNameAsString();
  
    if($field->isRequired()){
      $this->requireField($name,$field->getDefaultVal());
    }//if
  
    $this->field_map[$name] = $field;
    
  }//method
  
  /**
   *  get a field instance for the given name
   *  
   *  this will return any previously set field instance if it exists, if it doesn't
   *  then it will create a new one so it guarrantees to always return an mingo_field
   *  instance         
   *
   *  @param  string|array  $name the field's name   
   *  @return mingo_field
   */
  public function getField($name){
  
    $ret_instance = new mingo_field($name);
    
    $field_name = $ret_instance->getNameAsString();
    if(!empty($this->field_map[$field_name])){
    
      $ret_instance = $this->field_map[$field_name];
      
    }//if
  
    return $ret_instance;
  
  }//method
  
  /**
   *  return the required fields
   *  
   *  @return array
   */
  public function getRequiredFields(){ return $this->required_map; }//method
  
  /**
   *  set a required field
   *  
   *  @param  string  $name the field name, it should already be normalized
   *  @param  mixed $default_val  if something other than null then that value will be put into the field
   *                              and an error won't be thrown if the field isn't there      
   */
  protected function requireField($name,$default_val = null){
  
    // canary...
    if(empty($name)){ throw new InvalidArgumentException('$name cannot be empty'); }//if
    $this->required_map[$name] = $default_val;
    
  }//method
  
  /**
   *  normalizes all the index fields
   *  
   *  @param  array $field_list these are the fields that will be in the index
   *  @return array an array with field name keys and type values
   */
  protected function normalizeIndex($field_list){
  
    $index_map = array();
    
    foreach($field_list as $field){
    
      if(is_array($field)){
      
        foreach($field as $field_name => $type){
        
          $field_name = $this->normalizeField($field_name);
          $index_map[$field_name] = $type;
        
        }//foreach
      
      }else{
      
        $field = $this->normalizeField($field);
        $index_map[$field] = self::INDEX_ASC;
      
      }//if/else
    
    }//foreach
  
    return $index_map;
  
  }//method
  
  /**
   *  make the field name consistent
   *  
   *  @param  string  $field  the field name
   *  @return string|array  the $field, normalized
   */
  protected function normalizeField($field){
    
    $instance = new mingo_field($field);
    return $instance->getName();
    
  }//method

}//class     
