<?php

/**
 *  handle relational db abstraction for mingo for MySQL   
 *
 *  @version 0.3
 *  @author Jay Marcyes
 *  @since 3-19-10
 *  @package mingo 
 ******************************************************************************/
class MingoMySQLInterface extends MingoRDBMSInterface {

  /**
   *  this will be the default engine for all tables
   */
  protected $engine = 'InnoDB';

  /**
   *  everything is utf-8, I'm not even giving people a choice
   */
  protected $charset = 'UTF8';
  
  /**
   *  @see  getTables()
   *  
   *  @param  MingoTable  $table  
   *  @return array
   */
  protected function _getTables(MingoTable $table = null){
  
    $query = 'SHOW TABLES';
    $query_vars = array();
    if($table !== null){
      $query .= ' LIKE ?';
      $query_vars[] = $table;
    }//if
    
    // mysql returns each table in an array with a horrible name: Tables_in_DBNAME, but that is
    // only one row, so getQuery() just returns a list of the tables
    return $this->getQuery($query,$query_vars);
    
  }//method
  
  /**
   *  things to before the connection is established
   *  
   *  this is to allow the db interface to add options or whatnot before connecting      
   *   
   *  @since  1-11-12
   */
  protected function preConnect(MingoConfig $config){
  
    // http://stackoverflow.com/questions/1566602/is-set-character-set-utf8-necessary
    // another: 'SET CHARACTER SET UTF8';
    $config->setOption(PDO::MYSQL_ATTR_INIT_COMMAND,sprintf('SET NAMES %s',$this->charset));
  
  }//method
  
  /**
   *  get the dsn connection string that PDO will use to connect to the backend
   *   
   *  @link http://us2.php.net/manual/en/ref.pdo-mysql.php
   *  @link http://us2.php.net/manual/en/ref.pdo-mysql.connection.php   
   *  @since  10-18-10
   *  @param  \MingoConfig  $config
   *  @return string  the dsn   
   */
  protected function getDsn(MingoConfig $config){
  
    // canary...
    if(!$config->hasHost()){ throw new InvalidArgumentException('no host specified'); }//if
    if(!$config->hasName()){ throw new InvalidArgumentException('no name specified'); }//if
  
    // charset is actually ignored <5.3.6
    return sprintf(
      'mysql:host=%s;dbname=%s;charset=%s',
      $config->getHost(),
      $config->getName(),
      $this->charset
    );
  
  }//method
  
