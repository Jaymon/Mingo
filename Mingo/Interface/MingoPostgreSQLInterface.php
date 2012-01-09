<?php

/**
 *  handle relational db abstraction for mingo for Postgres
 *  
 *  @version 0.1
 *  @author Jay Marcyes
 *  @since 1-7-12
 *  @package mingo 
 ******************************************************************************/
class MingoPostgreSQLInterface extends MingoRDBMSInterface {
  
  /**
   *  gets the last insert id of the $table's auto increment key 
   *
   *  this is here because postgres has to be different
   *      
   *  @since  1-9-12   
   *  @param  \MingoTable $table
   *  @return integer   
   */
  protected function getInsertId(MingoTable $table){
  
    $db = $this->getDb();
    return $db->lastInsertId($table.'__id_seq');
  
  }//method
  
  /**
   *  return true if a string is binary
   *   
   *  this method is a cross between http://bytes.com/topic/php/answers/432633-how-tell-if-file-binary
   *  and this: http://groups.google.co.uk/group/comp.lang.php/msg/144637f2a611020c?dmode=source
   *  but I'm still not completely satisfied that it is 100% accurate, though it seems to be
   *  accurate for my purposes.
   *  
   *  @param  string  $val  the val to check to see if contains binary characters
   *  @return boolean true if binary, false if not
   */
  protected function isBinaryString($val){
  
    $val = (string)$val;
    $ret_bool = false;
    $not_printable_count = 0;
    for($i = 0, $max = strlen($val); $i < $max ;$i++){
      if(ord($val[$i]) === 0){ $ret_bool = true; break; }//if
      if(!ctype_print($val[$i])){
        if(++$not_printable_count > 5){ $ret_bool = true; break; }//if
      }//if 
    }//for
    
    return $ret_bool;
  
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

      if(!empty($val_list)){
  
        $k = key($val_list);
        if(ctype_digit((string)$k)){
        
          // indexes are 1 based, not zero based
          foreach($val_list as $i => $val){
          
            $stmt_handler->bindValue($i+1,$val,PDO::PARAM_STR);
          
          }//foreach
        
        }else{
    
          if(isset($val_list['body'])){
          
            $stmt_handler->bindValue(':body',$val_list['body'],PDO::PARAM_LOB);
            unset($val_list['body']);
          
          }//if
    
          foreach($val_list as $key => $val){
          
            $stmt_handler->bindValue(sprintf(':%s',$key),$val,PDO::PARAM_STR);
              
          }//foreach
          
        }//if/else
        
      }//if
    
      // execute the query...
      // passing in the values this way instead of doing bindValue() seems to
      // work fine even though all the values get treated like a string
      $is_success = $stmt_handler->execute();
      
    }catch(Exception $e){
    
      $stmt_handler->closeCursor();
      throw $e;
    
    }//try/catch
  
