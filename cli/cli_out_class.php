<?php
/** 
 *  handle command line stuff
 * 
 *  @version 0.1
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 6-8-10
 *  @package cli_tools
 ******************************************************************************/
class cli_out
{
  /**
   *  used for indent in functions like {@link aIter()} and {@link outInfo()}
   *  @var  string   
   */     
  protected $indent = "\t";
  
  protected $output = null;
  
  protected $timestamp_start = 0;
  protected $timestamp_stop = 0;

  /**
   *  default constructor
   *  
   *  @param  mixed $output what is going to be rendered to the screen
   */
  public function __construct($output = null,$timestamp_start = 0,$timestamp_stop = 0){
  
    $this->output = $output;
    $this->timestamp_start = $timestamp_start;
    $this->timestamp_stop = $timestamp_stop;
  
  }//method

  public function handle(){

    $output = $this->output;
    $count = 1;

    if(is_array($output)){
    
      $count = count($output);
      $row_count = 1;
    
      if(is_array($output[0]) || is_object($output[0])){
      
        // render each row with formatting...
        foreach($output as $row){
        
          echo sprintf('*************************** %s. row ***************************%s',$row_count,PHP_EOL);
          echo $this->handleArray($row);
          echo PHP_EOL;
          $row_count++;
        
        }//foreach
      
      }else{
      
        // render each row without any formatting since they aren't objects or arrays...
        foreach($output as $row){
        
          echo sprintf("%s.\t%s%s",$row_count,$row,PHP_EOL);
          $row_count++;
        
        }//foreach
      
      }//if/else
    
    }else{
    
      echo sprintf('*************************** 1. row ***************************%s',PHP_EOL);
    
      if(is_bool($output)){
      
        if(empty($output)){
        
          echo sprintf('Query Failed with: %s',$this->handleDefaultVal($output)); 
        
        }else{
        
          echo 'Query Ok!';
        
        }//if/else
      
      }else{
      
        echo $this->handleDefaultVal($output);
      
      }//if/else
      
      echo PHP_EOL;
    
    }//if/else
    
    echo sprintf(
      '%s %s in set (%s)%s',
      $count,
      (($count > 1) || ($count < 1)) ? 'rows' : 'row',
      $this->handleRounding($this->timestamp_stop,$this->timestamp_start),
      PHP_EOL
    );
    
    return true;
  
  }//method
  
  /**
   *  prints out the array or object
   *
   *  @return string  the array contents in nicely formatted string form
   */
  protected function handleArray($array){
    
    // canary...
    if(empty($array)){ return ''; }//if
    
    $ret_val = is_object($array) 
      ? $this->handleObject($array) 
      : $this->aIter($array,0);
    
    return $ret_val;
    
  }//method
  protected function aIter($array,$deep = 0){
  
    $ret_str = sprintf('Array (%s)%s(%s',count($array),PHP_EOL,PHP_EOL);
  
    foreach($array as $key => $val){
      
      $ret_str .= sprintf("\t[%s] => ",$key);
      
      if(is_object($val)){
      
        $ret_str .= trim($this->handleIndent($this->indent,$this->handleObject($val)));
      
      }else if(is_array($val)){
      
        $ret_str .= trim($this->handleIndent($this->indent,$this->aIter($val,$deep + 1)));
        
      }else{
      
        $ret_str .= sprintf('%s%s',$this->handleDefaultVal($val),PHP_EOL);
        
      }//if/else if/else
      
    }//foreach

    $prefix = str_repeat($this->indent,($deep > 1) ? 1 : $deep);
  
    return trim($this->handleIndent($prefix,sprintf('%s)',$ret_str))).PHP_EOL;
  
  }//method
  
  /**
   *  output an object
   *  
   *  @param  object  $obj  the object to output, this is different than outArray() and outVar() because
   *                        it can be called from {@link aIter()}              
   *  @return string  the printValue of an object
   */
  protected function handleObject($obj,$out_object = false){
  
    $ret_str = '';
  
    if(method_exists($obj,'__toString')){
      $ret_str = get_class($obj).'::__toString() - '.$obj;
    }else{
      if($out_object){
        $ret_str = print_r($obj,1);
      }else{
        $ret_str = get_class($obj).' instance';
      }//if/else
    }//if/else
  
    return $ret_str;
  
  }//method
  
  /**
   *  indent all the lines of $val with $indent
   *  
   *  @param  string  $indent something like a tab
   *  @param  string  $val  the string to indent
   *  @return string  indented string
   */
  protected function handleIndent($indent,$val){
    return preg_replace('#^(.)#mu',$indent.'\1',$val);
  }//method
  
  protected function handleDefaultVal($val){
  
    $ret_str = '';
  
    if(is_null($val)){
    
      $ret_str .= 'NULL';
      
    }else if(is_bool($val)){
      
      $ret_str .= $val ? 'TRUE' : 'FALSE';
      
    }else{
    
      $ret_str .= $val;
    
    }//if/else if/else
  
    return $ret_str;
    
  }//method
  
  /**
   *  format the time
   *  
   *  @param  float $timestamp_stop
   *  @param  float $timestamp_start
   *  @param  integer $round   
   *  @return string
   */
  protected function handleRounding($timestamp_stop,$timestamp_start,$round = 2){
  
    $time = $timestamp_stop - $timestamp_start;
  
    // first get the response in milliseconds (http://us2.php.net/manual/en/function.microtime.php#50962)
    $offset = 1000;
    $ret_str = round(($time * $offset),$round);
    
    // if the profile took longer than 100 ms, then round to seconds...
    if($ret_str > 100.00){
      $ret_str = round(($ret_str / $offset),$round).' s';
    }else{
      $ret_str .= ' ms';
    }//if/else
    
    return $ret_str;
    
  }//method

}//class
