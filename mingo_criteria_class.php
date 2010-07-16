<?php

/**
 *  creates criteria for querying a mingo db, it's up to the db's interface to convert
 *  this criteria into something the interface's backend can use  
 *
 *  allow an easy way to define most of the advanced queries defined here:
 *  http://www.mongodb.org/display/DOCS/Advanced+Queries#AdvancedQueries-{{group()}}
 *  http://www.mongodb.org/display/DOCS/Atomic+Operations
 *  http://www.mongodb.org/display/DOCS/Sorting   
 *  
 *  @version 0.3
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 11-17-09
 *  @package mingo 
 ******************************************************************************/
class mingo_criteria extends mingo_base {

  const ASC = 1;
  const DESC = -1;

  /**
   *  by default, this is the same command symbol Mongo uses by default, this is
   *  used internally to decide between commands, names, and values. Basically, if
   *  it starts with this symbol its a special command      
   *  
   *  @var  string
   *  @see  getCommandSymbol()
   */
  protected $command_symbol = '$';
  
  /**
   *  holds the internal structure of the "WHERE" criteria, usually returned from either
   *  {@link get()} or {@link getSql()}, while you can set this using {@link set()} it would
   *  be better to use the magic methods that {@link __call()} defines since that will
   *  guarrantee a valid criteria array
   *  
   *  @var  array               
   */
  protected $map_criteria = array();
  
  /**
   *  similar to {@link $map_criteria} but is for the "SORT BY..." part of a query
   *  
   *  this can only be set with the sort|asc|desc magic methods
   *  
   *  @var  array
   */
  protected $map_sort = array();
  
  /**
   *  used internally to map the commands to the internal methods that will handle them
   *
   *  @var  array   
   */
  protected $method_map = array();

  function __construct($map_criteria = array()){
  
    $command_symbol = ini_get('mongo.cmd');
    if(!empty($command_symbol)){ $this->command_symbol = $command_symbol; }//if
    
    $this->method_map = array(
      'in' => array('set' => 'handleList'),
      'nin' => array('set' => 'handleList'),
      'is' => array('set' => 'handleIs'), // =
      'not' => array('set' => 'handleVal'), // !=
      'gt' => array('set' => 'handleVal'), // >
      'gte' => array('set' => 'handleVal'), // >=
      'lt' => array('set' => 'handleVal'), // <
      'lte' => array('set' => 'handleVal'), // <=
      'sort' => array('set' => 'handleSort'),
      'asc' => array('set' => 'handleSortAsc'),
      'desc' => array('set' => 'handleSortDesc'), 
      'between' => array('set' => 'handleBetween'),
      'inc' => array('set' => 'handleAtomic'),
      'set' => array('set' => 'handleAtomic')
    );
    
    $this->set($map_criteria);
    
  }//method

