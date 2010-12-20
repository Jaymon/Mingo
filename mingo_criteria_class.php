<?php

/**
 *  creates a standard criteria for querying a mingo db
 *  
 *  it's up to the db's interface to convert this criteria into something the 
 *  interface's backend can use.  
 *
 *  allow an easy way to define most of the advanced queries defined here:
 *  http://www.mongodb.org/display/DOCS/Advanced+Queries#AdvancedQueries-{{group()}}
 *  http://www.mongodb.org/display/DOCS/Atomic+Operations
 *  http://www.mongodb.org/display/DOCS/Sorting   
 *  
 *  @version 0.4
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 11-17-09
 *  @package mingo 
 ******************************************************************************/
class mingo_criteria extends mingo_base {

  /**
   *  used for sort ascending
   */        
  const ASC = 1;
  
  /**
   *  used for sort descending
   */
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
   *  holds the internal structure of the "WHERE" criteria. While you can set this 
   *  using {@link setWhere()} it would be better to use the magic methods that 
   *  {@link __call()} defines since that will guarantee a valid where criteria array
   *  
   *  @see  getWhere(), setWhere(), hasWhere()   
   *  @var  array               
   */
  protected $map_where = array();
  
  /**
   *  is for the "SORT BY..." part of a query
   *  
   *  this can only be set with the sort|asc|desc magic methods
   *  
   *  @see  getSort(), setSort(), hasSort()   
   *  @var  array
   */
  protected $map_sort = array();
  
  /**
   *  handle atomic operations
   *  
   *  @see  getOperations(), setOperations(), hasOperations()   
   *  @var  array
   */
  protected $map_operations = array();
  
  /**
   *  holds the limit, page information
   *
   *  @see  getBounds(), setBounds(), hasBounds()   
   *  @var  array
   */  
  protected $map_bounds = array();
  
  /**
   *  used internally to map the commands to the internal methods that will handle them
   *  
   *  the key is used to tell the {@link __call()} method what to do, for example, if
   *  you wanted to query on foo less than 100, you could do: $this->ltFoo(100);            
   *
   *  @var  array   
   */
  protected $method_map = array(
    // these are the where map commands...
    'in' => array('callback' => 'handleList'),
    'nin' => array('callback' => 'handleList'),
    'is' => array('callback' => 'handleIs'), // =
    'not' => array('callback' => 'handleVal'), // !=
    'gt' => array('callback' => 'handleVal'), // >
    'gte' => array('callback' => 'handleVal'), // >=
    'lt' => array('callback' => 'handleVal'), // <
    'lte' => array('callback' => 'handleVal'), // <=
    'between' => array('callback' => 'handleBetween'),
    'near' => array('callback' => 'handleSpatial'), // spatial
    // these are the sort map commands...
    'sort' => array('callback' => 'handleSort'),
    'asc' => array('callback' => 'handleSortAsc'),
    'desc' => array('callback' => 'handleSortDesc'), 
    // these are the operations map commands...
    'inc' => array('callback' => 'handleAtomic'),
    'callback' => array('callback' => 'handleAtomic'),
    // these are read-only commands...
    'has' => array('callback' => 'handleHas')
  );

  final public function __construct(){
  
    $command_symbol = ini_get('mongo.cmd');
    if(!empty($command_symbol)){ $this->command_symbol = $command_symbol; }//if
    
    $this->start();
    
  }//method
  
  /**
   *  this is here so if this class is extended the developer has a plact to put init code
   */        
  protected function start(){}//method

  /** 
   *  uses the {@link $method_map} to decide how a called method should be handled
   *  and added to a criteria map.     
   *     
   *  For example, find any 'foo' row that has 1,2,3,4 in it: $this->inFoo(1,2,3,4);
   *  or if you want to find 'foo' using a variable: $foo->inField('foo',1,2,3,4). Check
   *  the method map for all the method prefixes (commands) you can do, (eg, lt for <, lte for <=)                        
   *
   *  @param  string  $method the method that was called
   *  @param  array $args the params passed into the method
   *  @return mixed whatever the callback method returns   
   */
  public function __call($method,$args){
  
    list($command,$field_instance,$args) = $this->splitMethod($method,$args);
    
    if(isset($this->method_map[$command])){
    
      $callback = $this->method_map[$command]['callback'];
      $ret_mixed = $this->{$callback}($command,$field_instance->getNameAsString(),$args);
    
    }else{
    
      throw new BadMethodCallException(sprintf('unknown command: %s',$command));
    
    }//if/else
  
    return $ret_mixed;
  
  }//method
  
  /**
   *  how field names are separated from commands is because commands start with
   *  a command symbol
   *  
   *  this method will return the full given command (ie, the command with the prefixed
   *  command symbol)
   *  
   *  @param  string  $command  the command to be built
   *  @return string  the full command
   */
  public function getCommand($command){
    return sprintf('%s%s',$this->command_symbol,$command);
  }//method
  
