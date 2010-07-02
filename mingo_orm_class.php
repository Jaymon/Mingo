<?php

/**
 *  maps a mingo collection or table to an object, this is the ORM part of the mingo
 *  package
 *  
 *  all ORMs in your project that use mingo should extend this class and implement
 *  the start() method 
 *
 *  this class reserves some keywords as special: 
 *    - row_id = the auto increment key, always set on SQL db, can be turned on with mongo 
 *               using $this->schema->setInc() in the child class's __construct() method
 *    - _id = the unique id of the row, always set for both mongo and sql
 *    - updated = holds a unix timestamp of the last time the row was saved into the db, always set
 *    - created = holds a unix timestamp of when the row was created, always set
 *
 *   
 *  @todo
 *    1 - move limit and page to the schema object? I'm not sure that
 *        would really work because what if there was a field in the db that was named
 *        either "limit" or "page". Plus, having limit and page part of this class makes
 *        hasMore() and getTotal() make more sense  
 *  
 *  @abstract 
 *  @version 0.3
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 11-14-09
 *  @package mingo 
 ******************************************************************************/
abstract class mingo_orm extends mingo_base implements ArrayAccess,Iterator,Countable {

  const _ID = '_id';
  const ROW_ID = 'row_id';
  const UPDATED = 'updated';
  const CREATED = 'created';

  /**
   *  holds the table that this class will access in the db
   *  @var  string
   */
  protected $table = '';
  
  /**
   *  holds the pointer to the current map in {@link $list}
   *  @var  integer
   */
  protected $current_i = 0;
  
  /**
   *  holds the total maps in {@link $list}
   *  @var  integer
   */
  protected $count = 0;
  
  /**
   *  holds this class's db access instance
   *  @var  mingo_db
   */
  protected $db = null;
  
  /**
   *  the internal representation of the lists that this class represents
   *  @var  array an array with each index having 'modified' and 'map' keys
   */
  protected $list = array();
  
  /**
   *  how many rows calling {@link load()} will actually load from the db
   *  @var  integer   
   */
  protected $limit = 0;
  /**
   *  what page or results {@link load()} will use to start loading results from
   *  @var  integer   
   */
  protected $page = 0;
  /**
   *  if {@link load()} could have loaded more results, this will be set to true
   *  @var  boolean   
   */
  protected $more = false;
  /**
   *  if {@link load()} has $set_load_count=true then get the total results that could
   *  be returned if no limit was specified
   *        
   *  @var  integer
   */
  protected $total = 0;
  
  /**
   *  hold the schema object for this instance
   *  
   *  usually, the schema will be populated/defined in the child class's __construct()
   *  
   *  @var  mingo_schema            
   */
  protected $schema = null;
  
  /**
   *  set to true if you want this instance to act like an array even if {@link hasCount()}
   *  equals 1
   *  
   *  @var  boolean
   */
  protected $array = false;

  /**
   *  default constructor
   */
  final function __construct(){
  
    $this->table = mb_strtolower(get_class($this));
  
    $this->setDb(mingo_db::getInstance());
    
    $this->setSchema(new mingo_schema($this->table));
    
    // do the child's initializing stuff, like setting ORM specific schema stuff...
    $this->start();
  
  }//method
  
  /**
   *  in this method you should do stuff like set the {@link $schema} in the child
   *  class. This gets called in {@link __construct()} and should do any of the initializing
   *  that the child class needs
   */
  abstract protected function start();
  
  /**
   *  get how many rows this class represents, remember if it's greater than 1 then
   *  this class represents multiple rows, so any get*, set* access functions will
   *  work on all the rows, so if you do something like $this->setTitle('blah') and
   *  there are 4 rows, then each row will have its title set to 'blah'
   *  
   *  @return integer how many rows this class represents               
   */
  function getCount(){ return $this->count; }//method
  function hasCount(){ return !empty($this->count); }//method
  function isCount($count){ return ($this->count == $count); }//method
  /**
   *  Required definition for Countable, allows count($this) to work
   *  @link http://www.php.net/manual/en/class.countable.php
   */
  function count(){ return $this->getCount(); }//method
  
