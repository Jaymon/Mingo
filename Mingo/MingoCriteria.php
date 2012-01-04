<?php

/**
 *  creates a standard criteria for querying a mingo db
 *  
 *  it's up to the db's interface to convert this criteria into something the 
 *  interface's backend can use.  
 *
 *  allow an easy way to define most of the advanced queries defined here:
 *  http://www.mongodb.org/display/DOCS/Advanced+Queries#AdvancedQueries-{{group()}}
 *  http://www.mongodb.org/display/DOCS/Sorting   
 *  
 *  For example, find any 'foo' row that has 1,2,3,4 in it: $this->inFoo(1,2,3,4);
 *  or if you want to find 'foo' using a variable: $foo->inField('foo',1,2,3,4). Check
 *  the method map for all the method prefixes (commands) you can do, (eg, lt for <, lte for <=)
 *  
 *  @version 0.6
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 11-17-09
 *  @package mingo 
 ******************************************************************************/
class MingoCriteria extends MingoMagic {

  /**
   *  used for sort ascending
   *  
   *  @var  integer      
   */        
  const ASC = 1;
  
  /**
   *  used for sort descending
   *  
   *  @var  integer      
   */
  const DESC = -1;

  /**
   *  by default, this is the same command symbol Mongo uses by default, this is
   *  used internally to decide between commands, names, and values. Basically, if
   *  it starts with this symbol its a special command      
   *  
   *  @var  string
   *  @see  getCommandSymbol()
   */
  protected $command_symbol = '$';
  
  /**
   *  holds the internal structure of the "WHERE" criteria. While you can set this 
   *  using {@link setWhere()} it would be better to use the magic methods that 
   *  {@link __call()} defines since that will guarantee a valid where criteria array
   *  
   *  @see  getWhere(), setWhere(), hasWhere()   
   *  @var  array               
   */
  protected $map_where = array();
  
  /**
   *  is for the "SORT BY..." part of a query
   *  
   *  this can only be set with the sort|asc|desc magic methods
   *  
   *  @see  getSort(), setSort(), hasSort()   
   *  @var  array
   */
  protected $map_sort = array();
  
  /**
   *  holds the limit, page information
   *
   *  @see  getBounds(), setBounds(), hasBounds()   
   *  @var  array
   */  
  protected $map_bounds = array();
  
  /**
   *  merge one MingoCriteria instance into this MingoCriteria instance
   *  
   *  @since  1-3-12      
   *  @param  \MingoCriteria  $criteria the criteria to merge into this one
   *  @return self
   */
  public function merge(MingoCriteria $criteria){
  
    $this->command_symbol = $criteria->getCommandSymbol();
    $this->map_where = $criteria->getWhere();
    $this->map_sort = $criteria->getSort();
    $this->field_map = $criteria->getFields();
    
    if($criteria->hasLimit()){ $this->setLimit($criteria->getLimit()); }//if
    if($criteria->hasPage()){ $this->setPage($criteria->getPage()); }//if
    if($criteria->hasOffset()){ $this->setOffset($criteria->getOffset()); }//if
    
    return $this;
  
  }//method
  
  /**
   *  $name = $val type queries
   *     
   *  @param  string  $name the field name
   *  @param  mixed $val
   *  @return MingoCriteria
   */
  public function setField($name,$val){ return $this->setWhereVal($name,'',$val); }//method
  public function isField($name,$val){ return $this->setField($name,$val); }//method
  
  /**
   *  handle whether a field is set or not in where or sort
   *  
   *  @param  string  $name the field name
   *  @param  array $args currently ignored
   *  @return boolean   
   */
  public function hasField($name){
    $normalized_name = $this->normalizeName($name);
    return isset($this->map_where[$normalized_name]) || isset($this->map_sort[$normalized_name]);
  }//method
  
