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
   *  holds a previous exception, the reason this is here is because <5.3 doesn't
   *  have native support for previous, so to keep backwards compatibility I had
   *  to add it
   *  
   *  @var  Exception
   */
  protected $previous = null;

  /**
   *  override parent constructor, we do this so $code doesn't have to be numeric
   *  
   *  @param  string  $message
   *  @param  string|integer  $code
   *  @param  Exception $previous
   */
  function __construct($message,$code = 0,Exception $previous = null){
  
    if(method_exists($this,'getPrevious')){
      parent::__construct($message,0,$previous); // php >5.3
    }else{
      parent::__construct($message,0); // php 5.2 compatible
    }//if/else
    $this->code = $code;
    $this->previous = $previous;
  
  }//method
  
  function __toString(){
  
    $e_msg = array();
    $e_msg[] = sprintf(
      '%s code(%s) - "%s" thrown in "%s:%s"',
      get_class($this),
      $this->getCode(),
      $this->getMessage(),
      $this->getFile(),
      $this->getLine()
    );
  
    if(!empty($this->previous)){
    
      $e_msg[] = $this->getPreviousAsString($this->previous);
    
      $e = $this->previous;
      while(method_exists($e,'getPrevious')){
        
        $e = $e->getPrevious();
        $prev_msg = $this->getPreviousAsString($e);
        if(!empty($prev_msg)){
          $e_msg[] = $prev_msg;
        }//if 
      
      }//while
      
    }//if
    
    $e_msg[] = '';
    $e_msg[] = 'Backtrace: ';
    $e_msg[] = $this->getTraceAsString();
    
    return join("\r\n",$e_msg);
  
  }//method
  
  /**
   *  outputs a previous set of exceptions
   *  
   *  @param  array|Exception $e
   *  @return string
   */
  private function getPreviousAsString($e){
  
    // sanity...
    if(empty($e)){ return ''; }//if
    if(!is_array($e)){ $e = array($e); }//if
  
    $ret_msg = array();
  
    foreach($e as $exception){
        
      $ret_msg[] = sprintf(
        'previously: %s code(%s) - "%s" thrown in "%s:%s"',
        get_class($exception),
        $exception->getCode(),
        $exception->getMessage(),
        basename($exception->getFile()),
        $exception->getLine()
      );
      
    }//if
  
    return join("\r\n",$ret_msg);
  
  }//method

}//class     
