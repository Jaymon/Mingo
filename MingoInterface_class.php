<?php
/**
 *  handle mingo db connections transparently between the different interfaces, this
 *  class is used to establish the singleton, and then allow the map to interact
 *  with the db layer.
 *  
 *  if you want to make an interface for mingo, just create a class that extends
 *  this class and implement all the functions below.
 *  
 *  the nice thing about this layer is that it handles all the error checking before
 *  passing anything to the interface, this allows interface developers to focus on the
 *  meat of their interface instead of worrying about error handling
 *  
 *  @notes
 *    - when implementing the interface, you don't really have to worry about error checking
 *      because the public methods handle all the error checking before passing anything 
 *      to the interface, this allows interface developers to focus on the meat of 
 *      their interface instead of worrying about error handling
 *    - in php 5.3 you can set default values for any of the abstract method params without
 *      an error being thrown, in php <5.3 the implemented method signatures have to match
 *      the abstract signature exactly 
 *    - there are certain reserved rows any implementation will have to deal with:
 *      - _id = the unique id assigned to a newly inserted row, this is a 24 character
 *              randomly created string, if you don't want to make your own, and there
 *              isn't an included one (like mongo) then you can use {@link getUniqueId()}
 *              defined in this class
 *      - row_id = this is an auto increment row, ie, the row number. This technically only
 *                 needs to be generated when the backend supports it and is set up (mongo ignores
 *                 it, mysql and sqlite set it) 
 *  
 *  @link http://www.php.net/manual/en/language.oop5.abstract.php    
 *
 *  @version 0.5
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 11-08-09
 *  @package mingo 
 ******************************************************************************/
abstract class MingoInterface extends MingoMagic {
  
  /**
   *  holds the actual db connection, established by calling {@link connect()}
   *  @var  mixed an instance of whatever backend this interface will use
   */
  protected $con_db = null;
  
  /**
   *  hold all the queries this instance has run
   *  @var  array
   */
  protected $query_list = array();
  
  /**
   *  used by {@link getInstance()} to keep a singleton object, the {@link getInstance()} 
   *  method should be the only place this is ever messed with so if you want to touch it, DON'T!  
   *  @var array  an array of mingo_db instances
   */
  private static $instance_map = array();
  
  public function __construct(){}//method
  
  /**
   *  can force class to follow the singelton pattern 
   *  
   *  {@link http://en.wikipedia.org/wiki/Singleton_pattern}
   *  and keeps all db classes to one instance, {@link $instance} should only 
   *  be messed with in this function
   *   
   *  @param  string|array  $class_list return an instance for the given class, if you pass in
   *                                    an array then it would usually be a list of the class and
   *                                    all its parents so inheritance can be respected and a child
   *                                    will receive the right instance if it inherits from a defined 
   *                                    parent         
   *  @return mingo_db  null on failure
   */
  public static function getInstance($class_list = array()){
  
    // canary...
    if(empty($class_list)){
      $class_list = array('MingoOrm');
    }else{
      $class_list = (array)$class_list;
    }//if/else
    
    $ret_instance = null;
    
    // look for a matching instance for the classes...
    foreach($class_list as $class){
      if(!empty(self::$instance_map[$class])){
        $ret_instance = self::$instance_map[$class];
        break;
      }//if
    }//foreach
  
    // if we couldn't find a match, create a new entry...
    if($ret_instance === null){
      self::$instance_map[$class_list[0]] = new self;
      $ret_instance = self::$instance_map[$class_list[0]];
    }//if
    
    return $ret_instance;
    
  }//method
  
