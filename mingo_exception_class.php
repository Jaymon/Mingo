<?php

/**
 *  all mingo objects throw this exception 
 *
 *  @version 0.1
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 11-14-09
 *  @package mingo 
 ******************************************************************************/
class mingo_exception extends Exception {

  /**
   *  override parent constructor, we do this so $code doesn't have to be numeric
   *  
   *  @param  string  $message
   *  @param  string|integer  $code
   *  @param  Exception $previous
   */
  function __construct($message,$code = 0,Exception $previous = null){
  
    parent::__construct($message,0); // php 5.2 compatible
    ///parent::__construct($message,0,$previous); // php >5.3
    $this->code = $code;
  
  }//method

}//class     