  /**
   *  set the limit that any {@link load()} call will use
   *  
   *  @param  integer|array if array, then array($limit,$page)
   */
  function setLimit($val){
    if(is_array($val)){
      if(isset($val[1])){
        $this->setPage($val[1]);
      }//if
      $val = isset($val[0]) ? $val[0] : 0;
    }//if
    $this->limit = (int)$val;
  }//method
  function getLimit(){ return $this->limit; }//method
  function hasLimit(){ return !empty($this->limit); }//method
  
  /**
   *  set the page that any {@link load()} call will offset from
   *  
   *  @param  integer|array if array, then array($limit,$page)
   */
  function setPage($val){
    if(is_array($val)){
      if(isset($val[0])){
        $this->setLimit($val[0]);
      }//if
      $val = isset($val[1]) ? $val[1] : 0;
    }//if
    $this->page = (int)$val;
  }//method
  function getPage(){ return $this->page; }//method
  function hasPage(){ return !empty($this->page); }//method

  protected function setMore($val){ $this->more = $val; }//method
  protected function getMore(){ return $this->more; }//method
  /**
   *  return true if the last db load could load more, but was limited by $limit 
   *
   *  @return boolean
   */
  function hasMore(){ return !empty($this->more); }//method
  
  protected function setTotal($val){ $this->total = $val; }//method
  function getTotal(){ return $this->total; }//method
  function hasTotal(){ return !empty($this->total); }//method
  
  function setDb($db){ $this->db = $db; }//method
  function getDb(){ return $this->db; }//method
  
  function setSchema($schema){ $this->schema = $schema; }//method
  
  function getTable(){ return $this->table; }//method
  
  /**
   *  pass in true to make this instance act like it is a list no matter what
   *  
   *  eg, you want to iterate through the results of a get* call using foreach
   *  but there might only be one result (which would normally cause only the value
   *  to be returned instead of a list of values)
   *  
   *  example:
   *    // foo extends mingo_orm...   
   *    $foo = new foo();
   *    $foo->set_id($_id);
   *    $foo->load();
   *    // foo will only have one row loaded since _id is always unique...
   *    $foo->get_id(); // '4z4b59960417cff191587927'
   *    $foo->setArray(true);
   *    $foo->get_id(); // array(0 => '4z4b59960417cff191587927')        
   *                
   *  
   *  @param  boolean $val
   */
  function setArray($val){ $this->array = $val; }//method
  function getArray(){ return $this->array; }//method
  function isArray(){ return !empty($this->array); }//method
  
  /**
   *  get the internal representation of this class
   *  
   *  the better method to use is {@link get()} because it will create instances
   *  for all the rows that get ripped from this isntance, this returns the raw
   *  array list only, it needs to be public so {@link append()} will work when 
   *  appending other instances of this class   
   *  
   *  @return array the internal {@link $list} array
   */
  function getList(){ return $this->list; }//method

  /**
   *  returns a list of all the maps this class contains
   *  
   *  between v0.2 and 0.3 this went through a change in that it always returns a list,
   *  before, it would return an object if there was only 1 map represented, now it
   *  will return array(0 => instance), I realize the idea of being able to make a quick
   *  clone is great, but it is almost as easy to do $this->get(0) to make the clone         
   *      
   *  @param  integer $i  the index we want to get if we don't want all of them      
   *  @return array
   */
  function get(){
  
    $ret_mix = null;
    $args = func_get_args();
  
    if(empty($args)){
    
      $ret_mix = array();
    
      foreach($this as $map){ $ret_mix[] = $map; }//foreach
      
      // we only have one index, so return that...
      ///if(!isset($ret_mix[1])){ $ret_mix = $ret_mix[0]; }//if
    
    }else{
    
      $ret_mix = $this->rip($args[0]);
    
    }//if/else
    
    return $ret_mix;
  
  }//method
  
