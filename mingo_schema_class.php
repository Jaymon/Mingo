<?php

/**
 *  allows you to define some stuff about how a mingo_orm should be set up (eg, indexes
 *  and the like)  
 *
 *  @version 0.1
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
   *  set using {@link setInc()} if you want the table to have an auto-increment field
   *  @var  array
   */        
  protected $inc_map = array();

  function __construct($table){
  
    $this->table = $table;
  
  }//method

  /**
   *  set an index on the table this schema represents
   *
   *  @param  string|array  $field,...
   *                          1 - pass in a bunch of strings, each one representing
   *                              a field name: setIndex('field_1','field_2',...);
   *                          2 - pass in one array with this structure: array('field_name' => direction,...)
   *                              where the field's name is the key and the value is an int direction
   *                              of either 1 (ASC) or -1 (DESC)
   *  @return boolean   
   */
  function setIndex(){
  
    $args = func_get_args();
    
    // canary...
    if(empty($args)){ throw new mingo_exception('no fields specified for the index'); }//if
    
    if(is_array($args[0])){
    
      $field_list = array();
      $index_map = array();
      foreach($args[0] as $field => $direction){
      
        $field = $this->normalizeField($field);
        $index_map[$field] = ($direction >= 0) ? self::INDEX_ASC : self::INDEX_DESC;
        $field_list[] = $field;
      
      }//foreach
    
      if(!empty($index_map)){
      
        // index is in the form: array('field_name' => direction,...)
        $index_name = sprintf('i%s',md5(join(',',$field_list)));
        $this->index_map[$index_name] = $index_map;
        
      }//if
      
    }else{
      
      // save the index...
      $index_name = sprintf('i%s',md5(join(',',$args)));
      $this->index_map[$index_name] = array();
      
      foreach($args as $field){
      
        $field = $this->normalizeField($field);
        if($field === '_id'){
          throw new mingo_exception('a table index cannot include the _id field');
        }//if
      
        $this->index_map[$index_name][$field] = self::INDEX_ASC;
      }//foreach
      
    }//if/else
    
    return true;
  
  }//method
  
  /**
   *  return the index map
   *     
   *  @return array
   */
  function getIndex(){ return $this->index_map; }//method
  function hasIndex(){ return !empty($this->index_map); }//method
  
  /**
   *  pass in true if this table should have an auto_increment field, if called, then
   *  a row_id will be added to every row. To index it you would have to use setIndex('row_id');   
   *  
   *  @param  integer $start_count  if you want to start the field at something other than 0   
   */
  function setInc($start_count = 0){
    $this->inc_map['field'] = 'row_id';
    $this->inc_map['start'] = $start_count;
  }//method
  function hasInc(){ return !empty($this->inc_map); }//method
  function getInc(){ return $this->inc_map; }//method
  function getIncField(){ return isset($this->inc_map['field']) ? $this->inc_map['field'] : ''; }//method
  function getIncStart(){ return isset($this->inc_map['start']) ? $this->inc_map['start'] : 0; }//method


}//class     
