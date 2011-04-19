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
  
  public static function addPostfix($postfix)
  {
    self::$postfix_list[] = $postfix;
  }//method
  
  public static function addPath($path)
  {
    if(is_array($path))
    
  
    // canary...
    if(is_file($path)){ $path = dirname($path); }//if
  
    self::$path_list[] = $path;
  
    /* set_include_path(
      $path
      .PATH_SEPARATOR.
      get_include_path()
    ); */
  
  }//method

  public static function register(){
  
    // canary...
    if(self::$is_registered){ return true; }//if
    self::$is_registered = true;
    
    self::addPath(__FILE__);
    
    self::addPostfix('_class.php');
    self::addPostfix('.class.php');
    self::addPostfix('.php');
    self::addPostfix('.inc');
    self::addPostfix('.class.inc');
    
    return spl_autoload_register(array(__CLASS__,'load'));
  
  }//method
  
  /**
   *  load a class   
   *      
   *  @return boolean true if the class was found, false if not (so other autoloaders can have a chance)
   */
  public static function load($class_name){
  
    $path_list = self::$path_list;
    $path_list = array_merge($path_list,array_reverse(explode(PATH_SEPARATOR,get_include_path())));
    
    $class_postfix_list = self::$postfix_list;
    $normalized_class_name = str_replace('_',DIRECTORY_SEPARATOR,$class_name);
    
    foreach($path_list as $path)
    {
      $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
          $path,
          FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::SELF_FIRST
      );
      foreach($iterator as $key => $val)
      {
        ///$basename = $val->getBasename();
        $base_path = $val->getPath();
        
        // for some reason, $val->isDir() was returning false for some folders...
        if(is_dir($base_path))
        {
          $normalized_class_path = join(DIRECTORY_SEPARATOR,array($base_path,sprintf('%s.php',$normalized_class_name)));
        
          if(is_file($normalized_class_path))
          {
            include($normalized_class_path);
            return true;
          
          }
          else
          {
            foreach($class_postfix_list as $class_postfix)
            {
              $class_path = join(DIRECTORY_SEPARATOR,array($base_path,sprintf('%s%s',$class_name,$class_postfix)));
          
              if(is_file($class_path))
              {
                include($class_path);
                return true;
              
              }//if
              
            }//foreach
            
          }//if/else
        
        }//if
      
      }//foreach
      
    }//foreach

    return false;
    
  }//method

}//class   