  /**
   *  returns a list of all the array maps this class contains
   *  
   *  Unlike {@link get()} which returns separate instances for each map this class represents
   *  this will return an actual map (eg, key/val paired array). Like get() you can do $this->getMap(0)
   *  to just get one map array      
   *  
   *  @param  integer $i  the index we want to get if we don't want all of them      
   *  @return array
   */
  function getMap(){
  
    $ret_mix = null;
    $args = func_get_args();
  
    if(empty($args)){
    
      $ret_mix = array();
    
      foreach($this->list as $map){ $ret_mix[] = $map['map']; }//foreach
      
    }else{
    
      $ret_mix = $this->list[$args[0]]['map'];
    
    }//if/else
    
    return $ret_mix;
  
  }//method

  /**
   *  save this instance into the db
   *  
   *  @return boolean
   *  @throws mingo_exception   
   */
  function set(){
  
    $ret_bool = true;
    $now = time();
    
    foreach(array_keys($this->list) as $key){
    
      // only try and save if it has changes...
      if(!empty($this->list[$key]['modified'])){
      
        // add created and last touched fields...
        if(empty($this->list[$key]['map'][self::CREATED])){ 
          $this->list[$key]['map'][self::CREATED] = $now;
        }//if
        $this->list[$key]['map'][self::UPDATED] = $now;
      
        $this->list[$key]['map'] = $this->db->set(
          $this->getTable(),
          $this->list[$key]['map'],
          $this->schema
        );
        $this->list[$key]['modified'] = false; // reset
      
      }//if
    
    }//foreach
  
    ///out::e($this->list);
  
    return $ret_bool;
  
  }//method
  
  /**
   *  attach a new row to this instance
   *  
   *  @param  array|object  either an associative array or instance of this class, to attach to
   *                        the end of this object
   *  @param  boolean $is_modified  if true, then the contents of $map will be saved when set() is called
   *                                if false, then $map won't be settable until a set* function is used   
   *  @return boolean
   */
  function append($map,$is_modified = false){
  
    // canary...
    if(isset($this->list[$this->count])){ $this->count = count($this->list); }//if
  
    if($map instanceof self){
    
      // go through each row this class tracks internally...
      foreach($map->getList() as $m){
        $this->append($m['map'],$m['modified']);
      }//method
    
    }else if(is_array($map)){
    
      $this->list[$this->count] = array();
      $this->list[$this->count]['map'] = $map;
      $this->list[$this->count]['modified'] = $is_modified;
      $this->count++;
    
    }else{
    
      throw new mingo_exception(sprintf('trying to append an unsupported $map type: %s',gettype($map)));
    
    }//if/else if/else
  
    return true;
  
  }//method
  
