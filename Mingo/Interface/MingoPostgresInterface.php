<?php

/**
 *  handle Postgres connection using hstore
 *  
 *  @todo sorting numberic values (except __rowid) doesn't work
 *  
 *  a query like:
 *  
 *  SELECT * FROM testsort ORDER BY 
 *    CASE WHEN body -> 'foo' < 'A' THEN lpad(body -> 'foo',255, '0') ELSE body -> 'foo' END;
 *  
 *  will make it sort correctly, but I just can't help but think that will be massively
 *  slow with larger datasets and it looks seriously ugly also, via:
 *  http://stackoverflow.com/questions/4080787/   
 *  
 *  @note if the indexes don't work with larger datasets (greater than 500k records or so)
 *  then you can try setting indexes on each of the hstore keys:
 *  
 *  CREATE INDEX foo ON myhstore((kvps->'yourkeyname')) WHERE (kvps->'yourkeyname') IS NOT NULL;  
 *  
 *  via: http://archives.postgresql.org/pgsql-performance/2011-05/msg00339.php
 *    http://archives.postgresql.org/pgsql-performance/2011-05/msg00286.php 
 *  
 *  @version 0.1
 *  @author Jay Marcyes
 *  @since 12-31-2011
 *  @package mingo
 ******************************************************************************/
class MingoPostgresInterface extends MingoPDOInterface {
  
  /**
   *  get the dsn connection string that PDO will use to connect to the backend
   *   
   *  @since  10-18-10
   *  @param  \MingoConfig  $config
   *  @return string  the dsn   
   */
  protected function getDsn(MingoConfig $config){
  
    // canary...
    if(!$config->hasHost()){ throw new InvalidArgumentException('no host specified'); }//if
    if(!$config->hasName()){ throw new InvalidArgumentException('no name specified'); }//if
  
    return sprintf(
      'pgsql:dbname=%s;host=%s;port=%s',
      $config->getName(),
      $config->getHost(),
      $config->getPort(5432)
    );
  
  }//method
  
  /**
   *  @see  getTables()
   *  
   *  @link http://stackoverflow.com/questions/435424/
   *  @link http://stackoverflow.com/questions/1766046/
   *  @link http://www.peterbe.com/plog/pg_class      
   *      
   *  @param  MingoTable  $table  
   *  @return array
   */
  protected function _getTables(MingoTable $table = null){
  
    $query = '';
    $val_list = array();
  
    if(empty($table)){
    
      $query = 'SELECT tablename FROM pg_tables';
    
    }else{
    
      $query = 'SELECT tablename FROM pg_tables WHERE tablename = ?';
      $val_list = array($table->getName());
      
    }//if/else
  
    $ret_list = $this->_getQuery($query,$val_list);
    return $ret_list;
  
  }//method
  
  /**
   *  @see  getCount()
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  mixed $where_criteria the where criteria ran through {@link normalizeCriteria())      
   *  @return integer the count
   */
  protected function _getCount($table,$where_criteria){
  
    $ret_int = 0;
    
    $where_criteria['select_str'] = 'count(*)';
    $where_criteria['sort_str'] = '';
    $where_criteria['limit_str'] = '';
    $select_query = $this->getSelectQuery($where_criteria);
    
    $ret_list = $this->getQuery(
      $select_query,
      isset($where_criteria['where_params']) ? $where_criteria['where_params'] : array()
    );
    
    if(isset($ret_list[0])){ $ret_int = (int)$ret_list[0]; }//if
    
    return $ret_int;
  
  }//method
  
  /**
   *  @see  get()
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  mixed $where_criteria the where criteria ran through {@link normalizeCriteria())      
   *  @return array   
   */
  protected function _get($table,$where_criteria){

    // what fields do we want to select
    $select_query = $this->getSelectQuery($where_criteria);
    ///\out::e($select_query);
    
    $stmt =  $this->getstatement(
      $select_query,
      isset($where_criteria['where_params']) ? $where_criteria['where_params'] : array()
    );
    
    $ret_list = array();
    
    // go through every row and parse the hstore body
    while($map = $stmt->fetch(PDO::FETCH_ASSOC)){
    
      $field_map = $this->fromHstore($map['body']);
      unset($map['body']);
      
      $field_map['_id'] = $map['_id'];
      $field_map['_rowid'] = $map['_rowid'];
      
      $ret_list[] = $field_map;
    
    }//while
    
    ///\out::e($ret_list);
    
    return $ret_list;

  }//method
  
