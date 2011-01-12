<?php
/** 
 *  handle command line stuff
 * 
 *  @version 0.1
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 6-8-10
 *  @package cli_tools
 ******************************************************************************/
class cli_in
{
  protected $input = '';
  
  protected $is_done = false;
  
  protected $cmd_map = array();

  protected $db = null;
  
  protected $query_list = array();

  /**
   *  hold the stdin resource to get user input
   *      
   *  @var  resource
   */
  protected $stdin = null;

  public function __construct(mingo_db $db){
  
    // open an input connection to get lines from the user...
    $this->stdin = fopen('php://stdin','r');
  
    $this->db = $db;
  
  }//method
  
  public function reset(){
  
    $this->input = '';
    $this->setDone(false);
  
  }//method
  
  public function getLine(){
  
    $line = rtrim(fgets($this->stdin));
    $this->append($line);
    
  }//method
  
  protected function append($line){
  
    if(!empty($line)){
    
      // decide if this is the end of the command...
      $end_of_line_count = 0;
      $line = preg_replace('#(\;|\\[gG])$#','',$line,1,$end_of_line_count);
      $this->setDone($end_of_line_count > 0);
      $this->input .= sprintf('%s%s',$line,PHP_EOL);
      
    }//if
  
  }//method
  
  protected function SetDone($val){ $this->is_done = (boolean)$val; }//method
  public function isDone(){ return $this->is_done; }//method
  
  public function hasInput(){ return !empty($this->input); }//method
  
  public function get(){ return $this->input; }//if
  
  public function getCommand(){
    return empty($this->cmd_map['command']) ? self::CMD_NONE : $this->cmd_map['command'];
  }//if

  public function handle(){
  
    // canary...
    if(empty($this->input)){ return false; }//if
    
    $ret_mix = null;
    
    $input = trim($this->input);
    $timestamp_start = microtime(true); 
    $matched = array();
    
    $this->query_list[] = $input;
    
    // do we have an exit...
    if(preg_match('#^(?:exit|quit)#i',$input)){
    
      throw new cli_stop_exception();
    
    }else if(preg_match('#^(?:help|h)$#i',$input)){
    
      $ret_mix = array();
      $ret_mix[] = 'HELP - Brings up this menu';
      $ret_mix[] = 'SHOW TABLES - show all the available Mingo tables';
      $ret_mix[] = 'SHOW INDEXES FROM <table_name> - show all the indexes of "table_name"';
      $ret_mix[] = 'DROP TABLE <table_name> - delete "table_name" from Mingo';
      $ret_mix[] = 'SHOW QUERIES - Show all the queries executed on Mingo during this session.';
      $ret_mix[] = 'SELECT ... - Follow ANSI SQL guidelines for SELECT queries. No joins are supported!';
      $ret_mix[] = 'DELETE FROM <table_name> WHERE ... - Follow ANSI SQL guidelines for DELETE queries.';
      
    }else if(preg_match('#^(?:show|get)\s+tables$#i',$input)){
    
      $ret_mix = $this->db->getTables();
      
    }else if(preg_match('#^(?:show|get)\s+index(?:es)?\s+from\s+(.+)$#i',$input,$matched)){
    
      $ret_mix = $this->db->getIndexes($matched[1]);
      foreach($ret_mix as $key => $map){
        $ret_mix[$key] = sprintf('[%s]',join(', ',array_keys($map)));
      }//foreach
    
    }else if(preg_match('#^drop\s+table\s+(.+)$#i',$input,$matched)){
    
      $ret_mix = $this->db->killTable($matched[1]);
    
    }else if(preg_match('#^(?:show|get)\s+queries$#i',$input)){
    
      $ret_mix = $this->query_list;
    
    }else{
    
      ///$this->parseSelect($input);
      ///out::x();
    
      $sql_parser = new parse_sql($input,'Mingo');
      $parse_map = $sql_parser->parse();
      
      if(isset($parse_map['table_names'][1])){
      
        throw new RangeException(
          sprintf(
            'there are no joins so you can only query one table at a time, you queried on tables: [%s]',
            join(',',$parse_map['table_names'])
          )
        );
      
      }//if
      
      $table = $parse_map['table_names'][0];
      $where_criteria = new mingo_criteria();
      
      out::e($parse_map); out::x();
      
      switch($parse_map['command']){
      
        case 'select':
        
          $is_count = false;
          if(!empty($parse_map['set_function'][0]['name'])){
          
            $is_count = ($parse_map['set_function'][0]['name'] === 'count');
            
          }//if
          
          if(!empty($parse_map['where_clause'])){
          
            $where_criteria = $this->handleWhere($where_criteria,$parse_map['where_clause']);
            
          }//if
          
          if(!empty($parse_map['limit_clause'])){
          
            $where_criteria->setLimit($parse_map['limit_clause']['length']);
            if(!empty($parse_map['limit_clause']['start'])){
              $where_criteria->setPage(
                (int)($parse_map['limit_clause']['start'] / $parse_map['limit_clause']['length'])
              );
            }//if
            
          }//if
          
          if(!empty($parse_map['sort_order'])){
          
            foreach($parse_map['sort_order'] as $field => $order){
            
              $where_criteria->sortField($field,($order === 'asc') ? mingo_criteria::ASC : mingo_criteria::DESC);
            
            }//foreach
            
          }//if
          
          $schema = $this->getSchema($table);
          
          if($is_count){
            $ret_mix = sprintf(
              'count: %s',
              $this->db->getCount($table,$schema,$where_criteria,$where_criteria->getBounds())
            );
          }else{
            $ret_mix = $this->db->get($table,$schema,$where_criteria,$where_criteria->getBounds());
          }//if/else
          
          break;
          
        case 'delete':
        
          if(!empty($parse_map['where_clause'])){
          
            $where_criteria = $this->handleWhere($where_criteria,$parse_map['where_clause']);
            
          }//if
          
          $schema = $this->getSchema($table);
          
          $ret_mix = sprintf(
            'Query Ok, rows affected: %s',
            $this->db->getCount($table,$schema,$where_criteria)
          );
          
          // now actually delete...
          $ret_bool = $this->db->kill($table,$schema,$where_criteria);
          if(empty($ret_bool))
          {
            $ret_mix = 'Query failed';
          }//if
          
          break;
          
        default:
        
          throw new UnexpectedValueException('Unsupported Query type');
          break;
      
      }//switch
    
    }//if/else
    
    $timestamp_stop = microtime(true);
  
    // return the out object so we can echo out the results...
    $ret_instance = new cli_out($ret_mix,$timestamp_start,$timestamp_stop);
  
    return $ret_instance;
  
  }//method
  
