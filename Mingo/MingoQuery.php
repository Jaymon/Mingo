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
class MingoQuery extends MingoCriteria {

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
  
  /**
   *  return a result set
   *  
   *  @return \MingoIterator  this will always return an iterator, even if there are no rows matching
   */
  public function get(){
  
    $orm = $this->createOrm();
    $iterator = $this->createIterator();
  
    $orm->load($this);
    $iterator->setOrm($orm);
  
    return $iterator;
  
  }//method
  
  /**
   *  return a \MingoOrm object that matches the criteria
   *  
   *  @return \MingoOrm or null if there was no orm found
   */
  public function getOne(){
  
    $orm = $this->createOrm();
    if(!$orm->loadOne($this)){
    
      $orm = null;
    
    }//if
  
    return $orm;
  
  }//method
  
  /**
   *  return how many rows match the criteria
   *
   *  @return integer   
   */
  public function count(){
  
    $orm = $this->createOrm();
    return $orm->loadTotal($this);
  
  }//method
  
  /**
   *  this will turn the {@link $orm_name} into a full fledged \MingoOrm instance and
   *  set the \MingoInterface db connection   
   *
   *  @return \MingoOrm   
   */
  protected function createOrm(){
  
    $class_name = $this->orm_name;
    $orm = new $class_name();
    if(!empty($this->db)){ $orm->setDb($this->db); }//if
  
    return $orm;
  
  }//method
  
  /**
   *  create an iterator
   *
   *  @return \MingoIterator   
   */
  protected function createIterator(){ return new MingoIterator(); }//method

}//class     
