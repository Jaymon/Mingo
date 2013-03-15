<?php
/**
 *  maps a mingo collection or table to an object, this is the ORM part of the mingo
 *  package
 *  
 *  all ORMs in your project that use mingo should extend this class and implement
 *  the start() method 
 *
 *  this class reserves some keywords as special: 
 *    - _id = the unique id of the row, always set for both mongo and sql
 *    - updated = holds a unix timestamp of the last time the row was saved into the db, always set
 *    - created = holds a unix timestamp of when the row was created, always set  
 *  
 *  @abstract 
 *  @version 0.9.1
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 11-14-09
 *  @package mingo 
 ******************************************************************************/
abstract class MingoOrm extends MingoMagic {

  /**
   *  every row that has, or will be, saved into the db will carry an _id
   *  
   *  the id is usually a ~24 character hash/string
   */
  const _ID = '_id';
  
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
   * set to true if the internal field map is changed in any way
   *
   * @since 2013-3-14
   * @var boolean
   */
  protected $is_modified = false;
  
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
   *  return a query object that will be used to query the db
   *     
   *  @since  4-3-12      
   *  @param  MingoInterface  $db the db connection to use to perform the query
   *  @return MingoQuery
   */
  public static function createQuery(MingoInterface $db = null){
  
    return new MingoQuery(get_called_class(), $db);
  
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
    $table->setField(self::_CREATED, MingoField::TYPE_INT);
    $table->setField(self::_UPDATED, MingoField::TYPE_INT);
    
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
   * return true if the map has been modified and can be set() into the db
   *
   * @since 2013-3-14
   * @return  boolean
   */
  public function isModified(){ return $this->is_modified; }//method
  
  /**
   *  save this instance into the db
   *  
   *  @return boolean true if db successfully saved, false otherwise
   */
  public function set(){

    // canary
    if(empty($this->is_modified)){ return false; }//if
  
    $db = $this->getDb();
    $this->_set();
    $this->field_map = $db->set($this->getTable(), $orm->getFields());
    $this->is_modified = false;
    return true;
  
  }//method
  
  /**
   * called right before $this is set into the db
   *
   * it just gives you an opportunity to manipulate the internal field map right before
   * it is saved into the db, but after all the error checking is done
   *  
   * @since  10-18-11
   */
  protected function _set(){}//method
  
  /**
   *  remove all the rows in this instance from the db
   *  
   *  @return boolean will only return true if all the rows were successfully removed, false otherwise      
   */
  public function kill(){

    $ret_bool = false;
    $query = static::createQuery(get_class($this), $this->getDb());
    if($query->is_id($this->get_id())->kill()){
      $this->kill_id();
      $this->kill_updated();
      $this->kill_created();
      $ret_bool = true;
    }//if

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
   *  resets the class's internal stuff, basically restore class virginity, but keep stuff
   *  like db and table (stuff that doesn't change)
   */
  public function reset(){
    $this->field_map = array();
    $this->is_modified = false;
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
    if(!$this->hasFields()){ return $default_val; }//if
  
    $ret_mixed = $this->handleCall('get', $name, array($default_val), null);
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
    if(!$this->hasFields()){ return false; }//if
  
    return $this->handleCall('has', $name, array(), true);
  
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
    if(!$this->hasFields()){ return false; }//if
  
    return $this->handleCall('exists', $name, array(), true);
  
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
  public function setField($name, $val){
  
    return $this->handleCall('set', $name, array($val), $this);
    $ref_name = '';
    if($ref_map = &$this->getRef($name, $ref_name, -1)){
      \out::e($ref_map, $ref_name);
      $ref_map[$ref_name] = $val;
    }//if

    \out::e($ref_map, $this->field_map);

    return $this;
  
  }//method

  protected function &getRef($name, &$ref_name, $count_offset){

    $table = $this->getTable();
    $field_instance = $table->getField($name);
    $ref_map = &$this->field_map;
  
    $field_list = (array)$field_instance->getName();
    $ref_name = $field_list[0];
    $last_i = count($field_list) + $count_offset;

    for($i = 0; $i < $last_i; $i++){
      $ref_name = $field_list[$i];
      if(isset($ref_map[$ref_name])){
        $ref_map = &$ref_map[$ref_name];
      }else{
        $ret_bool = false;
        break;
      }//if/else
    }//for

    return $ref_map;

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
  
    return $this->handleCall('bump', $name, array($count), $this);
  
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
  public function attachField($name, $val){
  
    // canary, need more than 1 arg...
    $args = func_get_args();
    $args = array_slice($args,1);
  
    return $this->handleCall('attach', $name, $args, $this);
  
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
    if(!$this->hasFields()){ return $this; }//if

    $args = func_get_args();
    $args = array_slice($args,1);
  
    return $this->handleCall('clear', $name, $args, $this);
  
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
  
    return $this->handleCall('kill', $name, array(), $this);
  
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
    if(!$this->hasFields()){ return false; }//if
  
    return $this->handleCall('is', $name, array($val), true);
  
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
  public function inField($name, $val){
  
    // canary...
    if(!$this->hasFields()){ return false; }//if

    // canary, need more than 1 arg...
    $args = func_get_args();
    $args = array_slice($args,1);
    
    return $this->handleCall('in', $name, $args, false);
  
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
  protected function handleCall($command, $name, $args, $ret_mixed = null){
  
    $table = $this->getTable();
    $field_instance = $table->getField($name);
  
    $field_list = (array)$field_instance->getName();
    $ret_val_list = array();
    $ret_found_list = array();
    $field_last_i = count($field_list) - 1;
  
    $field_i = 0;
    $field_ref = &$this->field_map;

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
      
        // break 2 - we are completely done with processing, we've found a final
        // value
        // break - we have an answer for this map's field, move onto the next map's field
      
        switch($command){
        
          case 'get':
          
            if(isset($field_ref[$field])){
              $ret_mixed = $field_instance->normalizeInVal($field_ref[$field]);
            }else{
              $ret_mixed = $args[0];
            }//if/else
          
            break;
        
          case 'has':
          
            if(empty($field_ref[$field])){
              $ret_mixed = false;
              break 2; // we're done, no need to go through any more rows
            }//if
          
            break;
        
          case 'set':
          
            $this->is_modified = true;
            
            $field_ref[$field] = $field_instance->normalizeInVal($args[0]);
            $ret_mixed = true;
            break;
            
          case 'bump':
          
            $this->is_modified = true;
            
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
            if($ret_mixed === false){ break 2; }//if
          
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
              break 2;
            }//if
          
            break;
            
          case 'attach':
          
            // the field has to exist...
            if(!isset($field_ref[$field])){
              throw new UnexpectedValueException('you can\'t attach to a field that doesn\'t exist');
            }//if
            
            $this->is_modified = true;
            
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
              break 2; // we're done, no need to go through any more rows
            }//if
          
            break;
          
          case 'kill':
          
            if(isset($field_ref[$field])){
              $this->is_modified = true;
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
                    
                      $this->is_modified = true;
                      unset($field_ref[$field]);
                    
                    }//if
                  
                  }//if
                  
                  // might have matched the full array, so only check each row if still set...
                  if(isset($field_ref[$field])){
                    
                    // we need all the keys to get rid of them...
                    $field_keys = array_keys($field_ref[$field],$arg,true);
                    foreach($field_keys as $field_key){
                      $this->is_modified = true;
                      unset($field_ref[$field][$field_key]);
                    }//foreach
                    
                  }//if
                  
                }//foreach
              
              }else{
              
                if(in_array($field_ref[$field],$args,true)){
                
                  $this->is_modified = true;
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
    
    return $ret_mixed;
  
  }//method
  
  /**
   * I'm slowly moving this to one orm represents one row, sadly, there is still
   * a lot code that needs to be changed/updated, this should be used over attach()
   * for replacing the internal contents of an orm
   *
   * @since 2013-3-7
   * @param array $map  the fields you want to set into the orm
   */
  public function setFields(array $field_map){
    $this->reset();
    $this->is_modified = true;
    parent::setFields($field_map);
    return $this;
  }//method
  
}//class     