  /**
   *  get the table's schema
   *  
   *  @since  1-12-11
   *  @param  string  $table  the table whose schema should be created
   *  @return mingo_schema
   */
  protected function getSchema($table)
  {
    $schema = new mingo_schema();
    foreach($this->db->getIndexes($table) as $index_map){
      
      // the _id can't be in the index...
      if(isset($index_map[mingo_orm::_ID])){
        unset($index_map[mingo_orm::_ID]);
      }//if
      
      if(!empty($index_map)){
        $schema->setIndex($index_map);
      }//if
      
    }//method
  
    return $schema;
  
  }//method
  
  protected function handleWhere($c,$where_clause){
  
    // canary...
    if(empty($where_clause)){ return $c; }//if
    
    // we need to check arg_1, op, and arg_2, recursing when necessary...
    $arg_1 = $where_clause['arg_1'];
    $op = $where_clause['op'];
    $arg_2 = $where_clause['arg_2'];
    
    if($op === 'and'){
    
      $c = $this->handleWhere($c,$arg_1);
      $c = $this->handleWhere($c,$arg_2);
    
    }else if($op === 'or'){
    
      throw new UnexpectedValueException('only AND is supported, no OR is allowed');
    
    }else{
    
      // this maps the sql symbol to the mingo_orm method that will be used
      $symbol_list = array(
        '=' => 'isField',
        '!=' => 'notField',
        '>' => 'gtField',
        '>=' => 'gteField',
        '<' => 'ltField',
        '<=' => 'lteField',
        'in' => 'inField',
        'not\s+in' => 'ninField'
      );
    
      $symbol_found = false;  
      foreach($symbol_list as $symbol => $method){
      
        if(preg_match(sprintf('#^%s$#i',$symbol),$op)){
        
          call_user_func(array($c,$method),$arg_1['value'],$arg_2['value']);
          $symbol_found = true;
          break;
          
        }//if
        
      }//foreach
      
      if(!$symbol_found){
      
        throw new UnexpectedValueException('Unsupported operator');
      
      }//if
    
    }//if/else
    
    
    return $c;
    
  }//method
  
  function __destruct(){
  
    fclose($this->stdin);
  
  }//method

}//class
