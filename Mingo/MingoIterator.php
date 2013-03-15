<?php

/**
 *  iterate through a MingoQuery result set  
 *  
 *  @version 0.1
 *  @author Jay Marcyes
 *  @since 1-12-12
 *  @package mingo 
 ******************************************************************************/
class MingoIterator implements ArrayAccess, Iterator, Countable {

  /**
   *  holds the pointer to the current map in {@link $list}
   *  @var  integer
   */
  protected $current_i = 0;

  /**
   *  if {@link load()} could have loaded more results, this will be set to true
   *  @var  boolean   
   */
  protected $has_more = false;

  /**
   *  the \MingoOrm that will be used to iterate
   *
   *  @var  \MingoOrm
   */
  protected $orm = null;

  /**
   * hold the returned results
   *
   * @var array
   */
  protected $list = array();

  /**
   * hold the query instance that was used to generate $list
   *
   * @var MingoQuery
   */
  protected $query = null;

  public function __construct(array $list, MingoQuery $query, $has_more = false){

    if($query){ $this->setQuery($query); }//if
    $this->list = $list;
    $this->setMore($has_more);
  
  }//method

  public function setQuery(MingoQuery $query){
  
    $this->query = $query;
    return $this;
  
  }//method

  public function getQuery(){ return $this->query; }//method
  
  /**
   *  return true if there are actual results in this instance to iterate through
   *
   *  @return boolean
   */
  public function has(){ return !empty($this->orm) || $this->count(); }//method

  /**
   *  Required definition for Countable, allows count($this) to work
   *  @link http://www.php.net/manual/en/class.countable.php
   */
  public function count(){ return count($this->list); }//method

  public function rewind(){ $this->current_i = 0; }//method
  public function current(){

    if(empty($this->orm)){

      $query = $this->getQuery();
      $this->orm = $query->getOrm();

    }//if

    $orm = clone $this->orm;
    $orm->reset(); // might not be necessary, but just in case
    $orm->setFields($this->list[$this->current_i]);
    return $orm;

  }//method
  public function key(){ return $this->current_i; }//method
  public function next(){ ++$this->current_i; }//method
  public function valid(){ return isset($this->list[$this->current_i]); }//method

  /**#@+
   *  Required definition of interface ArrayAccess
   *  @link http://www.php.net/manual/en/class.arrayaccess.php   
   */
  /**
   *  Set a value given it's key e.g. $A['title'] = 'foo';
   */
  public function offsetSet($key,$val){
  
    throw new BadMethodCallException(
      sprintf('%s is read only, you cannot %s()',get_class($this),__FUNCTION__)
    );
  
  }//method
  
  /**
   *  Return a value given it's key e.g. echo $A['title'];
   */
  public function offsetGet($key){
  
    // canary
    if(empty($this->orm)){ return null; }//if
    
    return $this->orm->get($key);
    
  }//method
  
  /**
   *  Unset a value by it's key e.g. unset($A['title']);
   */
  public function offsetUnset($key){
  
    throw new BadMethodCallException(
      sprintf('%s is read only, you cannot %s()',get_class($this),__FUNCTION__)
    );
    
  }//method
  /**
   *  Check value exists, given it's key e.g. isset($A['title'])
   */
  public function offsetExists($key){
  
    $orm = $this->offsetGet($key);
    return !empty($orm);
  
  }//method
  /**#@-*/
  
/**
   *  return true if the last db load could load more, but was limited by $limit 
   *
   *  @return boolean
   */
  public function hasMore(){ return !empty($this->has_more); }//method
  public function setMore($val){ $this->has_more = $val; }//method

}//class     
