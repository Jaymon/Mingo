<?php

/**
 *  creates criteria for querying a mingo db 
 *
 *  allow an easy way to define most of the advanced queries defined here:
 *  http://www.mongodb.org/display/DOCS/Advanced+Queries#AdvancedQueries-{{group()}}
 *  http://www.mongodb.org/display/DOCS/Atomic+Operations
 *  http://www.mongodb.org/display/DOCS/Sorting   
 *  
 *  @version 0.1
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 11-17-09
 *  @package mingo 
 ******************************************************************************/
class mingo_criteria {

  const ASC = 1;
  const DESC = -1;

  protected $command_symbol = '$';
  
  protected $map_criteria = array();
  protected $map_sort = array();

  function __construct(){
  
    $command_symbol = ini_get('mongo.cmd');
    if(!empty($command_symbol)){ $this->command_symbol = $command_symbol; }//if
    
  }//method

  /**
   *  build a valid criteria map for making a mongo db call, this can be done in
   *  one of 2 ways, you can put the field in the method, or pass it in as the first
   *  param...
   *  
   *  find any 'foo' row that has 1,2,3,4 in it: $instance->inFoo(1,2,3,4);
   *  or $instance->in('foo',1,2,3,4);
   *  
   *  when you want to get the actual maps, just call {@link get()}                     
   *
   *  @param  string  $method the method that was called
   *  @param  array $args the params passed into the method
   */
  function __call($method,$args){
  
    list($command,$name) = $this->splitMethod($method);
    
    $method_map = array(
      'in' => 'handleList',
      'nin' => 'handleList',
      'is' => 'handleIs',
      'gt' => 'handleVal',
      'gte' => 'handleVal',
      'lt' => 'handleVal',
      'lte' => 'handleVal',
      'sort' => 'handleSort',
      'between' => 'handleBetween',
      'inc' => 'handleAtomic',
      'set' => 'handleAtomic'
    );
    
    if(isset($method_map[$command])){
    
      if(empty($name)){
      
        // since there was no name, the first argument is the name...
        if(empty($args[0])){
          throw new mingo_exception('no name found, and none given in argument 1');
        }//if
        
        $name = $args[0];
        $args = array_slice($args,1);
      
      }else{
        // now lowercase the name...
        $name = mb_strtolower($name);
      }//if/else
    
      $callback = $method_map[$command];
      $this->{$callback}($command,$name,$args);
    
    }else{
    
      throw new mingo_exception(sprintf('unknown command: %s',$command));
    
    }//if/else
  
    return true;
  
  }//method
  
  /**
   *  get rid of the internally set criteria maps
   */
  function reset(){
    $this->map_criteria = array();
    $this->map_sort = array();
  }//method
  
  /**
   *  get the criterias created in this instance
   *  
   *  this returns both the criteria and the sort criteria
   *  
   *  @example  list($c,$sort_c) = $mc->get();         
   *  
   *  @return array array($criteria,$sort_criteria);            
   */
  function get(){ return array($this->map_criteria,$this->map_sort); }//method
  
  /**
   *  handle atomic operations
   *  
   *  @link http://www.mongodb.org/display/DOCS/Atomic+Operations      
   *  
   *  @param  string  $name the field name
   *  @param  array $args only $args[0] is used
   */
  protected function handleAtomic($command,$name,$args){
  
    // canary...
    if(empty($args)){
      throw new mingo_exception(sprintf('%s must have a value, none given',$command));
    }//if
  
    $command_full = $this->getCommand($command);
  
    if(!isset($this->map_criteria[$command_full])){
      $this->map_criteria[$command_full] = array();
    }//if

    $this->map_criteria[$command_full][$name] = $args[0];
    
  }//method
  
  /**
   *  handle a between, basically, you're looking for a value >= $args[0] and <= $args[1]
   *  
   *  @param  string  $name the field name
   *  @param  array $args should have 2 indexes        
   */
  protected function handleBetween($command,$name,$args){
  
    // canary...
    if(empty($args)){
      throw new mingo_exception(sprintf('%s must have a low and high value, none given',$command));
    }//if
    if(!isset($args[1])){
      throw new mingo_exception(sprintf('%s must have a high value, only low value given',$command));
    }//if
  
    $this->map_criteria[$name] = array(
      $this->getCommand('gte') => $args[0],
      $this->getCommand('lte') => $args[1]
    );
    
  }//method
  
  /**
   *  handle the most basic map: $name => $val
   *  
   *  @param  string  $name the field name
   *  @param  array $args only $arg[0] is used         
   */
  protected function handleSort($command,$name,$args){
  
    // canary...
    if(empty($args)){
      throw new mingo_exception(
        sprintf('%s must have either %sDESC or %s::ASC passed in',$command,__CLASS__,__CLASS__)
      );
    }//if
    
    $this->map_sort[$name] = ($args[0] > 0) ? self::ASC : self::DESC;
  
  }//method
  
  /**
   *  handle the most basic map: $name => $val
   *  
   *  @param  string  $name the field name
   *  @param  array $args only $arg[0] is used         
   */
  protected function handleIs($command,$name,$args){
  
    // canary...
    if(empty($args)){
      throw new mingo_exception(sprintf('%s must have a passed in value, none given',$command));
    }//if
  
    $this->map_criteria[$name] = $args[0];
  
  }//method
  
  /**
   *  handle a generic val type command, basically $name => array($command => $val)
   *  
   *  @param  string  $name the field name
   *  @param  array $args only $arg[0] is used         
   */
  protected function handleVal($command,$name,$args){
  
    // canary...
    if(empty($args)){
      throw new mingo_exception(sprintf('%s must have a passed in value, none given',$command));
    }//if
  
    $this->map_criteria[$name] = $this->getMap($command,$args[0]);
  
  }//method
  
  /**
   *  handle a generic list type command, basically $name => array($command => $list)
   *  
   *  @param  string  $name the field name
   *  @param  array $args a list of values that $name will be in         
   *
   */        
  protected function handleList($command,$name,$args){
  
    // get the in values...
    $in_list = array();
    foreach($args as $arg){
    
      if(is_array($arg)){
      
        $in_list = array_merge($in_list,$arg);
      
      }else{
      
        $in_list[] = $arg;
      
      }//if/else
    
    }//foreach
  
    $this->map_criteria[$name] = $this->getMap($command,$in_list);
  
  }//method
  
  
  private function getMap($command,$val){
    return array($this->getCommand($command) => $val);
  }//method
  
  private function getCommand($command){
    return sprintf('%s%s',$this->command_symbol,$command);
  }//method
  
  private function splitMethod($method){
  
    $ret_prefix = $ret_name = '';
  
    // get everything lowercase form start...
    for($i = 0,$max = mb_strlen($method); $i < $max ;$i++){
    
      $ascii = ord($method[$i]);
      if(($ascii < 97) || ($ascii > 122)){
      
        $ret_name = mb_substr($method,$i);
        break;
      
      }else{
      
        $ret_prefix .= $method[$i];
      
      }//if/else
    
    }//for
    
    return array($ret_prefix,$ret_name);
  
  }//method

}//class     
