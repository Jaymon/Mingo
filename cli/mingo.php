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
  foreach($path_list as $path){
  
    $class_path = join(DIRECTORY_SEPARATOR,array($path,sprintf('%s_class.php',$class_name)));
  
    if(file_exists($class_path)){
    
      include($class_path);
      return true;
    
    }//if
  
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
  $cli_in = new cli_in();
  
  // get input from the user...
  while(true){
    
    echo $cli_in->hasInput() ? '> ' : 'mingo> ';
    
    // get a line and append it to the total inputted command...
    $cli_in->getLine();
    
    // check for an ending delimiter to know the command is done being input...
    if($cli_in->isDone()){
    
      $cli_in->handle();
      $cli_in->reset();
    
      /*
      switch($cli_in->getCommand()){
      
        case cli_in::CMD_EXIT:
          break 2; // get out of the while loop also
          
        case cli_in::CMD_SELECT:
        
          out::e($cli_in->get());
        
          break;
          
        default:
        
          echo sprintf('Unknown/unsupported command. Please check your syntax%s',PHP_EOL);
          break;
      
      }//switch
      */
      
    }//if
  
  }//while

}catch(cli_stop_exception $e){

  echo 'Goodbye',PHP_EOL;

}catch(Exception $e){

  echo $e->getMessage();

}//try/catch

exit();


out::x();


$username = $password = '';
// mongo...
///$type = mingo_db::TYPE_MONGO;
///$db_name = 'model';
///$host = 'localhost:27017';

//*
// sqlite...
$db_interface = 'mingo_db_sqlite';
$db_name = '.'.DIRECTORY_SEPARATOR.'model.sqlite';
$host = '';
// */

/*
// mysql...
$type = mingo_db::TYPE_MYSQL;
$db_name = 'happy';
$host = 'localhost';
$username = 'plancast';
$password = '32jmaFbf90j@wegh9sDGSd';
// */

// activate singleton...
$db = mingo_db::getInstance();
$db->setDebug(true);
$db->connect($db_interface,$db_name,$host,$username,$password);

///exit();

$blocked_user_map = new BlockedUser();
$blocked_user_map->setUserId(100);
$blocked_user_map->setBlockedUserId(101);
$blocked_user_map->set();

$blocked_user_map = new BlockedUser();
$blocked_user_map->setLimit(10);
out::e($blocked_user_map->load());
foreach($blocked_user_map as $bum){
  out::e($bum);
}//foreach

out::i($blocked_user_map->getDb()->getQueries());

exit;


$blocked_user_map->setUserId(0);
$blocked_user_map->setBlockedUserId(0);
///$blocked_user_map->set();

out::e($blocked_user_map->getField(BlockedUser::USER_ID,true));

exit();

out::e($blocked_user_map->kill());
$blocked_user_map = new BlockedUser();
$blocked_user_map->setUserId(102);
$blocked_user_map->setBlockedUserId(100);

// let's make sure the user wasn't already blocked before...
$total_loaded = $blocked_user_map->load();
out::e($total_loaded);
exit();

if(empty($total_loaded)){
  
  out::e($blocked_user_map->getList());
  
  // user wasn't blocked, so go ahead and block them...
  if($blocked_user_map->set()){
  
    out::e('doing the other set stuff');
    
  }//if
  
}//if



exit();

$user = new user();
$user->setUsername('happy');
$user->setArray(true);
out::e($user->getUsername());
out::e($user->hasUsername());
out::e($user->set());

$c = new mingo_criteria();
$c->isUsername('happy');
$load_count = $user->load($c,true);
if($load_count){
  $user->kill();
}//if
///$user->load();

exit();

$c = new mingo_criteria();
$c->isUsername('jaymon');
$load_count = $user->load($c);

exit();


out::e($user->hasCount(),$user->getCount());

///$user->setUsername('bobby');
///out::e($user->getList());
$load_count = $user->load();
if(empty($load_count)){

  $user->set();

}//if

out::e($load_count,$user->hasCount(),$user->getCount());
out::e($user);

exit();


$c = new mingo_criteria();
$c->inField('Username','jaymon');
$count = $user->load($c);
out::e($count);

exit();

$c = new mingo_criteria();
$c->descUpdated();
$user->load($c);

$c = new mingo_criteria();
$c->is_id('adsfasdfdsafdsfsdafdsf');
$user->load($c);

foreach($user as $u){

  out::e($u->getUsername());

}//foreach


exit();

// now let's select the user...
$criteria = new mingo_criteria();
$criteria->inUsername('jaymon');
$user->load($criteria);
out::e($user->get());




















exit();

