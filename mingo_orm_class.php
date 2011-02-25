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
 *  @version 0.7
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
  
    $this->setTable(get_class($this));
  
    $schema = new mingo_schema();
    
    // set some of the default fields...
    $field = new mingo_field(self::CREATED,mingo_field::TYPE_INT);
    $schema->setField($field);
    $field = new mingo_field(self::UPDATED,mingo_field::TYPE_INT);
    $schema->setField($field);
  
    $this->setSchema($schema);
    
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
  
  public function setDb(mingo_db $db = null){ $this->db = $db; }//method
  public function hasDb(){ return !empty($this->db); }//method
  
  /**
   *  return the db object that this instance is using
   *  
   *  @return mingo_db  an instance of the db object that will be used
   */
  public function getDb(){
    
    if($this->db === null){
    
      // get all the names of this class and all parents in order to find the right instance...
      $class = get_class($this);
      $parent_list = array();
      // via: http://us2.php.net/manual/en/function.get-parent-class.php#57548
      for($parent_list[] = $class; $class = get_parent_class($class); $parent_list[] = $class);
    
      $this->setDb(mingo_db::getInstance($parent_list));
      
      if(empty($this->db)){
        throw new UnexpectedValueException('a valid mingo_db instance could not be found');
      }//if
      
      if(!$this->db->isConnected()){ $this->db->connect(); }//if
      
    }//if
    
    return $this->db;
    
  }//method
  
  public function setSchema($schema){ $this->schema = $schema; }//method
  public function getSchema(){ return $this->schema; }//method
  
  protected function setCriteria($criteria){ $this->criteria = $criteria; }//method
  public function getCriteria(){ return $this->criteria; }//method
  public function hasCriteria(){ return !empty($this->criteria); }//method
  
  public function getTable(){ return $this->table; }//method
  
  /**
   *  set the table
   *
   *  @since  10-25-10
   *  @param  string  $table  the table name   
   */
  protected function setTable($table){
  
    // canary...
    if(empty($table)){ throw new UnexpectedValueException('$table cannot be empty'); }//if
  
    $this->table = mb_strtolower($table);
    
  }//method
  
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
    
      $ret_mix = array();
      $total_args = func_num_args();
      
      if($total_args > 1){
      
        foreach($args as $arg){
          if(isset($this->list[$arg])){
            $ret_mix[] = $this->list[$arg]['map'];
          }//if
        }//foreach
        
      }else{
        
        if(isset($this->list[$args[0]])){
          $ret_mix = $this->list[$args[0]]['map'];
        }//if
        
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
    $db = $this->getDb();

    foreach(array_keys($this->list) as $key){
    
      // only try and save if it has changes...
      if(!empty($this->list[$key]['modified'])){
      
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
      
      // make sure the orm now knows it represents multiple rows...
      if($this->count > 1){ $this->setMulti(true); }//if
    
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
  
    // go back to square one...
    $this->reset();
  
    $ret_int = 0;
    $db = $this->getDb();
    $limit = $offset = $limit_paginate = 0;
    $this->setCriteria($where_criteria);
  
    // figure out what criteria to use on the load...
    if(!empty($where_criteria)){
    
      list($limit,$offset,$limit_paginate) = $where_criteria->getBounds();
    
    }//if
    
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
  
    // go back to square one...
    $this->reset();
  
    $ret_bool = false;
    $db = $this->getDb();
    $this->setCriteria($where_criteria);
    
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
  
      $_id_list = array();
  
      // get all the ids...
      if($this->hasField(self::_ID)){
      
        $_id_list = $this->getField(self::_ID);
      
      }else{
      
        for($i = 0; $i < $this->count ;$i++){
      
          if(isset($this->list[$i]['map']['_id'])){
            $_id_list[] = $this->list[$i]['map']['_id'];
          }//if
          
        }//for
      
      }//if/else
      
      if(!empty($_id_list)){
        
        $where_criteria = new mingo_criteria();
        $where_criteria->inField(self::_ID,$_id_list);
        if($db->kill($this->getTable(),$this->schema,$where_criteria)){
          $this->reset();
          $ret_bool = true;
        }//if
        
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
   *  @example  if you want to append to an array or a string field
   *              $this->appendFoo('bar'); // if foo is an array, append a new row (with ''bar' value
   *                                       // if foo is a string, append 'bar' on the end  
   *  
   *  @example  if you want to clear all the matching values from a field
   *              $this->clearFoo('bar'); // clears foo of any 'bar' values (will search within an array)
   *        
   *  @example  you can also reach into arrays...
   *              $this->setFoo(array('bar' => 'che')); // foo now is an array with one key, bar   
   *              $this->getField(array('foo','bar')); // would return 'che'
   *              $this->getFoo(); // would return array('bar' => 'che')
   *                             
   *  @param  string  $method the method that was called
   *  @param  array $args the arguments passed to the function
   *  @return mixed
   */      
  function __call($method,$args){
    
    list($command,$field,$args) = $this->splitMethod($method,$args);
    
    // canary...
    if(!$field->hasName()){
      throw new BadMethodCallException('field cannot be empty');
    }//if
    
    if(is_string($field->getName()) && array_key_exists($field->getName(),get_object_vars($this))){
      throw new BadMethodCallException(sprintf('a field cannot have this name: %s',$field->getName()));
    }//if
    
    $ret_mixed = $this->handleCall($command,$field,$args);
    
    // format the return value...
    if(!$this->isMulti()){
      
      if(is_array($ret_mixed) && !empty($ret_mixed)){
        $ret_mixed = $ret_mixed[0];
      }//if
      
    }//if
    
    return $ret_mixed;
  
  }//method
  
  /**
   *  handle the meet of __call()
   *  
   *  @since  10-5-10   
   *  @param  string  $command  the command that will be executed
   *  @param  mingo_field $field_instance the field to be retrieved   
   *  @param  array $args the arguments passed into the __call method
   *  @return mixed depending on the $command, something is returned
   */
  private function handleCall($command,$field_instance,$args){
  
    $ret_mixed = null;
  
    // canary, do the pre check stuff for each command to make sure we have what we need...
    switch($command){
    
      case 'get':
      
        // canary...
        if(!isset($args[0])){ $args[0] = null; }//if
        if(!$this->hasCount()){ return $this->isMulti() ? array() : $args[0]; }//if
        
        $ret_mixed = array();
        
        break;
    
      case 'has':
      case 'exists':
      
        if(!$this->hasCount()){ return false; }//if
        $ret_mixed = true;
        break;
    
      case 'set':
      case 'bump':
      case 'append':
      case 'clear': // I also liked: wipe, purge, and remove as other names
      
        // canary...
        if(!array_key_exists(0,$args)){
          throw new InvalidArgumentException(
            sprintf('you need to pass in an argument for %s',$command)
          );
        }//if
        
        // set up...
        if(empty($this->list)){ $this->append(array()); }//if
        $ret_mixed = false;
        break;
    
      case 'kill':
        break;
    
      case 'is':
      
        if(empty($args)){
          throw new BadMethodCallException('method had no arguments passed in, so no compare can be made');
        }//if
        if(!$this->hasCount()){ return false; }//if
        $ret_mixed = true;
        break;
      
      case 'in':
      
        if(empty($args)){
          throw new BadMethodCallException('method had no arguments passed in, so no compare can be made');
        }//if
        if(!$this->hasCount()){ return false; }//if
        $ret_mixed = false;
        break;
    
      default:
            
        throw new BadMethodCallException(
          sprintf('could not find a match for command: %s',$command)
        );
        break;
    
    }//switch
  
    $field_list = (array)$field_instance->getName();
    $ret_val_list = array();
    $ret_found_list = array();
    $field_last_i = count($field_list) - 1;
  
    for($list_i = 0; $list_i < $this->count ;$list_i++){
  
      ///$ret_mixed[$i] = $default_val;
      ///$ret_found[$i] = false;
  
      $field_i = 0;
      $field_ref = &$this->list[$list_i]['map'];

      foreach($field_list as $field){
      
        if($field_i < $field_last_i){
        
          // do iterative things because we haven't reached our final destination yet...
        
          switch($command){
          
            case 'set':
            case 'bump':
            
              if(isset($field_ref[$field])){
              
                if(!is_array($field_ref[$field])){
                
                  throw new DomainException(
                    sprintf('Burrowing into [%s] failed because %s is not an array',join(',',$field_list),$field)
                  );
                
                }//if
              
              }else{
              
                $field_ref[$field] = array();
              
              }//if/else
            
              $field_ref = &$field_ref[$field];
            
              break;
            
            case 'get':
            case 'kill':
            case 'has':
            case 'exists':
            case 'is':
            case 'in':
            case 'append':
            case 'clear':
          
              if(isset($field_ref[$field])){
                $field_ref = &$field_ref[$field];
              }else{
                // this field doesn't exist, so kill can just move on to the next list map...
                unset($field_ref);
                break 2;
              }//if/else
            
              break;
              
          }//switch

        }else{
        
          // we've reached our final destination, so do final things...
        
          switch($command){
          
            case 'get':
            
              if(isset($field_ref[$field])){
                $ret_mixed[] = $field_ref[$field];
              }else{
                $ret_mixed[] = $args[0];
              }//if/else
            
              break;
          
            case 'has':
            
              if(empty($field_ref[$field])){
                $ret_mixed = false;
                break 3; // we're done, no need to go through any more rows
              }//if
            
              break;
          
            case 'set':
            
              $this->list[$list_i]['modified'] = true;
              
              $schema_field_instance = $this->schema->getField($field_instance->getName());
              $field_ref[$field] = $schema_field_instance->normalizeInVal($args[0]);
              $ret_mixed = true;
              break;
              
            case 'bump':
            
              $this->list[$list_i]['modified'] = true;
              
              if(!isset($field_ref[$field])){
                $field_ref[$field] = 0;
              }//if
              
              $field_ref[$field] += (int)$args[0];
              $ret_mixed = true;
              break;
              
            case 'is':
            
              if(!isset($field_ref[$field]) || !in_array($field_ref[$field],$args,true)){
                $ret_mixed = false;
                break 3;
              }//if
            
              break;
              
            case 'in':
            
              if(array_key_exists($field,$field_ref)){
              
                if(is_array($field_ref[$field])){
                
                  foreach($args as $arg_i => $arg){
                  
                    // if arg is an array, we want to check the full value, then a sub-value...
                    if(is_array($arg)){
                    
                      if($arg === $field_ref[$field]){
                      
                        unset($args[$arg_i]);
                      
                      }//if
                    
                    }//if
                    
                    // might have matched the full array, so only check each row if still set...
                    if(isset($args[$arg_i])){
                      
                      if(in_array($arg,$field_ref[$field],true)){
                      
                        unset($args[$arg_i]);
                      
                      }//if
                      
                    }//if
                    
                  }//foreach
                
                }else{
                
                  $key = array_search($field_ref[$field],$args,true);
                  if($key !== false){
                  
                    unset($args[$key]);
                  
                  }//if
                  
                }//if/else
              
              }//if
              
              if(empty($args)){
                $ret_mixed = true;
                break 3;
              }//if
            
              break;
              
            case 'append':
            
              // the field has to exist...
              if(!isset($field_ref[$field])){
                throw new UnexpectedValueException('you can\'t append to a field that doesn\'t exist');
              }//if
              
              $this->list[$list_i]['modified'] = true;
              
              if(is_array($field_ref[$field])){
              
                $field_ref[$field] = array_merge($field_ref[$field],$args);
              
              }else if(is_string($field_ref[$field])){
              
                $field_ref[$field] = sprintf('%s%s',$field_ref[$field],join('',$args));
                
              }else{
              
                throw new RuntimeValueException('you can only append to a string or an array');
              
              }//if/else if/else
              
              $ret_mixed = true;
              break;
              
            case 'exists':
            
              // note would it be better to check if array and do array_key_exists here?
              // isset() catches everything but null values
            
              if(!isset($field_ref[$field])){
                $ret_mixed = false;
                break 3; // we're done, no need to go through any more rows
              }//if
            
              break;
            
            case 'kill':
            
              if(isset($field_ref[$field])){
                $this->list[$list_i]['modified'] = true;
                unset($field_ref[$field]);
              }//if
            
              $ret_mixed = true;
              break;
              
            case 'clear':
            
              if(isset($field_ref[$field])){
              
                if(is_array($field_ref[$field])){
                
                  foreach($args as $arg_i => $arg){
                  
                    // if arg is an array, we want to check the full value, then a sub-value...
                    if(is_array($arg)){
                    
                      if($arg === $field_ref[$field]){
                      
                        $this->list[$list_i]['modified'] = true;
                        unset($field_ref[$field]);
                      
                      }//if
                    
                    }//if
                    
                    // might have matched the full array, so only check each row if still set...
                    if(isset($field_ref[$field])){
                      
                      // we need all the keys to get rid of them...
                      $field_keys = array_keys($field_ref[$field],$arg,true);
                      foreach($field_keys as $field_key){
                        $this->list[$list_i]['modified'] = true;
                        unset($field_ref[$field][$field_key]);
                      }//foreach
                      
                    }//if
                    
                  }//foreach
                
                }else{
                
                  if(in_array($field_ref[$field],$args,true)){
                  
                    $this->list[$list_i]['modified'] = true;
                    unset($field_ref[$field]);
                  
                  }//if
                  
                }//if/else
              
              }//if
            
              $ret_mixed = true;
              break;
          
          }//switch
        
        }//if/else
        
        $field_i++;
        
      }//foreach
      
    }//for
    
    return $ret_mixed;
  
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
      $this->setField($key,array($val));
    }//if/else
  }//method
  /**
   *  Return a value given it's key e.g. echo $A['title'];
   */
  function offsetGet($key){ return $this->getField($key,null); }//method
  /**
   *  Unset a value by it's key e.g. unset($A['title']);
   */
  function offsetUnset($key){ return $this->killField($key); }//method
  /**
   *  Check value exists, given it's key e.g. isset($A['title'])
   */
  function offsetExists($key){  return $this->existsField($key); }//method
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