  /**
   *  load the contents from the db
   *  
   *  contents are loaded in one of 2 ways, if you pass in a $where_map then that 
   *  is used to load the internal contents of this instance, if you have set some
   *  variables using set* methods, and then call load() without passing in a $where_map
   *  then those set internal vars will be used as the map
   *  
   *  @example  
   *    load something using internal loading:
   *      $instance->set_id('4affd9e8da7f000000003645');
   *      $instance->load()                     
   *
   *    load something using $where_criteria:
   *      $mc = new mingo_criteria();
   *      $mc->in_id('4affd9e8da7f000000003645');
   *      $instance->load($mc);
   *
   *  @param  mingo_criteria  $where_criteria  criteria for loading db rows into this object
   *  @param  boolean $set_load_count if true, then {@link getTotal()} will return how many results
   *                                  are possible (eg, if you have a limit of 10 but there are 100
   *                                  results matching $where_criteria, getTotal() will return 100      
   *  @return integer how many rows were loaded
   */
  function load(mingo_criteria $where_criteria = null,$set_load_count = false){
  
    $ret_int = 0;
    $do_reset = false;
  
    // figure out what criteria to use on the load...
    if(empty($where_criteria)){
    
      if($this->count > 1){
        throw new mingo_exception('no $where_criteria passed in and one could not be inferred because count > 1');
      }//if
      
      if(!empty($this->list[0]['map'])){
      
        $where_criteria = new mingo_criteria();
      
        if(isset($this->list[0]['map']['_id'])){
          // only use the id if it is present...
          $where_criteria->is_id($this->list[0]['map']['_id']);
        }else{
          // use the whole map as a criteria since no _id was found...
          $where_criteria->set($this->list[0]['map']);
        }//if/else
        
      }//if
    
    }else{
    
      // there is criteria object, so reset anything that is currently in the instance
      $do_reset = true;
    
    }//if/else
    
    // set limit stuff...
    $limit_paginate = $this->getLimit();
    
    $limit_offset = 0;
    if($this->hasPage()){
      $limit_page = $this->getPage();
      $limit_offset = ($limit_page - 1) * $limit_paginate;
    }//if
    
    // get rows + 1 to test if there are more results in the db for pagination...
    if($limit_paginate > 0){
      $limit_paginate++;
    }//if
    
    $list = $this->db->get(
      $this->getTable(),
      $this->schema,
      $where_criteria,
      array($limit_paginate,$limit_offset)
    );
    
    if(!empty($list) || $do_reset){
      
      $this->reset();
      
      // set whether more results are available or not...
      $total_list = $ret_int = count($list);
      if(!empty($limit_paginate) && ($total_list == $limit_paginate)){
        
        // cut off the final row since it wasn't part of the original requested rows...
        $list = array_slice($list,0,-1);
        $this->setMore(true);
        $ret_int--;
        
        if($set_load_count){
          $this->setTotal($this->db->getCount($this->getTable(),$this->schema,$where_criteria));
        }else{
          $this->setTotal($total_list);
        }//if/else
        
      }else{
      
        $this->setMore(false);
        $this->setTotal($total_list);
        
      }//if/else
      
      foreach($list as $map){
        $this->append($map);
      }//foreach
      
    }//if
    
    return $ret_int;
  
  }//method
  
  /**
   *  remove all the rows in this instance from the db
   *  
   *  @return boolean will only return true if all the rows were successfully removed, false otherwise      
   */
  function kill(){
  
    $ret_bool = false;
  
    if($this->has_id()){
    
      // get all the ids...
      $where_criteria = new mingo_criteria();
      $where_criteria->in_id($this->get_id());
      if($this->db->kill($this->getTable(),$this->schema,$where_criteria)){
        $this->reset();
        $ret_bool = true;
      }//if
    
    }else{
    
      $ret_bool = true;
    
      for($i = 0; $i < $this->count ;$i++){
    
        $where_criteria = new mingo_criteria();
    
        if(isset($this->list[$i]['map']['_id'])){
        
          $where_criteria->is_id($this->list[$i]['map']['_id']);
      
        }else{
        
          $where_criteria->set($this->list[$i]['map']);
        
        }//if/else
        
        // we don't want to accidently delete everything by passing in an empty where...
        if($where_criteria->has()){
        
          if($this->db->kill($this->getTable(),$this->schema,$where_criteria)){
          
            unset($this->list[$i]);
            $this->count--;
          
          }else{
          
            $ret_bool = false;
            
          }//if/else
          
        }//if
        
      }//for
      
      if($ret_bool){
      
        // everything was successfully washed...
        $this->reset();
      
      }else{
      
        // we had an error, something didn't get killed, compensate for that...
        
        // re-do the keys...
        $this->list = array_values($this->list);
      
      }//if/else
    
    }//if/else
  
    return $ret_bool;
  
  }//method
  
  /**
   *  the install method for the class
   *  
   *  using the {@link $schema} instance, this method will go through and create
   *  the table and add indexes, etc.
   *  
   *  @param  boolean $drop_table true if you want to drop the table if it exists               
   *  @return boolean
   */
  function install($drop_table = false){
  
    if($drop_table){ $this->db->killTable($this->getTable()); }//if
  
    // create the table...
    if(!$this->db->setTable($this->getTable(),$this->schema)){
    
      throw new mingo_exception(sprintf('failed in table %s creation',$this->getTable()));
    
    }//if/else
    
    return true;
  
  }//method