$map_list = new test_map();

$mc = new mingo_criteria();
$mc->isAdmin(true);
$map_list->load($mc);
out::e($map_list);
exit();



$map_list->setCreated(600);
$map_list->set();
$mc = new mingo_criteria();
$mc->isCreated(600);
$map_list->load($mc);
out::e($map_list);


exit();


///$map_list->install(true);

$map_list->setLimit(2);
$mc = new mingo_criteria();
$mc->gteCreated(1260817592);
///$mc->in_id("4b268ce7d72b0a999fa04260","4b268cb882ea0471c74cc0e7");

///$mc->descCreated();
$map_list->load($mc,true);
out::e($map_list->getTotal(),$map_list);

exit();

for($i = 0; $i < 1 ;$i++){
  
  $map = new test_map();
  $map->setTitle('this is the title '.microtime(true));
  $map->setBody(getLI(rand(0,5)));
  $map->setPrivate(rand(0,1) ? true : false);
  $map_list->append($map);

}//for

$map_list->set();
$map_list->load();


out::e($db->getTables());

exit();



///out::e($db->getTables());
out::e($db->count('test_map'));

$map_list = new test_map();







$c = new mingo_criteria();
$c->inId(1,2,3,4,5);
$c->incId(1);
$c->sortCreated(-1);
$c->between('happy',1,2);
$c->betweenSad(3,4);
out::e($c->getSql());


out::e('done doing things');

exit('done');



/*
for($i = 0; $i < 5 ;$i++){
  
  $map = new test_map();
  $map->setTitle('this is the title '.microtime(true));
  $map->setBody(getLI(rand(0,5)));
  $map->setPrivate(rand(0,1) ? true : false);
  $map->set();
  $map_list->append($map);

}//for
*/

///out::e(count($map_list),$map_list->get());

///$map2->setLimit(2);
///$map2->setOffset(3);
$map_load = new test_map();
$loaded = $map_load->load();
out::e($loaded);

exit();

// 11-18-09 - schema stuff...
$test = new test_map();
///$test->install(true);

// 11-17-09 - criteria stuff...

$map = new test_map();
$map->setTitle('another title');
$map->setBody('here is the body');
$map->set();
out::e($map);
$map->setBody('updating the body to this');
$map->set();


$c = new mingo_criteria();
$c->inId(1,2,3,4,5);
$c->incId(1);
$c->sortCreated(-1);
$c->between('happy',1,2);
$c->betweenSad(3,4);
out::e($c->get());



exit();

// 11-14-09 - build out the ORM...

$map = new test_map();
$db->setInc($map->getTable());

/*
$map->setTitle('this is the title '.microtime(true));
$map->setBody(getLI(rand(0,5)));
$map->setPrivate(rand(0,1) ? true : false);
$map->set();
*/

$map1 = new test_map();
$map1->setLimit(2);
///$loaded = $map1->load(array('id' => array('$gt' => 0)));
$loaded = $map1->load(array('id' => 3));
out::e($loaded);
out::e($map1->getTitle());

$map2 = new test_map();
$map2->setLimit(2);
$map2->setOffset(3);
$loaded = $map2->load(array('id' => array('$gt' => 0)));
out::e($loaded);

$map1->append($map2);
out::e($map1->getList());

out::e($map1['title']);

///$map1->kill();


///$map_get->set_id('4affd9e8da7f000000003645');
///$map_get->load();






















exit();

// all this code was building the abstraction layer...

$table = 'test19';
$db->setInc($table);
///exit();

///out::e($db->get('mingo_db_sql_id'));

///$bool = $db->update($table,array('test' => 1),array('id' => 2803,'blah' => 'blech'));
///exit();
///out::e($bool);

///$result = $db->get($table,array('id' => 2803,'blah' => 'blech'));
///out::e($result);
///exit();

out::p();
//*
for($i = 0; $i < 10000; $i++){
  
  try{
    
    $map = array();
    $map['foo'] = microtime(true);
    $map['is_visible'] = rand(0,1) ? 1 : 0;
    $db->insert($table,$map);
    
  }catch(Exception $e){
    
    out::e($e->getMessage());
  
  }//try/catch

}//for
// */

out::p();

//*
$seen_id = array();
$result = $db->get($table);
$found_dupe = 0;
foreach($result as $map){

  if(isset($map['id'])){
  
    if(isset($seen_id[$map['id']])){
      ///out::e($map['id']);
      $found_dupe++;
    }else{
      $seen_id[$map['id']] = true;
    }//fi/else
  
  }//if

}//foreach
out::e($found_dupe);
// */

///out::e($result);






