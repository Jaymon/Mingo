<?php
/** 
 *  handle command line stuff
 * 
 *  @version 0.1
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 6-8-10
 *  @package cli_tools
 ******************************************************************************/
class cli_in
{
  const CMD_NONE = 0;
  const CMD_EXIT = 1;
  const CMD_SELECT = 2;

  protected $input = '';
  protected $is_done = false;
  
  protected $cmd_map = array();

  /**
   *  hold the stdin resource to get user input
   *      
   *  @var  resource
   */
  protected $stdin = null;

  public function __construct(){
  
    // open an input connection to get lines from the user...
    $this->stdin = fopen('php://stdin','r');
  
  }//method
  
  public function reset(){
  
    $this->input = '';
    $this->setDone(false);
  
  }//method
  
  public function getLine(){
  
    $line = rtrim(fgets($this->stdin));
    $this->append($line);
  
  }//method
  
  protected function append($line){
  
    if(!empty($line)){
    
      // decide if this is the end of the command...
      $end_of_line_count = 0;
      $line = preg_replace('#(\;|\\[gG])$#','',$line,1,$end_of_line_count);
      $this->setDone($end_of_line_count > 0);
      
    }//if
    
    $this->input .= sprintf('%s%s',$line,PHP_EOL);
  
  }//method
  
  protected function SetDone($val){ $this->is_done = (boolean)$val; }//method
  public function isDone(){ return $this->is_done; }//method
  
  public function hasInput(){ return !empty($this->input); }//method
  
  public function get(){ return $this->input; }//if
  
  public function getCommand(){
    return empty($this->cmd_map['command']) ? self::CMD_NONE : $this->cmd_map['command'];
  }//if

  public function handle(){
  
    // canary...
    if(empty($this->input)){ return false; }//if
    
    $input = trim($this->input);
    
    // do we have an exit...
    if(preg_match('#^(?:exit|quit)#i',$input)){
    
      throw new cli_stop_exception();
    
    }else{
    
      $sql_parser = new SQL_Parser($input);
      $parse_map = $sql_parser->parse();
      out::e($parse_map);
      out::x();
    
    }//if/else
  
  }//method
  
  protected function parseExit($input){
  
    $this->cmd_map['command'] = self::CMD_EXIT;
  
  }//method
  
  protected function parseSelect($input){
  
    $this->cmd_map['command'] = self::CMD_SELECT;
    
    $regex = '#'
      .'^select\s+(.*?)\s+' // get the fields to be returned
      .'from\s+(.*?)\s+' // get the table
      .'where\s+(.*?)$' // get the where fields that are being selected on
      .'#i';
    
    $matched = array();
    if(preg_match($regex,$input,$matched)){
    
      out::e($matched);
      
      $this->cmd_map['select_fields'] = array_map('trim',explode(',',$matched[1]));
      $this->cmd_map['select_table'] = $matched[2];
      
      $this->cmd_map['where_fields'] = array();
      
      $where_fields = trim($matched[3]);
      for($i = 0,$len = mb_strlen($where_fields); $i < $len ;$i++){
      
        $field_name = $field_symbol = $field_val = $field_sep = '';
      
        // first things first, get the field name...
        while(!ctype_space($where_fields[$i]) && ctype_alnum($where_fields[$i])){
          $field_name .= $where_fields[$i];
          $i++;
        }//while
        
        // move past any whitespace...
        while(ctype_space($where_fields[$i])){ $i++; }//while
      
        // now get the symbol...
        while($i < $len){
        
          $field_symbol .= $where_fields[$i];
          $i++;
          
          $symbol_list = array('=','!=','>','>=','<','<=');
          $symbol_regex_list = array('in','not\s+in');
          
          if(in_array($field_symbol,$symbol_list,true)){
          
            break;
            
          }else{
            
            foreach($symbol_regex_list as $symbol_regex){

              if(preg_match(sprintf('#^%s$#i',$symbol_regex),$field_symbol)){
                break 2;
              }//if
              
            }//for
            
          }//if/else
          
        }//while
        
        // move past any whitespace...
        while(ctype_space($where_fields[$i])){ $i++; }//while
        
        // now get the value...
        switch($where_fields[$i]){
        
          case "'": // we have a string, so go until we get another '
            
            $i++; // move past the '
          
            while($where_fields[$i] !== "'"){
              $field_val .= $where_fields[$i];
              $i++; 
            }//while
        
            $i++; //move past the last '
        
            break;
        
          case '(': // we have an array, go until )
        
            $i++; // move past the '
          
            while($where_fields[$i] !== ')'){
              $field_val .= $where_fields[$i];
              $i++; 
            }//while
        
            $field_val = array_map('trim',explode(',',$field_val));
        
            $i++; //move past the last '
        
            break;
            
          default: // we have a digit
          
            while(($i < $len) && !ctype_space($where_fields[$i])){
              $field_val .= $where_fields[$i];
              $i++; 
            }//while
            
            break;
        
        }//switch
        
        // move past any whitespace...
        while(ctype_space($where_fields[$i])){ $i++; }//while
        
        // if we have more, move past the AND, if it is an or then fail...
      
        out::e($field_name,$field_symbol,$field_val,$field_sep);
      
      }//for 
      
    
    
    }else{
    
      throw new UnexpectedValueException(
        'Invalid select query, please check your syntax and try again. '
        .'JOINS are not allowed!'
      );
    
    }//if/else
  
  }//method

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
  
  function __destruct(){
  
    fclose($this->stdin);
  
  }//method

}//class
