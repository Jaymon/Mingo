<?php

require_once('mingo_db_interface_Test.php');

class mingo_db_mongo_Test extends test_mingo_db_interface {

  /**
   *  @return string  the host string (something like server:port
   */
  public function getDbHost(){ return 'localhost:27017'; }//method

  public function getDbInterface(){
  
    return 'mingo_db_mongo';
  
  }//method
  
  public function xtestLog()
  {
    $db_name = $this->getDbName();
    $host = $this->getDbHost();
    $username = $this->getDbUsername();
    $password = $this->getDbPassword();
    $interface = $this->getDbInterface();
    
    $db = new $interface();
    $db->connect($db_name,$host,$username,$password);
    
    $mongo = $db->getDb();
    
    ///$col = $mongo->selectCollection(md5(microtime(true)));
    ///$obj = array( "title" => "Calvin and Hobbes", "author" => "Bill Watterson" );
    ///$col->insert($obj);
    ///out::e($mongo->listCollections('test_orm'));
    ///out::e($mongo->command(array('getCollectionName' => 'test_orm')));
    ///out::e($mongo->execute(sprintf('"" in db.getCollectionNames()','test_orm')));
    out::e($mongo->execute(sprintf('db.getCollectionNames().indexOf("%s")','test_orm')));
    out::e($mongo->execute(sprintf('db.getCollectionNames().indexOf("%s")','blah')));
    
    ///out::e($mongo->execute('test_mingo_db_interface.system.namespaces{}'));
    
    ///out::e($mongo->command(array('test_mingo_db_interface.system.namespaces' => 1)));
    
    return;
    
    
    $db = mingo_db::getInstance('MingoMongoOrm');
    $db->connect($interface,$db_name,$host,$username,$password);
    
    $log = new Log();
    $log->setDb($db);
    
    $log->load(new mingo_criteria());
    return;
    
    
    $db = new $interface();
    $db->connect($db_name,$host,$username,$password);
    
    ///$db->
    
    
    
    out::h();
  
  
  }//method

}//class
