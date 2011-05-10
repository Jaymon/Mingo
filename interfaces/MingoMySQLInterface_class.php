<?php

/**
 *  handle relational db abstraction for mingo for MySQL   
 *
 *  @version 0.2
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 3-19-10
 *  @package mingo 
 ******************************************************************************/
class MingoMySQLInterface extends MingoSQLInterface {

  /**
   *  this will be the default engine for all tables
   */
  const ENGINE = 'InnoDB';

  protected function start(){}//method
  
  /**
   *  @see  getTables()
   *  
   *  @param  MingoTable  $table  
   *  @return array
   */
  protected function _getTables(MingoTable $table = null){
  
    $ret_list = array();
    
    $query = 'SHOW TABLES';
    $query_vars = array();
    if($table !== null){
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
   *  @param  string  $name  the database name
   *  @param  string  $host the host
   *  @return string  the dsn         
   */
  protected function getDsn($name,$host){
  
    // canary...
    if(empty($host)){ throw new InvalidArgumentException('no $host specified'); }//if
  
    return sprintf('mysql:host=%s;dbname=%s;charset=%s',$host,$name,self::CHARSET);
  
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
  
  /**
   *  @see  setTable()
   *  @param  MingoTable  $table       
   *  @return boolean
   */
  protected function _setTable(MingoTable $table){
  
    $query = sprintf('CREATE TABLE `%s` (
      `row_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `_id` VARCHAR(24) NOT NULL,
      `body` LONGBLOB,
      UNIQUE KEY (`_id`)
    ) ENGINE=%s CHARSET=%s',$table,self::ENGINE,self::CHARSET);
    
    return $this->getQuery($query);
  
  }//method
  
  /**
   *  create an index table for the given $table and $index_map      
   *   
   *  @since  10-18-10
   *  @param  string  $table
   *  @param  array $index_map  the index structure 
   */
  protected function createIndexTable($table,array $index_map){
  
    $index_table = $this->getIndexTableName($table,$index_map);
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
    
        // according to this: http://dev.mysql.com/doc/refman/5.0/en/creating-spatial-columns.html
        // InnoDB POINT column support was added in 5.0.16, but index support is still lacking, so
        // to have an index, spatial tables have to be MyIsam tables...
        $engine = 'MyISAM';
    
        $spatial_field = $field;
        $query[] = '`%s` POINT NOT NULL,';
        $printf_vars[] = $field;
      
      }else{
        
        $query[] = '`%s` %s,';
        $printf_vars[] = $field;
        $printf_vars[] = $this->getSqlType($table,$field);
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
        
          $ret_map[$index_map['Column_name']] = self::INDEX_SPATIAL;
        
        }else{
        
          $ret_map[$index_map['Column_name']] = self::INDEX_ASC;
        
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
  protected function createIndex($table,array $index_map){
    
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
    // you can see indexes on the table using this: SHOW INDEX FROM ‘table’
    //
    // Mysql syntax...
    //  http://dev.mysql.com/doc/refman/5.0/en/alter-table.html
    
    // the order bit is ignored for sql, so we just need the keys...
    $field_list = array_keys($index_map);
    $field_list_str = join('`,`',$field_list);
    $index_name = 'i'.md5($field_list_str);
    
    $query = sprintf('ALTER TABLE `%s` ADD INDEX `%s` (`%s`)',$table,$index_name,$field_list_str);
    
    return $this->getQuery($query);
  
  }//method
  
  /**
   *  allows customizing the field sql type using the schema's field hints
   *
   *  @since  10-19-10
   *  @param  string  $field  the field name
   *  @param  MingoSchema $schema the schema for the table         
   *  @return string
   */
  protected function getSqlType(MingoTable $table,$field){
  
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
  
  /**
   *  formats the insert sql for index tables
   *  
   *  @since  10-21-10
   *  @param  string  $field  the field name
   *  @param  mixed $val  the field value
   *  @param  mixed $index_type the index type for $field
   *  @return array array($field,$val,$bind) where $bind is usually a question mark
   */
  protected function handleInsertSql($field,$val,$index_type = null){
  
    $field = sprintf('`%s`',$field);
    $bind = '?';
  
    if($this->isSpatialIndexType($index_type)){
    
      // canary, check the field is properly formatted...
      $mf = new MingoField();
      $mf->setType(MingoField::TYPE_POINT);
      $val = $mf->normalizeInVal($val);
  
      $val = sprintf(
        'POINT(%s %s)',
        (float)$val[0],
        (float)$val[1]
      );
  
      $bind = 'PointFromText(?)';
  
    }//if
  
    return array($field,$val,$bind);
  
  }//method
  
  /**
   *  if you want to do anything special with the table's name, override this method
   *  
   *  @since  10-21-10   
   *  @param  string  $table
   *  @return string  $table, formatted
   */
  protected function handleTableSql($table){ return sprintf('`%s`',$table); }//method
  
  /**
   *  get a bounding box for a given $point using $miles
   *  
   *  the bounding box will basically be $miles from $point in any direction
   *  
   *  links that helped me calculate miles to a point:
   *  http://mathforum.org/library/drmath/view/55461.html
   *  http://wiki.answers.com/Q/How_many_miles_are_in_a_degree_of_longitude_or_latitude
   *  http://answers.yahoo.com/question/index?qid=20070911165150AAQGeJc 
   *  
   *  @since  8-19-10   
   *  @param  integer $miles  how many miles we want to go in any direction from $point
   *  @param  array $point  array($lat,$long)
   *  @return array basically 4 points: array($sw,$se,$ne,$nw)
   */              
  protected function getSpatialBoundingBox($miles,$point){
  
    // canary...
    if(empty($miles)){ throw InvalidArgumentException('$miles should not be empty'); }//if
  
    list($latitude,$longitude) = $point;
  
    $latitude_miles = 69; // 1 degree of latitude, this is approximate but it's close enough
    $latitude_bounding = ($miles / $latitude_miles);
    
    // get the longitude bounding using cosine...
    $longitude_percentage = abs(cos($latitude * (pi()/180)));
    $longitude_miles = $latitude_miles * $longitude_percentage;
    $longitude_bounding = ($miles / $longitude_miles);
    
    // create a bounding rectangle...
    // http://maisonbisson.com/blog/post/12148/find-stuff-by-minimum-bounding-rectangle/
    $sw = array($latitude - $latitude_bounding,$longitude - $longitude_bounding);
    $se = array($latitude - $latitude_bounding,$longitude + $longitude_bounding);
    $ne = array($latitude + $latitude_bounding,$longitude + $longitude_bounding);
    $nw = array($latitude + $latitude_bounding,$longitude - $longitude_bounding);
    
    return array($sw,$se,$ne,$nw);
    
  }//method
  
}//class     
