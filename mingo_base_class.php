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
  protected function normalizeField($field){ return mb_strtolower($field); }//method

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
   *  @return array array($prefix,$name)
   */
  protected function splitMethod($method){
  
    $ret_prefix = $ret_name = '';
  
    // get everything lowercase form start...
    for($i = 0,$max = mb_strlen($method); $i < $max ;$i++){
    
      $ascii = ord($method[$i]);
      if(($ascii < 97) || ($ascii > 122)){
      
        $ret_name = mb_substr($method,$i);
        break;
      
      }else{
      
        $ret_prefix .= $method[$i];
      
      }//if/else
    
    }//for
    
    return array($ret_prefix,$ret_name);
  
  }//method

}//class     
