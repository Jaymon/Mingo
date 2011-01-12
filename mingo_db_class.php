<?php

/**
 *  handle mingo db connections transparently between the different interfaces, this
 *  class is used to establish the singleton, and then allow the map to interact
 *  with the db layer.
 *  
 *  the nice thing about this layer is that it handles all the error checking before
 *  passing anything to the interface, this allows interface developers to focus on the
 *  meat of their interface instead of worrying about error handling    
 *
 *  @version 0.4
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 11-08-09
 *  @package mingo 
 ******************************************************************************/
final class mingo_db {

  /**
   *  holds all the connection information this class used
   *  
   *  @var  array associative array
   */
  private $con_map = array();
  
  /**
   *  holds the actual db connection, established by calling {@link connect()}
   *  @var  mingo_db_interface
   */
  private $con_db = null;
  
  /**
   *  used by {@link getInstance()} to keep a singleton object, the {@link getInstance()} 
   *  method should be the only place this is ever messed with so if you want to touch it, DON'T!  
   *  @var array  an array of mingo_db instances
   */
  private static $instance_map = array();
  
  public function __construct(){}//method
  
  /**
   *  connect to the db
   *  
   *  @param  string $db_interface  the name of the class that extends mingo_db_interface that will be used   
   *  @param  string  $db_name  the db to use, defaults to {@link getName()}
   *  @param  string  $host the host to use, defaults to {@link getHost()}. if you want a specific
   *                        port, attach it to host (eg, localhost:27017 or example.com:27017)            
   *  @param  string  $username the username to use, defaults to {@link getUsername()}
   *  @param  string  $password the password to use, defaults to {@link getPassword()}   
   *  @return boolean
   *  @throws mingo_exception   
   */
  public function connect($db_interface = '',$db_name = '',$host = '',$username = '',$password = ''){
  
    // set all the connection variables...
    if(empty($db_interface)){
      if($this->hasInterface()){
        $db_interface = $this->getInterface();
      }else{
        throw new mingo_exception(
          'no $db_interface specified. An interface is a class that extends mingo_db_interface'
        );
      }//if/else
    }else{
      $this->setInterface($db_interface);
    }//if/else
    
    if(empty($db_name)){
      if($this->hasName()){
        $db_name = $this->getName();
      }else{
        throw new mingo_exception('no $db_name specified');
      }//if/else
    }else{
      $this->setName($db_name);
    }//if/else
    
    if(empty($host)){
      if($this->hasHost()){
        $host = $this->getHost();
      }//if
    }else{
      $this->setHost($host);
    }//if/else
    
    if(empty($username)){
      if($this->hasUsername()){
        $username = $this->getUsername();
      }//if
    }else{
      $this->setUsername($username);
    }//if/else
    
    if(empty($password)){
      if($this->hasPassword()){
        $password = $this->getPassword();
      }//if
    }else{
      $this->setPassword($password);
    }//if/else
    
    try{
      
      // make sure $db_interface exists and is compatible...
      if(class_exists($db_interface)){
  
        if(is_subclass_of($db_interface,'mingo_db_interface')){
  
          $this->con_db = new $db_interface();
          
        }else{
          throw new mingo_exception(
            sprintf(
              'class %s does not extend mingo_db_interface and is assumed not compatible',
              $db_interface
            )
          );
        }//if/else
        
      }else{
      
        throw new mingo_exception(
          sprintf('class %s does not seem to exist and is needed to connect to the db',$db_interface)
        );
      
      }//if/else
      
      // reset the debug level for the con_db just in case...
      $this->setDebug($this->getDebug());
      
      // actually connect to the db...
      $this->con_map['connected'] = $this->con_db->connect(
        $db_name,
        $host,
        $username,
        $password
      );
      
      if($this->hasDebug()){
        if(!is_bool($this->con_map['connected'])){
          throw new mingo_exception(
            sprintf('%s is not the expected return type of boolean',
              gettype($this->con_map['connected'])
            )
          );
        }//if
      }//if
      
    }catch(Exception $e){
    
      if($this->hasDebug()){
      
        $e_msg = array();
        $con_map_msg = array();
        foreach($this->con_map as $key => $val){
          $con_map_msg[] = sprintf('%s => %s',$key,empty($val) ? '""' : $val);
        }//foreach
        
        $e_msg[] = sprintf(
          'db connection failed with message "%s" and connection variables: [%s]',
          $e->getMessage(),
          join(',',$con_map_msg)
        );
        
        $e = new mingo_exception(join(PHP_EOL,$e_msg),$e->getCode(),$e);
        
      }//if
    
      $this->handleException($e);
      
    }//try/catch
    
    return $this->con_map['connected'];
  
  }//method
  
