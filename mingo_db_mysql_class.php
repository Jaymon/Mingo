<?php

/**
 *  handle relational db abstraction for mingo for MySQL   
 *
 *  @version 0.2
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 3-19-10
 *  @package mingo 
 ******************************************************************************/
class mingo_db_mysql extends mingo_db_sql {

  /**
   *  this will be the default engine for all tables
   */
  const ENGINE = 'InnoDB';

  protected function start(){}//method
  
  /**
   *  get all the tables of the currently connected db
   *  
   *  @return array a list of table names
   */
  public function getTables($table = ''){
  
    $ret_list = array();
    
    $query = 'SHOW TABLES';
    $query_vars = array();
    if(!empty($table)){
      $query .= ' LIKE ?';
      $query_vars[] = $table;
    }//if
    
    $list = $this->getQuery($query,$query_vars);
    if(!empty($list)){
      
      // for some reason, mysql puts each table in an array with a horrible name: Tables_in_DBNAME
      foreach($list as $table_map){
        $array_keys = array_keys($table_map);
        if(isset($table_map[$array_keys[0]])){ $ret_list[] = $table_map[$array_keys[0]]; }//if
      }//foreach
      
    }//if
    
    return $ret_list;
  
  }//method
  
  /**
   *  get the dsn connection string that PDO will use to connect to the backend
   *   
   *  @since  10-18-10
   *  @param  string  $db_name  the database name
   *  @param  string  $host the host
   *  @return string  the dsn         
   */
  protected function getDsn($db_name,$host){
  
    // canary...
    if(empty($host)){ throw new mingo_exception('no $host specified'); }//if
  
    return sprintf('mysql:host=%s;dbname=%s;charset=%s',$host,$db_name,self::CHARSET);
  
  }//method
  
  /**
   *  things to do once the connection is established
   *   
   *  @since  10-18-10
   */
  protected function onConnect(){
  
    // http://stackoverflow.com/questions/1566602/is-set-character-set-utf8-necessary
    $query_charset = sprintf('SET NAMES %s',self::CHARSET); // another: 'SET CHARACTER SET UTF8';
    $this->getQuery($query_charset);
  
  }//method
  
