<?php

/**
 *  used in the command line and the testing stuff to autoload mingo dependant classes  
 * 
 *  @version 0.4
 *  @author Jay Marcyes
 *  @since 10-5-10
 *  @package mingo 
 ******************************************************************************/
class MingoAutoload {

  protected static $is_registered = false;

  protected static $postfix_list = array();
  
  protected static $path_list = array();
  
  public static function addPostfix($postfix_list)
  {
    $postfix_list = (array)$postfix_list;
    self::$postfix_list = array_merge(self::$postfix_list,$postfix_list);
    
  }//method
  
  /**
   *  not only adds a path to the loader, but also to the include path
   *
   *  @since  4-21-11   
   */
  public static function addIncludePath($path_list)
  {
    self::addPath($path_list);
    
    $path_list = (array)$path_list;
    foreach($path_list as $i => $path){
      if(is_file($path)){ $path_list[$i] = dirname($path); }//if
    }//if
    
    set_include_path(
      join(PATH_SEPARATOR,$path_list)
      .PATH_SEPARATOR.
      get_include_path()
    );
  
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
  
  protected static function checkPearPath($path,$class_name)
  {
    $ret_bool = false;
    $normalized_class_name = sprintf('%s.php',str_replace('_',DIRECTORY_SEPARATOR,$class_name));
    $normalized_class_path = join(DIRECTORY_SEPARATOR,array($path,$normalized_class_name));
    
    if(is_file($normalized_class_path))
    {
      include($normalized_class_path);
      $ret_bool = true;
    
    }//if

    return $ret_bool;
  
  }//method
  
  protected static function checkPath($path,$class_name,$recursive = true){
  
    // canary...
    if(!is_dir($path)){ return false; }//if
    // ignore .name folders since they are almost always, by convention, special folders...
    $base = basename($path);
    if($base[0] === '.'){ return false; }//if
    
    $ret_bool = false;
    
    foreach(self::$postfix_list as $class_postfix){

      $file_glob = sprintf('%s%s%s%s',$path,DIRECTORY_SEPARATOR,$class_name,$class_postfix);

      $file_list = glob($file_glob);
      if(!empty($file_list)){
        foreach($file_list as $file){
          include($file);
          $ret_bool = true;
          break 2;
        }//foreach
      }//if
      
    }//foreach
    
    if(!$ret_bool){
    
      if(!($ret_bool = self::checkPearPath($path,$class_name))){
      
        if($recursive){
        
          foreach(glob(sprintf('%s%s*',$path,DIRECTORY_SEPARATOR),GLOB_ONLYDIR) as $dir){
            $ret_bool = self::checkPath($dir,$class_name);
            if($ret_bool){ break; }//if
          }//foreach
          
        }//if
        
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
    
    foreach($path_list as $path)
    {
      $ret_bool = self::checkPath($path,$class_name,true);
      if($ret_bool){ break; }//if
      
    }//foreach
    
    // check include paths for a normalized PEAR class name...
    if(!$ret_bool)
    {
      $include_path_list = explode(PATH_SEPARATOR,get_include_path());
      foreach($include_path_list as $include_path)
      {
        if(self::checkPearPath($include_path,$class_name))
        {
          $ret_bool = true;
          break;
        }//if
        
      }//foreach
      
      // last but not least, check just the included paths for any of the prefixes...
      if(!$ret_bool){
      
        foreach($include_path_list as $include_path)
        {
          $ret_bool = self::checkPath($include_path,$class_name,false);
          if($ret_bool){ break; }//if
        }//foreach
      
      }//if
      
    }//if
    
    return $ret_bool;
    
  }//method

}//class   
