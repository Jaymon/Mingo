<?php

/**
 *  maps a mingo collection or table to an object, this is the ORM part of the mingo
 *  package
 *  
 *  all mingo ORMs should extend this class
 *
 *  @todo
 *    1 - move limit and offset (change offset to page) to the schema object, but have this
 *        class do the limit+1 and set ->hasMore() or ->canLoadMore() if there are more db results  
 *  
 *  @version 0.1
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 11-14-09
 *  @package mingo 
 ******************************************************************************/
class mingo_map implements ArrayAccess,Iterator,Countable {

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
   *  how many rows calling {@link load()} will actually load
   *  @var  integer   
   */
  protected $limit = 0;
  /**
   *  where {@link load()} will start loading
   *  @var  integer   
   */
  protected $offset = 0;
  
  /**
   *  hold the schema object for this instance
   *  
   *  usually, the schema will be populated/defined in the child class's __construct()
   *  
   *  @var  mingo_schema            
   */
  protected $schema = null;

  /**
   *  default constructor
   *  
   *  YOU MUST CALL THIS IN ANY CHILD CLASS THAT EXTENDS THIS CLASS (eg, parent::__construct() in
   *  the child's __construct() method) BEFORE DOING ANYTHING ELSE IN __construct()
   */
  function __construct(){
  
    $this->table = mb_strtolower(get_class($this));
  
    $this->setDb(mingo_db::getInstance());
    
    $this->setSchema(new mingo_schema($this->table));
  
  }//method
  
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
   *  returns true if this instance is representing 1 or more rows
   *  
   *  @return boolean      
   */
  function has(){ return $this->hasCount(); }//method
  
  function setLimit($val){ $this->limit = $val; }//method
  function getLimit(){ return $this->limit; }//method
  function hasLimit(){ return !empty($this->limit); }//method
  
  function setOffset($val){ $this->offset = $val; }//method
  function getOffset(){ return $this->offset; }//method
  function hasOffset(){ return !empty($this->offset); }//method
  
  function setDb($db){ $this->db = $db; }//method
  function setSchema($schema){ $this->schema = $schema; }//method
  
  function getTable(){ return $this->table; }//method
  
  /**
   *  get the internal representation of this class
   *  
   *  the better method to use is {@link get()} because it will create instances
   *  for all the rows that get ripped from this isntance, this returns the raw
   *  list only
   *  
   *  @return array the internal {@link $list} array
   */
  function getList(){ return $this->list; }//method

