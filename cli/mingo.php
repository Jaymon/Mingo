#!/usr/bin/env php
<?php

// http://php.net/manual/en/features.commandline.php

// declare a mingo autoloader we can use...
include(
  join(
    DIRECTORY_SEPARATOR,
    array(
      join(DIRECTORY_SEPARATOR,array(dirname(__FILE__),'..')),
      'MingoAutoload_class.php'
    )
  )
);
MingoAutoload::register();
MingoAutoload::addIncludePath(__FILE__);

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
  $cli_handler = new Cli($argv,$required_argv_map);
  $argv_map = $cli_handler->get();
  
  // connect to the db using the command line arguments...
  $interface = $argv_map['interface'];
  $db = new $interface();
  $db->connect(
    $argv_map['name'],
    $argv_map['host'],
    $argv_map['username'],
    $argv_map['password']
  );
  $db->setDebug($argv_map['debug']);
  
  // this will handle all the input from the user...
  $cli_in = new CliIn($db);
  
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
        
      }catch(CliStopException $e){

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
