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
 *  @version 0.6
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
   *  true if {@link connect()} was called successfully
   *  
   *  @see  isConnected()
   *  @var  boolean
   */
  private $connected = false;
  
  public function __construct(){}//method
  
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
      $this->connected = $this->_connect(
        $name,
        $host,
        $username,
        $password,
        $options
      );
      
      if($this->hasDebug()){
        if(!is_bool($this->connected)){
          throw new UnexpectedValueException(
            sprintf('%s is not the expected return type of boolean',
              gettype($this->connected)
            )
          );
        }//if
      }//if
      
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
    
    return $this->connected;
  
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
  
  public function isConnected(){ return $this->connected; }//method
  
  /**
   *  delete the records that match $where_criteria in $table
   *  
   *  @param  MingoTable  $table 
   *  @param  MingoCriteria $where_criteria
   *  @return boolean
   */
  public function kill(MingoTable $table,MingoCriteria $where_criteria){
  
    // canary...
    $this->assure($table);
    if(!$where_criteria->hasWhere()){
      throw new UnexpectedValueException('aborting delete because $where_criteria had no where clause');
    }//if
    
    $ret_bool = false;
    $where_criteria->killSort(); // no need to sort when you're deleting
  
    $where_criteria->normalizeFields($table);
    $itable = $this->normalizeTable($table);
    $iwhere_criteria = $this->normalizeCriteria($table,$where_criteria);
  
    try{
    
      $ret_bool = $this->_kill($itable,$iwhere_criteria);
      
      if($this->hasDebug()){
        if(!is_bool($ret_bool)){
          throw new UnexpectedValueException(sprintf('%s is not the expected return type of boolean',gettype($ret_bool)));
        }//if
      }//if
    
    }catch(Exception $e){
    
      if($this->handleException($e,$table)){
        $ret_bool = $this->_kill($itable,$iwhere_criteria);
      }//if
    
    }//try/catch
  
    return $ret_bool;
  
  }//method
  
  /**
   *  @see  kill()
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  mixed $where_criteria the where criteria ran through {@link normalizeCriteria())      
   *  @return boolean
   */
  abstract protected function _kill($table,$where_criteria);
  
  /**
   *  allows a raw query to be run on the interface
   *  
   *  @since  10-8-10
   *  @param  mixed $query  a query the interface can understand
   *  @param  array $options  any options for this query
   *  @return mixed         
   */
  public function getQuery($query,array $options = array())
  {
    // canary...
    if(empty($query)){ throw new InvalidArgumentException('$query was empty'); }//if
    
    return $this->_getQuery($query,$options);
    
  }//method
  
  /**
   *  @see  getQuery()
   *  @param  mixed $query  a query the interface can understand
   *  @param  array $options  any options for this query
   *  @return mixed      
   */
  abstract protected function _getQuery($query,array $options = array());
  
  /**
   *  get a list of rows matching $where_criteria
   *  
   *  @param  MingoTable  $table
   *  @param  MingoCriteria $where_criteria   
   *  @return array
   */
  public function get(MingoTable $table,MingoCriteria $where_criteria = null){
    
    // canary...
    $this->assure($table);
    
    $ret_list = array();
    
    if(!empty($where_criteria)){ $where_criteria->normalizeFields($table); }//if
    $itable = $this->normalizeTable($table);
    $iwhere_criteria = $this->normalizeCriteria($table,$where_criteria);
    
    try{
    
      $ret_list = $this->_get($itable,$iwhere_criteria);
      
      if($this->hasDebug()){
        if(!is_array($ret_list)){
          throw new UnexpectedValueException(sprintf('%s is not the expected return type of array',gettype($ret_list)));
        }//if
      }//if
    
    }catch(Exception $e){
    
      if($this->handleException($e,$table)){
        $ret_list = $this->get($table,$where_criteria);
      }//if
    
    }//try/catch
    
    return $ret_list;

  }//method
  
  /**
   *  @see  get()
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  mixed $where_criteria the where criteria ran through {@link normalizeCriteria())      
   *  @return array   
   */
  abstract protected function _get($table,$where_criteria);
  
  /**
   *  get the first found row in $table according to $where_criteria
   *  
   *  @param  MingoTable  $table    
   *  @param  MingoCriteria $where_criteria
   *  @return array
   */
  public function getOne(MingoTable $table,MingoCriteria $where_criteria = null){
    
    // set up to only select 1...
    if($where_criteria === null){ $where_criteria = new MingoCriteria(); }//if
    $where_criteria->setLimit(1);
    $where_criteria->setPage(0);
    $where_criteria->setOffset(0);
    
    $ret_list = $this->get($table,$where_criteria);
    $ret_map = reset($ret_list);
    if($ret_map === false){ $ret_map = array(); }//if
    
    return $ret_map;
    
  }//method
  
  /**
   *  tell how many records match $where_criteria in $table
   *  
   *  @param  MingoTable  $table    
   *  @param  MingoCriteria $where_criteria   
   *  @return integer the count   
   */
  public function getCount(MingoTable $table,MingoCriteria $where_criteria = null){
  
    // canary...
    $this->assure($table);
    if(!empty($where_criteria)){
      $where_criteria->normalizeFields($table);
      $where_criteria->killSort(); // no need to sort when you're counting
    }//if
    
    $ret_int = 0;
    $itable = $this->normalizeTable($table);
    $iwhere_criteria = $this->normalizeCriteria($table,$where_criteria);
    
    try{
    
      $ret_int = $this->_getCount($itable,$iwhere_criteria);
      
      if($this->hasDebug()){
        if(!is_int($ret_int)){
          throw new UnexpectedValueException(sprintf('%s is not the expected return type of integer',gettype($ret_int)));
        }//if
      }//if
    
    }catch(Exception $e){
    
      if($this->handleException($e,$table)){
        $ret_int = $this->getCount($table,$where_criteria);
      }//if
    
    }//try/catch
    
    return $ret_int;
  
  }//method
  
  /**
   *  @see  getCount()
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  mixed $where_criteria the where criteria ran through {@link normalizeCriteria())      
   *  @return integer the count
   */
  abstract protected function _getCount($table,$where_criteria);
  
  /**
   *  insert $map into $table
   *  
   *  @param  MingoTable  $table
   *  @param  array $map  the key/value map that will be added to $table  
   *  @return array the $map that was just saved with _id set   
   */
  public function set(MingoTable $table,array $map){
  
    // canary...
    $this->assure($table);
    if(empty($map)){ throw new InvalidArgumentException('no point in setting an empty $map'); }//if
  
    $itable = $this->normalizeTable($table);
  
    // add created and last touched fields...
    $now = time();
    if(empty($map['created'])){ $map['created'] = $now; }//if
    $map['updated'] = $now;
  
    // check required fields...
    $req_field_list = array();
    foreach($table->getRequiredFields() as $req_field_name => $req_field_val){
    
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
        $map = $this->insert($itable,$map);
        
      }catch(Exception $e){
        if($this->handleException($e,$table)){
          $map = $this->insert($itable,$map);
        }//if
      }//try/catch
        
    }else{
    
      try{
      
        $id = $map['_id'];
        unset($map['_id']);
        $map = $this->update($itable,$id,$map);
        
      }catch(Exception $e){
        if($this->handleException($e,$table)){
          $map = $this->update($itable,$id,$map);
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
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  array  $map  the key/value map that will be added to $table  
   *  @return array the $map that was just saved, with the _id set               
   */
  abstract protected function insert($table,array $map);
  
  /**
   *  update $map from $table using $_id
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  string  $_id the _id attribute from $map   
   *  @param  array $map  the key/value map that will be added to $table
   *  @return array the $map that was just saved with _id set
   */
  abstract protected function update($table,$_id,array $map);
  
  /**
   *  deletes a table
   *  
   *  @param  MingoTable  $table 
   *  @return boolean
   */
  public function killTable(MingoTable $table){
    
    // canary...
    $this->assure($table);
    if(!$this->hasTable($table)){ return true; }//if
    
    $ret_bool = false;
    $itable = $this->normalizeTable($table);
    
    try{
    
      $ret_bool = $this->_killTable($itable);
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
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}      
   *  @return boolean
   */
  abstract protected function _killTable($table);
  
  /**
   *  get all the tables of the currently connected db
   *
   *  @param  MingoTable  $table 
   *  @return array a list of table names
   */
  public function getTables(MingoTable $table = null){
  
    // canary...
    $this->assure();
    
    $ret_list = array();
    try{
    
      $ret_list = $this->_getTables($table);
      if($this->hasDebug()){
        if(!is_array($ret_list)){
          throw new UnexpectedValueException(sprintf('%s is not the expected return type of array',gettype($ret_list)));
        }//if
      }//if
      
    }catch(Exception $e){
      $this->handleException($e,$table);
    }//try/catch
      
    return $ret_list;
  
  }//method
  
  /**
   *  @see  getTables()
   *  
   *  @param  MingoTable  $table  
   *  @return array
   */
  abstract protected function _getTables(MingoTable $table = null);
  
  /**
   *  get all the indexes of $table
   *
   *  @param  MingoTable  $table 
   *  @return array an array in the form of array(field_name => options,...)
   */
  public function getIndexes(MingoTable $table){
  
    $this->assure($table);
    
    $ret_list = array();
    try{
    
      $itable = $this->normalizeTable($table);
      $ret_list = $this->_getIndexes($itable);
      if($this->hasDebug()){
        if(!is_array($ret_list)){
          throw new UnexpectedValueException(sprintf('%s is not the expected return type of array',gettype($ret_list)));
        }//if
      }//if
      
    }catch(Exception $e){
      $this->handleException($e,$table);
    }//try/catch
      
    return $ret_list;
  
  }//method
  
  /**
   *  @see  getIndexes()
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}      
   *  @return array
   */
  abstract protected function _getIndexes($table);
  
  /**
   *  adds a table to the db
   *  
   *  @param  MingoTable  $table 
   *  @return boolean
   */
  public function setTable(MingoTable $table){
  
    // canary...
    $this->assure($table);
    
    $ret_bool = false;
    try{
    
      if(!($ret_bool = $this->hasTable($table))){
        $ret_bool = $this->_setTable($table);
      }//if
      
      if($this->hasDebug()){
        if(!is_bool($ret_bool)){
          throw new UnexpectedValueException(sprintf('%s is not the expected return type of boolean',gettype($ret_bool)));
        }//if
      }//if
      
      // add all the indexes for this table...
      if($table->hasIndexes()){
      
        foreach($table->getIndexes() as $index_map){
          
          $this->setIndex($table,$index_map);
        
        }//foreach
      
      }//if
      
    }catch(Exception $e){
      $this->handleException($e,$table);
    }//try/catch
    
    return $ret_bool;
    
  }//method
  
  /**
   *  @see  setTable()
   *  
   *  @param  MingoTable  $table       
   *  @return boolean
   */
  abstract protected function _setTable(MingoTable $table);
  
  /**
   *  check to see if a table is in the db
   *  
   *  @param  MingoTable  $table 
   *  @return boolean
   */
  public function hasTable(MingoTable $table){
  
    // canary...
    $this->assure($table);
    $table_list = $this->getTables($table);
    return !empty($table_list);
    
  }//method
  
  /**
   *  returns a list of the queries executed  
   *  
   *  the class that implements this interface should track the queries using {@link addQuery()}
   *  but that isn't assured. For example, the SQL interfaces only save queries when debug is true   
   *      
   *  @return array a list of queries executed by this db interface instance
   */
  public function getQueries(){ return $this->query_list; }//method
  
  /**
   *  record the query if debug is on
   *  
   *  @since  5-2-11
   *  @param  mixed $query  a query that the interface uses
   *  @return boolean
   */
  protected function addQuery($query){
  
    // canary...
    if(!$this->hasDebug()){ return false; }//if
  
    $this->query_list[] = $query;
  
    return true;
  
  }//method
  
  /**
   *  decide what to do with the exception
   *  
   *  this will also try and resolve the exception if $table is given, if solving
   *  it returns true then this method will return true allowing the failed code to try again         
   *  
   *  @see  _handleException()   
   *  @param  Exception $e  any exception
   *  @param  MingoTable  $table  the table the exception was encountered on
   *  @return boolean true if the exception was resolved, false if it wasn't
   */
  protected function handleException(Exception $e,MingoTable $table = null){
  
    // canary, check for infinite recursion...
    $traces = $e->getTrace();
    foreach($traces as $trace){
      if($trace['function'] === __FUNCTION__){ return false; }//if
    }//foreach
  
    $e_resolved = false;
  
    // only try and resolve an exception if we have some meta data...
    if($table !== null){
    
      $e_resolved = $this->_handleException($e,$table);
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
   *  
   *  @param  Exception $e  the thrown exceptino to handle   
   *  @param  MingoTable  $table     
   *  @return boolean false on failure to solve the exception, true if $e was successfully resolved
   */
  protected function _handleException(Exception $e,MingoTable $table){ return false; }//method
  
  /**
   *  adds an index to $table
   *  
   *  @param  string  $table  the table to add the index to
   *  @param  array $map  the keys are the field names, the values are the definitions for each field      
   *  @return boolean
   */
  protected function setIndex(MingoTable $table,array $index_map){
  
    // canary...
    if(empty($index_map)){
      throw new InvalidArgumentException('$index_map was empty');
    }//if
  
    $index = $this->normalizeIndex($table,$index_map);
    $itable = $this->normalizeTable($table);
    return $this->_setIndex($itable,$index);
  
  }//method
  
  /**
   *  @see  setIndex()
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  mixed $index  an index ran through {@link normalizeIndex()}
   *  @return boolean
   */
  abstract protected function _setIndex($table,$index);
  
  /**
   *  convert an array index map into something this interface understands
   *
   *  @since  5-2-11
   *  @param  MingoTable  $table 
   *  @param  array $index_map  an index map that is usually in the form of array(field_name => options,...)      
   *  @return mixed whatever this interface will understand
   */
  protected function normalizeIndex(MingoTable $table,array $index_map){
    return $index_map;
  }//method
  
  /**
   *  this should be used to take the generic $where_criteria and turn it into something
   *  the interface can use (eg, for a SQL interface, the $where_criteria would be turned
   *  into a valid SQL string).
   *  
   *  @param  MingoTable  $table    
   *  @param  MingoCriteria $where_criteria   
   *  @return mixed return whatever you want, however you want to return it, whatever is easiest for you
   */
  protected function normalizeCriteria(MingoTable $table,MingoCriteria $where_criteria = null){
    return $where_criteria;
  }//method
  
  /**
   *  turn the table into something the interface can understand
   *  
   *  @param  MingoTable  $table 
   *  @return mixed return whatever you want, however you want to return it, whatever is easiest for you
   */
  protected function normalizeTable(MingoTable $table){
    return $table;
  }//method
  
  /**
   *  generates a unique id for the _id of an inserted row
   *
   *  @param  string  $table  the table to be used in the hash
   *  @return string  a 1-24 character id string   
   */
  protected function getUniqueId(MingoTable $table = null){
    
    $id = uniqid('',true);
    return str_replace('.','',$id);
    
  }//method
  
  /**
   *  just some common stuff that needs to be assured before queries can work
   *  
   *  @since  4-29-11
   *  @param  MingoTable  $table
   *  @return boolean
   */
  protected function assure(MingoTable $table = null){
  
    // make sure table is valid...
    if($table !== null){
      if(!$table->hasName()){ throw new UnexpectedValueException('no $table specified'); }//if
    }//if
    
    // make sure connection is valid...
    if(!$this->isConnected()){
      if(!$this->connect()){
        throw new UnexpectedValueException('no db connection found');
      }//if
    }//if
    
    
    return true;
  
  }//method
  
}//class     