  /**
   *  connect to the db
   *  
   *  @param  string  $name  the db name to use, defaults to {@link getName()}
   *  @param  string  $host the host to use, defaults to {@link getHost()}. if you want a specific
   *                        port, attach it to host (eg, localhost:27017 or example.com:27017)            
   *  @param  string  $username the username to use, defaults to {@link getUsername()}
   *  @param  string  $password the password to use, defaults to {@link getPassword()} 
   *  @param  array $options  specific options you might want to use for connecting, defaults to {@link getOptions()}
   *  @return boolean
   */
  public function connect($name = '',$host = '',$username = '',$password = '',array $options = array()){
  
    // set all the connection variables...
    $name = $this->checkField('name',$name,true);
    $host = $this->checkField('host',$host);
    $username = $this->checkField('username',$username);
    $password = $this->checkField('password',$password);
    $options = $this->checkField('options',$options);
    $connected = false;
    
    try{
      
      // actually connect to the interface...
      $connected = $this->_connect(
        $name,
        $host,
        $username,
        $password,
        $options
      );
      
      if($this->hasDebug()){
        if(!is_bool($connected)){
          throw new UnexpectedValueException(
            sprintf('%s is not the expected return type of boolean',
              gettype($connected)
            )
          );
        }//if
      }//if
      
      $this->setField('connected',$connected);
      
    }catch(Exception $e){
    
      if($this->hasDebug()){
      
        $e_msg = array();
        $msg = array();
        foreach($this->field_map as $key => $val){
          $msg[] = sprintf('%s => %s',$key,empty($val) ? '""' : $val);
        }//foreach
        
        $e_msg[] = sprintf(
          'db connection failed with message "%s" and connection variables: [%s]',
          $e->getMessage(),
          join(',',$msg)
        );
        
        $e = new RuntimeException(join(PHP_EOL,$e_msg),$e->getCode(),$e);
        
      }//if
    
      $this->handleException($e);
      
    }//try/catch
    
    return $connected;
  
  }//method
  
  /**
   *  do the actual connecting of the interface
   *
   *  @see  connect()   
   *  @return boolean
   */
  abstract protected function _connect($name,$host,$username,$password,array $options);
  
  /**
   *  return the connected backend db connection.
   *  
   *  this is the raw db connection for whatever backend the interface is using
   *  
   *  @since  1-6-11
   *  @return object
   */
  public function getDb(){ return $this->con_db; }//method
  
  public function setName($val){ return $this->setField('name',$val); }//method
  public function getName(){ return $this->getField('name',''); }//method
  public function hasName(){ return $this->hasField('name'); }//method

  public function setHost($val){ return $this->setField('host',$val); }//method
  public function getHost(){ return $this->getField('host',''); }//method
  public function hasHost(){ return $this->hasField('host'); }//method
  
  public function setUsername($val){ return $this->setField('username',$val); }//method
  public function getUsername(){ return $this->getField('username',''); }//method
  public function hasUsername(){ return $this->hasField('username'); }//method
  
  public function setPassword($val){ return $this->setField('password',$val); }//method
  public function getPassword(){ return $this->getField('password',''); }//method
  public function hasPassword(){ return $this->hasField('password'); }//method
  
  /**
   *  @since  3-6-11
   */
  public function setOptions($val){ return $this->setField('options',$val); }//method
  public function getOptions(){ return $this->getField('options',array()); }//method
  public function hasOptions(){ return $this->hasField('options'); }//method
  
  public function setDebug($val){ $this->setField('debug',(boolean)$val); }//method
  public function getDebug(){ return $this->hasDebug(); }//method
  public function hasDebug(){ return $this->hasField('debug'); }//method
  
  public function isConnected(){ return $this->hasField('connected'); }//method
  
  /**
   *  delete the records that match $where_criteria in $table
   *  
   *  @param  string  $table
   *  @param  MingoSchema $schema the table schema    
   *  @param  MingoCriteria $where_criteria
   *  @return boolean
   */
  public function kill($table,MingoSchema $schema,MingoCriteria $where_criteria){
  
    // canary...
    $this->assure($table);
    if(!$where_criteria->hasWhere()){
      throw new UnexpectedValueException('aborting delete because $where_criteria had no where clause');
    }//if
    
    $ret_bool = false;
    $where_criteria->killSort(); // no need to sort when you're deleting
  
    try{
    
      $ret_bool = $this->_kill($table,$schema,$where_criteria);
      
      if($this->hasDebug()){
        if(!is_bool($ret_bool)){
          throw new UnexpectedValueException(sprintf('%s is not the expected return type of boolean',gettype($ret_bool)));
        }//if
      }//if
    
    }catch(Exception $e){
    
      if($this->handleException($e,$table,$schema)){
        $ret_bool = $this->kill($table,$schema,$where_criteria);
      }//if
    
    }//try/catch
  
    return $ret_bool;
  
  }//method
  
  /**
   *  @see  kill()
   *  @return boolean
   */
  abstract protected function _kill($table,MingoSchema $schema,MingoCriteria $where_criteria);
  