  /**
   *  lot's of the magic happens with this function, this magic method allows
   *  getting/setting of values, has/exists checks, and is value type lookups on
   *  internal values.
   *  
   *  to call this function, just include the prefix: get/set/is/exists/has and the
   *  name you want
   *  
   *  @example  set a value:
   *              $this->setFoo('bar'); // set 'foo' to 'bar'
   *
   *  @example  get a value:
   *              $this->getFoo(); // get the value of 'foo', null if 'foo' not found
   *              $this->getFoo('bar'); //get value of 'foo', 'bar' if 'foo' not found
   *
   *  @example  check if value exists                 
   *              $this->existsFoo(); // see if 'foo' exists at all
   *         
   *  @example  check if value exists and is non-empty
   *              $this->hasFoo(); // returns true if 'foo' exists and is non-empty
   *
   *  @example  see if 'foo' is some value
   *              $this->isFoo('bar'); // true if 'foo' has a value of 'bar'
   *              $this->isFoo('bar','cat'); // true if 'foo' has a value of 'bar', or 'cat'
   *  
   *  @example  get rid of 'foo'
   *              $this->killFoo(); // unsets 'foo' if it exists
   *              
   *  @example  bump 'foo' value by $count
   *              $this->bumpFoo($count); // bumps numeric 'foo' value by $count
   *              
   *  @example  if you want to set a field name with a string var for the name
   *              $this->setField('foo',$val); // *Field($name,...) will work with any prefix
   *  
   *  @example  if you want to see if a field has atleast one matching value
   *              $this->inFoo('bar'); // true if field foo contains bar atleast once   
   *                       
   *  @param  string  $method the method that was called
   *  @param  array $args the arguments passed to the function
   *  @return mixed
   */      
  function __call($method,$args){
    
    $method_map = array(
      'set' => 'handleSet',
      'get' => 'handleGet',
      'has' => 'handleHas',
      'is' => 'handleIs',
      'exists' => 'handleExists',
      'kill' => 'handleKill',
      'in' => 'handleIn',
      'bump' => 'handleBump'
    );
    
    list($command,$field,$args) = $this->splitMethod($method,$args);
    
    if(empty($method_map[$command])){
    
      throw new mingo_exception(sprintf('could not find a match for $method %s with command: %s',$method,$command));
    
    }else{
    
      $callback = $method_map[$command];
    
      if(array_key_exists($field,get_object_vars($this))){
      
        // @note  we use array_key_exists to compensate for null values which would cause isset to fail
      
        throw new mingo_exception(sprintf('a field cannot have this name: %s',$field));
      
      }else{
    
        $ret_mix = $this->{$callback}($field,$args);
        return $ret_mix;
        
      }//if/else
    
    }//if
  
  }//method
  
  /**
   *  internally handles all the set* functions of this class
   *  
   *  @param  string  $name the name of the index in the current map to set
   *  @param  array $args the passed in value, only index 0 is used
   *  @return boolean
   */
  private function handleSet($name,$args){
  
    // canary...
    if(!isset($args[0])){ return null; }//if
    if(empty($this->list)){ $this->append(array()); }//if
    
    $args[0] = $this->normalizeVal($args[0]);
    
    for($i = 0; $i < $this->count ;$i++){
    
      $this->list[$i]['modified'] = true;
      $this->list[$i]['map'][$name] = $args[0];
      
    }//for
  
    return true;
    
  }//method
  
  /**
   *  internally handles all the get* functions of this class
   *  
   *  @param  string  $name the name of the index in the current map to get
   *  @param  array $args the passed in value
   *  @return mixed whatever value is found, otherwise $args[0] (default_val), if there is
   *                only one row (ie $this->isCount(1) is true) then just that value is returned
   *                otherwise an array of values are returned      
   */
  private function handleGet($name,$args = array()){
  
    // canary...
    if(!isset($args[0])){ $args[0] = null; }//if
    if(!$this->hasCount()){ return $this->isArray() ? array() : $args[0]; }//if
    
    $ret_list = array();
    
    for($i = 0; $i < $this->count ;$i++){
    
      $ret_list[] = isset($this->list[$i]['map'][$name]) 
      ? $this->list[$i]['map'][$name] 
      : $args[0];
      
    }//for
    
    // check for just one index and just return that value if found...
    return ($this->isCount(1) && !$this->isArray()) ? $ret_list[0] : $ret_list;
  
  }//method
  