  /**
   *  handle a spatial call
   *  
   *  the form is taken from: http://www.mongodb.org/display/DOCS/Geospatial+Indexing
   *           
   *  @since  10-18-10
   *  @param  string  $name the field name
   *  @param  array $point  a latitude and longitude point
   *  @param  integer $distance the radius around the point
   *  @return MingoCriteria   
   */
  public function nearField($name,$point,$distance){
  
    // canary, make sure the point is valid...
    $field = new MingoField();
    $field->setType(MingoField::TYPE_POINT);
    $point = $field->normalizeInVal($point);
  
    $near_command = $this->normalizeCommand('near');
  
    $val = array(
      $near_command => $point,
      $this->normalizeCommand('maxDistance') => (int)$distance
    );
    
    return $this->setWhereVal($name,'',$val,array($near_command));
    
  }//method
  
  /**
   *  $name >= $low AND $name <= $high
   *
   *  @param  string  $name the field name
   *  @param  array $val
   *  @return MingoCriteria   
   */        
  public function betweenField($name,$low,$high){
  
    $gte_command = $this->normalizeCommand('gte');
    $lte_command = $this->normalizeCommand('lte');
  
    $val = array(
      $gte_command => $low,
      $lte_command => $high
    );
  
    return $this->setWhereVal($name,'',$val,array($gte_command,$lte_command));
    
  }//method
  
  /**
   *  $name <= $val
   *
   *  @param  string  $name the field name
   *  @param  array $val
   *  @return MingoCriteria   
   */        
  public function lteField($name,$val){
    return $this->setWhereVal($name,'lte',$val);
  }//method
  
  /**
   *  $name < $val
   *
   *  @param  string  $name the field name
   *  @param  array $val
   *  @return MingoCriteria   
   */        
  public function ltField($name,$val){
    return $this->setWhereVal($name,'lt',$val);
  }//method
  
  /**
   *  $name >= $val
   *
   *  @param  string  $name the field name
   *  @param  array $val
   *  @return MingoCriteria   
   */        
  public function gteField($name,$val){
    return $this->setWhereVal($name,'gte',$val);
  }//method
  
  /**
   *  $name > $val
   *
   *  @param  string  $name the field name
   *  @param  array $val
   *  @return MingoCriteria   
   */        
  public function gtField($name,$val){
    return $this->setWhereVal($name,'gt',$val);
  }//method
  
  /**
   *  $name IN ($list) type queries
   *
   *  @param  string  $name the field name
   *  @param  array $val
   *  @return MingoCriteria   
   */        
  public function inField($name){
    $val = func_get_args();
    $val = array_slice($val,1); // strip off the name
    $val_list = $this->flattenList($val);
    return $this->setWhereVal($name,'in',$val_list,array_keys($val_list));
  }//method
  
  /**
   *  $name NOT IN ($list) type queries
   *
   *  @param  string  $name the field name
   *  @param  array $val
   *  @return MingoCriteria   
   */        
  public function ninField($name){
    $val = func_get_args();
    $val = array_slice($val,1); // strip off the name
    $val_list = $this->flattenList($val);
    return $this->setWhereVal($name,'nin',$val_list,array_keys($val_list));
  }//method
  
  /**
   *  $name != $val
   *
   *  @param  string  $name the field name
   *  @param  array $val
   *  @return MingoCriteria   
   */        
  public function notField($name,$val){
    return $this->setWhereVal($name,'not',$val);
  }//method
  
  /**
   *  handle the most basic map: $name => $val
   *  
   *  @param  string  $name the field name
   *  @param  integer $direction  which way to sort        
   */
  public function sortField($name,$direction){
    
    $normalized_name = $this->normalizeName($name);
    $this->map_sort[$normalized_name] = ($direction > 0) ? self::ASC : self::DESC;
    
    return $this;
    
  }//method
  
  /**
   *  makes sort a little easier by allowing $this->ascFIELD_NAME instead of
   *  $this->sortFIELD_NAME(self::ASC)
   *  
   *  @see  sortField()
   */
  public function ascField($name){ return $this->sortField($name,self::ASC); }//method
  
  /**
   *  makes sort a little easier by allowing $this->descFIELD_NAME instead of
   *  $this->sortFIELD_NAME(self::DESC)
   *  
   *  @see  sortField()
   */
  public function descField($name){ return $this->sortField($name,self::DESC); }//method
  
