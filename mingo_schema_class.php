<?php

/**
 *  allows you to define some stuff about how a mingo_map should be set up (eg, indexes
 *  and the like)  
 *
 *  @version 0.1
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 11-18-09
 *  @package mingo 
 ******************************************************************************/
class mingo_schema {

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
   *  @param  string  $field,...  one or more field names to be indexed
   */
  function setIndex(){
  
    $args = func_get_args();
    
    // canary...
    if(empty($args)){ throw new mingo_exception('no fields specified for the index'); }//if
    
    // save the index...
    $index_name = sprintf('i%s',join(',',$args));
    $this->index_map[$index_name] = array();
    
    foreach($args as $field){
      $this->index_map[$index_name][$field] = 1;
    }//foreach
    
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
