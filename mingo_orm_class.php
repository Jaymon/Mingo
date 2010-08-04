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
 *  @abstract 
 *  @version 0.4
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 11-14-09
 *  @package mingo 
 ******************************************************************************/
abstract class mingo_orm extends mingo_base implements ArrayAccess,Iterator,Countable,Serializable {

  /**
   *  every row that has, or will be, saved into the db will carry an _id
   *  
   *  the id is usually a 24 character hash/string
   */
  const _ID = '_id';
  /**
   *  this is an auto-increment row id 
   *  
   *  the row_id is an integer
   */
  const ROW_ID = 'row_id';
  /**
   *  when the row was last updated
   *  
   *  a unix timestamp, which is an integer
   */
  const UPDATED = 'updated';
  /**
   *  when the row was created
   *  
   *  a unix timestamp, which is an integer
   */
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
   *  the internal representation of the lists that this class represents
   *  @var  array an array with each index having 'modified' and 'map' keys
   */
  protected $list = array();

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
   *  usually, the schema will be populated/defined in the child class's {@link start()}
   *  
   *  @var  mingo_schema            
   */
  protected $schema = null;
  
  /**
   *  set to true if you want this instance to act like an array even if {@link hasCount()}
   *  equals 1 (ie, this instance only represents one row)
   *  
   *  @var  boolean
   */
  protected $multi = false;
  
  /**
   *  this will point to the last loaded criteria object passed into methods like load()
   *  
   *  @see  load(), loadOne()
   *      
   *  @var  mingo_criteria
   */
  protected $criteria = null;
  
  /**
   *  holds this class's db access instance
   *  
   *  you can't touch this object directly in your class (eg, $this->db), instead, always
   *  get this object by using {@link getDb()}   
   *         
   *  @var  mingo_db
   */
  private $db = null;

