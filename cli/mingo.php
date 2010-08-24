#!/usr/bin/env php
<?php

// http://php.net/manual/en/features.commandline.php

include('E:\mis documentos\Projects\_SVN_lib\out_class.php');

// set up directories and autoloader...
$cli_basepath = dirname(__FILE__);
$mingo_basepath = realpath(sprintf('%s%s..',$cli_basepath,DIRECTORY_SEPARATOR));
set_include_path(
  get_include_path()
  .PATH_SEPARATOR.
  $cli_basepath
  .PATH_SEPARATOR.
  $mingo_basepath
);
function __autoload($class_name){
  
  $path_list = explode(PATH_SEPARATOR,get_include_path());
  
  $class_postfix_list = array('','_class','.class');
  
  foreach($path_list as $path){
  
    foreach($class_postfix_list as $class_postfix){
    
      $class_path = join(DIRECTORY_SEPARATOR,array($path,sprintf('%s%s.php',$class_name,$class_postfix)));
    
      if(file_exists($class_path)){
      
        include($class_path);
        return true;
      
      }//if
      
    }//foreach
  
  }//foreach
  
  ///include(sprintf('%s_class.php',$class_name));
  return false;
  
}//method

include(join(DIRECTORY_SEPARATOR,array('SQL','Parser.php')));

$required_argv_map = array(
  'interface' => null, // the interface to use to access mingo data
  'db' => null, // the database name
  'host' => '', // the host where the database is hosted
  'username' => '', // the username
  'password' => '', // the password
  'debug' => false // whether debugging should be on or off
);

try{

  // parse the command line arguments and make sure they work...
  $cli_handler = new cli($argv,$required_argv_map);
  $argv_map = $cli_handler->get();
  
  // connect to the db using the command line arguments...
  $db = mingo_db::getInstance();
  $db->connect(
    $argv_map['interface'],
    $argv_map['db'],
    $argv_map['host'],
    $argv_map['username'],
    $argv_map['password']
  );
  $db->setDebug($argv_map['debug']);
  
  // this will handle all the input from the user...
  $cli_in = new cli_in($db);
  
  // get input from the user...
  while(true){
    
    echo $cli_in->hasInput() ? '> ' : 'mingo> ';
    
    // get a line and append it to the total inputted command...
    $cli_in->getLine();
    
    // check for an ending delimiter to know the command is done being input...
    if($cli_in->isDone()){
    
      try{
      
        // handle the results...
        $cli_out = $cli_in->handle();
        
        // output the found results...
        $cli_out->handle();
        
      }catch(cli_stop_exception $e){

        echo 'Bye',PHP_EOL;
        exit();
      
      }catch(Exception $e){
      
        echo sprintf('ERROR: %s',$e->getMessage());
        echo PHP_EOL;
      
      }//try/catch
      
      // reset the in handler since the previous input has been handled...
      $cli_in->reset();
      
    }//if
  
  }//while

}catch(Exception $e){

  echo $e->getMessage();

}//try/catch

exit();