  /**
   *  @see  kill()
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  mixed $where_criteria the where criteria ran through {@link normalizeCriteria())      
   *  @return boolean
   */
  protected function _kill($table,$where_criteria){
  
    $query = 'DELETE FROM %s';
    $sprintf_vars = array($where_criteria['table_str']);
    $where_params = array();
    
    if(!empty($ret_map['where_params'])){
    
      $query .= ' %s';
      $sprintf_vars[] = $sql_map['where_str'];
      $where_params = $ret_map['where_params'];
      
    }//if
  
    $query = vsprintf($query,$sprintf_vars);
    $ret_bool = $this->getQuery($query,$where_params);
  
    return $ret_bool;
  
  }//method
  
  /**
   *  insert $map into $table
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  array  $map  the key/value map that will be added to $table  
   *  @return array the $map that was just saved, with the _id set               
   */
  protected function insert($table,array $map){
  
    $_id = $this->getUniqueId($table);
    
    // build the field list
    $field_list = array();
    $field_params = array();
    $field_params[] = $_id;
    $field_params[] = $this->toHstore($map);
    
    $query = sprintf(
      "INSERT INTO %s (_id,body) VALUES (?,hstore(?))",
      $this->normalizeTableSql($table)
    );
    
    if($this->getQuery($query,$field_params)){
    
      $map['_id'] = $_id;
    
    }//if
    
    return $map;
    
  }//method
  
  /**
   *  update $map from $table using $_id
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  string  $_id the _id attribute from $map   
   *  @param  array $map  the key/value map that will be added to $table
   *  @return array the $map that was just saved with _id set
   */
  protected function update($table,$_id,array $map){

    $query = sprintf('UPDATE %s SET body=hstore(?) WHERE _id=?',$this->normalizeTableSql($table));

    $param_list = array();
    $param_list[] = $this->toHstore($map);
    $param_list[] = $_id;

    $this->getQuery($query,$param_list);

    return $map;

  }//method
  
  /**
   *  @see  setIndex()
   *  
   *  Postgres sets all the indexes on the table when {@link setTable()} is called, it
   *  doesn't need to set any other indexes   
   *      
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  mixed $index  an index ran through {@link normalizeIndex()}
   *  @return boolean
   */
  protected function _setIndex($table,$index){ return false; }//method
  
  /**
   *  @see  getIndexes()
   *  
   *  since postgres hstore has an index on the whole thing, we can just return
   *  the indexes the MingoTable thinks we have, since they are technically indexed      
   *      
   *  @link http://stackoverflow.com/questions/2204058/
   *  
   *  other links:
   *    http://stackoverflow.com/questions/2204058/show-which-columns-an-index-is-on-in-postgresql
   *    http://www.postgresql.org/docs/current/static/catalog-pg-index.html
   *    http://www.manniwood.com/postgresql_stuff/index.html
   *    http://archives.postgresql.org/pgsql-php/2005-09/msg00011.php                  
   *      
   *  @param  mixed $table  the table ran through {@link normalizeTable()}      
   *  @return array
   */
  protected function _getIndexes($table){ return $table->getIndexes(); }//method
  
  /**
   *  @see  killTable()
   *  
   *  @link http://www.postgresql.org/docs/7.4/interactive/sql-droptable.html
   *        
   *  @param  mixed $table  the table ran through {@link normalizeTable()}      
   *  @return boolean
   */
  protected function _killTable($table){

    $query = sprintf(
      'DROP TABLE %s CASCADE',
      $this->normalizeTableSql($table)
    );
    
    return $this->getQuery($query);

  }//method

  /**
   *  @see  setTable()
   *      
   *  @param  MingoTable  $table       
   *  @return boolean
   */
  protected function _setTable(MingoTable $table){

    $query = sprintf('CREATE TABLE %s (
      _rowid SERIAL PRIMARY KEY,
      _id VARCHAR(24) NOT NULL,
      body hstore
    )',$this->normalizeTableSql($table));
  
    $ret_bool = $this->getQuery($query);
    
    if($ret_bool){
    
      // add some indexes to the table...
    
      $query = sprintf(
        'CREATE INDEX %s0 ON %s USING BTREE (_id)',
        $table,
        $this->normalizeTableSql($table)
      );
      $ret_bool = $this->getQuery($query);
    
      $query = sprintf(
        'CREATE INDEX %s2 ON %s USING GIN (body)',
        $table,
        $this->normalizeTableSql($table)
      );
      $ret_bool = $this->getQuery($query);
    
      /* $query = sprintf(
        'CREATE INDEX %s2 ON %s USING GIST (body)',
        $table,
        $table
      );
      $ret_bool = $this->getQuery($query); */
    
      $query = sprintf(
        'CREATE INDEX %s1 ON %s USING BTREE (body)',
        $table,
        $this->normalizeTableSql($table)
      );
      $ret_bool = $this->getQuery($query);
      
    }//if
    
    return $ret_bool;

  }//method
  
