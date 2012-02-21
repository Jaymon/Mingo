<?php

/**
 *  the base class for some of the public facing mingo classes, the idea is to massage 
 *  user given input to make it consistent across all the classes and add all the magical
 *  stuff to any child class    
 *  
 *  @abstract  
 *  @version 0.1
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 12-17-09
 *  @package mingo 
 ******************************************************************************/
abstract class MingoMagic implements ArrayAccess {

  /**
   *  a nice dumping area for any key/vals that don't have any place to go
   *  
   *  unless overridden, the *Field methods will use this array            
   *  
   *  @var  array associative array
   */
  protected $field_map = array();

  /**
   *  allow magicky method calls
   *
   *  to be magicky, this will convert any method call in the form: commmandFieldname($args)
   *  to: commandField($field_name,$args). See {@link splitMethod()} to see how the method
   *  is divided up   
   *
   *  @param  string  $method the method name in the form commandFieldname
   *  @param  array $args the arguments passed into the $method
   *  @return mixed   
   */
  public function __call($method,array $args){
    
    list($command,$name,$args) = $this->splitMethod($method,$args);
    
    // there must be a field name...
    if(empty($name)){
      throw new BadMethodCallException(sprintf('NAME of %sNAME() cannot be empty',$command));
    }//if
    
    // the *Field method must exist...
    $call_method = sprintf('%sField',$command);
    if(!method_exists($this,$call_method)){
      throw new BadMethodCallException(
        sprintf('Method "%s" inferred from __call "%s" does not exist',$call_method,$method)
      );
    }//if
    
    // the name cannot be any of the class's arguments...
    if(array_key_exists($name,get_object_vars($this))){
      throw new BadMethodCallException(sprintf('a field cannot have this name: %s',$field->getName()));
    }//if
    
    // the *Field method takes the field name as its first argument...
    array_unshift($args,$name);
    
    return call_user_func_array(array($this,$call_method),$args);
  
  }//method

  /**
   *  allows magic getting of what look like public class properties
   *  
   *  $a = new class();
   *  $a->prop = 'foo';         
   *
   *  @return mixed
   */
  public function __get($name){ return $this->getField($name); }//method

  /**
   *  allows magic setting of what look like public class properties
   *  
   *  $a = new class();
   *  $a->prop = 'foo';
   *  echo $a->prop; // 'foo'
   */
  public function __set($name,$val){ return $this->getField($name,$val); }//method

  /**
   *  allows verifying if magic property is set
   *  
   *  $a = new class();
   *  $a->prop = 'foo';
   *  echo isset($a->prop); // true
   *   
   *  @return boolean
   */
  public function __isset($name){ return $this->existsField($name); }//method

  /**
   *  allows unsetting magic property
   *  
   *  $a = new class();
   *  $a->prop = 'foo';
   *  unset($a->prop);
   */
  public function __unset($name){ return $this->killField($name); }//method

  /**
   *  Set a value given it's key e.g. $A['title'] = 'foo';
   *  
   *  Required definitions of interface ArrayAccess
   *  @link http://www.php.net/manual/en/class.arrayaccess.php      
   */
  public function offsetSet($name,$val){
    
    // canary...
    if($name === null){ throw InvalidArgumentException('$name was null'); }//if
    
    // they specified the key, so this will work on the internal objects...
    $this->setField($name,$val);
    
  }//method
  
  /**
   *  Return a value given it's key e.g. echo $A['title'];
   */
  public function offsetGet($name){ return $this->getField($name,null); }//method
  
  /**
   *  Unset a value by it's key e.g. unset($A['title']);
   */
  public function offsetUnset($name){ return $this->killField($name); }//method
  
  /**
   *  Check value exists, given it's key e.g. isset($A['title'])
   */
  public function offsetExists($name){  return $this->existsField($name); }//method

  /**
   *  set a field that this class can then use internally
   *
   *  @since  4-26-11
   *  @param  string  $name the name of the field
   *  @param  mixed $val  the value of the field         
   */
  public function setField($name,$val){
    $name = $this->normalizeField($name)->getNameAsString();
    $this->field_map[$name] = $val;
    return $this;
  }//method
  
  /**
   *  get a field
   *
   *  @since  4-26-11
   *  @param  string  $name the name of the field
   *  @param  mixed $default_val  what value the field should have if not present
   *  @return mixed            
   */
  public function getField($name,$default_val = null)
  {
    $name = $this->normalizeField($name)->getNameAsString();
    return isset($this->field_map[$name]) ? $this->field_map[$name] : $default_val;
  }//method
  
  /**
   *  does a field exist and is that field non-empty?
   *
   *  @since  4-26-11
   *  @param  string  $name the name of the field
   *  @return boolean       
   */
  public function hasField($name){
    $name = $this->normalizeField($name)->getNameAsString();
    return !empty($this->field_map[$name]);
  }//method
  
  /**
   *  does a field exist
   *
   *  @since  4-26-11
   *  @param  string  $name the name of the field
   *  @return boolean       
   */
  public function existsField($name){
    $name = $this->normalizeField($name)->getNameAsString();
    return isset($this->field_map[$name]);
  }//method
  