    return $stmt_handler;
  
  }//method
  
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
   *  @see  setTable()
   *  
   *  @link http://www.postgresql.org/docs/8.4/static/sql-createtable.html      
   *  @param  MingoTable  $table       
   *  @return boolean
   */
  protected function _setTable(MingoTable $table){
  
    $query = sprintf(
      'CREATE TABLE %s (
        _id SERIAL PRIMARY KEY,
        _created INTEGER NOT NULL,
        _updated INTEGER NOT NULL,
        body BYTEA
      )',
      $this->normalizeTableSQL($table)
    );
  
    $ret_bool = $this->getQuery($query);
  
    if($ret_bool){
    
      $ret_bool = $this->createIndex(
        $table,
        new MingoIndex('index_created',array('_created'))
      );
    
      $ret_bool = $this->createIndex(
        $table,
        new MingoIndex('index_updated',array('_updated'))
      );
    
    }//if
    
    return $ret_bool;
  
  }//method
  
  /**
   *  create an index table for the given $table and $index_map      
   *  
   *  http://www.sqlite.org/syntaxdiagrams.html#column-constraint
   *      
   *  @since  10-18-10
   *  @param  string  $table
   *  @param  array $index  the index structure
   */
  protected function setIndexTable(MingoTable $table,MingoIndex $index){
  
    $index_table = $this->getIndexTableName($table,$index);
    $format_vars = array();
    $format_query = array();
    $format_query[] = 'CREATE TABLE %s (';
    $format_vars[] = $this->normalizeTableSQL($index_table);
    $field_list = $index->getFieldNames();
    
    foreach($field_list as $field){
    
      $format_query[] = '%s %s,';
      
      $format_vars[] = $field;
      $format_vars[] = $this->normalizeSqlType($table,$field);
    
    }//foreach

    // this will be the foreign key to the main blob table
    $format_query[] = '_id INTEGER UNIQUE NOT NULL,';
    
    // primary key is all the fields in the index + foreign key
    /** $format_query[] = 'CONSTRAINT %s_%s_pk PRIMARY KEY(%s,_id),';
    $format_vars[] = $table;
    $format_vars[] = $index->getName();
    $format_vars[] = join(',',$field_list);
    **/
    
    // _rowid will be our binding to the master table
    // http://www.sqlite.org/foreignkeys.html
    $format_query[] = 'FOREIGN KEY(_id) REFERENCES %s(_id) ON DELETE CASCADE';
    $format_vars[] = $this->normalizeTableSQL($table);
    
    $format_query[] = ')';
    
    $query = vsprintf(join(PHP_EOL,$format_query),$format_vars);
    $ret_bool = $this->getQuery($query);
    
    // we create the index this way instead of 
    if($ret_bool){
    
      $field_list[] = '_id';
      $this->createIndex($index_table,new MingoIndex('pk',$field_list),array('unique' => true));
    
    }//if
    
    return $ret_bool;
  
  }//method
  
  /**
   *  get the indexes for the given table
   *  
   *  this should get all the indexes that are set on the $table
   *  
   *  @link http://stackoverflow.com/questions/2204058/show-which-columns-an-index-is-on-in-postgresql      
   *  
   *  other links:
   *    http://stackoverflow.com/questions/970562/postgres-and-indexes-on-foreign-keys-and-primary-keys   
   *    http://www.postgresql.org/docs/current/static/catalog-pg-index.html
   *    http://www.manniwood.com/postgresql_stuff/index.html
   *    http://archives.postgresql.org/pgsql-php/2005-09/msg00011.php       
   *         
   *  @see  getIndexes()
   *  @param  string  $table  the table name whose indexes you want
   *  @return array a list of MingoIndex instances
   */
  protected function getTableIndexes($table){
  
    $ret_list = array();
    
    $query = array();
    $query[] = 'SELECT'; 
    $query[] = '  tbl.relname AS table_name, i.relname AS index_name, a.attname AS field_name';
    $query[] = 'FROM';
    $query[] = '  pg_class tbl, pg_class i, pg_index ix, pg_attribute a';
    $query[] = 'WHERE';
    $query[] = '  tbl.oid = ix.indrelid AND i.oid = ix.indexrelid AND a.attrelid = tbl.oid';
    $query[] = '  AND a.attnum = ANY(ix.indkey) AND tbl.relkind = ? AND tbl.relname = ?';
    $query[] = 'ORDER BY';
    $query[] = '  tbl.relname, i.relname';
    
    $index_list = $this->getQuery(join(PHP_EOL,$query),array('r',$table));
    $ret_map = array(); // key will be index name with val being an array of fields
      
    foreach($index_list as $index_map){
    
      if(!isset($ret_map[$index_map['index_name']])){
        $ret_map[$index_map['index_name']] = array();
      }//if
    
      $ret_map[$index_map['index_name']][] = $index_map['field_name'];
    
    }//foreach
    
    foreach($ret_map as $index_name => $index_fields){
    
      $ret_list[] = new MingoIndex($index_name,$index_fields);
    
    }//foreach

    return $ret_list;
  
  }//method
  
  /**
   *  @see  getTables()
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
   *  this is here because each rdbms is a little different in how they create indexes
   *  
   *  it is not in the _setIndex because _setIndex creates a table for the index, and
   *  we need a way to set an index on an existing table   
   *
   *  @link http://www.postgresql.org/docs/8.2/static/sql-createindex.html   
   *  @param  string  $table
   *  @param  \MingoIndex $index   
   *  @return boolean  
   */
  protected function createIndex($table,MingoIndex $index,array $options = array()){

    $query = sprintf(
      'CREATE %sINDEX %s_%s ON %s USING BTREE (%s)',
      empty($options['unique']) ? '' : 'UNIQUE ',
      $table,
      $index->getName(),
      $this->normalizeTableSQL($table),
      join(',',$index->getFieldNames())
    );

    return $this->getQuery($query);
  
  }//method
  
  /**
   *  @see  killTable()
   *  
   *  Postgres needs the CASCADE on the end to work
   *      
   *  @link http://www.postgresql.org/docs/8.2/static/sql-droptable.html
   *      
   *  @param  mixed $table  the table ran through {@link normalizeTable()}      
   *  @return boolean
   */
  protected function _killTable($table){
  
    $query = sprintf('DROP TABLE IF EXISTS %s CASCADE',$this->normalizeTableSQL($table));
    $ret_bool = $this->getQuery($query);
    if($ret_bool){ $this->killIndexTables($table); }//if
    
    return $ret_bool;

  }//method
  
  /**
   *  allows customizing the field sql type using the schema's field hints
   *
   *  @link http://www.postgresql.org/docs/8.1/static/datatype.html   
   *  @param  \MingoTable $table   
   *  @param  string  $field  the field name
   *  @return string
   */
  protected function normalizeSqlType(MingoTable $table,$field){
  
    $ret_str = '';
    $field_instance = $table->getField($field);
  
    switch($field_instance->getType()){
    
      case MingoField::TYPE_INT:
      
        $ret_str = 'INTEGER';
        break;
      
      case MingoField::TYPE_BOOL:
      
        $ret_str = 'SMALLINT';
        break;
      
      case MingoField::TYPE_FLOAT:
      
        $ret_str = 'REAL';
        break;
      
      case MingoField::TYPE_POINT:
      
        $ret_str = 'POINT';
        break;
      
      case MingoField::TYPE_STR:
      case MingoField::TYPE_LIST:
      case MingoField::TYPE_MAP:
      case MingoField::TYPE_OBJ:
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
  
}//class     
