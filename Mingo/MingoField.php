<?php

/**
 *  allows you to define some stuff about how a mingo_orm should be set up (eg, indexes
 *  and the like)  
 *
 *  @version 0.1
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 10-19-10
 *  @package mingo 
 ******************************************************************************/
class MingoField {
  
  const TYPE_DEFAULT = 0;
  const TYPE_INT = 1;
  const TYPE_STR = 2;
  const TYPE_POINT = 3;
  const TYPE_LIST = 4;
  const TYPE_MAP = 5;
  const TYPE_OBJ = 6;
  const TYPE_BOOL = 7;
  const TYPE_FLOAT = 8;
  
  /**
   *  handle the type hints
   *  
   *  uses the FIELD_* constants to allow type hints
   *      
   *  @see  setType()   
   *  @since  10-19-10   
   *  @var  array
   */        
  protected $field_map = array();
  
  /**
   *  used to store field specific options
   *
   *  @since  6-24-11
   *  @var  array
   */
  protected $option_map = array();

  final public function __construct($name = '',$type = self::TYPE_DEFAULT,$range = null){
  
    $this->setName($name);
    $this->setType($type);
    $this->setRange($range);
  
  }//method
  
  public function setType($type){
  
    $this->field_map['type'] = $type;
  
  }//method
  
  public function hasType(){ return !empty($this->field_map['type']); }//method
  public function getType(){ return $this->hasType() ? $this->field_map['type'] : self::TYPE_DEFAULT; }//method
  
  /**
   *  true if the field is the passed in $type
   *
   *  @since  6-28-11
   *  @return boolean   
   */
  public function isType($type){
    
    // canary...
    if(!$this->hasType()){ return false; }//if
    
    return ($this->field_map['type'] === $type);
  
  }//method
  
  public function setRange($range){
  
    if(empty($range)){ return; }//if
    
    $this->field_map['range'] = array();
    
    if(is_array($range)){
    
      if(isset($range[0]) && !empty($range[1])){
      
        $this->field_map['range']['min'] = (int)$range[0];
        $this->field_map['range']['max'] = (int)$range[1];
      
      }else{
      
        unset($this->field_map['range']);
      
      }//if/else
    
    }else{
    
      $size = (int)$range;
      if(empty($size)){
      
        unset($this->field_map['range']);
      
      }else{
      
        $this->field_map['range']['min'] = $size;
        $this->field_map['range']['max'] = $size;
        
      }//if
    
    }//if/else
  
  }//method
  
  public function hasRange(){ return !empty($this->field_map['range']); }//method
  
  public function isFixedSize(){
    
    // canary...
    if(!$this->hasRange()){ return false; }//if
  
    return $this->field_map['range']['min'] === $this->field_map['range']['max'];
    
  }//method
  
  public function getMinSize(){
    
    // canary...
    if(!$this->hasRange()){ return null; }//if
  
    return $this->field_map['range']['min'];
  
  }//method
  
  public function getMaxSize(){
    
    // canary...
    if(!$this->hasRange()){ return null; }//if
  
    return $this->field_map['range']['max'];
  
  }//method
  
  /**
   *  make the val consistent
   *  
   *  @todo this should probably go through arrays also
   *      
   *  @param  string  $val  the val
   *  @return string  the $val, normalized
   */
  public function normalizeInVal($val){

    switch($this->getType()){
    
      case self::TYPE_INT:
        $val = (int)$val;
        break;
      
      case self::TYPE_STR:
      
        $val = (string)$val;
        break;
      
      case self::TYPE_POINT:
      
        // canary...
        if(empty($val)){
          throw new UnexpectedValueException(sprintf('point must be an array($lat (float),$long (float))'));
        }//if
        
        if(!is_array($val)){
        
          throw new InvalidArgumentException(
            sprintf('point must be an array($lat (float),$long (float)), %s given',gettype($val))
          );
          
        }else{
          
          if(!isset($val[0])){
          
            throw new UnexpectedValueException('no latitude (float) given');
            
          }else{
          
            $val[0] = $this->assureCoordinate($val[0]);
            if(($val[0] >= 90.0) || ($val[0] <= -90.0)){
              throw new UnexpectedValueException(
                sprintf('latitude of coordinate (%s) was not between 90 and -90 degrees',$val[0])
              );
            }//if
          
          }//if/else
          
          if(!isset($val[1])){
          
            throw new UnexpectedValueException('no longitude (float) given');
            
          }else{
          
            $val[1] = $this->assureCoordinate($val[1]);
            if(($val[1] >= 180.0) || ($val[1] <= -180.0)){
              throw new UnexpectedValueException(
                sprintf('longitude of coordinate (%s) was not between 180 and -180 degrees',$val[1])
              );
            }//if
          
          }//if/else
          
        }//if/else
        
        break;
      
      case self::TYPE_LIST:
      case self::TYPE_MAP:
      
        if(!is_array($val)){
        
          throw new UnexpectedValueException(
            sprintf('an array was expected, %s given',is_object($val) ? get_class($val) : gettype($val))
          );
        
        }//if
        break;
      
      case self::TYPE_OBJ:
      
        if(!is_object($val)){
        
          throw new UnexpectedValueException(
            sprintf('an object instance was expected, %s given',gettype($val))
          );
        
        }//if
        break;
      
      case self::TYPE_BOOL:
      
        $val = empty($val) ? 0 : 1;
        break;
      
      case self::TYPE_FLOAT:
      
        $val = (float)$val;
        break;
    
      case self::TYPE_DEFAULT:
      default:
      
        if(is_bool($val)){ $val = empty($val) ? 0 : 1; }//if
        break;
    
    }//switch
  
    return $val;
  
  }//method
  
