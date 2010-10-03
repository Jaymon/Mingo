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
    $index_map = array();
    
    foreach($args as $field){
    
      if(is_array($field)){
      
        foreach($field as $field_name => $direction){
        
          $field_name = $this->normalizeField($field_name);
          $index_map[$field_name] = $direction;
          $field_list[] = $field_name;
        
        }//foreach
      
      }else{
      
        $field = $this->normalizeField($field);
        $index_map[$field] = self::INDEX_ASC;
        $field_list[] = $field;
      
      }//if/else
    
    }//foreach
    
    // canary...
    if(isset($index_map[mingo_orm::_ID])){
      throw new UnexpectedValueException(
        sprintf('a table index cannot include the %s field',mingo_orm::_ID)
      );
    }//if
    
    $index_name = sprintf('i%s',md5(join(',',$field_list)));
    $this->index_map[$index_name] = $index_map;
    
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
   *  set a required field
   *  
   *  @param  string  $name the field name
   *  @param  mixed $default_val  if something other than null then that value will be put into the field
   *                              and an error won't be thrown if the field isn't there      
   */
  public function requireField($name,$default_val = null){
  
    // canary...
    if(empty($name)){ throw new InvalidArgumentException('$name cannot be empty'); }//if
    $this->required_map[$this->normalizeField($name)] = $default_val;
    
  }//method
  
  /**
   *  return the required fields
   *  
   *  @return array
   */
  public function getRequiredFields(){ return $this->required_map; }//method

}//class     