  /**
   *  internally handles all the has* functions of this class
   *  
   *  @param  string  $name the name of the index in the current map to get
   *  @param  array $args the passed in value
   *  @return boolean true if the $name exists and is non-empty, false otherwise
   */
  private function handleHas($name,$args = array()){
    
    // canary...
    if(!$this->hasCount()){ return false; }//if
    
    $ret_bool = true;
  
    for($i = 0; $i < $this->count ;$i++){
    
      if(empty($this->list[$i]['map'][$name])){
        $ret_bool = false;
        break;
      }//if
      
    }//for
  
    return $ret_bool;
    
  }//method
  
  /**
   *  internally handles all the exists* functions of this class
   *  
   *  @param  string  $name the name of the index in the current map to get
   *  @param  array $args the passed in value
   *  @return boolean true if the $name exists, false otherwise
   */
  private function handleExists($name,$args = array()){
  
    // canary...
    if(!$this->hasCount()){ return false; }//if
  
    $ret_bool = true;
  
    for($i = 0; $i < $this->count ;$i++){
    
      if(!isset($this->list[$i]['map'][$name])){
        $ret_bool = false;
        break;
      }//if
      
    }//for
  
    return $ret_bool;
  
  }//method
  
  /**
   *  internally handles all the is* functions of this class
   *  
   *  @param  string  $name the name of the index in the current map to get
   *  @param  array $args the passed in value
   *  @return boolean true if the $name exists and is the value found in $args, false otherwise
   */
  private function handleIs($name,$args){
    
    // canary...
    if(!$this->hasCount()){ return false; }//if
    // make sure the $name is present in all the maps, otherwise it fails...
    if(!$this->handleExists($name)){ return false; }//if
    
    $ret_bool = true;
    $get_list = $this->hasCount() ? $this->handleGet($name) : array();
    if($this->isCount(1) && !$this->isArray()){ $get_list = array($get_list); }//if
    
    foreach($get_list as $val){
    
      if(!in_array($val,$args,true)){
        $ret_bool = false;
        break;
      }//if
    
    }//foreach
    
    return $ret_bool;
    
  }//method
  
  /**
   *  internally handles all the in* functions of this class
   *  
   *  @param  string  $name the name of the index in the current map to get
   *  @param  array $args the passed in value
   *  @return boolean true if the $name exists and is the value found in $args, false otherwise
   */
  private function handleIn($name,$args){
    
    // canary...
    if(!$this->hasCount()){ return false; }//if
    
    $ret_bool = true;
    $get_list = $this->hasCount() ? $this->handleGet($name) : array();
    if($this->isCount(1) && !$this->isArray()){ $get_list = array($get_list); }//if
    
    foreach($args as $arg){
      
      if(!in_array($arg,$get_list,true)){
        $ret_bool = false;
        break;
      }//if
      
    }//foreach
    
    return $ret_bool;
    
  }//method
  
  /**
   *  internally handles all the kill* functions of this class
   *  
   *  @param  string  $name the name of the index in the current map to unset
   *  @param  array $args the passed in value
   *  @return boolean true if the $name exists, false otherwise
   */
  private function handleKill($name,$args){
  
    for($i = 0; $i < $this->count ;$i++){
    
      if(isset($this->list[$i]['map'][$name])){
        $this->list[$i]['modified'] = true;
        unset($this->list[$i]['map'][$name]);
      }//if
      
    }//for
  
    return true;
  
  }//method
  