  /**
   *  build a valid criteria map for making a mongo db call, this can be done in
   *  one of 2 ways, you can put the field in the method, or pass it in as the first
   *  param...
   *  
   *  find any 'foo' row that has 1,2,3,4 in it: $instance->inFoo(1,2,3,4);
   *  or $instance->in('foo',1,2,3,4);
   *  
   *  if you want to find 'foo' using a string: $instance->inField('foo',1,2,3,4)
   *      
   *  when you want to get the actual maps, just call {@link get()}                     
   *
   *  @param  string  $method the method that was called
   *  @param  array $args the params passed into the method
   */
  function __call($method,$args){
  
    list($command,$field,$args) = $this->splitMethod($method,$args);
    
    if(isset($this->method_map[$command])){
    
      $callback = $this->method_map[$command]['set'];
      $this->{$callback}($command,$field,$args);
    
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
   *  return the command symbol
   *
   *  @return string   
   */
  function getCommandSymbol(){ return $this->command_symbol; }//method
  
  /**
   *  convert an array criteria into an instance of this
   *  
   *  this method is dangerous because if the structure of your array isn't right
   *  then it will cause any queries to be made with it to be fubar'ed, so it's best
   *  to build your queries using this object's methods to ensure the structure of the
   *  map_criteria is correct.                   
   *
   *  @param  array $map_criteria the criteria that should be handled internally
   */
  function set($map_criteria){
    
    // canary...
    if(empty($map_criteria)){ $map_criteria = array(); }//if
    if(!is_array($map_criteria)){ throw new mingo_exception('$map_criteria isn\'t an array'); }//if
  
    $this->map_criteria = $map_criteria;
    
  }//method
  
  /**
   *  true if this criteria instance isn't empty
   *
   *  @return boolean
   */
  function has(){ return !empty($this->map_criteria) || !empty($this->map_sort); }//method
  
  /**
   *  set the limit
   *  
   *  @param  integer
   */
  function setLimit($val){ $this->limit = (int)$val; }//method
  function getLimit(){ return $this->limit; }//method
  function hasLimit(){ return !empty($this->limit); }//method
  
  /**
   *  set the page that will be used to calculate the offset
   *  
   *  @param  integer
   */
  function setPage($val){ $this->page = (int)$val; }//method
  function getPage(){ return $this->page; }//method
  function hasPage(){ return !empty($this->page); }//method
  
  /**
   *  takes either 2 values or an array to set the bounds (ie, limit and page) for
   *  the query
   * 
   *  @see  setLimit(), setPage()    
   *  @param  integer|array $limit  if an array, then array($limit,$page)
   *  @param  integer $page what page to use
   */
  function setBounds($limit,$page = 0){
  
    if(is_array($limit)){
      
      $this->setLimit(empty($limit[0]) ? 0 : $limit[0]);
      $this->setPage(empty($limit[1]) ? $page : $limit[1]);
      
    }else{
    
      $this->setLimit($limit);
      $this->setPage($page);
      
    }//if/else
    
  }//method
  
  /**
   *  this ones a little tricky because while setBounds takes a limit and a page
   *  this method returns a limit and an offset, and a limit_paginate (limit + 1 for
   *  finding out if there are more rows in the db)
   *  
   *  @return array array($limit,$offset,$limit_paginate)
   */
  public function getBounds(){
    
    $limit = $offset = $limit_paginate = 0;
    
    if($this->hasLimit()){
      
      $limit = $this->getLimit();
      
      // get rows + 1 to test if there are more results in the db for pagination...
      $limit_paginate = ($limit + 1);
      
    }//method
    
    if($this->hasPage()){
      $page = $this->getPage();
      $offset = ($limit - 1) * $limit;
    }//if
    
    return array($limit,$offset,$limit_paginate);
    
  }//method
  
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
        sprintf('%s must have either %s::DESC or %s::ASC passed in',$command,__CLASS__,__CLASS__)
      );
    }//if
    
    $this->map_sort[$name] = ($args[0] > 0) ? self::ASC : self::DESC;
  
  }//method
  
  /**
   *  makes sort a little easier by allowing $this->ascFIELD_NAME instead of
   *  $this->sortFIELD_NAME(self::ASC)
   *  
   *  @see  handleSort()
   */
  protected function handleSortAsc($command,$name,$args){
    return $this->handleSort($command,$name,array(self::ASC));
  }//method
  
  /**
   *  makes sort a little easier by allowing $this->descFIELD_NAME instead of
   *  $this->sortFIELD_NAME(self::DESC)
   *  
   *  @see  handleSort()
   */
  protected function handleSortDesc($command,$name,$args){
    return $this->handleSort($command,$name,array(self::DESC));
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
  
    if(empty($this->map_criteria[$name])){
      $this->map_criteria[$name] = array();
    }//if
    
    $this->map_criteria[$name][] = $this->getMap($command,$args[0]);
  
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
    $val = $this->normalizeVal($val);
    return array($this->getCommand($command) => $val);
  }//method
  
  private function getCommand($command){
    return sprintf('%s%s',$this->command_symbol,$command);
  }//method

}//class     
