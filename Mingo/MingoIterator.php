<?php
/**
 *  iterate through a MingoQuery result set  
 *
 *  TODO - add a setFull() method, that when set will allow iteration through all results, limit at a time,
 *  so if there were 500 results in the db, and your limit was 100, you could setFull(true) and then start
 *  iterating, and when it got to the 100th result, it would load results 101-200 and keep going, transparently
 *  until you went through all 500 results
 *  
 *  @version 0.2
 *  @author Jay Marcyes
 *  @since 1-12-12
 *  @package mingo 
 ******************************************************************************/
class MingoIterator extends MingoMagic implements Iterator, Countable {

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

  /**
   * hold the query instance that generated the internal $list
   *
   * @param MingoQuery  $query
   */
  public function setQuery(MingoQuery $query){
  
    $this->query = $query;
    return $this;
  
  }//method

  /**
   * get the query that generated the internal list of results
   *
   * @return  MingoQuery
   */
  public function getQuery(){ return $this->query; }//method
  
  /**
   *  return true if there are actual results in this instance to iterate through
   *
   *  @return boolean
   */
  public function has(){ return !empty($this->list); }//method

  /**
   *  Required definition for Countable, allows count($this) to work
   *  @link http://www.php.net/manual/en/class.countable.php
   */
  public function count(){ return count($this->list); }//method

  public function rewind(){ $this->current_i = 0; }//method
  public function current(){

    return $this->getOrm($this->list[$this->current_i]);

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
      sprintf('%s is read only, you cannot %s()', get_class($this), __FUNCTION__)
    );
  
  }//method
  
  /**
   *  Return a value given it's key e.g. echo $A['title'];
   */
  public function offsetGet($key){
  
    return $this->getOrm($this->list[$key]);

  }//method
  
  /**
   *  Unset a value by it's key e.g. unset($A['title']);
   */
  public function offsetUnset($key){
  
    throw new BadMethodCallException(
      sprintf('%s is read only, you cannot %s()', get_class($this), __FUNCTION__)
    );
    
  }//method
  /**
   *  Check value exists, given it's key e.g. isset($A['title'])
   */
  public function offsetExists($key){

    return isset($this->list[$key]);
  
  }//method
  /**#@-*/
  
  /**
   *  get a field from all orms
   *
   *  @since  2013-3-15
   *  @param  string  $name the name of the field
   *  @param  mixed $default_val  what value the field should have if not present
   *  @return mixed
   */
  public function getField($name, $default_val = null){

    // canary
    if(empty($this->list)){ return array(); }//if

    $ret_list = array();
    foreach($this as $orm){
      $ret_list[] = $orm->getField($name, $default_val);
    }//foreach

    return $ret_list;
    
  }//method
  
  /**
   *  does a field exist in all orm instances, and is that field non-empty?
   *
   *  @since  2013-3-15
   *  @param  string  $name the name of the field
   *  @return boolean       
   */
  public function hasField($name){

    // canary
    if(empty($this->list)){ return false; }//if

    $ret_bool = true;
    foreach($this as $orm){
      if(!$orm->hasField($name)){
        $ret_bool = false;
        break;
      }//if
    }//foreach

    return $ret_bool;
    
  }//method
  
  /**
   *  does a field exist in all orms
   *
   *  @since  2013-3-15
   *  @param  string  $name the name of the field
   *  @return boolean       
   */
  public function existsField($name){

    // canary
    if(empty($this->list)){ return false; }//if

    $ret_bool = true;
    foreach($this as $orm){
      if(!$orm->existsField($name)){
        $ret_bool = false;
        break;
      }//if
    }//foreach

    return $ret_bool;
    
  }//method
  
  /**
   *  are all $name fields in all the orms equal to $val
   *
   *  @since  2013-3-15
   *  @param  string  $name the name of the field
   *  @return boolean       
   */
  public function isField($name, $val){
  
    // canary
    if(empty($this->list)){ return false; }//if

    $ret_bool = true;
    foreach($this as $orm){
      if(!$orm->isField($name, $val)){
        $ret_bool = false;
        break;
      }//if
    }//foreach

    return $ret_bool;
    
  }//method
  
  /**
   *  the value at $name must contain $val
   *  
   *  @since  2013-3-15
   *  @param  string  $name the name of the field
   *  @param  mixed $val,...  one or more values to match against
   *  @return boolean   
   */        
  public function inField($name, $val){

    // canary
    if(empty($this->list)){ return false; }//if

    $ret_bool = true;
    foreach($this as $orm){
      if(!$orm->inField($name, $val)){
        $ret_bool = false;
        break;
      }//if
    }//foreach

    return $ret_bool;

  }//method

  /**
   *  remove a field
   *
   *  @since  2013-3-15
   *  @param  string  $name the name of the field
   */
  public function killField($name){

    throw new BadMethodCallException(
      sprintf('%s is read only, you cannot %s()', get_class($this), __FUNCTION__)
    );

  }//method
  
  public function hasFields(){
  
    // canary
    if(empty($this->list)){ return false; }//if

    $ret_bool = true;
    foreach($this as $orm){
      if(!$orm->hasFields()){
        $ret_bool = false;
        break;
      }//if
    }//foreach

    return $ret_bool;

  }//method
    
  /**
   *  return the defined fields
   *  
   *  @since  2013-3-15
   *  @return array an array with field names as key and mingo_field instances as values
   */
  public function getFields(){

    // canary
    if(empty($this->list)){ return array(); }//if

    $ret_list = array();
    foreach($this as $orm){
      $ret_list = $orm->getFields();
    }//foreach

    return $ret_list;

  }//method

  public function setFields(array $field_map){

    throw new BadMethodCallException(
      sprintf('%s is read only, you cannot %s()', get_class($this), __FUNCTION__)
    );

  }//method

/**
   *  return true if the last db load could load more, but was limited by $limit 
   *
   *  @return boolean
   */
  public function hasMore(){ return !empty($this->has_more); }//method
  public function setMore($val){ $this->has_more = $val; }//method

  /**
   * return a fully fleshed out orm instance
   *
   * used to turn associative arrays into orm instances in the iterator and array methods
   *
   * @param array $field_map  the array values that will be wrapped by the orm instance
   * @return  MingoOrm
   */
  protected function getOrm(array $field_map){

    if(empty($this->orm)){

      $query = $this->getQuery();
      $this->orm = $query->getOrm();

    }//if

    $orm = clone $this->orm;
    $orm->reset(); // might not be necessary, but just in case
    $orm->setFields($field_map);
    return $orm;

  }//method

}//class     