  /**
   *  set to true if you would like the values of this field to be unique
   *
   *  @since  12-9-11
   *  @param  boolean $val  true for unique, false for not unique      
   */
  public function setUnique($val){ $this->field_map['is_unique'] = (bool)$val; }//method
  public function isUnique(){ return !empty($this->field_map['is_unique']); }//method
  
  public function setRequired($val){ $this->field_map['is_required'] = !empty($val); }//method
  public function isRequired(){ return !empty($this->field_map['is_required']); }//method
  
  public function setDefaultVal($val){ $this->field_map['default_val'] = $val; }//method
  public function hasDefaultVal(){ return isset($this->field_map['default_val']); }//method
  public function getDefaultVal(){
    return $this->hasDefaultVal() ? $this->field_map['default_val'] : null;
  }//method
  
  public function __toString(){ return $this->getNameAsString(); }//method
  
  /**
   *  since {@link getName()} can return either a string or array, this guarrantees
   *  name will be string
   *  
   *  @return string
   */
  public function getNameAsString(){
    
    $name = $this->getName();
    if(is_array($name)){
      $name = join('.',$name);
    }//if
    
    return $name;
    
  }//method
  
  public function setName($name){
  
    // canary...
    if(empty($name)){
    
      $name = '';
    
    }else{
    
      $name = $this->normalizeName($name);
    
    }//if/else
  
    $this->field_map['name'] = $name;
  
  }//method
  
  public function getName(){ return $this->hasName() ? $this->field_map['name'] : ''; }//method
  public function hasName(){ return !empty($this->field_map['name']); }//method
  
  public function isName($name){ return $this->getName() === $name; }//method
  
  /**
   *  return all the options
   *  
   *  @since  6-24-11   
   *  @return array
   */
  public function getOptions(){ return $this->option_map; }//method
  
  /**
   *  get an option field, or $default_val if the option field isn't set
   *  
   *  @since  6-24-11
   *  @param  string  $name the option field name   
   *  @return mixed
   */
  public function getOption($name,$default_val = null){
    return isset($this->option_map[$name]) ? $this->option_map[$name] : $default_val;
  }//method
  
  /**
   *  set an option field
   *  
   *  @since  6-24-11   
   *  @param  string  $name the field name
   *  @param  mixed $val  the value the $name should be set to      
   */
  public function setOption($name,$val){
  
    // canary...
    if(empty($name)){ throw new InvalidArgumentException('$name cannot be empty'); }//if
    $this->option_map[$name] = $val;
    
  }//method
  
  /**
   *  make the field name consistent
   *  
   *  @param  string  $name  the field name
   *  @return string|array  the field's $name, normalized
   */
  protected function normalizeName($name){
    
    // canary...
    if(is_numeric($name)){
      throw new InvalidArgumentException(sprintf('an all numeric $name like "%s" is not allowed',$name));
    }//if
    
    $ret_mix = null;
    
    if(is_array($name)){
    
      $ret_mix = array();
    
      foreach($name as $key => $val){
        $ret_mix[$key] = mb_strtolower((string)$val);
      }//foreach
    
    }else{
    
      // get rid of any namespaces...
      $ret_mix = str_replace('\\','_',$name);
      $ret_mix = mb_strtolower((string)$ret_mix);
      
    }//if/else
    
    return $ret_mix;
    
  }//method
  
  /**
   *  convert a coordinate in any format to a decimal coordinate
   *  
   *  link I used to figure out the math:
   *  http://zonalandeducation.com/mmts/trigonometryRealms/degMinSec/degMinSec.htm
   *  
   *  @since  8-19-10   
   *  @param  string|array|float  $coordinate either a string "DEGREE MINUTE SECOND" or a tri-array with
   *                                          0 => degree, 1 => minute, 2 => second or a float that is
   *                                          already converted so it will be returned
   *  @return float the coordinate as a decimal
   */
  protected function assureCoordinate($coordinate)
  {
    // canary, if already numeric our work here is done...
    if(is_numeric($coordinate)){ return (float)$coordinate; }//if
    // canary, if we have a string then divide up into degrees minutes seconds array
    if(is_string($coordinate)){
      $coordinate = explode(' ',str_replace(array('°','"',"'"),'',$coordinate));
    }//if
    // canary, $coordinate needs to be an array by the time it gets here...
    if(!is_array($coordinate)){
      throw new UnexpectedValueException(
        sprintf('$coordinate was not a string or array, it was a %s',gettype($coordinate))
      );
    }//if
      
    // break the array into degrees minutes and seconds...
    $degrees = (float)$coordinate[0];
    $minutes = isset($coordinate[1]) ? (float)$coordinate[1] : 0.0; // 1/60th of a degree
    $seconds = isset($coordinate[2]) ? (float)$coordinate[2] : 0.0; // 1/60th of a minute
    
    return $degrees + ($minutes * (1/60.0)) + ($seconds * (1/3600));
      
  }//method


}//class     
