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

  function __construct($message,$code,Exception $previous = null){
  
    parent::__construct($message,0,$previous);
    $this->code = $code;
  
  }//method

}//class     
