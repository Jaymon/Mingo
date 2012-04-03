<?php
/**
 *  maps a mingo collection or table to an object, this is the ORM part of the mingo
 *  package
 *  
 *  all ORMs in your project that use mingo should extend this class and implement
 *  the start() method 
 *
 *  this class reserves some keywords as special: 
 *    - _rowid = the auto increment key, always set on SQL db
 *    - _id = the unique id of the row, always set for both mongo and sql
 *    - updated = holds a unix timestamp of the last time the row was saved into the db, always set
 *    - created = holds a unix timestamp of when the row was created, always set  
 *  
 *  @abstract 
 *  @version 0.9
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 11-14-09
 *  @package mingo 
 ******************************************************************************/
abstract class MingoOrm extends MingoMagic implements Iterator,Countable {

  /**
   *  every row that has, or will be, saved into the db will carry an _id
   *  
   *  the id is usually a ~24 character hash/string
   */
  const _ID = '_id';
  
  /**
   *  this is an auto-increment row id 
   *  
   *  the _rowid is an integer
   */
  const _ROWID = '_rowid';
  
  /**
   *  when the row was last updated
   *  
   *  a unix timestamp, which is an integer
   */
  const _UPDATED = '_updated';
  
  /**
   *  when the row was created
   *  
   *  a unix timestamp, which is an integer
   */
  const _CREATED = '_created';

  /**
   *  holds the table that this class will access in the db
   *  
   *  @see  getTable(), setTable()      
   *  @var  MingoTable
   */
  protected $table = null;
  
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
   *  @var  MingoCriteria
   */
  protected $criteria = null;
  
  /**
   *  holds this class's db access instance
   *  
   *  you can't touch this object directly in your class (eg, $this->db), instead, always
   *  get this object by using {@link getDb()}   
   *         
   *  @var  MingoInterface
   */
  private $db = null;

  /**
   *  default constructor
   *  
   *  @param  array|string  $_id_list one or more unique _ids to load into this instance      
   */
  public function __construct($_id_list = array()){
    
    // load the id list if passed in...
    $this->loadBy_Id($_id_list);
  
  }//method
  
  /**
   *  return a query object that will be used to query the db
   *     
   *  @since  4-3-12      
   *  @param  MingoInterface  $db the db connection to use to perform the query
   *  @return MingoQuery
   */
  public static function createQuery(MingoInterface $db = null){
  
    return new MingoQuery(get_called_class(),$db);
  
  }//method
  
  /**
   *  get the orm in a state to be serialized
   *
   *  @since  10-3-11
   *  @return array a list of the params to be serialized   
   */
  public function __sleep(){
  
    $map = get_object_vars($this);
    unset($map['db']);
    
    return array_keys($map);
  
  }//method
  
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
  public function count(){ return $this->getCount(); }//method

  /**
   *  return true if the last db load could load more, but was limited by $limit 
   *
   *  @return boolean
   */
  public function hasMore(){ return !empty($this->more); }//method
  protected function setMore($val){ $this->more = $val; }//method
  protected function getMore(){ return $this->more; }//method
  
  public function setDb(MingoInterface $db = null){ $this->db = $db; }//method
  public function hasDb(){ return !empty($this->db); }//method
  
  /**
   *  return the db object that this instance is using
   *  
   *  @see  setDb()   
   *  @return MingoInterface  an instance of the db object that will be used
   */
  public function getDb(){
    
    // canary...
    if(empty($this->db)){
      throw new UnexpectedValueException('a valid MingoInterface instance has not been set using setDb()');
    }//if
    
    return $this->db;
    
  }//method
  
  /**
   *  if the instance has loaded any rows then the criteria that was used should be 
   *  stored using this method
   *  
   *  @param  MingoCriteria $criteria
   */        
  protected function setCriteria(MingoCriteria $criteria = null){ $this->criteria = $criteria; }//method
  public function getCriteria(){ return $this->criteria; }//method
  public function hasCriteria(){ return !empty($this->criteria); }//method
  
  /**
   *  get the table name that this class will use in the db
   *
   *  @since  11-1-11
   *  @return string      
   */
  protected function getTableName(){ return get_class($this); }//method
  