  /**
   *  allows a raw query to be run on the interface
   *  
   *  all this method does is pull out any passed in args, and pass them as a list
   *  to the interface's getQuery() method.   
   *      
   *  It is not recommended that you use this because it is up to the interface 
   *  to decide what params need to be passed in and how they are formatted, so it
   *  locks you into the interface, however, sometimes you just have to run custom
   *  queries on your backend, and this allows you to do that.
   *  
   *  @since  10-8-10   
   *  @param  mixed $args,... as many or as few as you want, it is up to the interface to error check
   *  @return mixed whatever the interface chooses to return
   */
  /* public function getQuery()
  {
    throw new BadMethodCallException('tbi');
    $args = func_get_args();
    return $this->con_db->getQuery($args);
  
  }//method */
  
  /**
   *  get a list of rows matching $where_criteria
   *  
   *  @param  string  $table
   *  @param  MingoSchema $schema the table schema   
   *  @param  MingoCriteria $where_criteria
   *  @param  integer|array $limit  either something like 10, or array($limit,$offset)   
   *  @return array
   */
  public function get($table,MingoSchema $schema,MingoCriteria $where_criteria = null,$limit = 0){
    
    // canary...
    $this->assure($table);
    
    $ret_list = array();
    list($limit,$offset) = $this->getLimit($limit);
    
    try{
    
      $ret_list = $this->_get($table,$schema,$where_criteria,array($limit,$offset));
      
      if($this->hasDebug()){
        if(!is_array($ret_list)){
          throw new UnexpectedValueException(sprintf('%s is not the expected return type of array',gettype($ret_list)));
        }//if
      }//if
    
    }catch(Exception $e){
    
      if($this->handleException($e,$table,$schema)){
        $ret_list = $this->get($table,$schema,$where_criteria,array($limit,$offset));
      }//if
    
    }//try/catch
    
    return $ret_list;

  }//method
  
  /**
   *  @see  get()
   *  @return array   
   */
  abstract protected function _get($table,MingoSchema $schema,MingoCriteria $where_criteria = null,array $limit);
  
  /**
   *  get the first found row in $table according to $where_criteria
   *  
   *  @param  string  $table
   *  @param  MingoSchema $schema the table schema   
   *  @param  MingoCriteria $where_criteria
   *  @return array
   */
  public function getOne($table,MingoSchema $schema,MingoCriteria $where_criteria = null){
    
    // canary...
    $this->assure($table);
    
    $ret_map = array();
    
    try{
    
      $ret_map = $this->_getOne($table,$schema,$where_criteria);
      
      if($this->hasDebug()){
        if(!is_array($ret_map)){
          throw new UnexpectedValueException(sprintf('%s is not the expected return type of array',gettype($ret_map)));
        }//if
      }//if
    
    }catch(Exception $e){
    
      if($this->handleException($e,$table,$schema)){
        $ret_map = $this->getOne($table,$schema,$where_criteria);
      }//if
    
    }//try/catch
    
    return $ret_map;
    
  }//method
  
  /**
   *  @see  getOne()
   *  @return array
   */
  abstract protected function _getOne($table,MingoSchema $schema,MingoCriteria $where_criteria = null);
  
  /**
   *  tell how many records match $where_criteria in $table
   *  
   *  @param  string  $table
   *  @param  MingoSchema $schema the table schema   
   *  @param  MingoCriteria $where_criteria
   *  @param  integer|array $limit  either something like 10, or array($limit,$offset)   
   *  @return integer the count   
   */
  public function getCount($table,MingoSchema $schema,MingoCriteria $where_criteria = null,$limit = 0){
  
    // canary...
    $this->assure($table);
    if(!empty($where_criteria)){
      $where_criteria->killSort(); // no need to sort when you're counting
    }//if
    
    $ret_int = 0;
    list($limit,$offset) = $this->getLimit($limit);
    
    try{
    
      $ret_int = $this->_getCount($table,$schema,$where_criteria,array($limit,$offset));
      
      if($this->hasDebug()){
        if(!is_int($ret_int)){
          throw new UnexpectedValueException(sprintf('%s is not the expected return type of integer',gettype($ret_int)));
        }//if
      }//if
    
    }catch(Exception $e){
    
      if($this->handleException($e,$table,$schema)){
        $ret_int = $this->getCount($table,$schema,$where_criteria,array($limit,$offset));
      }//if
    
    }//try/catch
    
    return $ret_int;
  
  }//method
  