  /**
   *  returns a list of all the maps this class contains
   *  
   *  @param  integer $i  the index we want to get if we don't want all of them      
   *  @return array|object
   */
  function get(){
  
    $ret_mix = null;
    $args = func_get_args();
  
    if(empty($args)){
    
      $ret_mix = array();
    
      foreach($this as $map){ $ret_mix[] = $map; }//foreach
      
      // we only have one index, so return that...
      if(!isset($ret_mix[1])){ $ret_mix = $ret_mix[0]; }//if
    
    }else{
    
      $ret_mix = $this->rip($args[0]);
    
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
        if(empty($this->list[$key]['map']['created'])){ 
          $this->list[$key]['map']['created'] = $now;
        }//if
        $this->list[$key]['map']['updated'] = $now;
      
        $this->list[$key]['map'] = $this->db->update(
          $this->getTable(),
          $this->list[$key]['map'],
          null,
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
   *    load something using $where_map
   *      $instance->load(array('_id' => '4affd9e8da7f000000003645'));
   *
   *  @param  mingo_criteria  $where_criteria  criteria for loading db rows into this object
   *  @return integer how many rows were loaded
   */
  function load(mingo_criteria $where_criteria = null){
  
    // canary...
    if(empty($where_criteria)){
    
      if($this->count > 1){
        throw new mingo_exception('no $where_criteria passed in and one could not be inferred because count > 1');
      }//if
      
      if(!empty($this->list[0]['map'])){
        $where_criteria = new mingo_criteria($this->list[0]['map']);
      }//if
    
    }//if
    
    $list = $this->db->get($this->getTable(),$where_criteria,array($this->getLimit(),$this->getOffset()));
    
    $this->reset();
    
    foreach($list as $map){
      $this->append($map);
    }//foreach
    
    return $this->count;
  
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
      $where_map = array('_id' => $this->get_id());
      if($this->db->delete($this->getTable(),$where_map)){
        $this->reset();
        $ret_bool = true;
      }//if
    
    }else{
    
      $ret_bool = true;
    
      for($i = 0; $i < $this->count ;$i++){
    
        $where_map = array();
    
        if(isset($this->list[$i]['map']['_id'])){
      
          $where_map = array('_id' => $this->list[$i]['map']['_id']);
          
        }else{
        
          $where_map = $this->list[$i]['map'];
        
        }//if/else
        
        // we don't want to accidently delete everything by passing in an empty where...
        if(!empty($where_map)){
        
          if($this->db->delete($this->getTable(),$where_map)){
          
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
   *  @param  string  $method the method that was called
   *  @param  array $args the arguments passed to the function
   *  @return mixed
   */      
  function __call($method,$args){
  
    // canary...
    if(empty($this->list)){ $this->append(array()); }//if
  
    $method = mb_strtolower($method);
    
    $method_map = array(
      'set' => 'handleSet',
      'get' => 'handleGet',
      'has' => 'handleHas',
      'is' => 'handleIs',
      'exists' => 'handleExists',
      'kill' => 'handleKill',
      'bump' => 'handleBump'
    );
    
    foreach($method_map as $key => $callback){
    
      if(mb_strpos($method,$key) === 0){
      
        $name = mb_substr($method,mb_strlen($key));
        
        /* currently this can't be supported because set(), and get() are actual
        functions...
        if(empty($name)){
        
          // since there was no name, the first argument is the name...
          if(empty($args[0])){
            throw new mingo_exception('no name found, and none given in argument 1');
          }//if
          
          $name = mb_strtolower($args[0]);
          $args = array_slice($args,1);
        
        }//if */
      
        if(array_key_exists($name,get_object_vars($this))){
        
          throw new mingo_exception(sprintf('a field cannot have this name: %s',$name));
        
        }else{
      
          $ret_mix = $this->{$callback}($name,$args);
          
          if(is_array($ret_mix)){
          
            // we only have one index, so return that...
            if(!isset($ret_mix[1])){ $ret_mix = $ret_mix[0]; }//if
          
          }//if
          
          return $ret_mix;
          
        }//if/else
      
      }//if
    
    }//foreach
  
    throw new mingo_exception(sprintf('could not find a match for $method %s',$method));
  
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
   *  @return mixed whatever value is found in {@link $db_map}, otherwise $arg[0] (default_val)
   */
  private function handleGet($name,$args = array()){
  
    // canary...
    if(!isset($args[0])){ $args[0] = null; }//if
    
    $ret_list = array();
    
    for($i = 0; $i < $this->count ;$i++){
    
      $ret_list[] = isset($this->list[$i]['map'][$name]) 
      ? $this->list[$i]['map'][$name] 
      : $args[0];
      
    }//for
  
    return $ret_list;
  
  }//method
  
  /**
   *  internally handles all the has* functions of this class
   *  
   *  @param  string  $name the name of the index in the current map to get
   *  @param  array $args the passed in value
   *  @return boolean true if the $name exists and is non-empty, false otherwise
   */
  private function handleHas($name,$args = array()){
    
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
    // make sure the $name is present in all the maps, otherwise if fails...
    if(!$this->handleExists($name)){ return false; }//if
    
    $ret_bool = true;
    $get_list = $this->handleGet($name);
    
    foreach($get_list as $val){
    
      if(!in_array($val,$args,true)){
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
    
    for($i = 0; $i < $this->count ;$i++){
    
      if(!isset($this->list[$i]['map'][$name])){
        $this->list[$i][$name] = 0;
      }//if
      
      $this->list[$i]['modified'] = true;
      $this->list[$i][$name] += $args[0];
    
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
   *  Required definition of interface ArrayAccess
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
      $this->__call(sprintf('set%s',$key),array($val));
    }//if/else
  }//method
  /**
   *  Return a value given it's key e.g. echo $A['title'];
   */
  function offsetGet($key){ return $this->__call(sprintf('get%s',$key),array()); }//method
  /**
   *  Unset a value by it's key e.g. unset($A['title']);
   */
  function offsetUnset($key){ return $this->__call(sprintf('kill%s',$key)); }//method
  /**
   *  Check value exists, given it's key e.g. isset($A['title'])
   */
  function offsetExists($key){  return $this->__call(sprintf('exists%s',$key)); }//method
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

}//class     