  /**
   *  get the table this class will use on the db side
   *  
   *  @return MingoTable
   */
  public function getTable(){
  
    // canary...
    if(!empty($this->table)){ return $this->table; }//if 
  
    $table = new MingoTable($this->getTableName());
    
    // set some of the default fields...
    $table->setField(self::_ROWID,MingoField::TYPE_INT);
    $table->setField(self::_CREATED,MingoField::TYPE_INT);
    $table->setField(self::_UPDATED,MingoField::TYPE_INT);
    
    // let some custom stuff be added...
    $this->populateTable($table);
    
    $this->setTable($table);
    
    return $this->table;
  
  }//method
  
  /**
   *  set the table
   *
   *  @since  10-25-10
   *  @param  MingoTable  $table  the table with information set
   */
  protected function setTable(MingoTable $table){ $this->table = $table; }//method
  
  /**
   *  add indexes and fields that this orm should know about to the table
   *  
   *  it isn't, by default, required to add anything, but it makes Mingo more aware of the data
   *  it will be wrapping and also might be required by certain Interfaces         
   *
   *  @since  5-2-11
   *  @param  MingoTable  $table
   */
  abstract protected function populateTable(MingoTable $table);
  
  /**
   *  pass in true to make this instance act like it is a list no matter what
   *  
   *  eg, you want to iterate through the results of a get* call using foreach
   *  but there might only be one result (which would normally cause only the value
   *  to be returned instead of a list of values)
   *  
   *  example:
   *    // Foo extends MingoOrm...   
   *    $foo = new Foo();
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
   *  for all the rows that get detached from this instance, this returns the raw
   *  array list only, it needs to be public so {@link attach()} will work when 
   *  attaching other instances of this class   
   *  
   *  @return array the internal {@link $list} array
   */
  public function getList(){ return $this->list; }//method