  /**
   *  @see  getCount()   
   *  @return integer the count
   */
  abstract protected function _getCount($table,MingoSchema $schema,MingoCriteria $where_criteria = null,array $limit);
  
  /**
   *  insert $map into $table
   *  
   *  @param  string  $table  the table name
   *  @param  array $map  the key/value map that will be added to $table
   *  @param  MingoSchema $schema the table schema  
   *  @return array the $map that was just saved with _id set
   *     
   *  @throws mingo_exception on any failure               
   */
  public function set($table,array $map,MingoSchema $schema){
  
    // canary...
    $this->assure($table);
    if(empty($map)){ throw new InvalidArgumentException('no point in setting an empty $map'); }//if
  
    // add created and last touched fields...
    $now = time();
    if(empty($map['created'])){ $map['created'] = $now; }//if
    $map['updated'] = $now;
  
    // check required fields...
    $req_field_list = array();
    foreach($schema->getRequiredFields() as $req_field_name => $req_field_val){
    
      if(!array_key_exists($req_field_name,$map)){
      
        if($req_field_val !== null){
        
          $map[$req_field_name] = $req_field_val;
          
        }else{
        
          $req_field_list[] = $req_field_name;
          
        }//if/else
      
      }//if
    
    }//foreach
    
    if(!empty($req_field_list)){
    
      throw new DomainException(
        sprintf(
          'cannot set() because $map is missing required field(s): [%s]',
          join(', ',$req_field_list)
        )
      );
      
    }//if
  
    if(empty($map['_id'])){
    
      try{
    
        // since there isn't an _id, insert...
        $map = $this->insert($table,$map,$schema);
        
      }catch(Exception $e){
        if($this->handleException($e,$table,$schema)){
          $map = $this->insert($table,$map,$schema);
        }//if
      }//try/catch
        
    }else{
    
      try{
      
        $id = $map['_id'];
        unset($map['_id']);
        $map = $this->update($table,$id,$map,$schema);
        
      }catch(Exception $e){
        if($this->handleException($e,$table,$schema)){
          $map = $this->update($table,$id,$map,$schema);
        }//if
      }//try/catch
        
    }//if
    
    if(is_array($map)){
      
      // make sure _id was set...
      if(empty($map['_id'])){
        throw new UnexpectedValueException('$map returned from either insert or update without _id being set');
      }//if
      
    }else{
    
      if($this->hasDebug()){
        throw new UnexpectedValueException(sprintf('%s is not the expected return type of array',gettype($map)));
      }//if
    
    }//if/else
  
    return $map;
  
  }//method
  
  /**
   *  insert $map into $table
   *  
   *  @param  string  $table  the table name
   *  @param  array|mingo_criteria  $map  the key/value map that will be added to $table
   *  @param  MingoSchema $schema the table schema   
   *  @return array the $map that was just saved, with the _id set               
   */
  abstract protected function insert($table,array $map,MingoSchema $schema);
  
  /**
   *  update $map from $table using $_id
   *  
   *  @param  string  $table  the table name
   *  @param  string  $_id the _id attribute from $map   
   *  @param  array $map  the key/value map that will be added to $table
   *  @param  MingoSchema $schema the table schema      
   *  @return array the $map that was just saved with _id set
   */
  abstract protected function update($table,$_id,array $map,MingoSchema $schema);
  
  /**
   *  deletes a table
   *  
   *  @param  string  $table  the table to delete from the db
   *  @return boolean
   */
  public function killTable($table){
    
    // canary...
    $this->assure($table);
    if(!$this->hasTable($table)){ return true; }//if
    
    $ret_bool = false;
    try{
    
      $ret_bool = $this->_killTable($table);
      if($this->hasDebug()){
        if(!is_bool($ret_bool)){
          throw new UnexpectedValueException(sprintf('%s is not the expected return type of boolean',gettype($ret_bool)));
        }//if
      }//if
      
    }catch(Exception $e){
      $this->handleException($e,$table);
    }//try/catch
      
    return $ret_bool;
  
  }//method
  
