<?php
/** 
 *  handle command line stuff
 * 
 *  @version 0.1
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 6-8-10
 *  @package cli_tools
 ******************************************************************************/
class Cli
{
  protected $argv_map = array();

  public function __construct($argv,$required_argv_map = array()){
  
    if(!empty($argv)){
    
      // do some hackish stuff to decide if the first argv needs to be stripped...
      $bt = debug_backtrace();
      if(!empty($bt[0]['file'])){
        $file_path = $bt[0]['file'];
        $file_name = basename($bt[0]['file']);
        if(($argv[0] == $file_path) || ($argv[0] == $file_name)){
          $argv = array_slice($argv,1);
        }//if
      }//if
      
      $this->argv_map = $this->parseArgv($argv,$required_argv_map);
      
    }//if
  
  }//method
  
  public function get(){ return $this->argv_map; }//if

  /**
   *  function to make passing arguments into a CLI script easier
   *  
   *  an argument has to be in the form: --name=val or --name if you want name to be true
   *  
   *  if you want to do an array, then specify the name multiple times: --name=val1 --name=val2 will
   *  result in ['name'] => array(val1,val2)
   *  
   *  @param  array $argv the values passed into php from the commmand line
   *  @param  array $required_argv_map hold required args that need to be passed in to be considered valid.
   *                                  The name is the key and the required value will be the val, if the val is null
   *                                  then the name needs to be there with a value (in $argv), if the val 
   *                                  is not null then that will be used as the default value if 
   *                                  the name isn't passed in with $argv 
   *  @return array the key/val mappings that were parsed from --name=val command line arguments
   */
  public function parseArgv($argv,$required_argv_map = array())
  {
    $ret_map = array();
  
    // build the map that will be returned...
    if(!empty($argv)){
    
      foreach($argv as $arg){
      
        // canary...
        if((!isset($arg[0]) || !isset($arg[1])) || ($arg[0] != '-') || ($arg[1] != '-')){
          throw new InvalidArgumentException(
            sprintf('%s does not conform to the --name=value convention',$arg)
          );
        }//if
      
        $arg_bits = explode('=',$arg,2);
        // strip off the dashes...
        $name = mb_substr($arg_bits[0],2);
        
        $val = true;
        if(isset($arg_bits[1])){
          
          $val = $arg_bits[1];
          
          if(!is_numeric($val)){
            
            // convert literal true or false into actual booleans...
            switch($val){
            
              case 'true':
              case 'TRUE':
                $val = true;
                
              case 'false':
              case 'FALSE':
                $val = false;
            
            }//switch
          
          }//if
          
        }//if
        
        if(isset($ret_map[$name])){
        
          if(!is_array($ret_map[$name])){
            $ret_map[$name] = array($ret_map[$name]);
          }//if
          
          $ret_map[$name][] = $val;
          
        }else{
        
          $ret_map[$name] = $val;
          
        }//if/else
      
      }//foreach
      
    }//if
  
    // make sure any required key/val pairings are there...
    if(!empty($required_argv_map)){
    
      foreach($required_argv_map as $name => $default_val){
      
        if(!isset($ret_map[$name])){
          if($default_val === null){
            throw new UnexpectedValueException(
              sprintf(
                '%s was not passed in and is required, you need to pass it in: --%s=[VALUE]',
                $name,
                $name
              )
            );
          }else{
            $ret_map[$name] = $default_val;
          }///if/else
        }//if
      
      
      }//foreach
    
    }//if
  
    return $ret_map;
  
  }//method

}//class
