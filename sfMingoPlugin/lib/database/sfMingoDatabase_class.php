<?php

/**
 *  a Mingo DB driver for symfony 
 *  
 *  this class is instantiated in sfDatabaseManager::loadConfiguration() and can be
 *  retrieved by calling:  sfDatabaseManager::getDatabase('mingo')
 *  
 *  @version 0.1
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 1-6-10
 *  @package mingo
 *  @subpackage symfony
 ******************************************************************************/
class sfMingoDatabase extends sfDatabase {

  /**
   * Connects to the database.
   *
   * @throws <b>sfDatabaseException</b> If a connection could not be created
   */
  function connect(){

    // canary...
    if($this->connection !== null){ return $this->connection; }//if
    
    $timer = null;
    $debug = sfConfig::get('sf_debug');
    if($debug){ $timer = sfTimerManager::getTimer('Connect "Mingo"'); }//if
  
    // get an instance...
    $this->connection = mingo_db::getInstance();
    
    // set debugging based on the project's overall debugging...
    $configuration = sfProjectConfiguration::getActive();
    if($configuration instanceof sfProjectConfiguration){
    
      $this->connection->setDebug($configuration->isDebug()); // activate mingo agile mode
    
    }//if
    
    try{
    
      // actually connect to the db...
      $this->connection->connect(
        $this->parameterHolder->get('interface',''),
        $this->parameterHolder->get('dbname',''),
        $this->parameterHolder->get('host',''),
        $this->parameterHolder->get('username',''),
        $this->parameterHolder->get('password','')
      );
      
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
    if(sfContext::hasInstance())
    {
      // use the debug toolbar...
      sfContext::getInstance()->getEventDispatcher()->connect('debug.web.load_panels', array(
        'sfMingoDebugToolbar',
        'listenToAddPanelEvent'
      ));
      
    }//if
    
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
  function shutdown()
  {
    /*
    // http://stackoverflow.com/questions/2400665/symfony-log-custom-propel-query
    // doesn't seem to work though, that might be because shutdown isn't called fast enough
    $db = mingo_db::getInstance();
    $queries = $db->getQueries();
    foreach($queries as $query)
    {
      sfContext::getInstance()->getLogger()->info(sprintf("{sfCreole} executeQuery(): [x.xx ms] %s", $query));
    }//foreach
    */
  
  }//method
  
}//class     