  /**
   *  default constructor
   *  
   *  @param  array|string  $_id_list one or more unique _ids to load into this instance      
   */
  final public function __construct($_id_list = array()){
  
    $this->table = mb_strtolower(get_class($this));
  
    $this->setSchema(new mingo_schema($this->table));
    
    // do the child's initializing stuff, like setting ORM specific schema stuff...
    $this->start();
    
    // load the id list if passed in...
    $this->loadBy_Id($_id_list);
  
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
  public function getCount(){ return $this->count; }//method
  public function hasCount(){ return !empty($this->count); }//method
  public function isCount($count){ return ($this->count == $count); }//method
  /**
   *  Required definition for Countable, allows count($this) to work
   *  @link http://www.php.net/manual/en/class.countable.php
   */
  function count(){ return $this->getCount(); }//method

  /**
   *  return true if the last db load could load more, but was limited by $limit 
   *
   *  @return boolean
   */
  public function hasMore(){ return !empty($this->more); }//method
  protected function setMore($val){ $this->more = $val; }//method
  protected function getMore(){ return $this->more; }//method
  
  public function setDb($db){ $this->db = $db; }//method
  public function hasDb(){ return !empty($this->db); }//method
  
  /**
   *  return the db object that this instance is using
   *  
   *  @return mingo_db  an instance of the db object that will be used
   */
  public function getDb(){
    
    if($this->db === null){
      $this->setDb(mingo_db::getInstance());
      
      if(empty($this->db)){
        throw new UnexpectedValueException('a valid mingo_db instance could not be found');
      }//if
      
    }//if
    
    return $this->db;
    
  }//method
  
  public function setSchema($schema){ $this->schema = $schema; }//method
  public function getSchema(){ return $this->schema; }//method
  
  protected function setCriteria($criteria){ $this->criteria = $criteria; }//method
  public function getCriteria(){ return $this->criteria; }//method
  
  public function getTable(){ return $this->table; }//method
  
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
   *    $foo->setMulti(true);
   *    $foo->get_id(); // array(0 => '4z4b59960417cff191587927')        
   *                
   *  
   *  @param  boolean $val
   */
  public function setMulti($multi){ $this->multi = $multi; }//method
  public function isMulti(){ return !empty($this->multi); }//method
  
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
    
    }else{
    
      $total_args = func_num_args();
      if($total_args > 1){
        foreach($args as $arg){ $ret_mix[] = $this->rip($arg); }//foreach
      }else{
        $ret_mix = $this->rip($args[0]);
      }//if
    
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
    
      $total_args = func_num_args();
      if($total_args > 1){
        foreach($args as $arg){ $ret_mix[] = $this->list[$args[0]]['map']; }//foreach
      }else{
        $ret_mix = $this->list[$args[0]]['map'];
      }//if
    
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
    $db = $this->getDb();

    foreach(array_keys($this->list) as $key){
    
      // only try and save if it has changes...
      if(!empty($this->list[$key]['modified'])){
      
        // add created and last touched fields...
        if(empty($this->list[$key]['map'][self::CREATED])){ 
          $this->list[$key]['map'][self::CREATED] = $now;
        }//if
        $this->list[$key]['map'][self::UPDATED] = $now;
      
        $this->list[$key]['map'] = $db->set(
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
  
    if(is_object($map)){
    
      $class_name = get_class($this);
    
      if($map instanceof $class_name){
      
        // go through each row this class tracks internally...
        foreach($map->getList() as $m){
          $this->append($m['map'],$m['modified']);
        }//method
        
      }else{
      
        throw new InvalidArgumentException(
          sprintf(
            '$map is not the correct instance. Expected %s, but got %s',
            $class_name,
            get_class($map)
          )
        );
      
      }//if/else
    
    }else if(is_array($map)){
    
      $this->list[$this->count] = array();
      $this->list[$this->count]['map'] = $map;
      $this->list[$this->count]['modified'] = $is_modified;
      $this->count++;
    
    }else{
    
      throw new InvalidArgumentException(sprintf('trying to append an unsupported $map type: %s',gettype($map)));
    
    }//if/else if/else
  
    return true;
  
  }//method
  
  protected function setTotal($val){ $this->total = $val; }//method
  public function getTotal(){ return $this->total; }//method
  public function hasTotal(){ return !empty($this->total); }//method
  
  /**
   *  load the total number of rows $where_criteria touches
   *  
   *  this is like doing a select count(*)... sql query
   *  
   *  @param  mingo_criteria  $where_criteria  criteria that will restrict the count
   *  @return integer the total rows counted
   */
  public function loadTotal(mingo_criteria $where_criteria = null){
    
    $db = $this->getDb();
    $this->setTotal(
      $db->getCount($this->getTable(),$this->schema,$where_criteria,$where_criteria->getBounds())
    );
    
    return $this->getTotal();
    
  }//method
  
  /**
   *  load the contents from the db
   *  
   *  contents are loaded passing in a $where_criteria that is used to load the 
   *  internal contents of this instance
   *  
   *  @example                      
   *    // load something using $where_criteria:
   *    $mc = new mingo_criteria();
   *    $mc->in_id('4affd9e8da7f000000003645');
   *    $instance->load($mc);
   *
   *  @param  mingo_criteria  $where_criteria  criteria for loading db rows into this object
   *  @param  boolean $set_load_total if true, then {@link getTotal()} will return how many results
   *                                  are possible (eg, if you have a limit of 10 but there are 100
   *                                  results matching $where_criteria, getTotal() will return 100      
   *  @return integer how many rows were loaded
   */
  function load(mingo_criteria $where_criteria = null,$set_load_total = false){
  
    $ret_int = 0;
    $db = $this->getDb();
    $limit = $offset = $limit_paginate = 0;
    $this->setCriteria($where_criteria);
  
    // figure out what criteria to use on the load...
    if(!empty($where_criteria)){
    
      list($limit,$offset,$limit_paginate) = $where_criteria->getBounds();
    
    }//if
    
    // go back to square one...
    $this->reset();
    
    // get stuff from the db...
    $list = $db->get(
      $this->getTable(),
      $this->schema,
      $where_criteria,
      array($limit_paginate,$offset)
    );
    
    // re-populate this instance...
    if(!empty($list)){
      
      // set whether more results are available or not...
      $ret_int = count($list);
      if(!empty($limit_paginate) && ($ret_int == $limit_paginate)){
        
        // cut off the final row since it wasn't part of the original requested rows...
        $list = array_slice($list,0,-1);
        $this->setMore(true);
        $ret_int--;
        
        if($set_load_total){
          $bounds_map = $where_criteria->getBounds();
          $where_criteria->setBounds(0,0);
          $this->loadTotal($where_criteria);
          $where_criteria->setBounds($bounds_map);
        }else{
          $this->setTotal($ret_int);
        }//if/else
        
      }else{
      
        $this->setMore(false);
        $this->setTotal($ret_int);
        
      }//if/else
      
      // we append so that the structure of the internal map is maintained...
      foreach($list as $map){ $this->append($map); }//foreach
      
    }//if
    
    if($ret_int > 0){
      
      if($ret_int === 1){
        $this->setMulti(false);
      }else{
        $this->setMulti(true);
      }//if/else
      
    }//if
    
    return $ret_int;
  
  }//method
  
  /**
   *  load one row from the db
   *  
   *  @example                      
   *    // load something using $where_criteria:
   *    $mc = new mingo_criteria();
   *    $mc->is_id('4affd9e8da7f000000003645');
   *    $this->loadOne($mc);
   *
   *  @param  mingo_criteria  $where_criteria  criteria for loading db rows into this object      
   *  @return boolean
   */
  function loadOne(mingo_criteria $where_criteria){
  
    // canary...
    if(empty($where_criteria)){
      throw new InvalidArgumentException('$where_criteria cannot be empty');
    }//if
  
    $ret_bool = false;
    $db = $this->getDb();
    $this->setCriteria($where_criteria);
  
    // go back to square one...
    $this->reset();
    
    // get stuff from the db...
    $map = $db->getOne(
      $this->getTable(),
      $this->getSchema(),
      $where_criteria
    );
    
    // re-populate this instance...
    if(!empty($map)){
      
      $this->setMore(false);
      $this->setTotal(1);
      $this->append($map);
      $this->setMulti(false);
      $ret_bool = true;
      
    }//if
    
    return $ret_bool;
  
  }//method
  
  /**
   *  load by the unique _ids
   *
   *  @param  integer|array $_id_list one or more _ids
   *  @return integer how many rows where loaded
   */
  public function loadBy_Id($_id_list){
  
    // canary...
    if(empty($_id_list)){ return 0; }//if
    
    $ret_bool = false;
    
    $where_criteria = new mingo_criteria();
    if(is_array($_id_list)){
      $where_criteria->inField(self::_ID,$_id_list);
      $ret_bool = ($this->load($where_criteria) > 0) ? true : false;
    }else{
      $where_criteria->isField(self::_ID,$_id_list);
      $ret_bool = $this->loadOne($where_criteria);
    }//if/else
  
    return $ret_bool;
  
  }//method
  
  /**
   *  remove all the rows in this instance from the db
   *  
   *  @param  mingo_criteria  $where_criteria  criteria for deleting db rows   
   *  @return boolean will only return true if all the rows were successfully removed, false otherwise      
   */
  function kill(mingo_criteria $where_criteria = null){
  
    $ret_bool = false;
    $db = $this->getDb();
  
    if($where_criteria !== null){
    
      $ret_bool = $db->kill($this->getTable(),$this->schema,$where_criteria);
        
    }else{
  
      if($this->hasField(self::_ID)){
      
        // get all the ids...
        $where_criteria = new mingo_criteria();
        $where_criteria->inField(self::_ID,$this->getField(self::_ID));
        if($db->kill($this->getTable(),$this->schema,$where_criteria)){
          $this->reset();
          $ret_bool = true;
        }//if
      
      }else{
      
        $ret_bool = true;
      
        for($i = 0; $i < $this->count ;$i++){
      
          if(isset($this->list[$i]['map']['_id'])){
          
            $where_criteria = new mingo_criteria();
            $where_criteria->isField(self::_ID,$this->list[$i]['map']['_id']);
            
            if($db->kill($this->getTable(),$this->schema,$where_criteria)){
            
              unset($this->list[$i]);
              $this->count--;
            
            }else{
            
              $ret_bool = false;
              
            }//if/else
        
          }else{
          
            $ret_bool = false;
            
          }//if/else
          
        }//for
      
        if($ret_bool){
        
          // everything was successfully washed...
          $this->reset();
        
        }else{
        
          // we had an error or something didn't get killed, compensate for that...
          
          // re-do the keys...
          $this->list = array_values($this->list);
        
        }//if/else
      
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
  public function install($drop_table = false){
  
    $db = $this->getDb();
  
    if($drop_table){ $db->killTable($this->getTable()); }//if
  
    // create the table...
    if(!$db->setTable($this->getTable(),$this->schema)){
    
      throw new mingo_exception(sprintf('failed in table %s creation',$this->getTable()));
    
    }//if/else
    
    return true;
  
  }//method
  
  /**
   *  resets the class's internal list   
   */
  public function reset(){
  
    $this->current_i = 0;
    $this->list = array();
    $this->count = 0;
    $this->setMulti(false);
    $this->setMore(false);
    $this->setTotal(0);
    $this->setCriteria(null);
  
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
    if(!$this->hasCount()){ return $this->isMulti() ? array() : $args[0]; }//if
    
    $ret_list = array();
    
    for($i = 0; $i < $this->count ;$i++){
    
      $ret_list[] = isset($this->list[$i]['map'][$name]) 
      ? $this->list[$i]['map'][$name] 
      : $args[0];
      
    }//for
    
    // check for just one index and just return that value if found...
    return ($this->isMulti()) ? $ret_list : $ret_list[0];
  
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
    $multi_orig = $this->isMulti();
    $this->setMulti(true);
    $get_list = $this->handleGet($name);
    $this->setMulti($multi_orig);
    
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
    $multi_orig = $this->isMulti();
    $this->setMulti(true);
    $get_list = $this->handleGet($name);
    $this->setMulti($multi_orig);
    
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
   *  close the db connection so we don't run afoul of anything when serializing this
   *  class   
   *  
   *  @note if your child class has private variables it will have to overload this
   *        method to save those private variables, just keep that in mind   
   *      
   *  required method for Serializable interface, I chose this over __sleep() because
   *  I was having a private variable problem.   
   *      
   *  http://www.php.net/manual/en/class.serializable.php   
   *  
   *  @return the properties of this class serialized      
   */
  public function serialize(){
  
    $this->setDb(null);
    $property_map = get_object_vars($this);
    return serialize($property_map);
  
  }//method
  
  /**
   *  unserialize and restore property values
   *
   *  @note if your child class has private variables it will have to overload this
   *        method to restore those private variables   
   *      
   *  required method for Serializable interface:
   *  http://www.php.net/manual/en/class.serializable.php   
   */
  public function unserialize($serialized){
  
    $property_map = unserialize($serialized);
    
    foreach($property_map as $property_name => $property_val){
    
      $this->{$property_name} = $property_val;
    
    }//foreach
    
  }//method
  
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

}//class     