  /**
   *  remove a field
   *
   *  @since  4-27-11   
   *  @param  string  $name the name of the field
   */
  public function killField($name)
  {
    $name = $this->normalizeField($name)->getNameAsString();
    if(isset($this->field_map[$name])){ unset($this->field_map[$name]); }//if
    return $this;
  }//method
  
  /**
   *  true if there are any fields
   *  
   *  @since  1-11-12   
   *  @return boolean
   */
  public function hasFields(){ return !empty($this->field_map); }//method
  
  /**
   *  return the defined fields
   *  
   *  @since  11-4-10   
   *  @return array an array with field names as key and mingo_field instances as values
   */
  public function getFields(){ return $this->field_map; }//method

  /**
   *  get the best value for a field
   *
   *  @since  3-24-11
   *  @param  string  $name the partial method name that will be appended to set and get to make the full
   *                        method names
   *  @param  mixed $val  the value that was originally passed in
   *  @param  boolean $assure true to have an exception thrown if no valid value is found
   *  @return mixed the found value               
   */
  protected function checkField($name,$val,$assure = false)
  {
    if(empty($val))
    {
      $field_val = $this->getField($name);
      if(empty($field_val))
      {
        if($assure)
        {
          throw new UnexpectedValueException(
            sprintf('no %s found. Please set it',$name)
          );
        }//if
      }else{
        $val = $field_val;
      }//if/else
    
    }else{
      $this->setField($name,$val);
    }//if/else

    return $val;

  }//method

  /**
   *  splits the $method by the first non lowercase char found
   *  
   *  the reason why we split on the first capital is because if we just did find
   *  first substring that matches in __call(), then something like gt and gte would 
   *  match the same method, so we enforce camel casing (eg, gteEdward and gtEdward) 
   *  so that all method names can be matched. And we use this method across all
   *  __call() using classes to make it consistent.         
   *  
   *  @param  string  $method the method name that was called
   *  @param  array $args the argument array that was passed into __call() with the method
   *  @return array array($prefix,$field,$args) where prefix is a string, field is a 
   *                mingo_field instance and args is an array   
   */
  protected function splitMethod($method,$args = array()){
  
    $ret_prefix = $ret_field = '';
  
    // get everything lowercase form start...
    for($i = 0,$max = mb_strlen($method); $i < $max ;$i++){
    
      $ascii = ord($method[$i]);
      if(($ascii < 97) || ($ascii > 122)){
      
        ///$ret_field = new MingoField(mb_substr($method,$i));
        $ret_field = mb_substr($method,$i);
        break;
      
      }else{
      
        $ret_prefix .= $method[$i];
      
      }//if/else
    
    }//for
    
    if(empty($ret_field)){
    
      throw new BadMethodCallException(
        sprintf(
          'No field was specified in the method "%s". for example, if you want to "get" '
          .'the field "foo" you would do: getFoo() or getField("foo")',
          $method
        )
      ); 
    
    }else{
    
      /*
      if($ret_field->isName('field')){
      
        if(empty($args)){
        
          throw new InvalidArgumentException(
            'you are calling getField() with no field name, when using the generic '
            .'field method, you need to pass the field name as the first argument'
          );
        
        }else{
      
          $ret_field->setName($args[0]);
          $args = array_slice($args,1);
          
        }//if/else
      
      }//if */
    
    }//if/else
    
    return array($ret_prefix,$ret_field,$args);
  
  }//method
  
  /**
   *  get a standard name
   *  
   *  @since  4-29-11
   *  @param  string  $name the name
   *  @return string  the normalized name
   */
  protected function normalizeName($name){
  
    $field = $this->normalizeField($name);
    $normalized_name = $field->getName(); 
    return $normalized_name;
  
  }//method

  /**
   *  get a standard field
   *  
   *  @since  5-2-11
   *  @param  string|MingoField  $name the name
   *  @return MingoField
   */
  protected function normalizeField($field){
  
    if(!($field instanceof MingoField)){
    
      $field = new MingoField($field);
      
    }//if/else
    
    return $field;
  
  }//method
  
  /**
   *  take the value and turn it into a flat array
   *  
   *  @example
   *    $list = array(1,2,3,array(4,5,array(6,7)));
   *    $a = $this->getList($list);
   *    print_r($a); // array(1,2,3,4,5,6,7)         
   *      
   *  @param  array $list the array to flatten        
   *  @return array
   */
  protected function flattenList($list){
  
    // canary...
    if(empty($list)){
      throw new InvalidArgumentException('$list was empty');
    }//if
  
    $ret_list = array();
    $list = (array)$list;
    foreach($list as $val){
    
      if(is_array($val)){
      
        $ret_list = array_merge($ret_list,$this->flattenList($val));
      
      }else{
      
        $ret_list[] = $val;
      
      }//if/else
    
    }//foreach
  
    return $ret_list;
  
  }//method

}//class     