  /**
   *  @see  handleException()
   *  
   *  @param  MingoTable  $table
   *  @return boolean false on failure to solve the exception, true if $e was successfully resolved
   */
  protected function canHandleException(Exception $e){
    
    $ret_bool = false;
    
    if($e->getCode() === '42P01'){
    
      // SQLSTATE[42P01]: Undefined table: 7 ERROR: relation "<table_name>" does not exist 
      $ret_bool = true;
    
    }//if
    
    return $ret_bool;
    
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
  protected function normalizeNameSQL($name){
  
    // canary
    if($name === '_id'){ return $name; }//if
    if($name === '_rowid'){ return $name; }//if
  
    return sprintf('body -> \'%s\'',$name);
    
  }//method
  
  /**
   *  convert a php array to an hstore
   *
   *  seriously, it sucks this can't be done natively and that these methods are needed
   *      
   *  @since  1-4-12
   *  
   *  @param  array $map  the array to convert to hstore
   *  @return string  the hstore suitable to be inserted into the hstore column of the table         
   */
  protected function toHstore(array $map){
  
    $field_list = array();
  
    foreach($map as $name => $val){
    
      if($val === null){
      
        $field_list[] = sprintf('"%s"=>NULL',$name);
        
      }else if(is_array($val)){
      
        // @todo  turns out that the serializing of arrays messes up the structure and
        // errors are thrown, so I'm just removing support until I figure out what to
        // do, I could serialize and then base64? SIGH 
      
        throw new \DomainException(
          sprintf('%s does not support values that are arrays, sorry',get_class($this))
        );
      
        ///$field_list[] = sprintf('"%s"=>"%s"',$name,serialize($val));
      
      }else{
      
        $field_list[] = sprintf('"%s"=>"%s"',$name,$val);
        
      }//if/else
      
    }//foreach

    
    return join(',',$field_list);
  
  }//method
  
  /**
   *  parse the hstore into a php array since sadly, there doesn't seem to be a way to
   *  do it natively   
   *
   *  inspired by these projects:
   *    https://github.com/chanmix51/Pomm/blob/master/Pomm/Converter/PgHStore.php
   *    https://github.com/DmitryKoterov/db_type
   *    http://stackoverflow.com/questions/6742563/convert-postgresql-hstore-to-php-array
   *    http://stackoverflow.com/questions/1738000/implementing-postgresql-arrays-in-zend-model         
   *
   *  I did a token parser since I figured that would be easier to fix bugs when they
   *  are found than a regex solution   
   *      
   *  @since  1-4-12
   *  
   *  @param  string  $body the hstore body
   *  @return array the parsed $body         
   */
  protected function fromHstore($body){
  
    // canary
    if(empty($body)){ throw new InvalidArgumentException('$body was empty'); }//if
  
    $ret_map = array();
    $str = str_split($body);
    $len = count($str);
    
    for($i = 0; $i < $len ;$i++){
    
      ///\out::e(substr($body,$i));
    
      if($str[$i] === '"'){
      
        $key = '';
        $val = '';
      
        // go until we get a "=>"
        for($j = $i + 1; $j < $len ;$j++){
        
          if($str[$j] === '"'){
          
            if($str[$j + 1] === '='){
          
              if($str[$j + 2] === '>'){
          
                if($str[$j + 3] === '"'){
                
                  $j += 4;
                  break;
                
                }else if($str[$j + 3] === 'N'){
          
                  $j += 3;
                  break;
          
                }//if
          
              }//if
          
            }//if
          
          }//if
        
          $key .= $str[$j];
        
        }//for
        
        // now go until we get a ", "
        for($k = $j; $k < $len ;$k++){
        
          if($str[$k] === 'N'){
          
            if(($str[$k + 1] === 'U') && ($str[$k + 2] === 'L') && ($str[$k + 3] === 'L')){
            
              $k += 3;
              $val = null;
              break;
            
            }//if
        
          }else if($str[$k] === '"'){
          
            if(isset($str[$k + 1])){
            
              if($str[$k + 1] === ','){
            
                if($str[$k + 2] === ' '){
            
                  if($str[$k + 3] === '"'){ // this will be the quote that starts a new key
            
                    $k += 2;
                    break;
            
                  }//if
            
                }//if
            
              }//if
              
            }else{
            
              $k += 1;
              break;
            
            }//if/else
          
          }//if
        
          $val .= $str[$k];
        
        }//for
      
        $i = $k;
        $ret_map[$key] = $val;
      
      }//if
    
    }//foreach

    ///\out::e($ret_map);
    return $ret_map;

  }//method
  
}//class     

