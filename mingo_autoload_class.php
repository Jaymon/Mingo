<?php

/**
 *  used in the command line and the testing stuff to autoload mingo dependant classes  
 * 
 *  @version 0.1
 *  @author Jay Marcyes
 *  @since 10-5-10
 *  @package mingo 
 ******************************************************************************/

// personal debugging stuff, ignore..
$out_path_list = array(
  'out_class.php',
  'C:\Projects\Plancast\_active\lib\out_class.php',
  'E:\Projects\sandbox\out\git_repo\out_class.php'
);
foreach($out_path_list as $out_path){
  if(is_file($out_path)){ include_once($out_path); break; }//if
}//foreach

class mingo_autoload {

  private static $is_registered = false;

  public static function register(){
  
    // canary...
    if(self::$is_registered){ return true; }//if
  
    // add the directories to the path...
    $mingo_basepath = dirname(__FILE__);
    $cli_basepath = realpath(join(DIRECTORY_SEPARATOR,array($mingo_basepath,'cli')));
    
    set_include_path(
      get_include_path()
      .PATH_SEPARATOR.
      $mingo_basepath
      .PATH_SEPARATOR.
      $cli_basepath
    );
    
    self::$is_registered = true;
    
    return spl_autoload_register(array(__CLASS__,'load'));
    
  }//method
  
  /**
   *  load a class   
   *      
   *  @return boolean true if the class was found, false if not (so other autoloaders can have a chance)
   */
  public static function load($class_name){
  
    $path_list = explode(PATH_SEPARATOR,get_include_path());
  
    $class_postfix_list = array('','_class','.class');
    
    foreach($path_list as $path){
    
      foreach($class_postfix_list as $class_postfix){
      
        $class_path = join(DIRECTORY_SEPARATOR,array($path,sprintf('%s%s.php',$class_name,$class_postfix)));
      
        if(file_exists($class_path)){
        
          include_once($class_path);
          return true;
        
        }//if
        
      }//foreach
    
    }//foreach
    
    return false;
  
  }//method

}//class   
