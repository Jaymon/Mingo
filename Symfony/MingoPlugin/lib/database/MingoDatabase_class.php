<?php

/**
 *  a Mingo DB driver for symfony 
 *  
 *  this class is instantiated in sfDatabaseManager::loadConfiguration() and can be
 *  retrieved by calling:  sfDatabaseManager::getDatabase('mingo')
 *  
 *  @version 0.4
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 1-6-10
 *  @package mingo
 *  @subpackage symfony
 ******************************************************************************/
class MingoDatabase extends sfDatabase {

  /**
   *  gets the mingo dbs ready to be connected, mingo will take care of the actual connecting
   *  on demand   
   *
   *  @throws <b>sfDatabaseException</b> If a connection could not be created
   * 
   *  @return array a map with the key being the name of the connection, and the value being
   *                the actual connection         
   */
  function connect(){

    // canary...
    if(!empty($this->connection)){ return $this->connection; }//if
    
    $timer = null;
    $debug = sfConfig::get('sf_debug');
    if($debug){ $timer = sfTimerManager::getTimer('Prepare "Mingo"'); }//if
  
    $this->connection = array();
    
    try{
      
      // iterate through all the servers that should be connection ready
      $server_list = $this->parameterHolder->get('servers',array());
      foreach($server_list as $name => $server_map){
      
        // canary, interface has to exist...
        if(empty($server_map['interface'])){ 
          throw new UnexpectedValueException(
            sprintf('Server namespace "%s" does not have an interface key!',$name)
          );
        }//if
        
        $this->connection[$name] = mingo_db::getInstance($name);
        $this->connection[$name]->setDebug($debug);
        
        $this->connection[$name]->setInterface($server_map['interface']);
        
        // set all the other optional connection params...
        if(isset($server_map['name'])){
          $this->connection[$name]->setName($server_map['name']);
        }//if
        if(isset($server_map['host'])){
          $this->connection[$name]->setHost($server_map['host']);
        }//if
        if(isset($server_map['username'])){
          $this->connection[$name]->setUsername($server_map['username']);
        }//if
        if(isset($server_map['password'])){
          $this->connection[$name]->setPassword($server_map['password']);
        }//if
        
        // we don't connect here, connection will happen when the server is actually
        // needed, this way we don't waste time connecting to a server that isn't
        // needed for this request
      
      }//foreach
      
    }catch(Exception $e){
    
      throw new sfDatabaseException(
        sprintf(
          '%s %s: "%s" originally thrown in %s:%s',
          get_class($e),
          $e->getCode(),
          $e->getMessage(),
          $e->getFile(),
          $e->getLine()
        ),
        $e->getCode()
      );
    
    }//try/catch
    
    if($debug){ $timer->addTime(); }//if
    
    return $this->connection;
    
  }//method
  
  /**
   * Initializes this sfDatabase object.
   *
   * @param array $parameters An associative array of initialization parameters
   *
   * @return bool true, if initialization completes successfully, otherwise false
   *
   * @throws <b>sfInitializationException</b> If an error occurs while initializing this sfDatabase object
   */
  public function initialize($parameters = array())
  {
    parent::initialize($parameters);
    
    $this->connect();
    return true;
    
  }//method
  
  /**
   * Executes the shutdown procedure.
   *
   * @return void
   *
   * @throws <b>sfDatabaseException</b> If an error occurs while shutting down this database
   */
  function shutdown(){}//method
  
}//class     
