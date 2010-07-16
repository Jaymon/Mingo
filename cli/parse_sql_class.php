<?php
class parse_sql extends SQL_Parser {

  function __construct($string = null, $dialect = 'Mingo'){
  
    $this->dialects[] = 'Mingo';
    
    // let's do some normalizing to get past something stupid the parser does...
    if(!empty($string) && preg_match('#^select#i',$string)){
    
      $string = preg_replace('#offset#i',',',$string,1);
    
    }//if
  
    $this->SQL_Parser($string,$dialect);
  
  }//method
  
  /**
   *  turn an error into an exception
   *  
   *  PEAR::setErrorHandling(PEAR_ERROR_EXCEPTION); should have been able to do this
   *  without resorting to overriding the method, but it raises a warning saying it's
   *  deprecated, my hell!            
   *
   *  @param  string  $message
   *  @throws Exception   
   */
  function raiseError($message) {
      
      $error_map = parent::raiseError($message);
      throw new Exception($error_map->getMessage());
      
  }//method

}//class
?>