  /**
   *  @see  setTable()
   *  @param  MingoTable  $table       
   *  @return boolean
   */
  protected function createTable(MingoTable $table){
  
    $query = sprintf(
      'CREATE TABLE %s (
        `_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `_created` INT(11) NOT NULL,
        `_updated` INT(11) NOT NULL,
        `body` BLOB,
        KEY (`_created`),
        KEY (`_updated`)
      ) ENGINE=%s CHARSET=%s',
      $this->normalizeTableSQL($table),
      $this->engine,
      $this->charset
    );
    
    return $this->getQuery($query);
  
  }//method
  
  /**
   *  create an index table for the given $table and $index_map      
   *   
   *  @since  10-18-10
   *  @param  \MingoTable $table
   *  @param  \MingoIndex $index  the index structure
   */
  protected function createIndexTable(MingoTable $table,MingoIndex $index){
  
    $index_table = $this->getIndexTableName($table,$index);
    $format_vars = array();
    $spatial_field = '';
    $pk_field_list = array();
    $engine = $this->engine;
  
    $format_query = array();
    $format_query[] = 'CREATE TABLE %s (';
    $format_vars[] = $this->normalizeTableSQL($index_table);
    
    foreach($index->getFieldNames() as $field){
    
      $sql_field = $this->normalizeNameSQL($field);
    
      $format_query[] = '%s %s,';
      
      $pk_field_list[] = $sql_field;
      $format_vars[] = $sql_field;
      $format_vars[] = $this->normalizeSqlType($table,$field);
    
    }//foreach
  
    // this will be the foreign key to the main blob table
    $sql_field = $this->normalizeNameSQL('_id');
    $pk_field_list[] = $sql_field;
    $format_query[] = sprintf('%s INT(11) UNSIGNED UNIQUE NOT NULL,',$sql_field);
    $format_query[] = sprintf('PRIMARY KEY (%s),',join(',',$pk_field_list));
    
    $format_query[] = sprintf(
      'CONSTRAINT `%s_fk` FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE CASCADE',
      $index_table,
      $this->normalizeNameSQL('_id'),
      $this->normalizeTableSQL($table),
      $this->normalizeNameSQL('_id')
    );
    
    $format_query[] = ') ENGINE=%s CHARSET=%s';
    $format_vars[] = $engine;
    $format_vars[] = $this->charset;
    
    $query = vsprintf(join(PHP_EOL,$format_query),$format_vars);
    
    return $this->getQuery($query);
  
  }//method
  
  /**
   *  @see  handleException()
   *  
   *  @since  10-18-10   
   *  @param  MingoTable  $table
   *  @return boolean false on unsolvable the exception, true if $e can be successfully resolved
   */
  protected function canHandleException(Exception $e){
    
    $ret_bool = false;
  
    $e_code = $e->getCode();
    if(!empty($e_code)){
    
      $ret_bool = ($e_code == '42S02');
    
    }//if
    
    return $ret_bool;
    
  }//method
  
  protected function getTableIndexes($table){
  
    $ret_list = array();
  
    // http://www.techiegyan.com/?p=209
      
    // see also: http://www.xaprb.com/blog/2006/08/28/how-to-find-duplicate-and-redundant-indexes-in-mysql/
    // for another way to find indexes
    
    // also good reading: http://www.mysqlperformanceblog.com/2006/08/17/duplicate-indexes-and-redundant-indexes/
  
    $query = sprintf('SHOW INDEX FROM %s',$this->normalizeTableSQL($table));
    $index_list = $this->getQuery($query);
    
    $ret_map = array(); // key will be index name with val being an array of fields
      
    foreach($index_list as $index_map){
    
      $index_name = sprintf('%s-%s',$index_map['Table'],$index_map['Key_name']);
    
      if(!isset($ret_map[$index_name])){
        $ret_map[$index_name] = array();
      }//if
    
      $ret_map[$index_name][] = $index_map['Column_name'];
    
    }//foreach
    
    foreach($ret_map as $index_name => $index_fields){
    
      $ret_list[] = new MingoIndex($index_name,$index_fields);
    
    }//foreach
    
    return $ret_list;
  
  }//method
  
  /**
   *  allows customizing the field sql type using the schema's field hints
   *
   *  @since  10-19-10
   *  @param  string  $field  the field name
   *  @param  MingoSchema $schema the schema for the table         
   *  @return string
   */
  protected function normalizeSqlType(MingoTable $table,$field){
  
    $ret_str = '';
    $field_instance = $table->getField($field);
  
    switch($field_instance->getType()){
    
      case MingoField::TYPE_INT:
      
        if($field_instance->hasRange())
        {
          $max_size = $this->getMaxSize();
          // http://help.scibit.com/mascon/masconMySQL_Field_Types.html
          if($max_size < 128)
          {
            $ret_str = 'TINYINT(4)';
          }else if($max_size < 32768){
            $ret_str = 'SMALLINT';
          }else if($max_size < 8388608){
            $ret_str = 'MEDIUMINT';
          }else if($max_size < 2147483648){
            $ret_str = 'INT(11)';
          }else{
            $ret_str = 'BIGINT';
          }//if/else if.../else
        
        }else{
        
          $ret_str = 'INT(11)';
        
        }//if/else
      
        break;
      
      case MingoField::TYPE_POINT:
      
        $ret_str = 'POINT';
        break;
      
      case MingoField::TYPE_BOOL:
      
        $ret_str = 'TINYINT(4)';
        break;
      
      case MingoField::TYPE_FLOAT:
      
        $ret_str = 'FLOAT';
        break;
      
      case MingoField::TYPE_STR:
      case MingoField::TYPE_LIST:
      case MingoField::TYPE_MAP:
      case MingoField::TYPE_DEFAULT:
      default:
        
        if($field_instance->hasRange())
        {
          if($field_instance->isFixedSize())
          {
            $ret_str = sprintf('CHAR(%s)',$field_instance->getMaxSize());
          }else{
            $ret_str = sprintf('VARCHAR(%s)',$field_instance->getMaxSize());
          }//if/else
        
        }else{
          $ret_str = 'VARCHAR(100)';
        }//if/else
        
        break;
    
    }//switch
  
    return $ret_str;
  
  }//method
  
  /**
   *  if you want to do anything special with the table's name, override this method
   *  
   *  @since  10-21-10   
   *  @param  string  $table
   *  @return string  $table, formatted
   */
  protected function normalizeTableSql($table){ return sprintf('`%s`',$table); }//method
  
  /**
   *  if you want to do anything special with the field's name, override this method
   *  
   *  for example, mysql might want to wrap teh name in `, so foo would become `foo`      
   *  
   *  @since  1-2-12
   *  @param  string  $name
   *  @return string  the $name, formatted
   */
  protected function normalizeNameSQL($name){ return sprintf('`%s`',$name); }//method
  
}//class     
