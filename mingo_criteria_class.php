<?php

/**
 *  creates criteria for querying a mingo db, also maps those to sql when using 
 *  a relational backend 
 *
 *  allow an easy way to define most of the advanced queries defined here:
 *  http://www.mongodb.org/display/DOCS/Advanced+Queries#AdvancedQueries-{{group()}}
 *  http://www.mongodb.org/display/DOCS/Atomic+Operations
 *  http://www.mongodb.org/display/DOCS/Sorting   
 *  
 *  @version 0.2
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 11-17-09
 *  @package mingo 
 ******************************************************************************/
class mingo_criteria extends mingo_base {

  const ASC = 1;
  const DESC = -1;

  protected $command_symbol = '$';
  
  /**
   *  holds the internal structure of the "WHERE" criteria, usually returned from either
   *  {@link get()} or {@link getSql()}, while you can set this using {@link set()} it would
   *  be better to use the magic methods that {@link __call()} defines since that will
   *  guarrantee a valid criteria array
   *  
   *  @var  array               
   */
  protected $map_criteria = array();
  
  /**
   *  similar to {@link $map_criteria} but is for the "SORT BY..." part of a query
   *  
   *  this can only be set with the sort|asc|desc magic methods
   *  
   *  @var  array
   */
  protected $map_sort = array();
  
  /**
   *  used internally to map the commands to the internal methods that will handle them
   *
   *  @var  array   
   */
  protected $method_map = array();

  function __construct($map_criteria = array()){
  
    $command_symbol = ini_get('mongo.cmd');
    if(!empty($command_symbol)){ $this->command_symbol = $command_symbol; }//if
    
    $this->method_map = array(
      'in' => array('set' => 'handleList', 'sql' => 'handleListSql', 'symbol' => 'IN'),
      'nin' => array('set' => 'handleList', 'sql' => 'handleListSql', 'symbol' => 'NOT IN'),
      'is' => array('set' => 'handleIs', 'sql' => 'handleValSql', 'symbol' => '='), // =
      'ne' => array('set' => 'handleVal', 'sql' => 'handleValSql', 'symbol' => '!='), // !=
      'gt' => array('set' => 'handleVal', 'sql' => 'handleValSql', 'symbol' => '>'), // >
      'gte' => array('set' => 'handleVal', 'sql' => 'handleValSql', 'symbol' => '>='), // >=
      'lt' => array('set' => 'handleVal', 'sql' => 'handleValSql', 'symbol' => '<'), // <
      'lte' => array('set' => 'handleVal', 'sql' => 'handleValSql', 'symbol' => '<='), // <=
      'sort' => array('set' => 'handleSort', 'sql' => 'handleSortSql'),
      'asc' => array('set' => 'handleSortAsc', 'sql' => 'handleSortSql'),
      'desc' => array('set' => 'handleSortDesc', 'sql' => 'handleSortSql'), 
      'between' => array('set' => 'handleBetween', 'sql' => ''), // between is handled by >= and <= sql handlers
      'inc' => array('set' => 'handleAtomic', 'sql' => ''),
      'set' => array('set' => 'handleAtomic', 'sql' => '')
    );
    
    $this->set($map_criteria);
    
  }//method

  /**
   *  build a valid criteria map for making a mongo db call, this can be done in
   *  one of 2 ways, you can put the field in the method, or pass it in as the first
   *  param...
   *  
   *  find any 'foo' row that has 1,2,3,4 in it: $instance->inFoo(1,2,3,4);
   *  or $instance->in('foo',1,2,3,4);
   *  
   *  if you want to find 'foo' using a string: $instance->inField('foo',1,2,3,4)
   *      
   *  when you want to get the actual maps, just call {@link get()}                     
   *
   *  @param  string  $method the method that was called
   *  @param  array $args the params passed into the method
   */
  function __call($method,$args){
  
    list($command,$field,$args) = $this->splitMethod($method,$args);
    
    if(isset($this->method_map[$command])){
    
      $callback = $this->method_map[$command]['set'];
      $this->{$callback}($command,$field,$args);
    
    }else{
    
      throw new mingo_exception(sprintf('unknown command: %s',$command));
    
    }//if/else
  
    return true;
  
  }//method
  
  /**
   *  get rid of the internally set criteria maps
   */
  function reset(){
    $this->map_criteria = array();
    $this->map_sort = array();
  }//method
  
  /**
   *  get the criterias created in this instance
   *  
   *  this returns both the criteria and the sort criteria
   *  
   *  @example  list($c,$sort_c) = $mc->get();         
   *  
   *  @return array array($criteria,$sort_criteria);            
   */
  function get(){ return array($this->map_criteria,$this->map_sort); }//method
  
