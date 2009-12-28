<?php

/**
 *  the base class for some of the public facing mingo classes, the idea is to massage 
 *  user given input to make it consistent across all the classes  
 *   
 *  @version 0.1
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 12-17-09
 *  @package mingo 
 ******************************************************************************/
class mingo_base {

  /**
   *  make the val consistent
   *  
   *  @todo this should probably go through arrays also
   *      
   *  @param  string  $field  the field name
   *  @return string  the $field, normalized
   */
  protected function normalizeVal($val){

    // booleans aren't really supported, so map them to integers...
    if(is_bool($val)){ $val = empty($val) ? 0 : 1; }//if
    
    return $val;
    
  }//if/else

  /**
   *  make the field name consistent
   *  
   *  @param  string  $field  the field name
   *  @return string  the $field, normalized
   */
  protected function normalizeField($field){
    
    // canary...
    if(is_numeric($field)){
      throw new mingo_exception(aprintf('an all numeric $field like %s is not allowed',$field));
    }//if
    
    return mb_strtolower((string)$field);
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
   *  @return array array($prefix,$field,$args)
   */
  protected function splitMethod($method,$args = array()){
  
    $ret_prefix = $ret_field = '';
  
    // get everything lowercase form start...
    for($i = 0,$max = mb_strlen($method); $i < $max ;$i++){
    
      $ascii = ord($method[$i]);
      if(($ascii < 97) || ($ascii > 122)){
      
        $ret_field = $this->normalizeField(mb_substr($method,$i));
        break;
      
      }else{
      
        $ret_prefix .= $method[$i];
      
      }//if/else
    
    }//for
    
    if(empty($ret_field)){
    
      throw new mingo_exception(
        'no field was specified in the method, for example, if you want to "get" the field "foo" '.
        'you would do: getFoo() or getField(\'foo\')'
      ); 
    
    }else{
    
      if($ret_field === 'field'){
      
        if(empty($args)){
        
          throw new mingo_exception('you are calling getField() with no field name, when using the '.
            'generic field method, you need to pass the field name as the first argument'
          );
        
        }else{
      
          $ret_field = $this->normalizeField($args[0]);
          $args = array_slice($args,1);
          
        }//if/else
      
      }//if
    
    }//if/else
    
    return array($ret_prefix,$ret_field,$args);
  
  }//method

}//class     
