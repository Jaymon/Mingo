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
   *  these are directly correlated with MingoCriteria's $where_criteria internal
   *  map that is returned from calling MingoCriteria::getWhere(). These are used in
   *  the {@link normalizeCriteria()} method to change a MingoCriteria object into usable SQL      
   *
   *  @var  array   
   */
  protected $method_map = array(
    'in' => array('arg' => 'normalizeListSQL', 'symbol' => 'IN'),
    'nin' => array('arg' => 'normalizeListSQL', 'symbol' => 'NOT IN'),
    'is' => array('arg' => 'normalizeValSQL', 'symbol' => '='),
    'not' => array('arg' => 'normalizeValSQL', 'symbol' => '!='),
    'gt' => array('arg' => 'normalizeValSQL', 'symbol' => '>'),
    'gte' => array('arg' => 'normalizeValSQL', 'symbol' => '>='),
    'lt' => array('arg' => 'normalizeValSQL', 'symbol' => '<'),
    'lte' => array('arg' => 'normalizeValSQL', 'symbol' => '<='),
    'sort' => array('arg' => 'normalizeSortSQL')
  );

  /**
   *  get the dsn connection string that PDO will use to connect to the backend
   *   
   *  @since  10-18-10
   *  @param  \MingoConfig  $config
   *  @return string  the dsn   
   */
  abstract protected function getDsn(MingoConfig $config);
  
  /**
   *  true if the $e is for a missing table exception
   *
   *  @since  10-18-10
   *  @see  handleException()         
   *  @param  Exception $e  the thrown exception
   *  @return boolean
   */
  abstract protected function canHandleException(Exception $e);
  
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
      
    }catch(Exception $e){
    
      // print out lots of debugging information
      if($this->hasDebug()){
      
        $e_msg = array();
        $e_msg[] = $e->getMessage();
        
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
    
    if($connected){ $this->onConnect(); }//if
    
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
  
    ///\out::b($query,3);
  
    $ret_mixed = false;
  
    // prepare the statement and run the query...
    // http://us2.php.net/manual/en/function.PDO-prepare.php
    $stmt_handler = $this->getStatement($query,$options);
    
    try{
    
      $col_count = $stmt_handler->columnCount();
      
      if($col_count === 1){
      
        $ret_mixed = $stmt_handler->fetchAll(PDO::FETCH_COLUMN,0);
      
      }else if($col_count === 0){

        ///$arr = $stmt_handler->errorInfo();
        ///\out::e($arr);
        ///\out::e($stmt_handler->errorCode());

        $ret_mixed = true;
      
      }else{
        
        $ret_mixed = $stmt_handler->fetchAll(PDO::FETCH_ASSOC);
        
      }//if/else
      
      $stmt_handler->closeCursor();
    
    }catch(Exception $e){
    
      $stmt_handler->closeCursor();
      throw $e;
    
    }//try/catch

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
  protected function normalizeCriteria(MingoTable $table,MingoCriteria $where_criteria = null){
  
    $ret_map = array();
    $ret_map['select_str'] = '*';
    $ret_map['table_str'] = $this->normalizeTableSQL($table);
    $ret_map['where_criteria'] = $where_criteria;
    $ret_map['where_str'] = '';
    $ret_map['where_params'] = array();
    $ret_map['sort_str'] = '';
    $ret_map['limit_str'] = '';
    $ret_map['limit'] = array(0,0);
  
    // canary
    if(empty($where_criteria)){ return $ret_map; }//if
  
    $ret_where = $ret_sort = '';
  
    $criteria_where = $where_criteria->getWhere();
    $criteria_sort = $where_criteria->getSort();
    
    $command_symbol = $where_criteria->getCommandSymbol();
  
    foreach($criteria_where as $name => $map){
    
      $where_sql = '';
      $where_val = array();
      $name_sql = $this->normalizeNameSQL($name);

      if(is_array($map)){
      
        $total_map = count($map);
      
        // go through each map val and append it to the sql string...
        foreach($map as $command => $val){
  
          if($where_criteria->isCommand($command)){
          
            $command_bare = $where_criteria->getCommand($command);
            $command_sql = '';
            $command_val = array();
          
            // build the sql...
            if(isset($this->method_map[$command_bare])){
            
              $symbol = empty($this->method_map[$command_bare]['symbol'])
                ? ''
                : $this->method_map[$command_bare]['symbol'];
            
              if(!empty($this->method_map[$command_bare]['arg'])){
              
                $callback = $this->method_map[$command_bare]['arg'];
                list($command_sql,$command_val) = $this->{$callback}(
                  $symbol,
                  $name_sql,
                  $map[$command]
                );
                
              }//if
            
              list($where_sql,$where_val) = $this->appendSql(
                'AND',
                $command_sql,
                $command_val,
                $where_sql,
                $where_val
              );
            
            }//if
          
          }else{
          
            throw new UnexpectedValueException(
              sprintf(
                'there is an error in the internal structure of your %s instance',
                get_class($where_criteria)
              )
            );
          
          }//if/else
          
        }//foreach
        
        // we want to parenthesize the sql since there was more than one value for the field
        if($total_map > 1){ $where_sql = sprintf(' (%s)',trim($where_sql)); }//if
      
      }else{
      
        // we have a NAME=VAL (an is* method call)...
        list($where_sql,$where_val) = $this->normalizeValSql('=',$name_sql,$map);
      
      }//if/else
      
      list($ret_map['where_str'],$ret_map['where_params']) = $this->appendSql(
        'AND',
        $where_sql,
        $where_val,
        $ret_map['where_str'],
        $ret_map['where_params']
      );
    
    }//foreach
  
    if(!empty($ret_map['where_params'])){
    
      $ret_map['where_str'] = sprintf('WHERE%s',$ret_map['where_str']);
      
    }//if
    
    // build the sort sql...
    foreach($criteria_sort as $name => $direction){
    
      $name_sql = $this->normalizeNameSQL($name);
      $dir_sql = ($direction > 0) ? 'ASC' : 'DESC';
      if(empty($ret_map['sort_sql'])){
      
        $ret_map['sort_str'] = sprintf('ORDER BY %s %s',$name_sql,$dir_sql);
        
      }else{
        
        $ret_map['sort_str'] = sprintf('%s,%s %s',$ret_map['sort_sql'],$name_sql,$dir_sql);
        
      }//if/else
    
    }//foreach

    if($where_criteria !== null){
      $ret_map['limit'] = array((int)$where_criteria->getLimit(),(int)$where_criteria->getOffset());
    }//if

    if(!empty($ret_map['limit'][0])){
    
      $ret_map['limit_str'] = sprintf(
        'LIMIT %d OFFSET %d',
        $ret_map['limit'][0],
        (empty($ret_map['limit'][1]) ? 0 : $ret_map['limit'][1])
      );
      
    }//if

    return $ret_map;
  
  }//method
  
  /**
   *  if you want to do anything special with the field's name, override this method
   *  
   *  for example, mysql might want to wrap teh name in `, so foo would become `foo`      
   *  
   *  @since  1-2-12
   *  @param  string  $name
   *  @return string  the $name, formatted
   */
  protected function normalizeNameSQL($name){ return $name; }//method
  
  /**
   *  if you want to do anything special with the table's name, override this method
   *  
   *  @since  10-21-10   
   *  @param  string  $table
   *  @return string  $table, formatted
   */
  protected function normalizeTableSQL($table){ return $table; }//method
  
  /**
   *  allows customizing the field sql type using the schema's field hints
   *
   *  @since  10-19-10
   *  @param  string  $field  the field name        
   *  @return string
   */
  protected function normalizeTypeSQL(MingoTable $table,$field){
    $ret_str = 'VARCHAR(100)';
    return $ret_str;
  }//method
  

  
  
  
  /**
   *  handle sql'ing a generic list: NAME SYMBOL (...)
   *  
   *  @param  string  $symbol the symbol to use in the sQL string
   *  @param  string  $name the name of the field      
   *  @param  array $args a list of values that $name will be in         
   *  @return array array($sql,$val_list);
   */
  protected function normalizeListSQL($symbol,$name,$args){
  
    $ret_str = sprintf(' %s %s (%s)',$name,$symbol,join(',',array_fill(0,count($args),'?')));
    return array($ret_str,$args);
  
  }//method
  
  /**
   *  handle sql'ing a generic val: NAME SYMBOL ?
   *  
   *  @param  string  $symbol the symbol to use in the sQL string
   *  @param  string  $name the name of the field      
   *  @param  array $arg  the argument         
   *  @return array array($sql,$val);
   */
  protected function normalizeValSQL($symbol,$name,$arg){

    $ret_str = sprintf(' %s %s ?',$name,$symbol);
    return array($ret_str,$arg);
  
  }//method
  
  /**
   *  handle appending to a sql string
   *  
   *  @param  string  $separator  something like 'AND' or 'OR'
   *  @param  string  $new_sql  the sql that will be appended to $old_sql
   *  @param  array $new_val  if $new_sql has any ?'s then their values need to be in $new_val
   *  @param  string  $old_sql  the original sql that will have $new_sql appended to it using $separator
   *  @param  array $old_val  all the old values that will be merged with $new_val
   *  @return array array($sql,$val)
   */
  protected function appendSQL($separator,$new_sql,$new_val,$old_sql,$old_val){
  
    // sanity...
    if(empty($new_sql)){ return array($old_sql,$old_val); }//if
  
    // build the separator...
    if(empty($old_sql)){
      $separator = '';
    }else{
      $separator = ' '.trim($separator);
    }//if
          
    $old_sql = sprintf('%s%s%s',$old_sql,$separator,$new_sql);
    
    if(is_array($new_val)){
      $old_val = array_merge($old_val,$new_val);
    }else{
      $old_val[] = $new_val;
    }//if/else
  
    return array($old_sql,$old_val);
  
  }//method
  
  /**
   *  builds a select query suitable to be passed into {@link getQuery()}
   *  
   *  this function puts all the different parts together
   *      
   *  @param  string  $table  the table
   *  @param  array $sql_map  can have a number of keys:
   *                            'select_str' - string - the fields to select from (usually * or count(*), or _id)
   *                            'where_str' - string - the where part of the string (starts with WHERE ...)
   *                            'sort_str' - string - the sort part of the string (ORDER BY ...)
   *                            'limit_str' - string - the limit part of the string (LIMIT n OFFSET n)   
   *  @return string  the built query
   */
  protected function getSelectQuery($table,array $sql_map){
  
    $query = 'SELECT';
    $printf_vars = array();
        
    // build the query...
    
    if(empty($sql_map['select_str'])){
    
      $query .= ' *';
    
    }else{
    
      $query .= ' %s';
      $printf_vars[] = $sql_map['select_str'];
    
    }//if/else
    
    $query .= ' FROM %s';
    $printf_vars[] = $this->normalizeTableSql($table);
    
    if(!empty($sql_map['where_str'])){
    
      $query .= ' %s';
      $printf_vars[] = $sql_map['where_str'];
    
    }//if
    
    // add sort...
    if(!empty($sql_map['sort_str'])){
    
      $query .= ' '.$sql_map['sort_str'];
    
    }//if
    
    // add limit...
    if(!empty($sql_map['limit_str'])){
      
      $query .= ' '.$sql_map['limit_str'];
      
    }//if

    return vsprintf($query,$printf_vars);
    
  }//method
  
  /**
   *  @see  handleException()
   *  
   *  @param  MingoTable  $table     
   *  @return boolean false on failure to solve the exception, true if $e was successfully resolved
   */
  protected function _handleException(MingoTable $table,Exception $e){
    
    $ret_bool = false;
    if($this->canHandleException($e)){
    
      // table was missing, so assure the table and everything...
      $ret_bool = $this->setTable($table);
    
    }//if
      
    return $ret_bool;
    
  }//method

}//class     