  /**
   *  convert an array criteria into an instance of this
   *
   *  @param  array $map_criteria the criteria that should be handled internally
   */
  function set($map_criteria){
    
    // canary...
    if(empty($map_criteria)){ $map_criteria = array(); }//if
    if(!is_array($map_criteria)){ throw new mingo_exception('$map_criteria isn\'t an array'); }//if
  
    $this->map_criteria = $map_criteria;
    
  }//method
  
  /**
   *  true if this criteria instance isn't empty
   *
   *  @return boolean
   */
  function has(){ return !empty($this->map_criteria) || !empty($this->map_sort); }//method
  
  /**
   *  convert the internal criteria into SQL
   *  
   *  the sql is suitable to be used in PDO, and so the string has ? where each value
   *  should go, the value array will correspond to each of the ?      
   *      
   *  @return array an array map with 'where_str', 'where_val', and 'sort_str' keys set      
   */
  function getSql(){
  
    $ret_map = array();
    $ret_map['where_str'] = ''; $ret_map[0] = &$ret_map['where_str'];
    $ret_map['where_val'] = array(); $ret_map[1] = &$ret_map['where_val'];
    $ret_map['sort_str'] = array(); $ret_map[2] = &$ret_map['sort_str'];
  
    $ret_where = $ret_sort = '';
  
    list($criteria_where,$criteria_sort) = $this->get();
  
    foreach($criteria_where as $name => $map){
    
      // we only deal with non-command names right now for sql...
      if($name[0] != $this->command_symbol){
      
        $where_sql = '';
        $where_val = array();
      
        if(is_array($map)){
        
          $total_map = count($map);
        
          // go through each map val and append it to the sql string...
          foreach($map as $command => $val){
    
            if($command[0] == $this->command_symbol){
            
              $command_bare = mb_substr($command,1);
              $command_sql = '';
              $command_val = array();
            
              // build the sql...
              if(isset($this->method_map[$command_bare])){
              
                if(!empty($this->method_map[$command_bare]['sql'])){
                
                  $callback = $this->method_map[$command_bare]['sql'];
                  list($command_sql,$command_val) = $this->{$callback}(
                    $this->method_map[$command_bare]['symbol'],
                    $name,
                    $map[$command]
                  );
                  
                  list($where_sql,$where_val) = $this->appendSql(
                    'AND',
                    $command_sql,
                    $command_val,
                    $where_sql,
                    $where_val
                  );
                  
                }//if
              
              }//if
            
            }else{
            
              // @todo  throw an error, there shouldn't ever be an array value outside a command
              throw new mingo_exception(
                'there is an error in your criteria, this happens when you pass in an array to '
                .'the constructor, maybe try generating your criteria using the object\'s methods '
                .'and not passing in an array.'
              );
            
            }//if/else
            
          }//foreach
          
          if($total_map > 1){ $where_sql = sprintf(' (%s)',$where_sql); }//if
        
        }else{
        
          // we have a NAME=VAL (an is* method call)...
          list($where_sql,$where_val) = $this->handleValSql('=',$name,$map);
        
        }//if/else
        
        list($ret_map['where_str'],$ret_map['where_val']) = $this->appendSql(
          'AND',
          $where_sql,
          $where_val,
          $ret_map['where_str'],
          $ret_map['where_val']
        );
      
      }//if
    
    }//foreach
  
    if(!empty($ret_map['where_val'])){
      $ret_map['where_str'] = sprintf('WHERE%s',$ret_map['where_str']);
    }//if
    
    // build the sort sql...
    foreach($criteria_sort as $name => $direction){
    
      $dir_sql = ($direction > 0) ? 'ASC' : 'DESC';
      if(empty($ret_map['sort_sql'])){
        $ret_map['sort_str'] = sprintf('ORDER BY %s %s',$name,$dir_sql);
      }else{
        $ret_map['sort_str'] = sprintf('%s,%s %s',$ret_map['sort_sql'],$name,$dir_sql);
      }//if/else
    
    }//foreach

    return $ret_map;
  
  }//method
  
