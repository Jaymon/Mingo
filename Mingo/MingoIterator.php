<?php

/**
 *  iterate through a MingoQuery result set  
 *  
 *  @version 0.1
 *  @author Jay Marcyes
 *  @since 1-12-12
 *  @package mingo 
 ******************************************************************************/
class MingoIterator implements ArrayAccess, Iterator, Count {

  /**
   *  the \MingoOrm that will be used to iterate
   *
   *  @var  \MingoOrm
   */
  protected $orm = null;

  public function setOrm(MingoOrm $orm){
  
    $this->orm = $orm;
    return $this;
  
  }//method
  
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
  public function count(){
    
    // canary...
    if(empty($this->orm)){ return 0; }//if
    
    return count($this->orm);
    
  }//method

  /**#@+
   *  Required method definitions of Iterator interface
   *  
   *  @link http://php.net/manual/en/class.iterator.php      
   */
  public function rewind(){ $this->orm->rewind(); }//method
  public function current(){ return $this->orm->current(); }//method
  public function key(){ return $this->orm->key(); }//method
  public function next(){ $this->orm->next(); }//method
  public function valid(){
    
    // canary
    if(empty($this->orm)){ return false; }//if
    
    return $this->orm->valid();
    
  }//method
  /**#@-*/
  
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
   *  return true if there is at least another page of results available
   *
   *  @return boolean
   */
  public function hasMore(){
  
    // canary
    if(empty($this->orm)){ return false; }//if
  
    return $this->orm->hasMore();
    
  }//method alias

}//class     
