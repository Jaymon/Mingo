<?php
/**
 *  handle sql relational db abstraction for mingo    
 *
 *  basically, this class will handle the common parts of sql relational dbs that 
 *  PDO can talk to
 *  
 *  original name was MingoSQLDBMSInterface but that was too long for me
 *  
 *  @link http://en.wikipedia.org/wiki/Database_management_system
 *  
 *  @abstract 
 *  @version 0.8
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 12-31-09
 *  @package mingo 
 ******************************************************************************/
abstract class MingoPDOInterface extends MingoInterface {

  /**
   *  get the dsn connection string that PDO will use to connect to the backend
   *   
   *  @since  10-18-10
   *  @param  \MingoConfig  $config
   *  @return string  the dsn   
   */
  abstract protected function getDsn(MingoConfig $config);
  
  /**
   *  do the actual connecting of the interface
   *
   *  @see  connect()   
   *  @return boolean
   */
  protected function _connect(MingoConfig $config){
  
    $connected = false;
    $pdo_options = array(
      PDO::ERRMODE_EXCEPTION => true,
      // references I can find of the exit code 1 error is here:
      // http://bugs.php.net/bug.php?id=43199
      // it's this bug: http://bugs.php.net/42643 and it only affects CLI on <=5.2.4...
      ///PDO::ATTR_PERSISTENT => (strncasecmp(PHP_SAPI, 'cli', 3) === 0) ? false : true, 
      PDO::ATTR_EMULATE_PREPARES => true,
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    );
    
    $username = $config->getUsername();
    $password = $config->getPassword();
    $options = $config->getOptions();
    
    // passed in options take precedence...
    if(!empty($options['pdo_options'])){
      $pdo_options = array_merge($pdo_options,$options['pdo_options']);
    }//if

    $dsn = $this->getDsn($config);
    $con_class = empty($options['pdo_class']) ? 'PDO' : $options['pdo_class'];
    
    try{
    
      $this->con_db = new $con_class($dsn,$username,$password,$pdo_options);
      
      $connected = true;
      $this->onConnect();
      
    }catch(Exception $e){
    
      // print out lots of debugging information
      if($this->getConfig()->hasDebug()){
      
        $e_msg = array();
        
        $con_map_msg = array();
        foreach($pdo_options as $key => $val){
          $con_map_msg[] = sprintf('[%s] => %s',$key,$val);
        }//foreach
        
        $e_msg[] = sprintf(
          'new %s("%s","%s","%s",array(%s)) failed.',
          $con_class,
          $dsn,
          $username,
          $password,
          join(',',$con_map_msg)
        );
        
        $e_msg[] = '';
        $e_msg[] = sprintf(
          'available drivers if original exception was "could not find driver" exception: [%s]',
          join(',',PDO::getAvailableDrivers())
        );
        
        throw new PDOException(join(PHP_EOL,$e_msg),$e->getCode());
        
      }else{
      
        throw $e;
        
      }//if/else
    
    }//try/catch
    
    return $connected;
  
  }//method
  
  /**
   *  things to do once the connection is established
   *   
   *  @since  10-18-10
   */
  protected function onConnect(){}//method

  /**
   *  @see  getQuery()
   *  @param  mixed $query  a query the interface can understand
   *  @param  array $options  any options for this query
   *  @return mixed      
   */
  protected function _getQuery($query,array $options = array()){
  
    $ret_mixed = false;
  
    // prepare the statement and run the query...
    // http://us2.php.net/manual/en/function.PDO-prepare.php
    $stmt_handler = $this->getStatement($query,$options);
    
    $col_count = $stmt_handler->columnCount();
    
    if($col_count === 1){
    
      $ret_mixed = $stmt_handler->fetchAll(PDO::FETCH_COLUMN,0);
    
    }else if($col_count === 0){
    
      $ret_mixed = $stmt_handler->fetch();
      // @todo  maybe just set to true?
    
    }else{
      
      $ret_mixed = $stmt_handler->fetchAll(PDO::FETCH_ASSOC);
      
    }//if/else
    
    $stmt_handler->closeCursor();

    return $ret_mixed;
  
  }//method
  
  /**
   *  prepares and executes the query and returns the PDOStatement instance
   *  
   *  @param  string  $query  the query to prepare and run
   *  @param  array $val_list the values list for the query, if the query has ?'s then 
   *                          the values should be in this array      
   *  @return \PDOStatement
   */
  public function getStatement($query,array $val_list = array()){
  
    $query = trim($query);
    $this->addQuery($query);
  
    // prepare the statement and run the query...
    // http://us2.php.net/manual/en/function.PDO-prepare.php
    $stmt_handler = $this->con_db->prepare($query);
    
    try{
    
      // execute the query...
      // passing in the values this way instead of doing bindValue() seems to
      // work fine even though all the values get treated like a string
      $is_success = empty($val_list) ? $stmt_handler->execute() : $stmt_handler->execute($val_list);
      
    }catch(Exception $e){
    
      $stmt_handler->closeCursor();
      throw $e;
    
    }//try/catch
  
    return $stmt_handler;
  
  }//method
  
  /**
   *  this should be used to take the generic $where_criteria and turn it into something
   *  the interface can use (eg, for a SQL interface, the $where_criteria would be turned
   *  into a valid SQL string).
   *  
   *  @param  MingoTable  $table    
   *  @param  MingoCriteria $where_criteria   
   *  @return mixed return whatever you want, however you want to return it, whatever is easiest for you
   */
  protected function translate(MingoTable $table,MingoCriteria $where_criteria = null){
  
    return new MingoSQLTranslate($table,$where_criteria);
  
  }//method

}//class     

