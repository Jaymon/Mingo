<?php

/**
 *  create a query instance that can be used to query the db
 *  
 *  this is a great class to extend if you want to create a Peer or Table class
 *  that will do all the selecting for a MingoOrm class
 *  
 *  This class is the first step of the transition from using MingoOrm's load*() methods
 *  and having MingoOrm do everything to a more traditional Table class and row class that
 *  most Orms use. I think the one class to rule them all was a nice idea in theory, it made
 *  for some confusing code. Also, if you added a function that should only work on one row
 *  in the MingoOrm child class you would have to check to make sure the MingoOrm class had
 *  only loaded one child and throw an error if it had more than one row loaded, this was
 *  impractical over the long haul
 *  
 *  @example
 *  
 *    // get all the FooBar table's foo fields that match the values:
 *    $query = new MingoQuery('FooBar',$db);
 *    $iterator =  $query->inFoo(1,2,3,4)->get();
 *    
 *    // get the first 5 values of FooBar's foo values matching 1 and sort them by bar   
 *    $query = new MingoQuery('FooBar',$db);
 *    $iterator =  $query->isFoo(1)->descBar()->setLimit(5)->get();  
 * 
 *    // get the orm matching the _id
 *    $query = new MingoQuery('FooBar',$db);
 *    $orm = $query->is_id($_id)->getOne();  
 *  
 *  @version 0.1
 *  @author Jay Marcyes
 *  @since 1-12-12
 *  @package mingo 
 ******************************************************************************/
class MingoQuery extends MingoCriteria implements IteratorAggregate, Countable {

  /**
   *  the \MingoOrm orm name
   *
   *  @var  string  the name of a MingoOrm derived class   
   */
  protected $orm_name = '';
  
  /**
   *  the MingoInterface that will be used to query the db
   *
   *  @var  \MingoInterface
   */
  protected $db = null;

  /**
   *  build a query
   *  
   *  @param  string  $orm_name the name of the \MingoOrm class      
   *  @param  \MingoInterface $db the db connection
   */
  public function __construct($orm_name,MingoInterface $db = null){
  
    $this->orm_name = $orm_name;
    $this->db = $db;
  
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
   *  return a result set
   *  
   *  @return \MingoIterator  this will always return an iterator, even if there are no rows matching
   */
  public function get(){
  
    $has_more = false;
    $count = 0;
    $orm = $this->getOrm();
    $db = $this->getDb();
    $limit = $offset = $limit_paginate = 0;
    list($limit, $offset, $limit_paginate) = $this->getBounds();
    // we change the limit right here to make the request and check if has more results
    $this->setLimit($limit_paginate);
    $this->setOffset($offset);

    // get stuff from the db...
    $list = $db->get($orm->getTable(), $this);
    
    // re-populate this instance...
    if(!empty($list)){
      
      // set whether more results are available or not...
      $count = count($list);
      ///\out::e($limit, $offset, $limit_paginate, $count);
      if(!empty($limit_paginate) && ($count == $limit_paginate)){
        
        // cut off the final row since it wasn't part of the original requested rows...
        $list = array_slice($list, 0, -1);
        $has_more = true;
        $count--;
        
      }//if
      
    }//if
    
    $this->setLimit($limit); // reset the limit and no one is the wiser we messed with it
    $iterator = new MingoIterator($list, $this, $has_more);

    return $iterator;
    
  }//method
  
  /**
   *  return a \MingoOrm object that matches the criteria
   *  
   *  @return \MingoOrm or null if there was no orm found
   */
  public function getOne(){
  
    $db = $this->getDb();
    $orm = $this->getOrm();
  
    // get stuff from the db...
    $map = $db->getOne($orm->getTable(), $this);
    if(empty($map)){

      $orm = null;

    }else{

      $orm->setFields($map);

    }//if/else
    
    return $orm;
  
  }//method
  
  /**
   *  return how many rows match the criteria
   *
   *  @return integer   
   */
  public function count(){
  
    $db = $this->getDb();
    $orm = $this->getOrm();
    return $db->getCount($orm->getTable(), $this);
  
  }//method

  public function kill(){

    $ret_bool = false;
    $orm = $this->getOrm();
    $db = $this->getDb();
    $ret_bool = $db->kill($orm->getTable(), $this);
  
    return $ret_bool;

  }//method
  
  /**
   *  this will turn the {@link $orm_name} into a full fledged \MingoOrm instance and
   *  set the \MingoInterface db connection   
   *
   *  @return \MingoOrm   
   */
  public function getOrm(){
  
    $class_name = $this->orm_name;
    $orm = new $class_name();
    if(!empty($this->db)){ $orm->setDb($this->db); }//if
  
    return $orm;
  
  }//method
  
  /**
   * returns an iterator with the results
   *
   * basically, this is just to make it easy to put stuff in the query, and then just
   * start foreaching the query and have the results work without explicitely calling get()
   *
   * @since 2013-3-7
   * @return  Traversable
   */
  public function getIterator(){
    return $this->get();
  }//method

}//class     
