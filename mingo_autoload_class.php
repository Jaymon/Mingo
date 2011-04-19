<?php

/**
 *  used in the command line and the testing stuff to autoload mingo dependant classes  
 * 
 *  @version 0.3
 *  @author Jay Marcyes
 *  @since 10-5-10
 *  @package mingo 
 ******************************************************************************/
class mingo_autoload {

  protected static $is_registered = false;

  protected static $postfix_list = array();
  
  protected static $path_list = array();
  
  public static function addPostfix($postfix_list)
  {
    $postfix_list = (array)$postfix_list;
    self::$postfix_list = array_merge(self::$postfix_list,$postfix_list);
    
  }//method
  
  public static function addPath($path_list)
  {
    $path_list = (array)$path_list;
    
    foreach($path_list as $path)
    {
      if(is_file($path)){ $path = dirname($path); }//if
  
      self::$path_list[] = $path;
  
    }//foreach
  
  }//method

  public static function register(){
  
    // canary...
    if(self::$is_registered){ return true; }//if
    self::$is_registered = true;
    
    self::addPath(__FILE__);
    ///self::addPath(array_reverse(explode(PATH_SEPARATOR,get_include_path())));
    
    self::addPostfix(
      array('_class.php','.class.php','.php','.inc','.class.inc')
    );
    
    return spl_autoload_register(array(__CLASS__,'load'));
  
  }//method
  
  protected static function checkPath($path,$class_name){
  
    // canary...
    if(!is_dir($path)){ return false; }//if
    // ignore .name folders since they are almost always special folders by convention...
    $base = basename($path);
    if($base[0] === '.'){ return false; }//if
    
    $changed = chdir($path);
    out::e($changed);
    
    $ret_bool = false;
    $class_postfix_list = self::$postfix_list;
    $file_glob = join(sprintf(',%s',$class_name),self::$postfix_list);
    $file_glob = sprintf('{%s%s}',$class_name,$file_glob);

    out::e($path,$file_glob);

    $file_list = glob($file_glob);
    out::e($file_list);
    if(!empty($file_list)){
      foreach($file_list as $file){
        include($file);
        $ret_bool = true;
        break;
      }//foreach
    }//if
    
    if(!$ret_bool){
    
      $normalized_class_name = str_replace('_',DIRECTORY_SEPARATOR,$class_name);
      $normalized_class_path = join(DIRECTORY_SEPARATOR,array($path,sprintf('%s.php',$normalized_class_name)));
    
      if(is_file($normalized_class_path)){
      
        include($normalized_class_path);
        $ret_bool = true;
      
      }else{
        
        foreach(glob(sprintf('%s%s*',$path,DIRECTORY_SEPARATOR),GLOB_ONLYDIR) as $dir){
          $ret_bool = self::checkPath($dir,$class_name);
          if($ret_bool){ break; }//if
        }//foreach
        
      }//if/else
      
    }//if
    
    return $ret_bool;
  
  }//method
  
  /**
   *  load a class   
   *      
   *  @return boolean true if the class was found, false if not (so other autoloaders can have a chance)
   */
  public static function load($class_name){
  
    $ret_bool = false;
    $path_list = self::$path_list;
    $cwd = getcwd();
    
    foreach($path_list as $path)
    {
      $ret_bool = self::checkPath($path,$class_name);
      if($ret_bool){ break; }//if
      
    }//foreach
    
    chdir($cwd);

    return $ret_bool;
    
  }//method

}//class   
