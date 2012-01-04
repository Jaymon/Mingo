<?php

/**
 *  converts a MingoCriteria into sql  
 * 
 *  @version 0.1
 *  @author Jay Marcyes
 *  @since 01-2-2012
 *  @package mingo 
 ******************************************************************************/
class MingoSQLCriteria extends MingoCriteria {

  protected $sql_map = array();

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

  public function __construct(MingoCriteria $criteria = null){
  
    if(empty($criteria)){
  
      $this->merge($criteria);
      $this->sql_map = $this->translate();
      
      \out::e($this->sql_map);
      
    }//if
  
  }//method

  /**
   *  this will do the actual conversion from one format to the other
   *
   *  @return void
   */
  protected function translate(){
  
    $ret_map = array();
    $ret_map['where_str'] = '';
    $ret_map['where_params'] = array();
    $ret_map['sort_str'] = array();
  
    $ret_where = $ret_sort = '';
  
    $criteria_where = $this->getWhere();
    $criteria_sort = $this->getSort();
    
    $command_symbol = $this->getCommandSymbol();
  
    foreach($criteria_where as $name => $map){
    
      $where_sql = '';
      $where_val = array();
      $name_sql = $this->normalizeNameSQL($name);
    
      if(is_array($map)){
      
        $total_map = count($map);
      
        // go through each map val and append it to the sql string...
        foreach($map as $command => $val){
  
          if($this->isCommand($command)){
          
            $command_bare = $this->getCommand($command);
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
        list($where_sql,$where_val) = $this->normalizeValSql('=',$name,$map);
      
      }//if/else
      
      list($ret_map['where_str'],$ret_map['where_val']) = $this->appendSql(
        'AND',
        $where_sql,
        $where_val,
        $ret_map['where_str'],
        $ret_map['where_val']
      );
    
    }//foreach
  
    if(!empty($ret_map['where_val'])){
    
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

    $limit = array(0,0);
    if($where_criteria !== null){
      $limit = array($where_criteria->getLimit(),$where_criteria->getOffset());
    }//if

    $ret_map['limit'] = $limit;
    $ret_map['limit_str'] = '';
    if(!empty($limit[0])){
    
      $ret_map['limit_str'] = sprintf(
        'LIMIT %d OFFSET %d',
        (int)$limit[0],
        (empty($limit[1]) ? 0 : (int)$limit[1])
      );
      
    }//if
  
    \out::e($ret_map);
  
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

}//class   
