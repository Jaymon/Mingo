<?php

/**
 *  hold connection information to connect to a MingoInterface instance  
 * 
 *  @version 0.1
 *  @author Jay Marcyes
 *  @since 12-31-11
 *  @package mingo 
 ******************************************************************************/
class MingoConfig implements IteratorAggregate {

  protected $name = '';
  
  protected $host = '';
  
  protected $port = 0;
  
  protected $username = '';
  
  protected $password = '';
  
  protected $options = array();
  
  protected $debug = false;

  /**
   *  connect to the db
   *  
   *  @param  string  $name  the db name to use, defaults to {@link getName()}
   *  @param  string  $host the host to use, defaults to {@link getHost()}. if you want a specific
   *                        port, attach it to host (eg, localhost:27017 or example.com:27017)            
   *  @param  string  $username the username to use, defaults to {@link getUsername()}
   *  @param  string  $password the password to use, defaults to {@link getPassword()} 
   *  @param  array $options  specific options you might want to use for connecting, defaults to {@link getOptions()}
   *  @return boolean
   */
  public function __construct($name = '',$host = '',$username = '',$password = '',array $options = array()){
  
    $this->setName($name);
    $this->setHost($host);
    $this->setUsername($username);
    $this->setPassword($password);
    $this->setOptions($options);
  
  }//method

  public function setName($val){
    $this->name = $val;
    return $this;
  }//method
  
  public function getName(){ return $this->name; }//method
  
  public function hasName(){ return !empty($this->name); }//method

  public function setHost($val){
  
    if(empty($val)){
    
      $this->host = $val;
    
    }else{
    
      list($host,$port) = $this->splitHost($val);
    
      $this->host = $host;
      
      if(!empty($port)){ $this->setPort($port); }//if
      
    }//if/else
  
    return $this;
    
  }//method
  
  public function getHost(){ return $this->host; }//method
  
  public function hasHost(){ return !empty($this->host); }//method
  
  /**
   *  gets the port and host from a combined host:port
   *  
   *  @since  12-31-11   
   *  @param  string  $host
   *  @param  integer $default_port the default port if none is specified   
   *  @return array array($host,$port)
   */
  protected function splitHost($host,$default_port = 0){
  
    // canary...
    if(empty($host)){ throw new InvalidArgumentException('cannot split an empty $host'); }//if
    
    $url_map = parse_url($host);
    $host = empty($url_map['host']) ? $host : $url_map['host'];
    $port = empty($url_map['port']) ? $default_port : (int)$url_map['port'];
  
    return array($host,$port);
  
  }//method
  
  public function setPort($val){
  
    $this->port = (int)$val;
    return $this;
  
  }//method
  
  public function getPort($port = 0){ return $this->hasPort() ? $this->port : $port; }//method
  
  public function hasPort(){ return !empty($this->port); }//method
  
  public function setUsername($val){
  
    $this->username = $val;
    return $this;
  
  }//method
  
  public function getUsername(){ return $this->username; }//method
  
  public function hasUsername(){ return !empty($this->username); }//method
  
  public function setPassword($val){
  
    $this->password = $val;
    return $this;
  
  }//method
  
  public function getPassword(){ return $this->password; }//method
  
  public function hasPassword(){ return !empty($this->password); }//method
  
  /**
   *  set a specific option
   *  
   *  @since  1-11-12      
   *  @param  string  $name the option name
   *  @param  mixed $val  the value
   *  @return self      
   */
  public function setOption($name,$val){
  
    $this->options[$name] = $val;
    return $this;
  
  }//method
  
  /**
   *  @since  3-6-11
   */
  public function setOptions(array $val){
  
    $this->options = $val;
    return $this;
  
  }//method
  
  public function getOptions(){ return $this->options; }//method
  
  public function hasOptions(){ return !empty($this->options); }//method
  
  public function setDebug($val){
  
    $this->debug = (bool)$val;
    return $this;
  
  }//method
  
  public function getDebug(){ return $this->debug; }//method
  
  public function hasDebug(){ return !empty($this->debug); }//method
  
  /**
   *  @link http://us2.php.net/iteratoraggregate
   *  @return \Traversable   
   */
  public function getIterator(){
  
    return new ArrayIterator(get_object_vars($this));
    
  }//method

}//class   