  /**
   *  forces class to follow the singelton pattern 
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
      $class_list = array('mingo_orm');
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
   *  get the interface instance that this class created when {@link connect()} was called
   *  
   *  @return mingo_db_interface
   */
  public function getDb(){ return $this->con_db; }//method

  public function setInterface($val){ $this->con_map['interface'] = $val; }//method
  public function getInterface(){ return $this->hasInterface() ? $this->con_map['interface'] : ''; }//method
  public function hasInterface(){ return !empty($this->con_map['interface']); }//method
  public function isInterface($val){ return ((string)$this->getInterface() === (string)$val); }//method
  
  public function setName($val){ $this->con_map['name'] = $val; }//method
  public function getName(){ return $this->hasName() ? $this->con_map['name'] : ''; }//method
  public function hasName(){ return !empty($this->con_map['name']); }//method

  public function setHost($val){ $this->con_map['host'] = $val; }//method
  public function getHost(){ return $this->hasHost() ? $this->con_map['host'] : ''; }//method
  public function hasHost(){ return !empty($this->con_map['host']); }//method
  
  public function setUsername($val){ $this->con_map['username'] = $val; }//method
  public function getUsername(){ return $this->hasUsername() ? $this->con_map['username'] : ''; }//method
  public function hasUsername(){ return !empty($this->con_map['username']); }//method
  
  public function setPassword($val){ $this->con_map['password'] = $val; }//method
  public function getPassword(){ return $this->hasPassword() ? $this->con_map['password'] : ''; }//method
  public function hasPassword(){ return !empty($this->con_map['password']); }//method
  
  public function setDebug($val){
    $this->con_map['debug'] = $val;
    if($this->con_db !== null){ $this->con_db->setDebug($val); }//if
  }//method
  public function getDebug(){ return $this->hasDebug(); }//method
  public function hasDebug(){ return !empty($this->con_map['debug']); }//method
  
  public function isConnected(){ return !empty($this->con_map['connected']); }//method
  