  /**
   *  get rid of the internally set criteria maps
   */
  public function reset(){
    $this->map_where = array();
    $this->map_sort = array();
    $this->map_bounds = array();
  }//method
  
  /**
   *  how field names are separated from commands is because commands start with
   *  a command symbol
   *  
   *  this method will return the given raw command (ie, the command with the prefixed
   *  command symbol stripped off)
   *  
   *  @param  string  $command  the command to be stripped of the command symbol
   *  @return string  the raw command without the command symbol
   */
  public function getCommand($command){
  
    // canary
    if(!$this->isCommand($command)){ return $command; }//if
  
    return mb_substr($command,1);
    
  }//method
  
  /**
   *  how field names are separated from commands is because commands start with
   *  a command symbol
   *  
   *  this method will return the full given command (ie, the command with the prefixed
   *  command symbol)
   *  
   *  @since  12-31-11 this method was originall getCommand(), but that one was changed   
   *  @param  string  $command  the command to be built
   *  @return string  the full command
   */
  public function normalizeCommand($command){
  
    // canary
    if($this->isCommand($command)){ return $command; }//if
  
    return sprintf('%s%s',$this->getCommandSymbol(),$command);
    
  }//method
  
  /**
   *  return the command symbol
   *
   *  @return string   
   */
  public function getCommandSymbol(){ return $this->command_symbol; }//method
  
  /**
   *  true if the passed in val is a command
   * 
   *  @since  5-23-11    
   *  @param  string  $val
   *  @return boolean
   */
  public function isCommand($val){ return ($val[0] === $this->getCommandSymbol()); }//method

  public function getWhere(){ return $this->map_where; }//method
  public function hasWhere(){ return !empty($this->map_where); }//method
  public function setWhere(array $map){
    
    $this->map_where = $map;
    return $this;
  }//method
  
  public function getSort(){ return $this->map_sort; }//method
  public function hasSort(){ return !empty($this->map_sort); }//method
  public function setSort(array $map){
    
    $this->map_sort = $map;
    return $this;
  }//method
  public function killSort(){ return $this->setSort(array()); }//method
  
  /**
   *  set the limit
   *  
   *  @param  integer
   */
  public function setLimit($val){ $this->map_bounds['limit'] = (int)$val; }//method
  public function getLimit(){ return empty($this->map_bounds['limit']) ? 0 : $this->map_bounds['limit']; }//method
  public function hasLimit(){ return !empty($this->map_bounds['limit']); }//method
  
  /**
   *  set the page that will be used to calculate the offset
   *  
   *  a set offset will take precedence over the page
   *      
   *  @param  integer
   */
  public function setPage($val){
    unset($this->map_bounds['offset']);
    $this->map_bounds['page'] = (int)$val;
  }//method
  public function getPage(){ return empty($this->map_bounds['page']) ? 0 : $this->map_bounds['page']; }//method
  public function hasPage(){ return !empty($this->map_bounds['page']); }//method
  public function existsPage(){ return isset($this->map_bounds['page']); }//method
  
  /**
   *  set the page that will be used to calculate the offset
   *  
   *  this takes precedence over the page
   *      
   *  @since  2-1-11   
   *  @param  integer
   */
  public function setOffset($val){
    unset($this->map_bounds['page']);
    $this->map_bounds['offset'] = (int)$val;
  }//method
  public function getOffset(){ return empty($this->map_bounds['offset']) ? 0 : $this->map_bounds['offset']; }//method
  public function hasOffset(){ return !empty($this->map_bounds['offset']); }//method
  public function existsOffset(){ return isset($this->map_bounds['offset']); }//method
  
  /**
   *  takes either 2 values or an array to set the bounds (ie, limit and page) for
   *  the query
   * 
   *  if you want to set a limit and an offset (instead of the page) then you will need
   *  to call setLimit() and setOffset() separately, as this method only provides a shortcut
   *  for limit and page      
   *      
   *  @see  setLimit(), setPage()  
   *  @param  integer|array $limit  if an array, then array($limit,$page)
   *  @param  integer $page what page to use
   */
  public function setBounds($limit,$page = 0){
  
    if(is_array($limit)){
      
      $this->setLimit(empty($limit[0]) ? 0 : $limit[0]);
      $this->setPage(empty($limit[1]) ? $page : $limit[1]);
      
    }else{
    
      $this->setLimit($limit);
      $this->setPage($page);
      
    }//if/else
    
  }//method
  
