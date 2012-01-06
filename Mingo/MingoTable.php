<?php
/**
 *  allows you to define some stuff about how a MingoOrm's table should be set up (eg, indexes
 *  and the like)  
 *
 *  If you want certain fields to be required (eg, don't insert unless 'foo' is set,
 *  use the {@link MingoField} class and set the required method, then pass it to {@link addField()}.
 *  
 *  If you want to make sure certain fields are of a given type (eg, 'foo' must be an int) then
 *  use the {@link MingoField} class with {@link addField()} or use the shortcut method {@link setField()}
 *  
 *  If you want to set db interface specific options, use the {@link setOption()} method. It is
 *  up to the specific db interface to tell you what the options are, and not all options
 *  (I would guess most) will be cross db interface compatible (eg, if you use the Mongo
 *  db interface's capped collections, then don't expect that to work using the SQL interface)         
 *  
 *  @version 0.6
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 11-18-09
 *  @package mingo 
 ******************************************************************************/
class MingoTable extends MingoMagic {

  /**
   *  the name of the table
   *
   *  @var  string   
   */
  protected $name = '';

  /**
   *  hold all the indexes
   *  @var  array
   */
  protected $index_map = array();
  
  /**
   *  used to store db interface specific things
   *  
   *  it is up to the db interface to specify what can go in here and how it should be used.
   *  This just allows for a convenient way for the frontend to talk to the backend in a semi
   *  documented common way               
   *
   *  @since  2-22-11
   *  @var  array
   */
  protected $option_map = array();

  /**
   *  @param  string  $name the table name
   */
  public function __construct($name){
  
    $this->setName($name);
  
  }//method
  
  public function __toString(){ return $this->getName(); }//method
  
  /**
   *  set the table's name
   *  
   *  caution! Using this method will result in the table's name being different, which
   *  means if you try and save or something it will do stuff to a different table in the db,
   *  the reason this is public is because there are genuine use cases for being able
   *  to change the table's name               
   *      
   *  @param  string  $val  the new name
   */
  public function setName($val){
    $val = $this->normalizeName($val);
    $this->name = (string)$val;
    return $this;
  }//method
  
  /**
   *  get the table's name
   *  
   *  @return string
   */
  public function getName(){ return $this->name; }//method
  
  public function hasName(){ return !empty($this->name); }//method
  
  /**
   *  add an index on the table this schema represents
   *
   *  @since  1-5-12  this is the new setIndex() that replaces addIndex()
   *  @param  string  $name the name of the index   
   *  @param  array $fields either an array with a list of fields or a key/val array with
   *                        key as fieldname and val as the definition
   *  @return self
   */
  public function setIndex($name,array $fields){
  
    // canary...
    if(empty($name)){ throw new InvalidArgumentException('the index must have a name'); }//if
    if(empty($fields)){ throw new InvalidArgumentException('no fields specified for the index'); }//if
    
    $index = new MingoIndex($name,$fields);
    return $this->addIndex($index);
    
  }//method
  
  /**
   *  add an index to the table
   *  
   *  @param  \MingoIndex $index  the index to add to the table         
   *  @return self
   */
  public function addIndex(MingoIndex $index){
  
    // canary...
    if($index->hasField('_id')){
      throw new UnexpectedValueException('an index cannot include the _id field');
    }//if
  
    $name = $index->getName();
    $this->index_map[$name] = $index;
    return $this;
  
  }//method
  
  /**
   *  get all the indexes (of any type) this schema contains
   *
   *  @since  10-15-10   
   *  @return array   
   */
  public function getIndexes(){ return $this->index_map; }//method
  
  /**
   *  return the index map
   *     
   *  @param  string  $name the name of the index   
   *  @return array
   */
  public function getIndex($name){
    
    $ret_index = array();
    $index_map = $this->getIndexes();
    if(isset($index_map[$name])){
    
      $ret_index = $index_map[$name];
    
    }//if
    
    return $ret_index;
    
  }//method
  
  /**
   *  true if this schema has indexes of any type
   *  
   *  @since  10-15-10   
   *  @return boolean
   */
  public function hasIndexes(){ return !empty($this->index_map); }//method
  public function hasIndex($name){
  
    $ret_index = array();
    $index_map = $this->getIndexes();
    if(isset($index_map[$name])){
    
      $ret_index = $index_map[$name];
    
    }//if
    
    return $ret_index;
    
  
    return $this->hasIndexes();
    
  }//method
  
  /**
   *  Set a value given it's key e.g. $A['title'] = 'foo';
   *  
   *  Required definitions of interface ArrayAccess
   *  @link http://www.php.net/manual/en/class.arrayaccess.php      
   */
  public function offsetSet($name,$val){
    
    // canary...
    if(($name === null) && ($val instanceof MingoField)){
      $this->addField($val);
    }//if
    
    parent::offsetSet($name,$val);
    
  }//method
  
  /**
   *  set a field that this class can then use internally
   *      
   *  @since  4-26-11
   *  @param  string  $name the name of the field
   *  @param  integer $type the type of the field, one of the MingoField::TYPE_* constants         
   */
  public function setField($name,$type){
  
    $field = new MingoField($name);
    $field->setType($type);
    return $this->addField($field);
  
  }//method
  
  /**
   *  add a field
   *  
   *  originally, I wanted this to be named setField but in order for this class to
   *  extend MingoMagic I can't override the setField method to only take one parameter
   *  (which is one of my least favorite things about php) and so addField was the
   *  next best method name I could think of, but I also changed setIndex() to addIndex()
   *  so it would match this method                      
   *
   *  @param  MingoField  $field  the field
   *  @return MingoSchema   
   */
  public function addField(MingoField $field){
  
    // canary...
    if(!$field->hasName()){
      throw new InvalidArgumentException('$field must have a name set');
    }//if
  
    $name = $field->getNameAsString();
    $this->field_map[$name] = $field;
    
    return $this;
    
  }//method
  
  /**
   *  get a field instance for the given name
   *  
   *  this will return any previously set field instance if it exists, if it doesn't
   *  then it will create a new one so it guarrantees to always return an mingo_field
   *  instance         
   *
   *  @param  string|array  $name the field's name   
   *  @return mingo_field
   */
  public function getField($name,$default_val = null){
  
    $ret_instance = parent::getField($name,null);
    
    if($ret_instance === null){
      $ret_instance = new MingoField($name);
      $ret_instance->setDefaultVal($default_val);
    }//if
  
    return $ret_instance;
  
  }//method
  
  /**
   *  return all the options
   *  
   *  @since  2-22-11   
   *  @return array
   */
  public function getOptions(){ return $this->option_map; }//method
  
  /**
   *  get an option field, or $default_val if the option field isn't set
   *  
   *  @since  2-22-11
   *  @param  string  $name the option field name   
   *  @return mixed
   */
  public function getOption($name,$default_val = null){
    return isset($this->option_map[$name]) ? $this->option_map[$name] : $default_val;
  }//method
  
  /**
   *  set an option field
   *  
   *  @since  2-22-11   
   *  @param  string  $name the field name
   *  @param  mixed $val  the value the $name should be set to      
   */
  public function setOption($name,$val){
  
    // canary...
    if(empty($name)){ throw new InvalidArgumentException('$name cannot be empty'); }//if
    $this->option_map[$name] = $val;
    
  }//method

}//class     