  /**
   *  handle sql'ing a generic list: NAME SYMBOL (...)
   *  
   *  @param  string  $symbol the symbol to use in the sQL string
   *  @param  string  $name the name of the field      
   *  @param  array $args a list of values that $name will be in         
   *  @return array array($sql,$val_list);
   */
  protected function handleListSql($symbol,$name,$args){
  
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
  protected function handleValSql($symbol,$name,$arg){
  
    $ret_str = sprintf(' %s %s ?',$name,$symbol);
    return array($ret_str,$arg);
  
  }//method
  
  /**
   *  handle atomic operations
   *  
   *  @link http://www.mongodb.org/display/DOCS/Atomic+Operations      
   *  
   *  @param  string  $name the field name
   *  @param  array $args only $args[0] is used
   */
  protected function handleAtomic($command,$name,$args){
  
    // canary...
    if(empty($args)){
      throw new mingo_exception(sprintf('%s must have a value, none given',$command));
    }//if
  
    $command_full = $this->getCommand($command);
  
    if(!isset($this->map_criteria[$command_full])){
      $this->map_criteria[$command_full] = array();
    }//if

    $this->map_criteria[$command_full][$name] = $args[0];
    
  }//method
  
  /**
   *  handle a between, basically, you're looking for a value >= $args[0] and <= $args[1]
   *  
   *  @param  string  $name the field name
   *  @param  array $args should have 2 indexes        
   */
  protected function handleBetween($command,$name,$args){
  
    // canary...
    if(empty($args)){
      throw new mingo_exception(sprintf('%s must have a low and high value, none given',$command));
    }//if
    if(!isset($args[1])){
      throw new mingo_exception(sprintf('%s must have a high value, only low value given',$command));
    }//if
  
    $this->map_criteria[$name] = array(
      $this->getCommand('gte') => $args[0],
      $this->getCommand('lte') => $args[1]
    );
    
  }//method
  
  /**
   *  handle the most basic map: $name => $val
   *  
   *  @param  string  $name the field name
   *  @param  array $args only $arg[0] is used         
   */
  protected function handleSort($command,$name,$args){
  
    // canary...
    if(empty($args)){
      throw new mingo_exception(
        sprintf('%s must have either %s::DESC or %s::ASC passed in',$command,__CLASS__,__CLASS__)
      );
    }//if
    
    $this->map_sort[$name] = ($args[0] > 0) ? self::ASC : self::DESC;
  
  }//method
  
  /**
   *  makes sort a little easier by allowing $this->ascFIELD_NAME instead of
   *  $this->sortFIELD_NAME(self::ASC)
   *  
   *  @see  handleSort()
   */
  protected function handleSortAsc($command,$name,$args){
    return $this->handleSort($command,$name,array(self::ASC));
  }//method
  
  /**
   *  makes sort a little easier by allowing $this->descFIELD_NAME instead of
   *  $this->sortFIELD_NAME(self::DESC)
   *  
   *  @see  handleSort()
   */
  protected function handleSortDesc($command,$name,$args){
    return $this->handleSort($command,$name,array(self::DESC));
  }//method
  
  /**
   *  handle the most basic map: $name => $val
   *  
   *  @param  string  $name the field name
   *  @param  array $args only $arg[0] is used         
   */
  protected function handleIs($command,$name,$args){
  
    // canary...
    if(empty($args)){
      throw new mingo_exception(sprintf('%s must have a passed in value, none given',$command));
    }//if
  
    $this->map_criteria[$name] = $args[0];
  
  }//method
  
  /**
   *  handle a generic val type command, basically $name => array($command => $val)
   *  
   *  @param  string  $name the field name
   *  @param  array $args only $arg[0] is used         
   */
  protected function handleVal($command,$name,$args){
  
    // canary...
    if(empty($args)){
      throw new mingo_exception(sprintf('%s must have a passed in value, none given',$command));
    }//if
  
    $this->map_criteria[$name] = $this->getMap($command,$args[0]);
  
  }//method
  
  /**
   *  handle a generic list type command, basically $name => array($command => $list)
   *  
   *  @param  string  $name the field name
   *  @param  array $args a list of values that $name will be in         
   *
   */        
  protected function handleList($command,$name,$args){
  
    // get the in values...
    $in_list = array();
    foreach($args as $arg){
    
      if(is_array($arg)){
      
        $in_list = array_merge($in_list,$arg);
      
      }else{
      
        $in_list[] = $arg;
      
      }//if/else
    
    }//foreach
  
    $this->map_criteria[$name] = $this->getMap($command,$in_list);
  
  }//method
  
  
  private function getMap($command,$val){
    $val = $this->normalizeVal($val);
    return array($this->getCommand($command) => $val);
  }//method
  
  private function getCommand($command){
    return sprintf('%s%s',$this->command_symbol,$command);
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
  private function appendSql($separator,$new_sql,$new_val,$old_sql,$old_val){
  
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