  /**
   *  delete the records that match $where_criteria in $table
   *  
   *  @param  string  $table
   *  @param  mingo_schema  $schema the table schema    
   *  @param  mingo_criteria $where_criteria
   *  @return boolean
   */
  public function kill($table,mingo_schema $schema,mingo_criteria $where_criteria){
  
    // canary...
    if(empty($table)){ throw new mingo_exception('no $table specified'); }//if
    if(!$this->isConnected()){ throw new mingo_exception('no db connection found'); }//if
    if(empty($where_criteria)){
      throw new mingo_exception('no $where_criteria specified');
    }else{
      if(!$where_criteria->hasWhere()){
        throw new mingo_exception('aborting delete because $where_criteria had no where clause');
      }//if
      
      $where_criteria->killSort(); // no need to sort when you're deleting
      
    }//if/else
  
    $ret_bool = false;
  
    try{
    
      $ret_bool = $this->con_db->kill($table,$schema,$where_criteria);
      
      if($this->hasDebug()){
        if(!is_bool($ret_bool)){
          throw new mingo_exception(sprintf('%s is not the expected return type of boolean',gettype($ret_bool)));
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
  public function getQuery()
  {
    $args = func_get_args();
    return $this->con_db->getQuery($args);
  
  }//method
  
  /**
   *  get a list of rows matching $where_criteria
   *  
   *  @param  string  $table
   *  @param  mingo_schema  $schema the table schema   
   *  @param  mingo_criteria  $where_criteria
   *  @param  integer|array $limit  either something like 10, or array($limit,$offset)   
   *  @return array
   */
  public function get($table,mingo_schema $schema,mingo_criteria $where_criteria = null,$limit = 0){
    
    // canary...
    if(empty($table)){ throw new mingo_exception('no $table specified'); }//if
    if(!$this->isConnected()){ throw new mingo_exception('no db connection, please call connect()'); }//if
    if(!empty($where_criteria)){
      if(!$where_criteria->hasWhere() && $where_criteria->hasOperations()){
        throw new mingo_exception(
          '$where_criteria does not have a where clause but does have an operations criteria, '
          .'that means you most likely used setField(...) when you meant isField(...)'
        );
      }//if
    }//if
    
    $ret_list = array();
    list($limit,$offset) = $this->getLimit($limit);
    
    try{
    
      $ret_list = $this->con_db->get($table,$schema,$where_criteria,array($limit,$offset));
      
      if($this->hasDebug()){
        if(!is_array($ret_list)){
          throw new mingo_exception(sprintf('%s is not the expected return type of array',gettype($ret_list)));
        }//if
      }//if
    
    }catch(Exception $e){
    
      if($this->handleException($e,$table,$schema)){
        $ret_list = $this->con_db->get($table,$schema,$where_criteria,array($limit,$offset));
      }//if
    
    }//try/catch
    
    return $ret_list;

  }//method
  
  /**
   *  get the first found row in $table according to $where_criteria
   *  
   *  @param  string  $table
   *  @param  mingo_schema  $schema the table schema   
   *  @param  mingo_criteria  $where_criteria
   *  @return array
   */
  public function getOne($table,mingo_schema $schema,mingo_criteria $where_criteria = null){
    
    // canary...
    if(empty($table)){ throw new mingo_exception('no $table specified'); }//if
    if(!$this->isConnected()){ throw new mingo_exception('no db connection, please call connect()'); }//if
    if(!empty($where_criteria)){
      if(!$where_criteria->hasWhere() && $where_criteria->hasOperations()){
        throw new mingo_exception(
          '$where_criteria does not have a where clause but does have an operations criteria, '
          .'that means you most likely used setField(...) when you meant isField(...)'
        );
      }//if
    }//if
    
    $ret_map = array();
    
    try{
    
      $ret_map = $this->con_db->getOne($table,$schema,$where_criteria);
      
      if($this->hasDebug()){
        if(!is_array($ret_map)){
          throw new mingo_exception(sprintf('%s is not the expected return type of array',gettype($ret_map)));
        }//if
      }//if
    
    }catch(Exception $e){
    
      if($this->handleException($e,$table,$schema)){
        $ret_map = $this->con_db->getOne($table,$schema,$where_criteria);
      }//if
    
    }//try/catch
    
    
    return $ret_map;
    
  }//method
  
  /**
   *  tell how many records match $where_criteria in $table
   *  
   *  @param  string  $table
   *  @param  mingo_schema  $schema the table schema    
   *  @param  mingo_criteria $where_criteria
   *  @param  integer|array $limit  either something like 10, or array($limit,$offset)   
   *  @return integer the count   
   */
  public function getCount($table,mingo_schema $schema,mingo_criteria $where_criteria = null,$limit = 0){
  
    // canary...
    if(empty($table)){ throw new mingo_exception('no $table specified'); }//if
    if(!$this->isConnected()){ throw new mingo_exception('no db connection, please call connect()'); }//if
    if(!empty($where_criteria)){
      if(!$where_criteria->hasWhere() && $where_criteria->hasOperations()){
        throw new mingo_exception(
          '$where_criteria does not have a where clause but does have an operations criteria, '
          .'that means you most likely used setField(...) when you meant isField(...)'
        );
      }else{
        $where_criteria->killSort(); // no need to sort when you're counting
      }//if/else
    }//if
    
    $ret_int = 0;
    list($limit,$offset) = $this->getLimit($limit);
    
    try{
    
      $ret_int = $this->con_db->getCount($table,$schema,$where_criteria,array($limit,$offset));
      
      if($this->hasDebug()){
        if(!is_int($ret_int)){
          throw new mingo_exception(sprintf('%s is not the expected return type of integer',gettype($ret_int)));
        }//if
      }//if
    
    }catch(Exception $e){
    
      if($this->handleException($e,$table,$schema)){
        $ret_int = $this->con_db->getCount($table,$schema,$where_criteria,array($limit,$offset));
      }//if
    
    }//try/catch
    
    
    return $ret_int;
  
  }//method
  
  /**
   *  insert $map into $table
   *  
   *  @param  string  $table  the table name
   *  @param  array $map  the key/value map that will be added to $table
   *  @param  mingo_schema  $schema the table schema  
   *  @return array the $map that was just saved with _id set
   *     
   *  @throws mingo_exception on any failure               
   */
  public function set($table,array $map,mingo_schema $schema){
  
    // canary...
    if(empty($table)){ throw new mingo_exception('no $table specified'); }//if
    if(empty($map)){ throw new mingo_exception('no point in setting an empty $map'); }//if
    if(empty($schema)){ throw new mingo_exception('no $schema specified'); }//if
    if(!$this->isConnected()){ throw new mingo_exception('no db connection found'); }//if
  
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
        $map = $this->con_db->insert($table,$map,$schema);
        
      }catch(Exception $e){
        if($this->handleException($e,$table,$schema)){
          $map = $this->con_db->insert($table,$map,$schema);
        }//if
      }//try/catch
        
    }else{
    
      try{
      
        $id = $map['_id'];
        unset($map['_id']);
        $map = $this->con_db->update($table,$id,$map,$schema);
        
      }catch(Exception $e){
        if($this->handleException($e,$table,$schema)){
          $map = $this->con_db->update($table,$id,$map,$schema);
        }//if
      }//try/catch
        
    }//if
    
    if(is_array($map)){
      
      // make sure _id was set...
      if(empty($map['_id'])){
        throw new mingo_exception('$map returned from either insert or update without _id being set');
      }//if
      
    }else{
    
      if($this->hasDebug()){
        throw new mingo_exception(sprintf('%s is not the expected return type of array',gettype($map)));
      }//if
      
    
    }//if/else
  
    return $map;
  
  }//method
  
  /**
   *  deletes a table
   *  
   *  @param  string  $table  the table to delete from the db
   *  @return boolean
   */
  public function killTable($table){
    
    // canary...
    if(empty($table)){ throw new mingo_exception('no $table given'); }//if
    if(!$this->isConnected()){ throw new mingo_exception('no db connection found'); }//if
    
    $ret_bool = false;
    try{
    
      $ret_bool = $this->con_db->killTable($table);
      if($this->hasDebug()){
        if(!is_bool($ret_bool)){
          throw new mingo_exception(sprintf('%s is not the expected return type of boolean',gettype($ret_bool)));
        }//if
      }//if
      
    }catch(Exception $e){
      $this->handleException($e,$table);
    }//try/catch
      
    return $ret_bool;
  
  }//method
  
  /**
   *  get all the tables of the currently connected db
   *
   *  @return array a list of table names
   */
  public function getTables(){
  
    if(!$this->isConnected()){ throw new mingo_exception('no db connection found'); }//if
    
    $ret_list = array();
    try{
    
      $ret_list = $this->con_db->getTables();
      if($this->hasDebug()){
        if(!is_array($ret_list)){
          throw new mingo_exception(sprintf('%s is not the expected return type of array',gettype($ret_list)));
        }//if
      }//if
      
    }catch(Exception $e){
      $this->handleException($e);
    }//try/catch
      
    return $ret_list;
  
  }//method
  
  /**
   *  get all the indexes of $table
   *
   *  @param  string  $table  the table to get the indexes from
   *  @return array an array in the same format that {@link mingo_schema::getIndexes()} returns
   */
  public function getIndexes($table){
  
    if(!$this->isConnected()){ throw new mingo_exception('no db connection found'); }//if
    
    $ret_list = array();
    try{
    
      $ret_list = $this->con_db->getIndexes($table);
      if($this->hasDebug()){
        if(!is_array($ret_list)){
          throw new mingo_exception(sprintf('%s is not the expected return type of array',gettype($ret_list)));
        }//if
      }//if
      
    }catch(Exception $e){
      $this->handleException($e);
    }//try/catch
      
    return $ret_list;
  
  }//method
  
  /**
   *  adds a table to the db
   *  
   *  @param  string  $table  the table to add to the db
   *  @param  mingo_schema  a schema object that defines indexes, etc. for this 
   *  @return boolean
   */
  public function setTable($table,mingo_schema $schema){
  
    // canary...
    if(empty($table)){ throw new mingo_exception('no $table given'); }//if
    if(empty($schema)){ throw new mingo_exception('$schema must be present'); }//if
    if(!$this->isConnected()){ throw new mingo_exception('no db connection found'); }//if
    
    $ret_bool = false;
    try{
    
      $ret_bool = $this->con_db->setTable($table,$schema);
      if($this->hasDebug()){
        if(!is_bool($ret_bool)){
          throw new mingo_exception(sprintf('%s is not the expected return type of boolean',gettype($ret_bool)));
        }//if
      }//if
      
    }catch(Exception $e){
      $this->handleException($e,$table);
    }//try/catch
      
    return $ret_bool;
    
  }//method
  
  /**
   *  check to see if a table is in the db
   *  
   *  @param  string  $table  the table to check
   *  @return boolean
   */
  public function hasTable($table){
  
    // canary...
    if(empty($table)){ throw new mingo_exception('no $table given'); }//if
    if(!$this->isConnected()){ throw new mingo_exception('no db connection, please call connect()'); }//if
    
    $ret_bool = false;
    try{
    
      $ret_bool = $this->con_db->hasTable($table);
      if($this->hasDebug()){
        if(!is_bool($ret_bool)){
          throw new mingo_exception(sprintf('%s is not the expected return type of boolean',gettype($ret_bool)));
        }//if
      }//if
      
    }catch(Exception $e){
      $this->handleException($e,$table);
    }//try/catch
      
    return $ret_bool;
    
  }//method
  
  /**
   *  return all the queries that have been executed by the connection
   *  
   *  there is no guarrantee that the db interface that is being used will return 
   *  the queries, it is up to the developer to save and return them.
   *  
   *  @return array a list of queries executed on the db using the db_interface
   */
  public function getQueries(){
    
    // canary...
    if(!$this->isConnected()){ return array(); }//if
    
    return $this->con_db->getQueries();
    
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
    $this->con_map['connected'] = false;
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
   *  @param  mingo_schema  $table's schema
   *  @return boolean true if the exception was resolved, false if it wasn't
   *  @throws mingo_exception all exceptions get re-thrown as mingo_exception if not resolved
   */
  private function handleException(Exception $e,$table = '',mingo_schema $schema = null){
  
    $e_resolved = false;
  
    // only try and resolve an exception if we have some meta data...
    if(!empty($table) && ($schema !== null)){
    
      $e_resolved = $this->con_db->handleException($e,$table,$schema);
      if($this->hasDebug()){
        if(!is_bool($e_resolved)){
          throw new mingo_exception(sprintf('%s is not the expected return type of boolean',gettype($e_resolved)));
        }//if
      }//if
      
    }//if
    
    if(!$e_resolved){
    
      if($e instanceof mingo_exception){
      
        // just pass a mingo_exception on up the chain...
        throw $e;
      
      }else{
      
        // map the caught exception to a mingo_exception and pass it on up the chain...
        throw new mingo_exception(
          sprintf(
            '%s %s: "%s" originally thrown in %s:%s',
            get_class($e),
            $e->getCode(),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
          ),
          $e->getCode()
        );
        
      }//if/else
      
    }//if
  
    return $e_resolved;
  
  }//method
  
}//class     