  /**
   *  returns a list of all the maps this class contains         
   *      
   *  @param  integer $i  the index we want to get if we don't want all of them      
   *  @return array
   */
  public function get($i){
  
    $ret_mix = null;
    $args = func_get_args();
  
    if(empty($args)){
    
      $ret_mix = array();
    
      foreach($this as $map){ $ret_mix[] = $map; }//foreach
    
    }else{
    
      if(isset($args[1])){
        foreach($args as $arg){ $ret_mix[] = $this->detach($arg); }//foreach
      }else{
        $ret_mix = $this->detach($args[0]);
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
  public function getMap($i){
  
    $ret_mix = null;
    $args = func_get_args();
  
    if(empty($args)){
    
      $ret_mix = array();
    
      foreach($this->list as $map){ $ret_mix[] = $map['map']; }//foreach
      
    }else{
    
      $ret_mix = array();
      
      if(isset($args[1])){
      
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
   *  @return integer how many objects were persisted
   */
  public function set(){
  
    $ret_count = 0;
    
    foreach(array_keys($this->list) as $key){
    
      // only try and save if it has changes...
      if(!empty($this->list[$key]['modified'])){
      
        $this->list[$key]['map'] = $this->setOne($this->detach($key));
        $this->list[$key]['modified'] = false; // reset
        $ret_count++;
        
      }//if
      
    }//foreach
  
    return $ret_count;
  
  }//method
  
  /**
   *  just set the individual row
   *  
   *  this is blown out from {@link set()} to make it easier for the child classes
   *  to mess with one row right before saving it
   *  
   *  @since  10-18-11
   *  @param  MingoOrm  $orm  the Orm that represents just one row   
   *  @return array the map from the orm, now saved
   */
  protected function setOne(MingoOrm $orm){
  
    $db = $this->getDb();
    $map = $db->set($this->getTable(),$orm->getMap(0));
    return $map;
  
  }//method
  
  /**
   *  just set the individual map
   *  
   *  this is blown out from {@link set()} to make it easier for the child classes
   *  to mess with the map right before saving it
   *  
   *  @since  6-24-11   
   *  @param  array $map  the map that will be saved into the db   
   *  @return array the same map, but now saved
   */
  protected function setMap(array $map){
  
    $db = $this->getDb();
    $map = $db->set($this->getTable(),$map);
    return $map;
  
  }//method
  
  protected function setTotal($val){ $this->total = $val; }//method
  public function getTotal(){ return $this->total; }//method
  public function hasTotal(){ return !empty($this->total); }//method
  
  /**
   *  load the total number of rows $where_criteria touches
   *  
   *  this is like doing a select count(*)... sql query
   *  
   *  @param  MingoCriteria  $where_criteria  criteria that will restrict the count
   *  @return integer the total rows counted
   */
  public function loadTotal(MingoCriteria $where_criteria = null){
    
    $db = $this->getDb();
    $this->setTotal(
      $db->getCount($this->getTable(),$where_criteria)
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
   *    $mc = new MingoCriteria();
   *    $mc->in_id('4affd9e8da7f000000003645');
   *    $instance->load($mc);
   *
   *  @param  MingoCriteria  $where_criteria  criteria for loading db rows into this object
   *  @param  boolean $set_load_total if true, then {@link getTotal()} will return how many results
   *                                  are possible (eg, if you have a limit of 10 but there are 100
   *                                  results matching $where_criteria, getTotal() will return 100      
   *  @return integer how many rows were loaded
   */
  public function load(MingoCriteria $where_criteria = null,$set_load_total = false){
  
    // go back to square one...
    $this->reset();
  
    $ret_int = 0;
    $db = $this->getDb();
    $limit = $offset = $limit_paginate = 0;
    $c = null;

    // figure out what criteria to use on the load...
    if($where_criteria !== null){
    
      $c = clone $where_criteria; // let's make a deep copy
      list($limit,$offset,$limit_paginate) = $where_criteria->getBounds();
      $c->setLimit($limit_paginate);
      $c->setOffset($offset);
    
    }//if
    
    // get stuff from the db...
    $list = $db->get($this->getTable(),$c);
    
    // re-populate this instance...
    if(!empty($list)){
      
      // set whether more results are available or not...
      $ret_int = count($list);
      if(!empty($limit_paginate) && ($ret_int == $limit_paginate)){
        
        // cut off the final row since it wasn't part of the original requested rows...
        $list = array_slice($list,0,-1);
        $this->setMore(true);
        $ret_int--;
        
        if($set_load_total && ($c !== null)){
          $bounds_map = $c->getBounds();
          $c->setBounds(0,0);
          $this->loadTotal($c);
          $c->setBounds($bounds_map);
        }else{
          $this->setTotal($ret_int);
        }//if/else
        
        $where_criteria->setLimit($limit);
        
      }else{
      
        $this->setMore(false);
        $this->setTotal($ret_int);
        
      }//if/else
      
      // we attach so that the structure of the internal map is maintained...
      array_map(array($this,'attach'),$list);
      ///foreach($list as $map){ $this->attach($map); }//foreach
      
    }//if
    
    $this->setMulti(true); // any load is multi, use loadOne to make multi false
    /* if($ret_int > 0){
      
      if($ret_int === 1){
        $this->setMulti(false);
      }else{
        $this->setMulti(true);
      }//if/else
      
    }//if */
    
    $this->setCriteria($c);
    return $ret_int;
  
  }//method
  
  /**
   *  load one row from the db
   *  
   *  @example                      
   *    // load something using $where_criteria:
   *    $mc = new MingoCriteria();
   *    $mc->is_id('4affd9e8da7f000000003645');
   *    $this->loadOne($mc);
   *
   *  @param  MingoCriteria  $where_criteria  criteria for loading db rows into this object      
   *  @return boolean
   */
  public function loadOne(MingoCriteria $where_criteria){
  
    // go back to square one...
    $this->reset();
  
    $ret_bool = false;
    $db = $this->getDb();
    $this->setCriteria($where_criteria);
    
    // get stuff from the db...
    $map = $db->getOne(
      $this->getTable(),
      $where_criteria
    );
    
    // re-populate this instance...
    if(!empty($map)){
      
      $this->setMore(false);
      $this->attach($map);
      $this->setMulti(false);
      $ret_bool = true;
      
    }//if
    
    return $ret_bool;
  
  }//method
  
  /**
   *  load by the unique _ids
   *
   *  @param  integer|array $_id_list one or more _ids
   *  @return integer how many rows were loaded
   */
  public function loadBy_Id($_id_list){
  
    // canary...
    if(empty($_id_list)){ return 0; }//if
    
    $ret_int = 0;
    
    $where_criteria = new MingoCriteria();
    if(is_array($_id_list)){
      $where_criteria->inField(self::_ID,$_id_list);
      $ret_int = $this->load($where_criteria);
    }else{
      $where_criteria->isField(self::_ID,$_id_list);
      if($this->loadOne($where_criteria)){ $ret_int = 1; }//if
    }//if/else
  
    return $ret_int;
  
  }//method
  
  /**
   *  remove all the rows in this instance from the db
   *  
   *  @param  MingoCriteria  $where_criteria  criteria for deleting db rows   
   *  @return boolean will only return true if all the rows were successfully removed, false otherwise      
   */
  public function kill(MingoCriteria $where_criteria = null){
  
    $ret_bool = false;
    $db = $this->getDb();
  
    if($where_criteria !== null){
    
      $ret_bool = $db->kill($this->getTable(),$where_criteria);
        
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
        
        $where_criteria = new MingoCriteria();
        $where_criteria->inField(self::_ID,$_id_list);
        if($db->kill($this->getTable(),$where_criteria)){
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
   *  using the {@link $table} instance, this method will go through and create
   *  the table and add indexes, etc.
   *  
   *  @param  boolean $drop_table true if you want to drop the table if it exists               
   *  @return boolean
   */
  public function install($drop_table = false){
  
    $db = $this->getDb();
  
    if($drop_table){ $db->killTable($this->getTable()); }//if

    // create the table...
    if(!$db->setTable($this->getTable())){
    
      throw new UnexpectedValueException(sprintf('failed in table %s creation',$this->getTable()));
    
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
   *  get a field
   *  
   *  @example  get a value:
   *              $this->getFoo(); // get the value of 'foo', null if 'foo' not found
   *              $this->getFoo('bar'); //get value of 'foo', 'bar' if 'foo' not found      
   *
   *  @return mixed   
   */
  public function getField($name,$default_val = null){
  
    // canary...
    if(!$this->hasCount()){ return $this->isMulti() ? array() : $default_val; }//if
  
    $ret_mixed = $this->handleCall('get',$name,array($default_val),array());
    
    // format the return value...
    if(!$this->isMulti()){
      
      if(is_array($ret_mixed) && !empty($ret_mixed)){
        $ret_mixed = $ret_mixed[0];
      }//if
      
    }//if
  
    return $ret_mixed;
  
  }//method
  
  /**
   *  true if a field exists and is not-empty
   *
   *  @example  check if value exists and is non-empty
   *              $this->hasFoo(); // returns true if 'foo' exists and is non-empty
   *
   *  @param  string  $name the name of the field   
   *  @return boolean   
   */
  public function hasField($name){
  
    // canary...
    if(!$this->hasCount()){ return false; }//if
  
    return $this->handleCall('has',$name,array(),true);
  
  }//method
  
  /**
   *  true if a field exists
   *
   *  @example  check if value exists                 
   *              $this->existsFoo(); // see if 'foo' exists at all
   *              
   *  @param  string  $name the name of the field
   *  @return boolean         
   */
  public function existsField($name){
  
    // canary...
    if(!$this->hasCount()){ return false; }//if
  
    return $this->handleCall('exists',$name,array(),true);
  
  }//method
  
  /**
   *  set the value of a field
   *
   *  @example  set a value:
   *              $this->setFoo('bar'); // set 'foo' to 'bar
   *
   *  @example  if you want to set a field name with a string var for the name
   *              $this->setField('foo',$val); // *Field($name,...) will work with any prefix
   *      
   *  @param  string  $name the name of the field
   *  @param  mixed $val  the value to set $name to                 
   *  @return MingoOrm
   */
  public function setField($name,$val){
  
    // canary...
    if(empty($this->list)){ $this->attach(array()); }//if
  
    return $this->handleCall('set',$name,array($val),$this);
  
  }//method
  
  /**
   *
   *  @example  bump 'foo' value by $count
   *              $this->bumpFoo($count); // bumps numeric 'foo' value by $count
   *              
   *  @param  string  $name the name of the field
   *  @param  mixed $count  how many to bump by (can be + or -)                 
   *  @return MingoOrm 
   */
  public function bumpField($name,$count = 1){
  
    // canary...
    if(empty($this->list)){ $this->attach(array()); }//if
  
    return $this->handleCall('bump',$name,array($count),$this);
  
  }//method
  
  /**
   *  attach $val onto the end of the field
   *  
   *  @example  if you want to attach to an array or a string field
   *              $this->attachFoo('bar'); // if foo is an array, attach a new row (with ''bar' value
   *                                       // if foo is a string, attach 'bar' on the end      
   * 
   *  @param  string  $name the name of the field
   *  @param  mixed $val,...  one or more values to append
   *  @return MingoOrm   
   */        
  public function attachField($name,$val){
  
    // canary...
    if(empty($this->list)){ $this->attach(array()); }//if
    // canary, need more than 1 arg...
    $args = func_get_args();
    $args = array_slice($args,1);
  
    return $this->handleCall('attach',$name,$args,$this);
  
  }//method
  
  /**
   *  clear the field of a certain value
   * 
   *  @note I also liked: wipe, purge, and remove as other names     
   *  @example  if you want to clear all the matching values from a field
   *              $this->clearFoo('bar'); // clears foo of any 'bar' values (will search within an array)
   *    
   *  @param  string  $name the name of the field
   *  @param  mixed $val,...  one or more values to clear
   *  @return MingoOrm                
   */        
  public function clearField($name,$val){
  
    // canary...
    if(empty($this->list)){ return $this; }//if
    $args = func_get_args();
    $args = array_slice($args,1);
  
    return $this->handleCall('clear',$name,$args,$this);
  
  }//method
  
  /**
   *  unset $name
   *   
   *  @example  get rid of 'foo'
   *              $this->killFoo(); // unsets 'foo' if it exists   
   *
   *  @param  string  $name the name of the field 
   *  @return MingoOrm      
   */
  public function killField($name){
  
    return $this->handleCall('kill',$name,array(),$this);
  
  }//method
  
  /**
   *  the value at $name must be the same as $val 
   *     
   *  @example  see if 'foo' is some value
   *              $this->isFoo('bar'); // true if 'foo' has a value of 'bar'
   *              $this->isFoo('bar','cat'); // true if 'foo' has a value of 'bar', or 'cat'
   *
   *  @param  string  $name the name of the field
   *  @param  mixed $val,...  one or more values to match against
   *  @return boolean      
   */
  public function isField($name,$val){
  
    // canary...
    if(!$this->hasCount()){ return false; }//if
  
    return $this->handleCall('is',$name,array($val),true);
  
  }//method
  
  /**
   *  the value at $name must contain $val
   *  
   *  @example  if you want to see if a field has atleast one matching value
   *              $this->inFoo('bar'); // true if field foo contains bar atleast once      
   *
   *  @param  string  $name the name of the field
   *  @param  mixed $val,...  one or more values to match against
   *  @return boolean   
   */        
  public function inField($name,$val){
  
    // canary...
    if(!$this->hasCount()){ return false; }//if
    // canary, need more than 1 arg...
    $args = func_get_args();
    $args = array_slice($args,1);
    
    return $this->handleCall('in',$name,$args,false);
  
  }//method
  
  /**
   *  handle the meat of the various *Field methods
   *  
   *  @example  you can also reach into arrays...
   *              $this->setFoo(array('bar' => 'che')); // foo now is an array with one key, bar   
   *              $this->getField(array('foo','bar')); // would return 'che'
   *              $this->getFoo(); // would return array('bar' => 'che')
   *      
   *  @since  10-5-10   
   *  @param  string  $command  the command that will be executed
   *  @param  mingo_field $field_instance the field to be retrieved   
   *  @param  array $args the arguments passed into the __call method
   *  @return mixed depending on the $command, something is returned
   */
  protected function handleCall($command,$name,$args,$ret_mixed = null){
  
    $table = $this->getTable();
    $field_instance = $table->getField($name);
  
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
            case 'attach':
            case 'clear':
          
              if(isset($field_ref[$field])){
                $field_ref = &$field_ref[$field];
              }else{
                // this field doesn't exist, so just move on to the next list map...
                unset($field_ref);
                break 2;
              }//if/else
            
              break;
              
          }//switch

        }else{
        
          // we've reached our final destination, so do final things...
        
          // break 3 - we are completely done with processing, we've found a final
          // value
          // break - we have an answer for this map's field, move onto the next map's field
        
          switch($command){
          
            case 'get':
            
              if(isset($field_ref[$field])){
                $ret_mixed[] = $field_instance->normalizeInVal($field_ref[$field]);
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
              
              $field_ref[$field] = $field_instance->normalizeInVal($args[0]);
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
            
              // normalize the arg value for compare...
              $args[0] = $field_instance->normalizeInVal($args[0]);
              
              if(empty($field_ref[$field])){
                
                if(!empty($args[0])){
                  
                  $ret_mixed = false;
                
                }//if
              
              }else{
              
                if(empty($args[0])){
                
                  $ret_mixed = false;
                
                }else{
                
                  if($field_ref[$field] != $args[0]){
                  
                    $ret_mixed = false;
                  
                  }//if
                
                }//if/else
              
              
              }//if/else
              
              // if we find a false then we can end, if true then continue to next value...
              if($ret_mixed === false){ break 3; }//if
            
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
              
            case 'attach':
            
              // the field has to exist...
              if(!isset($field_ref[$field])){
                throw new UnexpectedValueException('you can\'t attach to a field that doesn\'t exist');
              }//if
              
              $this->list[$list_i]['modified'] = true;
              
              if(is_array($field_ref[$field])){
              
                $field_ref[$field] = array_merge($field_ref[$field],$args);
              
              }else if(is_string($field_ref[$field])){
              
                $field_ref[$field] = sprintf('%s%s',$field_ref[$field],join('',$args));
                
              }else{
              
                throw new RuntimeValueException('you can only attach to a string or an array');
              
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
  public function rewind(){ $this->current_i = 0; }//method
  public function current(){ return $this->detach($this->current_i); }//method
  public function key(){ return $this->current_i; }//method
  public function next(){ ++$this->current_i; }//method
  public function valid(){ return isset($this->list[$this->current_i]); }//method
  /**#@-*/
  
  /**
   *  Set a value given it's key e.g. $A['title'] = 'foo';
   */
  public function offsetSet($key,$val){
    
    if($key === null){
      // they are trying to do a $obj[] = $val so let's attach the $val
      // via: http://www.php.net/manual/en/class.arrayobject.php#93100
      $this->attach($val);
    }else{
      // they specified the key, so this will work on the internal objects...
      $this->setField($key,$val);
    }//if/else
    
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
  public function attach($map,$is_modified = false){
  
    // canary...
    if(isset($this->list[$this->count])){ $this->count = count($this->list); }//if
  
    if(is_object($map)){
    
      $class_name = get_class($this);
    
      if($map instanceof $class_name){
      
        // go through each row this class tracks internally...
        foreach($map->getList() as $m){
          $this->attach($m['map'],$m['modified']);
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
    
      throw new InvalidArgumentException(sprintf('trying to attach an unsupported $map type: %s',gettype($map)));
    
    }//if/else if/else
  
    return true;
  
  }//method
  
  /**
   *  get a new instance of this object
   *  
   *  @since  12-21-11
   *  @return self
   */
  protected function getInstance(){
  
    ///$class = get_class($this);
    ///$ret_map = new $class();
  
    $ret_orm = clone $this;
    $ret_orm->reset();
    return $ret_orm;
  
  }//method
  
  /**
   *  rips out the map found at index $i into its own instance
   *  
   *  this class is internal, but you can see it used in {@link current()} and {@link get()}
   *  
   *  @param  integer $i  the index to rip out and return
   *  @return object
   */
  protected function detach($i){
  
    // canary...
    if(!isset($this->list[$i])){ return null; }//if
    
    $ret_map = $this->getInstance();
    
    ///$ret_map = new self(); // returns mingo_orm
    $ret_map->attach(
      $this->list[$i]['map'],
      $this->list[$i]['modified']
    );
    
    return $ret_map;
  
  }//method

}//class     