  /**
   *  get rid of the internally set criteria maps
   */
  public function reset(){
    $this->map_where = array();
    $this->map_sort = array();
    $this->map_operations = array();
    $this->map_bounds = array();
  }//method
  
  /**
   *  return the command symbol
   *
   *  @return string   
   */
  function getCommandSymbol(){ return $this->command_symbol; }//method

  public function getWhere(){ return $this->map_where; }//method
  public function hasWhere(){ return !empty($this->map_where); }//method
  public function setWhere($map){
    // canary...
    if(empty($map)){ $map = array(); }//if
    if(!is_array($map)){ throw new UnexpectedValueException('$map isn\'t an array'); }//if
    
    $this->map_where = $map;
    return $this->map_where;
  }//method
  
  public function getSort(){ return $this->map_sort; }//method
  public function hasSort(){ return !empty($this->map_sort); }//method
  public function setSort($map){
    // canary...
    if(empty($map)){ $map = array(); }//if
    if(!is_array($map)){ throw new UnexpectedValueException('$map isn\'t an array'); }//if
    
    $this->map_sort = $map;
    return $this->map_sort;
  }//method
  public function killSort(){ return $this->setSort(array()); }//method
  
  public function getOperations(){ return $this->map_operations; }//method
  public function hasOperations(){ return !empty($this->map_operations); }//method
  public function setOperations($map){
    // canary...
    if(empty($map)){ $map = array(); }//if
    if(!is_array($map)){ throw new mingo_exception('$map isn\'t an array'); }//if
    
    $this->map_operations = $map;
    return $this->map_operations;
  }//method
  
  /**
   *  set the limit
   *  
   *  @param  integer
   */
  public function setLimit($val){ $this->map_bounds['limit'] = (int)$val; }//method
  public function getLimit(){ return empty($this->map_bounds['limit']) ? 0 : $this->map_bounds['limit']; }//method
  public function hasLimit(){ return !empty($this->map_bounds['limit']); }//method
  
  /**
   *  set the page that will be used to calculate the offset
   *  
   *  @param  integer
   */
  public function setPage($val){ $this->map_bounds['page'] = (int)$val; }//method
  public function getPage(){ return empty($this->map_bounds['page']) ? 0 : $this->map_bounds['page']; }//method
  public function hasPage(){ return !empty($this->map_bounds['page']); }//method
  
  /**
   *  takes either 2 values or an array to set the bounds (ie, limit and page) for
   *  the query
   * 
   *  @see  setLimit(), setPage()    
   *  @param  integer|array $limit  if an array, then array($limit,$page)
   *  @param  integer $page what page to use
   */
  public function setBounds($limit,$page = 0){
  
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
      $offset = ($page - 1) * $limit;
    }//if
    
    return array($limit,$offset,$limit_paginate);
    
  }//method
  
  public function hasBounds(){
    return !empty($this->map_bounds);
  }//method
  
  /**
   *  handle whether a field is set or not in where or sort
   *  
   *  @param  string  $name the field name
   *  @param  array $args currently ignored
   *  @return boolean   
   */
  protected function handleHas($command,$name,$args){

    return isset($this->map_where[$name]) || isset($this->map_sort[$name]);
    
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
  
    if(!isset($this->map_operations[$command_full])){
      $this->map_operations[$command_full] = array();
    }//if

    $this->map_operations[$command_full][$name] = $args[0];
    
  }//method
  
  /**
   *  handle a spatial call
   *  
   *  the form is taken from: http://www.mongodb.org/display/DOCS/Geospatial+Indexing
   *           
   *  @since  10-18-10
   *  @param  string  $name the field name
   *  @param  array $args should have 2 indexes: point and distance
   */
  protected function handleSpatial($command,$name,$args){
  
    // canary, make sure the point is valid...
    $field = new mingo_field();
    $field->setType(mingo_field::TYPE_POINT);
    $args[0] = $field->normalizeInVal($args[0]);
  
    $this->map_where[$name] = array(
      $this->getCommand('near') => $args[0],
      $this->getCommand('maxDistance') => $args[1]
    );
    
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
  
    $this->map_where[$name] = array(
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
  
    $this->map_where[$name] = $args[0];
  
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
  
    if(empty($this->map_where[$name])){
      $this->map_where[$name] = array();
    }//if
    
    $this->map_where[$name] = $this->getMap($command,$args[0]);
  
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
  
    $this->map_where[$name] = $this->getMap($command,$in_list);
  
  }//method
  
  protected function getMap($command,$val){
  
    // format the val...
    // @todo  this might be better deeper into the mingo_db so it would have access to the schema
    $field_instance = new mingo_field();
    $val = $field_instance->normalizeInVal($val);
  
    return array($this->getCommand($command) => $val);
  }//method

}//class     
