<?php
/** 
 *  handle command line stuff
 * 
 *  @version 0.1
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 6-8-10
 *  @package cli_tools
 ******************************************************************************/
class CliIn
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

  public function __construct(MingoInterface $db){
  
    // open an input connection to get lines from the user...
    $this->stdin = fopen('php://stdin','r');
  
    $this->db = $db;
  
  }//method
  
  public function reset(){
  
    $this->input = '';
    $this->setDone(false);
  
  }//method
  
  public function getLine(){
  
    $prompt = $this->hasInput() ? '    -> ' : 'mingo> ';
  
    if($this->isWindows()){
  
      echo $prompt;
      $line = rtrim(fgets($this->stdin));
      
    }else{
    
      // http://www.php.net/manual/en/book.readline.php
      $line = readline($prompt);
    
    }//if/else
      
    $this->append($line);
    
  }//method
  
  protected function append($line){
  
    if(!empty($line)){
    
      // decide if this is the end of the command...
      $end_of_line_count = 0;
      $line = preg_replace('#(\;|\\\\[gG])$#','',$line,1,$end_of_line_count);
      $this->setDone(($end_of_line_count > 0) || preg_match('#^(?:exit|quit)#',$line));
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
    
    // we need to specifically set the history if we are in linux...
    if(!$this->isWindows()){
    
      $line = str_replace(PHP_EOL,' ',$input);
      $line .= ';';
      readline_add_history($line);
    
    }//if
    
    // do we have an exit...
    if(preg_match('#^(?:exit|quit)#i',$input)){

      throw new CliStopException();
    
    }else if(preg_match('#^(?:help|h)$#i',$input)){
    
      $ret_mix = array();
      $ret_mix[] = 'HELP - Brings up this menu';
      $ret_mix[] = 'SHOW TABLES - show all the available Mingo tables';
      $ret_mix[] = 'SHOW INDEXES FROM <table_name> - show all the indexes of "table_name"';
      $ret_mix[] = 'DROP TABLE <table_name> - delete "table_name" from Mingo';
      $ret_mix[] = 'SHOW QUERIES - Show all the queries executed on Mingo during this session.';
      $ret_mix[] = 'SELECT ... - Follow ANSI SQL guidelines for SELECT queries. No joins are supported!';
      $ret_mix[] = 'DELETE FROM <table_name> WHERE ... - delete from the "table_name"';
      $ret_mix[] = 'INSERT INTO <table_name> (<field_1>, <field_2>, ...) VALUES (<val_1>, <val_2>,  ...) - insert a new row into "table_name"';
      $ret_mix[] = 'UPDATE <table_name> SET <field_1>=<val_1>, <field_2>=<val_2>, ... WHERE ... - update rows in "table_name"';
      
    }else if(preg_match('#^(?:show|get)\s+tables$#i',$input)){
    
      $ret_mix = $this->db->getTables();
      
    }else if(preg_match('#^(?:show|get)\s+index(?:es)?\s+from\s+(.+)$#i',$input,$matched)){
    
      $ret_mix = $this->db->getIndexes($matched[1]);
      foreach($ret_mix as $key => $map){
        $ret_mix[$key] = sprintf('[%s]',join(', ',array_keys($map)));
      }//foreach
    
    }else if(preg_match('#^drop\s+table\s+(.+)$#i',$input,$matched)){
    
      $ret_mix = $this->db->killTable($this->getTable($matched[1]));
    
    }else if(preg_match('#^(?:show|get)\s+queries$#i',$input)){
    
      $ret_mix = $this->query_list;
    
    }else{
    
      ///$this->parseSelect($input);
      ///out::x();
    
      $sql_parser = new MingoParseSQL($input,'Mingo');
      $parse_map = $sql_parser->parse();
      
      if(isset($parse_map['table_names'][1])){
      
        throw new RangeException(
          sprintf(
            'there are no joins so you can only query one table at a time, you queried on tables: [%s]',
            join(',',$parse_map['table_names'])
          )
        );
      
      }//if
      
      $table_name = $parse_map['table_names'][0];
      $where_criteria = new MingoCriteria();
      
      ///out::e($parse_map); out::x();
      
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
          
            // compensate for bug in the SQL parser...
          
            if($parse_map['limit_clause']['start'] === 0){
            
              // start is the offset and length is the limit if start is 0...
              $where_criteria->setLimit($parse_map['limit_clause']['length']);
              $where_criteria->setPage(0);
            
            }else{
            
              // start is the limit and length is the offset if start > 0
            
              $where_criteria->setLimit($parse_map['limit_clause']['start']);
              $where_criteria->setOffset($parse_map['limit_clause']['length']);
            
            }//if/else
            
          }//if
          
          if(!empty($parse_map['sort_order'])){
          
            foreach($parse_map['sort_order'] as $field => $order){
            
              $where_criteria->sortField($field,($order === 'asc') ? MingoCriteria::ASC : MingoCriteria::DESC);
            
            }//foreach
            
          }//if
          
          $table = $this->getTable($table_name);
          
          if($is_count){
            $ret_mix = sprintf(
              'count: %s',
              $this->db->getCount($table,$where_criteria)
            );
          }else{
            $ret_mix = $this->db->get($table,$where_criteria);
          }//if/else
          
          break;
          
        case 'delete':
        
          if(!empty($parse_map['where_clause'])){
          
            $where_criteria = $this->handleWhere($where_criteria,$parse_map['where_clause']);
            
          }//if
          
          $table = $this->getTable($table_name);
          
          $ret_mix = sprintf(
            'Query Ok, %s rows affected',
            $this->db->getCount($table,$where_criteria)
          );
          
          // now actually delete...
          $ret_bool = $this->db->kill($table,$where_criteria,true);
          if(empty($ret_bool))
          {
            $ret_mix = 'Query failed';
          }//if
          
          break;
          
        case 'insert':
        
          $map = $this->handleMap($parse_map);
          $table = $this->getTable($table_name);
          $map = $this->db->set($table,$map);
        
          $ret_mix = sprintf(
            'Query Ok, new row inserted with _id %s',
            $map['_id']
          );
        
          break;
          
        case 'update':
        
          if(empty($parse_map['where_clause'])){
            throw new RuntimeException('no WHERE clause in your UPDATE statement');
          }//if
        
          $map = $this->handleMap($parse_map);
          $table = $this->getTable($table_name);
          
          $where_criteria = $this->handleWhere($where_criteria,$parse_map['where_clause']);
          
          // iterate through each row and update it...
          $count = 0;
          
          $list = $this->db->get($table,$where_criteria);
          foreach($list as $result_map){
          
            $map = array_merge($result_map,$map);
            $map = $this->db->set($table,$map);
            $count++;
          
          }//foreach
          
          $ret_mix = sprintf(
            'Query Ok, %s rows affected',
            $count
          );
        
          break;
          
        default:
        
          throw new UnexpectedValueException('Unsupported Query type');
          break;
      
      }//switch
    
    }//if/else
    
    $timestamp_stop = microtime(true);
  
    // return the out object so we can echo out the results...
    $ret_instance = new CliOut($ret_mix,$timestamp_start,$timestamp_stop);
  
    return $ret_instance;
  
  }//method
  
  /**
   *  get the table's schema
   *  
   *  @since  1-12-11
   *  @param  string  $table_name  the table whose schema should be created
   *  @return mingo_schema
   */
  protected function getTable($table_name)
  {
    $table = new MingoTable($table_name);
    foreach($this->db->getIndexes($table) as $index_map){
      
      // the _id can't be in the index...
      if(isset($index_map[MingoOrm::_ID])){
        unset($index_map[MingoOrm::_ID]);
      }//if
      
      // the row_id can't be in the index...
      if(isset($index_map[MingoOrm::ROW_ID])){
        unset($index_map[MingoOrm::ROW_ID]);
      }//if
      
      if(!empty($index_map)){
        $table->setIndex($index_map);
      }//if
      
    }//method
  
    return $table;
  
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
  
  /**
   *  builds the map for, as an example, the INSERT and UPDATE queries
   * 
   *  @since  1-12-11    
   *  @param  array $parse_map  the parsed values
   *  @return array the fields maped to their values
   */
  protected function handleMap(array $parse_map){
  
    // canary...
    $field_count = count($parse_map['column_names']);
    $val_count = count($parse_map['values']);
    if($field_count !== $val_count)
    {
      throw new RuntimeException(
        sprintf(
          'You need the same number of fields as values, you currently have %s fields and %s values',
          $field_count,
          $val_count
        )
      );
    
    }//if
  
    $map = array();
    foreach($parse_map['column_names'] as $key => $field){
    
      // @todo  add support for array [] and object/map {} types, I guess these would
      // be strings that will need to be json encoded.
    
      $val = null;
      switch($parse_map['values'][$key]['type']){
      
        case 'int_val':
        
          $val = (int)$parse_map['values'][$key]['value'];
      
        case 'text_val':
        default:
        
          $val = $parse_map['values'][$key]['value'];
          break;
      
      }//switch
    
      $map[$field] = $val;
    
    }//foreach
  
    // canary, some things can't be set by the user...
    if(isset($map['_id'])){
      throw new UnexpectedValueException('cannot set "_id" manually');
    }//if
    if(isset($map['row_id'])){
      throw new UnexpectedValueException('cannot set "row_id" manually');
    }//if
  
    return $map;
  
  }//method
  
  /**
   *  true if php is running on a Windows OS
   *  
   *  @return boolean
   */
  protected function isWindows()
  {
    return (substr(PHP_OS,0,3) === 'WIN');
  }//method
  
  function __destruct(){
  
    fclose($this->stdin);
  
  }//method

}//class
