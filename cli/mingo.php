#!/usr/bin/env php
<?php

// http://php.net/manual/en/features.commandline.php

// declare a mingo autoloader we can use...
include(
  join(
    DIRECTORY_SEPARATOR,
    array(
      join(DIRECTORY_SEPARATOR,array(dirname(__FILE__),'..')),
      'mingo_autoload_class.php'
    )
  )
);
mingo_autoload::register();

// personal debugging stuff, ignore..
$out_path_list = array(
  'out_class.php',
  'C:\Projects\Plancast\_active\lib\out_class.php',
  'E:\Projects\sandbox\out\git_repo\out_class.php'
);
foreach($out_path_list as $out_path){
  if(is_file($out_path)){ include_once($out_path); break; }//if
}//foreach

bla::h();
out::x();

$required_argv_map = array(
  'interface' => null, // the interface to use to access mingo data
  'name' => null, // the database name
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
    $argv_map['name'],
    $argv_map['host'],
    $argv_map['username'],
    $argv_map['password']
  );
  $db->setDebug($argv_map['debug']);
  
  // this will handle all the input from the user...
  $cli_in = new cli_in($db);
  
  // get input from the user...
  while(true){
    
    ///echo $cli_in->hasInput() ? '    -> ' : 'mingo> ';
    
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
      
        ///out::e($e->getTraceAsString());
        
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