  /**
   *  this ones a little tricky because while setBounds takes a limit and a page
   *  this method returns a limit and an offset, and a limit_paginate (limit + 1 for
   *  finding out if there are more rows in the db)
   *  
   *  @return array array($limit,$offset,$limit_paginate)
   */
  public function getBounds(){
    
    $limit = $offset = $limit_paginate = 0;
    
    if($this->hasLimit()){
      
      $limit = $this->getLimit();
      
      // get rows + 1 to test if there are more results in the db for pagination...
      $limit_paginate = ($limit + 1);
      
    }//method
    
    if($this->existsOffset()){
    
      $offset = $this->getOffset();
    
    }else{
      
      if($this->hasPage()){
        $page = $this->getPage();
        $offset = ($page - 1) * $limit;
      }//if
      
    }//if/else

    return array($limit,$offset,$limit_paginate);
    
  }//method
  
  public function hasBounds(){
    return !empty($this->map_bounds);
  }//method
  
  /**
   *  using the $table normalize the fields to make sure the values are what they should be
   *  to query against the table   
   *
   *  @since  5-7-11
   *  @param  MingoTable  $table   
   */
  public function normalizeFields(MingoTable $table){
  
    foreach($this->field_map as $name => $val_list){
    
      if($table->hasField($name)){
      
        $field = $table->getField($name);
      
        foreach(array_keys($val_list) as $key){
          
          $val_list[$key] = $field->normalizeInVal($val_list[$key]);
        
        }//foreach
        
      }//if
    
    }//foreach
  
  }//method
  
  /**
   *  set a $val at $name using the $command in the {@link $where_map}  
   *
   *  @since  4-29-11   
   *  @param  string  $name the field name to use
   *  @param  string  $command  the command that wil be used to set the value into {@link $where_map}
   *  @param  mixed $val  the value that will be set into the {@link $where_map}
   *  @param  array $val_keys the keys in $val that refer to actual passed in values for the $name,
   *                          this will be an array like array($lt,$gt), and sometimes it
   *                          might correspond to $command (eg, $command = $gt and $val_keys = array($gt))        
   *  @return MingoCriteria
   */
  protected function setWhereVal($name,$command,$val,$val_keys = array()){
  
    $normalized_name = $this->normalizeName($name);
    
    // get the references to the value ready...
    if(!isset($this->field_map[$normalized_name])){
      $this->field_map[$normalized_name] = array();
    }//if
    
    if(empty($command)){
    
      $this->map_where[$normalized_name] = $val;

      // set the references to the actual values, basically, it holds a key (field name) with
      // an array of all the values of that field in the criteria, allows methods like 
      // normalizeFields() to work
      if(empty($val_keys)){
        $this->field_map[$normalized_name][] = &$this->map_where[$normalized_name];
      }else{
        foreach($val_keys as $val_key){
          $this->field_map[$normalized_name][] = &$this->map_where[$normalized_name][$val_key];
        }//foreach
      }//if/else
    
    }else{
    
      $normalized_command = $this->normalizeCommand($command);
      $this->map_where[$normalized_name] = array($normalized_command => $val);
      
      // set the references to the actual values, basically, it holds a key (field name) with
      // an array of all the values of that field in the criteria, allows methods like 
      // normalizeFields() to work
      if(empty($val_keys)){
        $this->field_map[$normalized_name][] = &$this->map_where[$normalized_name][$normalized_command];
      }else{
        foreach($val_keys as $val_key){
          $this->field_map[$normalized_name][] = &$this->map_where[$normalized_name][$normalized_command][$val_key];
        }//foreach
      }//if/else
    
    }//if/else
    
    return $this;
    
  }//method

}//class     