  /**
   *  @see  killTable()
   *  @return boolean
   */
  abstract protected function _killTable($table);
  
  /**
   *  get all the tables of the currently connected db
   *
   *  @param  string  $table  a table to filter the results by   
   *  @return array a list of table names
   */
  public function getTables($table = ''){
  
    if(!$this->isConnected()){ throw new UnexpectedValueException('no db connection found'); }//if
    
    $ret_list = array();
    try{
    
      $ret_list = $this->_getTables($table);
      if($this->hasDebug()){
        if(!is_array($ret_list)){
          throw new UnexpectedValueException(sprintf('%s is not the expected return type of array',gettype($ret_list)));
        }//if
      }//if
      
    }catch(Exception $e){
      $this->handleException($e);
    }//try/catch
      
    return $ret_list;
  
  }//method
  
  /**
   *  @see  getTables()
   *  @return array
   */
  abstract protected function _getTables($table = '');
  
  /**
   *  get all the indexes of $table
   *
   *  @param  string  $table  the table to get the indexes from
   *  @return array an array in the same format that {@link mingo_schema::getIndexes()} returns
   */
  public function getIndexes($table){
  
    if(!$this->isConnected()){ throw new UnexpectedValueException('no db connection found'); }//if
    
    $ret_list = array();
    try{
    
      $ret_list = $this->_getIndexes($table);
      if($this->hasDebug()){
        if(!is_array($ret_list)){
          throw new UnexpectedValueException(sprintf('%s is not the expected return type of array',gettype($ret_list)));
        }//if
      }//if
      
    }catch(Exception $e){
      $this->handleException($e);
    }//try/catch
      
    return $ret_list;
  
  }//method
  
  /**
   *  @see  getIndexes()
   *  @return array
   */
  abstract protected function _getIndexes($table);
  
  /**
   *  adds a table to the db
   *  
   *  @param  string  $table  the table to add to the db
   *  @param  MingoSchema $schema a schema object that defines indexes, etc. for this 
   *  @return boolean
   */
  public function setTable($table,MingoSchema $schema){
  
    // canary...
    $this->assure($table);
    
    $ret_bool = false;
    try{
    
      $ret_bool = $this->_setTable($table,$schema);
      
      if($this->hasDebug()){
        if(!is_bool($ret_bool)){
          throw new UnexpectedValueException(sprintf('%s is not the expected return type of boolean',gettype($ret_bool)));
        }//if
      }//if
      
      // add all the indexes for this table...
      if($schema->hasIndexes()){
      
        foreach($schema->getIndexes() as $index_map){
        
          $this->setIndex($table,$index_map,$schema);
        
        }//foreach
      
      }//if
      
    }catch(Exception $e){
      $this->handleException($e,$table);
    }//try/catch
    
    return $ret_bool;
    
  }//method
  
  /**
   *  @see  setTable()
   *  @return boolean
   */
  abstract protected function _setTable($table,MingoSchema $schema);
  
  /**
   *  check to see if a table is in the db
   *  
   *  @param  string  $table  the table to check
   *  @return boolean
   */
  public function hasTable($table){
  
    // canary...
    $this->assure($table);
    $table_list = $this->getTables($table);
    return !empty($table_list);
    
  }//method
  
  /**
   *  returns a list of the queries executed  
   *  
   *  the class that implements this interface should track the queries using {@link $query_list}
   *  but that isn't assured. For example, the SQL interfaces only save queries when debug is true   
   *      
   *  @return array a list of queries executed by this db interface instance
   */
  public function getQueries(){
    
    // canary...
    if(!$this->isConnected()){ return array(); }//if
    
    return $this->query_list;
    
  }//method
  
  /**
   *  set the limit/page
   *  
   *  this basically normalizes the limit and the page so you don't have to worry about one or the other
   *  not being present in the implemenations         
   *  
   *  @param  integer|array $limit  can be either int (eg, limit=10) or array (eg, array($limit,$offset)
   *  @return array array($limit,$offset)
   */
  protected function getLimit($limit){
  
    // canary...
    if(empty($limit)){ return array(0,0); }//if
  
    $ret_limit = $ret_offset = 0;
  
    if(is_array($limit)){
      $ret_limit = (int)$limit[0];
      if(isset($limit[1])){
        $ret_offset = (int)$limit[1];
      }//if
    }else{
      $ret_limit = (int)$limit;
    }//if/else
  
    return array($ret_limit,$ret_offset);
  
  }//method
  
