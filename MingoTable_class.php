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
 *  @version 0.5
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
   *  used to set fields that should be there when the db is set
   *     
   *  @see  requireField()
   *  @var  array   
   */        
  protected $required_map = array();
  
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
  
  /**
   *  set the table's name
   *  
   *  @param  string  $val  the new name
   */
  protected function setName($val){
    $val = $this->normalizeName($val);
    $this->name = $val;
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
   *  @param  string|array  $field,...
   *                          1 - pass in a bunch of strings, each one representing
   *                              a field name: setIndex('field_1','field_2',...);
   *                          2 - pass in an array with this structure: array('field_name' => options,...)
   *                              where the field's name is the key and the value is anything your chosen
   *                              interface can understand (bear in mind using options might limits 
   *                              portability)      
   *  @return MingoSchema
   */
  public function addIndex(){
  
    $args = func_get_args();
    
    // canary...
    if(empty($args)){ throw new InvalidArgumentException('no fields specified for the index'); }//if
    
    $field_list = array();
    $index_map = $this->normalizeIndex($args);
    
    // canary...
    if(isset($index_map['_id'])){
      throw new UnexpectedValueException('a table index cannot include the _id field');
    }//if
    
    $this->index_map[] = $index_map;
    
    return $this;
  
  }//method
  public function setIndex(){
    $args = func_get_args();
    return call_user_func_array(array($this,'addIndex'),$args);
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
   *  @return array
   */
  public function getIndex(){ return $this->getIndexes(); }//method
  
  /**
   *  true if this schema has indexes of any type
   *  
   *  @since  10-15-10   
   *  @return boolean
   */
  public function hasIndexes(){ return !empty($this->index_map); }//method
  public function hasIndex(){ return $this->hasIndexes(); }//method
  
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
  
    if($field->isRequired()){
      $this->requireField($name,$field->getDefaultVal());
    }//if
  
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
   *  return the required fields
   *  
   *  @return array
   */
  public function getRequiredFields(){ return $this->required_map; }//method
  
  /**
   *  set a required field
   *  
   *  @param  string  $name the field name, it should already be normalized
   *  @param  mixed $default_val  if something other than null then that value will be put into the field
   *                              and an error won't be thrown if the field isn't there      
   */
  protected function requireField($name,$default_val = null){
  
    // canary...
    if(empty($name)){ throw new InvalidArgumentException('$name cannot be empty'); }//if
    $this->required_map[$name] = $default_val;
    
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
  
  /**
   *  normalizes all the index fields
   *  
   *  @param  array $field_list these are the fields that will be in the index
   *  @return array an array with field name keys and type values
   */
  protected function normalizeIndex($field_list){
  
    $index_map = array();
    $i = 0;
    
    foreach($field_list as $field){
    
      if(is_array($field)){
      
        foreach($field as $field_name => $type){
        
          $field_name = $this->normalizeName($field_name);
          $index_map[$field_name] = $type;
        
          $i++;
        
        }//foreach
      
      }else{
      
        $field = $this->normalizeName($field);
        ///$index_map[$field] = self::INDEX_ASC;
        $index_map[$field] = '';
      
      }//if/else
    
      $i++;
    
    }//foreach
  
    return $index_map;
  
  }//method

}//class     