  protected function createTable($table,mingo_schema $schema){
  
    $query = sprintf('CREATE TABLE `%s` (
        `row_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `_id` VARCHAR(24) NOT NULL,
        `body` LONGBLOB,
        UNIQUE KEY (`_id`)
    ) ENGINE=%s CHARSET=%s',$table,self::ENGINE,self::CHARSET);
    
    return $this->getQuery($query);
  
  }//method
  
  protected function createIndexTable($table,array $index_map,mingo_schema $schema){
  
    list($field_list,$field_list_str,$index_table) = $this->getIndexInfo($table,$index_map);
  
    // canary...
    if($this->hasTable($index_table)){ return true; }//if
  
    $printf_vars = array();
    $spatial_field = '';
    $pk_field_list = array();
    $engine = self::ENGINE;
  
    $query = array();
    $query[] = 'CREATE TABLE `%s` (';
    $printf_vars[] = $index_table;
    
    foreach($index_map as $field => $index_type){
    
      if($this->isSpatialIndexType($index_type)){
    
        // canary...
        if(!empty($spatial_field)){
          throw new DomainException('the index table can only have one spatial index');
        }//if
    
        $engine = 'MyISAM'; // spatial tables have to be MyIsam tables
    
        $spatial_field = $field;
        $query[] = '`%s` POINT NOT NULL,';
        $printf_vars[] = $field;
      
      }else{
      
        $query[] = '`%s` VARCHAR(100) NOT NULL,';
        $printf_vars[] = $field;
        $pk_field_list[] = $field;
        
      }//if/else
    
    }//foreach
  
    $query[] = '`_id` VARCHAR(24) NOT NULL,';
    
    if(!empty($pk_field_list)){
    
      $query[] = sprintf('PRIMARY KEY (`%s`,`_id`),',join('`,`',$pk_field_list));
      
    }//if
    
    if(!empty($spatial_field)){
    
      $query[] = 'SPATIAL INDEX (`%s`),';
      $printf_vars[] = $spatial_field;
    
    }//if
    
    $query[] = 'KEY (`_id`)';
    
    $query[] = ') ENGINE=%s CHARSET=%s';
    $printf_vars[] = $engine;
    $printf_vars[] = self::CHARSET;
    
    $query = vsprintf(join(PHP_EOL,$query),$printf_vars);
    return $this->getQuery($query);
  
  }//method
  
  protected function getTableIndexes($table){
  
    $ret_list = array();
  
    // http://www.techiegyan.com/?p=209
      
    // see also: http://www.xaprb.com/blog/2006/08/28/how-to-find-duplicate-and-redundant-indexes-in-mysql/
    // for another way to find indexes
    
    // also good reading: http://www.mysqlperformanceblog.com/2006/08/17/duplicate-indexes-and-redundant-indexes/
  
    $query = sprintf('SHOW INDEX FROM `%s`',$table);
    $index_list = $this->getQuery($query);
    
    $ret_map = array();
    
    foreach($index_list as $i => $index_map){
    
      if($index_map['Seq_in_index'] > 1){
      
        $ret_map[$index_map['Column_name']] = 1;
        
      }else{
      
        $ret_map = array();
        
        if($index_map['Index_type'] === 'SPATIAL'){
        
          $ret_map[$index_map['Column_name']] = mingo_schema::INDEX_SPATIAL;
        
        }else{
        
          $ret_map[$index_map['Column_name']] = 1;
        
        }//if/else
      
      }//if/else
      
      $next_i = $i + 1;
      $have_index = !isset($index_list[$next_i]) || ($index_list[$next_i]['Seq_in_index'] == 1);
      if($have_index){
      
        $ret_list[] = $ret_map;
        $ret_map = array();
        
      }//if
      
    }//foreach

    return $ret_list;
  
  }//method
  
  /**
   *  insert into an index table
   *  
   *  @param  string  $table  the master table, not the index table
   *  @param  string  $_id the _id of the $table where $map is found
   *  @param  array $map  the key/value pairs found in $table's body field
   *  @param  array $index_map  the map that represents the index
   *  @return boolean
   */
  protected function insertIndex($table,$_id,$map,$index_map){
    
    list($field_list,$field_name_str,$index_table) = $this->getIndexInfo($table,$index_map);

    $field_list[] = '_id';
    $val_bind_list = array();
    $val_list = array();
    
    foreach($index_map as $field => $index_type){
    
      if($this->isSpatialIndexType($index_type)){
    
        // canary...
        if(!is_array($map[$field]) || (!isset($map[$field][0]) || !isset($map[$field][1]))){
          throw new UnexpectedValueException(
            'the SPATIAL field "%s" was not in the form: array($latitude,$longitude)',
            $field
          ); 
        }//if
    
        $val_list[] = sprintf(
          'POINT(%s %s)',
          (float)$map[$field][0],
          (float)$map[$field][1]
        );
    
        $val_bind_list[] = 'PointFromText(?)';
    
      }else{
      
        $val = '';
        if(isset($map[$field])){
          $val = $map[$field];
        }//if
        
        $val_list[] = $val;
        $val_bind_list[] = '?';
        
      }//if/else
    
    }//foreach
    
    $val_list[] = $_id;
    $val_bind_list[] = '?';
    
    $query = sprintf(
      'INSERT INTO `%s` (`%s`) VALUES (%s)',
      $index_table,
      join('`,`',$field_list),
      join(',',$val_bind_list)
    );

    return $this->getQuery($query,$val_list);
  
  }//method
  
  /**
   *  handle sql'ing a spatial point
   *  
   *  @since  10-18-10   
   *  @param  string  $symbol the symbol to use in the sQL string
   *  @param  string  $name the name of the field      
   *  @param  array $args the entire map under the $name key         
   *  @return array array($sql,$val);
   */
  protected function handleSpatialSql($symbol,$name,$args){
    
    reset($args);
    $point = current($args);
    $distance = end($args);
  
    $ret_str = sprintf(' Intersects(`%s`,GeomFromText(?))',(string)$name);
    
    // create a bounding rectangle...
    list($point_a,$point_b,$point_c,$point_d) = $this->getSpatialBoundingBox($distance,$point);
    
    $val_list = array();
    // POLYGON(( $point_a, $point_b, $point_c, $point_d, $point_a ))
    $val_list[] = sprintf(
      'LineString(%s, %s, %s, %s, %s)',
      join(' ',$point_a), 
      join(' ',$point_b),
      join(' ',$point_c),
      join(' ',$point_d),
      join(' ',$point_a)
    );
    
    return array($ret_str,$val_list);
  
  }//method
  
  /**
   *  true if the $e is for a missing table exception
   *
   *  @since  10-18-10
   *  @see  handleException()         
   *  @param  Exception $e  the thrown exception
   *  @return boolean
   */
  protected function isNoTableException(Exception $e){
  
    $ret_bool = false;
  
    $e_code = $e->getCode();
    if(!empty($e_code)){
    
      $ret_bool = ($e_code == '42S02');
    
    }//if
    
    return $ret_bool;
    
  }//method
  
  /**
   *  adds an index to $table
   *  
   *  I don't currently have a use for this method, but thought I would keep it around
   *      
   *  @param  string  $table  the table to add the index to
   *  @param  array $map  usually something like array('field_name' => 1), this isn't need for sql
   *                      but it's the same way to keep compatibility with Mongo   
   *  @return boolean
   */
  function createIndex($table,array $index_map){
    
    // ALTER TABLE table_name`ADD|DROP [FULLTEXT] INDEX(column_name,...);
    // http://www.w3schools.com/sql/sql_alter.asp
    //
    // good info on indexes: http://www.mysqlperformanceblog.com/2006/08/17/duplicate-indexes-and-redundant-indexes/
    //  and: http://www.xaprb.com/blog/2006/08/28/how-to-find-duplicate-and-redundant-indexes-in-mysql/
    //  http://www.sql-server-performance.com/tips/optimizing_indexes_general_p2.aspx
    //  http://www.sql-server-performance.com/articles/per/index_not_equal_p1.aspx
    //  http://www.databasejournal.com/features/mysql/article.php/1382791
    //  Error 1170 when trying to create an index, means you are creating an unlimited index:
    //    http://www.mydigitallife.info/2007/07/09/mysql-error-1170-42000-blobtext-column-used-in-key-specification-without-a-key-length/
    // you can see indexes on the table using this: SHOW INDEX FROM �table�
    //
    // Mysql syntax...
    //  http://dev.mysql.com/doc/refman/5.0/en/alter-table.html
    
    // the order bit is ignored for sql, so we just need the keys...
    $field_list = array_keys($index_map);
    $field_list_str = join('`,`',$field_list);
    $index_name = 'i'.md5($field_list_str);
    
    $query = sprintf('ALTER TABLE `%s` ADD INDEX `%s` (`%s`)',$table,$index_name,$field_list_str);
    
    return $this->getQuery($query);
  
  }//method */
  
}//class     