  /**
   *  internally handles all the bump* functions of this class
   *  
   *  @param  string  $name the name of the index in the current map to get
   *  @param  array $args the passed in value
   *  @return boolean true if the $name exists, false otherwise
   */
  private function handleBump($name,$args){
    
    // canary...
    if(!isset($args[0])){ throw new mingo_exception(sprintf('no $count specified to bump %s',$name)); }//if
    if(empty($this->list)){ $this->append(array()); }//if
    
    for($i = 0; $i < $this->count ;$i++){
    
      if(!isset($this->list[$i]['map'][$name])){
        $this->list[$i]['map'][$name] = 0;
      }//if
      
      $this->list[$i]['modified'] = true;
      $this->list[$i]['map'][$name] += $args[0];
    
    }//for
    
    return true;
    
  }//method
  
  /**#@+
   *  Required method definitions of Iterator interface
   *  
   *  @link http://php.net/manual/en/class.iterator.php      
   */
  function rewind(){ $this->current_i = 0; }//method
  function current(){ return $this->rip($this->current_i); }//method
  function key(){ return $this->current_i; }//method
  function next(){ ++$this->current_i; }//method
  function valid(){ return isset($this->list[$this->current_i]); }//method
  /**#@-*/
  
  /**#@+
   *  Required definitions of interface ArrayAccess
   *  @link http://www.php.net/manual/en/class.arrayaccess.php   
   */
  /**
   *  Set a value given it's key e.g. $A['title'] = 'foo';
   */
  function offsetSet($key,$val){
    
    if($key === null){
      // they are trying to do a $obj[] = $val so let's append the $val
      // via: http://www.php.net/manual/en/class.arrayobject.php#93100
      $this->append($val);
    }else{
      // they specified the key, so this will work on the internal objects...
      $this->__call(sprintf('set%s',ucfirst($key)),array($val));
    }//if/else
  }//method
  /**
   *  Return a value given it's key e.g. echo $A['title'];
   */
  function offsetGet($key){ return $this->__call(sprintf('get%s',ucfirst($key)),array()); }//method
  /**
   *  Unset a value by it's key e.g. unset($A['title']);
   */
  function offsetUnset($key){ return $this->__call(sprintf('kill%s',ucfirst($key))); }//method
  /**
   *  Check value exists, given it's key e.g. isset($A['title'])
   */
  function offsetExists($key){  return $this->__call(sprintf('exists%s',ucfirst($key))); }//method
  /**#@-*/
  
  /**
   *  rips out the map found at index $i into its own instance
   *  
   *  this class is internal, but you can see it used in {@link current()} and {@link get()}
   *  
   *  @param  integer $i  the index to rip out and return
   *  @return object
   */
  private function rip($i){
  
    // canary...
    if(!isset($this->list[$i])){ return null; }//if
  
    $class = get_class($this);
    $ret_map = new $class();
    ///$ret_map = new self(); // returns mingo_orm
    $ret_map->append(
      $this->list[$i]['map'],
      $this->list[$i]['modified']
    );
    
    return $ret_map;
  
  }//method
  
  /**
   *  resets the class's internal list   
   */
  private function reset(){
  
    $this->current_i = 0;
    $this->list = array();
    $this->count = 0;
  
  }//method
  
  /**
   *  increment the limit by one if it exists
   *  
   *  if a limit exists ($limit>0), then the limit is incremented by one, the original
   *  limit is returned in $orig_limit. This is to make the limit info useful to {@link setHasMoreResults()}   
   *      
   *  @param  integer|array $page if array, then array($limit,$page) otherwise $page and $limit
   *                              will use the default found in {@link limit()}         
   *  @return array array($limit,$page)
   */
  private function assureLimit($limit){
  
    $limit = $page = 0;
    
    if(is_array($page)){
      
      $limit = empty($page[0]) ? 0 : (int)$page[0];
      $page = empty($page[1]) ? 1 : (int)$page[1];
      
    }else{
    
      $limit = self::limit();
      $page = ($page < 1) ? 1 : (int)$page;
      
    }//if/else
  
    return array($limit,(int)$page);
  }//method

}//class     