  /**
   *  close the db connection so we don't run afoul of anything when serializing this
   *  class   
   *  
   *  http://www.php.net/manual/en/language.oop5.magic.php#language.oop5.magic.sleep
   *        
   *  @return the names of all the variables that should be serialized      
   */
  public function __sleep(){
    $this->con_db = null;
    $this->setField('connected',false);
    return array_keys(get_object_vars($this));
  }//method
  
  /**
   *  takes any exception and maps it to a mingo_exception
   *  
   *  this will also try and resolve the exception if $table and $schema are given, if solving
   *  it returns true then this method will return true allowing the failed code to try again         
   *  
   *  @param  Exception $e  any exception
   *  @param  string  $table  the table the exception was encountered on
   *  @param  MingoSchema $table's schema
   *  @return boolean true if the exception was resolved, false if it wasn't
   */
  protected function handleException(Exception $e,$table = '',MingoSchema $schema = null){
  
    $e_resolved = false;
  
    // only try and resolve an exception if we have some meta data...
    if(!empty($table) && ($schema !== null)){
    
      $e_resolved = $this->_handleException($e,$table,$schema);
      if($this->hasDebug()){
        if(!is_bool($e_resolved)){
          throw new UnexpectedValueException(sprintf('%s is not the expected return type of boolean',gettype($e_resolved)));
        }//if
      }//if
      
    }//if
    
    if(!$e_resolved){ throw $e; }//if
    return $e_resolved;
  
  }//method
  
  /**
   *  @see  handleException()
   *  @return boolean false on failure to solve the exception, true if $e was successfully resolved
   */
  abstract protected function _handleException(Exception $e,$table,MingoSchema $schema);
  
  /**
   *  adds an index to $table
   *  
   *  @param  string  $table  the table to add the index to
   *  @param  array $map  the keys are the field names, the values are the definitions for each field
   *  @param  MingoSchema $schema the table schema      
   *  @return boolean
   */
  protected function setIndex($table,array $index_map,MingoSchema $schema){
  
    // canary...
    if(empty($index_map)){
      throw new InvalidArgumentException('$index_map was empty');
    }//if
  
    $index_map = $this->normalizeIndex($index_map,$schema);
    return $this->_setIndex($table,$index_map,$schema);
  
  }//method
  
  /**
   *  @see  setIndex()
   *  
   *  @param  string  $table
   *  @param  mixed $index  an index this interface understands
   *  @param  MingoSchema $schema   
   *  @return boolean
   */
  abstract protected function _setIndex($table,$index,MingoSchema $schema);
  
  /**
   *  convert an array index map into something this interface understands
   *
   *  @since  5-2-11
   *  @return mixed whatever this interface will understand
   */
  abstract protected function normalizeIndex(array $index_map,MingoSchema $schema);
  
  /**
   *  this should be used to take the generic $where_criteria and turn it into something
   *  the interface can use (eg, for a SQL interface, the $where_criteria would be turned
   *  into a valid SQL string).
   *  
   *  @param  MingoCriteria $where_criteria
   *  @return mixed return whatever you want, however you want to return it, whatever is easiest for you
   */
  abstract protected function normalizeCriteria(MingoCriteria $where_criteria);
  
  /**
   *  generates a 24 character unique id for the _id of an inserted row
   *
   *  @param  string  $table  the table to be used in the hash
   *  @return string  a 24 character id string   
   */
  protected function getUniqueId($table = ''){
    
    // took out x and b, because 1 id started 0x which made it a hex number, and b just because
    $str = '1234567890acdefghijklmnpqrstuvwyz';
    $id = uniqid(sprintf('%s%s',$str[rand(0,32)],$str[rand(0,32)]),true);
    return str_replace('.','',$id);
    
  }//method
  
  /**
   *  just some common stuff that needs to be assured before queries can work
   *  
   *  @since  4-29-11
   *  @param  string  $table
   *  @return boolean
   */
  protected function assure($table){
  
    if(empty($table)){ throw new InvalidArgumentException('no $table specified'); }//if
    if(!$this->isConnected()){ throw new UnexpectedValueException('no db connection found'); }//if
    return true;
  
  }//method
  
}//class     
