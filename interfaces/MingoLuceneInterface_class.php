<?php

/**
 *  mingo interface for interacting with Lucene
 *  
 *
 *  @link http://framework.zend.com/manual/en/zend.search.lucene.html 
 *  @link http://wiki.apache.org/lucene-java/LuceneFAQ
 *  @link http://en.wikipedia.org/wiki/Lucene 
 *   
 *  @version 0.1
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 5-2-11
 *  @package mingo 
 ******************************************************************************/
class MingoLuceneInterface extends MingoInterface {

  /**#@+
   *  options, MaxMergeDocs, MergeFactor, MaxBufferedDocs, check ZEND_SEARCH_LUCENE for what these do
   *  
   *  these are set to lucene in {@link open()}, so if you want to set their values, do it before open()
   *  is called   
   *  
   *  look here for some stuff on what these vals do {@link http://framework.zend.com/manual/en/zend.search.lucene.best-practice.html}      
   *
   */
  public $max_merged_docs = 5000;
  public $merge_factor = 10;
  public $max_buffered_docs = 10;
  /**#@-*/

  /**
   *  do the actual connecting of the interface
   *
   *  @see  connect()   
   *  @return boolean
   */
  protected function _connect($name,$host,$username,$password,array $options){
  
    // we need Zend Lucene in order to work...
    if(!class_exists('Zend_Search_Lucene',true)){
      throw new UnexpectedValueException(
        '"Zend_Search_Lucene" cannot be found, is the ZF path in the included paths?'
      );
    }//if
    
    $path = $name;
    
    // make sure path doesn't end with a slash...
    $path_last_char = mb_substr($path,-1);
    if(($path_last_char === DIRECTORY_SEPARATOR) || ($path_last_char === '/')){
      $path = mb_substr($path,0,-1);
    }//if
    
    $this->setField('path',$path);
    
    return true;
  
  }//method
  
  /**
   *  @see  getTables()
   *  
   *  @param  MingoTable  $table  
   *  @return array
   */
  protected function _getTables(MingoTable $table = null){
  
    // canary...
    ///if($table !== null){ return array($table->getName()); }//if
  
    $path[] = $this->getField('path');
    $path[] = empty($table) ? '*' : $table->getName();
    
    $ret_list = array_map('basename',glob(join(DIRECTORY_SEPARATOR,$path),GLOB_ONLYDIR));
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
  
    $lucene = $this->getLucene($table);
    return count($lucene->find($where_criteria['query']));
  
  }//method
  
  /**
   *  @see  get()
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  mixed $where_criteria the where criteria ran through {@link normalizeCriteria())      
   *  @return array   
   */
  protected function _get($table,$where_criteria){

    $lucene = $this->getLucene($table);
    
    // set limit...
    if(!empty($where_criteria['limit'][0])){
      Zend_Search_Lucene::setResultSetLimit($where_criteria['limit'][0]);
    }//if

    $list = $lucene->find($where_criteria['query']);
    
    // get rid of the offset that was added to limit...
    if(!empty($where_criteria['limit'][1])){
      $list = array_slice($list,$where_criteria['limit'][1]);
    }//if
    
    foreach($list as $i => $hit){
    
      $list[$i] = $this->getMap($hit->getDocument()->getFieldValue('body'));
    
    }//foreach

    return $list;

  }//method
  
  /**
   *  @see  kill()
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  mixed $where_criteria the where criteria ran through {@link normalizeCriteria())      
   *  @return boolean
   */
  protected function _kill($table,$where_criteria){
  
    
  
  }//method
  
  /**
   *  @see  getQuery()
   *  @param  mixed $query  a query the interface can understand
   *  @param  array $options  any options for this query
   *  @return mixed      
   */
  protected function _getQuery($query,array $options = array()){
  
  }//method
  
  /**
   *  insert $map into $table
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  array  $map  the key/value map that will be added to $table  
   *  @return array the $map that was just saved, with the _id set               
   */
  protected function insert($table,array $map){
    
    $map['_id'] = $this->getUniqueId($table);
    $document = $this->normalizeMap($table,$map);
    
    $lucene = $this->getLucene($table);
    $precount = $lucene->count();
    $lucene->addDocument($document);
    $lucene->commit(); // http://framework.zend.com/manual/en/zend.search.lucene.advanced.html
    $postcount = $lucene->count();
    
    if($postcount <= $precount){
      throw new UnexpectedValueException(
        sprintf('Document was not added. Precount: %s, postcount: %s',$precount,$postcount)
      );
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

  }//method
  
  /**
   *  @see  setIndex()
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  mixed $index  an index ran through {@link normalizeIndex()}
   *  @return boolean
   */
  protected function _setIndex($table,$index){
    return true;
  }//method
  
  /**
   *  @see  getIndexes()
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}      
   *  @return array
   */
  protected function _getIndexes($table){
  
    // everything in lucene is an index, so just return whatever the table has...
    return $table->getIndexes();
  
  }//method
  
  /**
   *  @see  killTable()
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}      
   *  @return boolean
   */
  protected function _killTable($table){

    $table_name = $table->getName();
    if(isset($this->con_db[$table_name])){
      $this->con_db[$table_name] = null;
      unset($this->con_db[$table_name]);
    }//if
  
    ///$lucene = $this->getLucene($table);
    ///$lucene = null;
    ///unset($lucene);
  
    return $this->removePath(join(DIRECTORY_SEPARATOR,array($this->getField('path'),$table)));
    
  }//method

  /**
   *  @see  setTable()
   *      
   *  @param  MingoTable  $table       
   *  @return boolean
   */
  protected function _setTable(MingoTable $table){

    /* if(!is_writeable($this->getField('path'))){
      throw new UnexpectedValueException(sprintf('"%s" is not writeable',$this->getField('path')));
    }//if */

    $lucene = $this->getLucene($table);
    return ($lucene instanceof Zend_Search_Lucene_Proxy);

  }//method

  /**
   *  get the actual lucene instance for the $table
   *  
   *  @param  string  $table
   *  @return Zend_Search_Lucene_Proxy         
   */
  protected function getLucene(MingoTable $table){
  
    $table_name = $table->getName();
    // canary...
    if(isset($this->con_db[$table_name])){ return $this->con_db[$table_name]; }//if
  
    $ret_instance = null;
    $path = array();
    $path[] = $this->getField('path');
    $path[] = $table_name;
    $index_path = join(DIRECTORY_SEPARATOR,$path);
    
    // install the index if it doesn't already exist...
    if(is_dir($index_path)){

      $ret_instance = Zend_Search_Lucene::open($index_path);  
    
    }else{
    
      $ret_instance = Zend_Search_Lucene::create($index_path);
    
    }//if/else
  
    if($ret_instance !== null){
        
      // set some values...
      /*
      Matlin on #phpc turned me onto MaxMergeDocs, MergeFactor, MaxBufferedDocs as probably
      the cause for the weird http 500 timeout issues I have when adding to the index
      */
      $ret_instance->setMaxMergeDocs((int)$this->max_merged_docs);
      $ret_instance->setMaxBufferedDocs((int)$this->max_buffered_docs);
      $ret_instance->setMergeFactor((int)$this->merge_factor);
      
      // treat numbers like words, don't worry about case 
      // see: http://framework.zend.com/manual/en/zend.search.lucene.charset.html 38.6.2....
      Zend_Search_Lucene_Analysis_Analyzer::setDefault(
        new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive()
      );
      
      // default to and, instead of or, when there is no operator...
      // http://framework.zend.com/manual/en/zend.search.lucene.query-language.html#zend.search.lucene.query-language.boolean.no-operator
      Zend_Search_Lucene_Search_QueryParser::setDefaultOperator(
        Zend_Search_Lucene_Search_QueryParser::B_AND
      );
      
    }else{
    
      throw new UnexpectedValueException('could not create Lucene connection');
    
    }//if/else
  
    $this->con_db[$table_name] = $ret_instance;
    return $ret_instance;
  
  }//method
  
  /**
   *  converts the map into a document so Lucene can save it
   *
   *  @param  MingoTable  $table
   *  @param  array $map  the raw map passed to {@link insert()} or {@link update()}      
   *  @return Zend_Search_Lucene_Document
   */           
  protected function normalizeMap(MingoTable $table,array $map){
  
    $document = new Zend_Search_Lucene_Document();
    
    // add some fields that will be present in all documents...
    $document->addField(Zend_Search_Lucene_Field::unStored('_id',$map['_id'],$this->charset));
    $document->addField(Zend_Search_Lucene_Field::binary('body',$this->getBody($map)));
    
    // add all the indexes into the document...
    foreach($table->getIndexes() as $index){
    
      foreach($index as $name => $options){
      
        // use array_key... to account for null values...
        if(array_key_exists($name,$map)){
      
          $document->addField(Zend_Search_Lucene_Field::UnStored($name,$map[$name],$this->charset));  
        
          // let's not try and add it twice...
          unset($map[$name]);
        
        }//if
        
      }//foreach
    
    }//foreach
  
    return $document;
  
  }//method
  
  /**
   *  this should be used to take the generic $where_criteria and turn it into something
   *  the interface can use (eg, for a SQL interface, the $where_criteria would be turned
   *  into a valid SQL string).
   *  
   *  currently not supported: 'near'
   *      
   *  @param  MingoTable  $table    
   *  @param  MingoCriteria $where_criteria   
   *  @return mixed return whatever you want, however you want to return it, whatever is easiest for you
   */
  protected function normalizeCriteria(MingoTable $table,MingoCriteria $where_criteria = null){
    
    $ret_map = array();
    $ret_map['where_criteria'] = $where_criteria;
    
    $query = new Zend_Search_Lucene_Search_Query_Boolean();
    
    // add all the required search keys and their values...
    $criteria_where = $where_criteria->getWhere();
  
    foreach($criteria_where as $name => $map){
    
      $where_sql = '';
      $where_val = array();
    
      if(is_array($map)){
      
        $command = $where_criteria->getCommand('in');
        if(isset($map[$command])){
          $subquery = $this->handleMulti($name,$map[$command],true);
        }//if 
      
        $command = $where_criteria->getCommand('nin');
        if(isset($map[$command])){
          $subquery = $this->handleMulti($name,$map[$command],false);
        }//if
        
        $command = $where_criteria->getCommand('not');
        if(isset($map[$command])){
          $subquery = $this->handleEquals($name,$map[$command],false);
        }//if
        
        $command1 = $where_criteria->getCommand('gte');
        $command2 = $where_criteria->getCommand('lte');
        if(isset($map[$command1]) && isset($map[$command2])){
          $subquery = $this->handleRange($name,$map[$command1],$map[$command2],true);
        }//if
        
        if(isset($map[$command1])){
          $subquery = $this->handleRange($name,$map[$command1],null,true);
        }//if
      
        if(isset($map[$command2])){
          $subquery = $this->handleRange($name,null,$map[$command2],true);
        }//if
        
        $command = $where_criteria->getCommand('gt');
        if(isset($map[$command])){
          $subquery = $this->handleRange($name,$map[$command],null,false);
        }//if
      
        $command = $where_criteria->getCommand('lt');
        if(isset($map[$command])){
          $subquery = $this->handleRange($name,null,$map[$command],false);
        }//if
        
      }else{
      
        if($name === '_q'){
        
          $subquery = Zend_Search_Lucene_Search_QueryParser::parse($map);
        
        }else{
      
          $subquery = $this->handleEquals($name,$map,true);
          
        }//if/else
      
      }//if/else
      
      $query->addSubquery($subquery,true);
      
    }//foreach
    
    $ret_map['query'] = $query;
    
    // caution from docs:
    // Please use caution when using a non-default search order; 
    // the query needs to retrieve documents completely from an index, 
    // which may dramatically reduce search performance.
    // http://framework.zend.com/manual/en/zend.search.lucene.searching.html#zend.search.lucene.searching.sorting
    
    /*
    $criteria_sort = $where_criteria->getSort();
    
    // build the sort sql...
    foreach($criteria_sort as $name => $direction){
    
      $dir_sql = ($direction > 0) ? 'ASC' : 'DESC';
      if(empty($ret_map['sort_sql'])){
        $ret_map['sort_str'] = sprintf('ORDER BY %s %s',$name,$dir_sql);
      }else{
        $ret_map['sort_str'] = sprintf('%s,%s %s',$ret_map['sort_sql'],$name,$dir_sql);
      }//if/else
    
    }//foreach
    */
    
    // limit offset...
    $offset = $where_criteria->getOffset();
    $ret_map['limit'] = array(
      $where_criteria->getLimit() + $offset,
      $offset
    );
    
    return $ret_map;
    
  }//method
  
  /**
   *  
   *  @param  mixed $from pass in null to ignore the from value (>,>=)
   *  @param  mixed $from pass in null to ignore the to value (<, <=)
   *  @param  boolean $inclusive  true to make this a (>= or <=) and false to just do (>,<)
   *  @return Zend_Search_Lucene_Search_Query_Range
   */
  protected function handleRange($name,$from_val,$to_val,$inclusive){
  
    // canary...
    if(($from_val === null) && ($to_val === null)){
      throw new InvalidArgumentException('$from and $to are both null');
    }//if
  
    $from = $to = null;
    if($from_val !== null){
      $from = new Zend_Search_Lucene_Index_Term($from_val,$name);
    }//if
    if($to_val !== null){
      $to = new Zend_Search_Lucene_Index_Term($to_val,$name);
    }//if
  
    $subquery = new Zend_Search_Lucene_Search_Query_Range($from,$to,$inclusive);
    return $subquery;
  
  }//method
  
  protected function handleEquals($name,$val,$required){
  
    $subquery = new Zend_Search_Lucene_Search_Query_Term(
      new Zend_Search_Lucene_Index_Term($val,$name),
      $required
    );
    
    return $subquery;
  
  }//method
  
  protected function handleMulti($name,$val,$required){
  
    $subquery = new Zend_Search_Lucene_Search_Query_MultiTerm();
              
    foreach($val as $v){
      $subquery->addTerm(
        new Zend_Search_Lucene_Index_Term($v,$name),
        $required
      );
    }//foreach
  
    return $subquery;
  
  }//method
  
  /**
   *  get the body that is the key/val pairs that will go in the body field of the table
   *  
   *  I zlib compress: http://www.php.net/manual/en/ref.zlib.php
   *  Not really sure why except that Friendfeed does it, and I don't want to be different         
   *
   *  between version .1 and .2 this changed from json to serialize because of the
   *  associative arrays becoming stdObjects problem
   *      
   *  @param  array $map  the key/value pairings
   *  @return string  a zlib compressed json encoded string
   */
  protected function getBody(array $map){
  
    // get rid of table stuff...
    ///if(isset($map['row_id'])){ unset($map['row_id']); }//if
    ///if(isset($map['_id'])){ unset($map['_id']); }//if
    
    return gzcompress(serialize($map));
  
  }//method
  
  /**
   *  opposite of {@link getBody()}
   *  
   *  between version .1 and .2 this changed from json to serialize because of the
   *  associative arrays becoming stdObjects problem   
   *      
   *  @param  string  $body the getBody() compressed string, probably returned from a db call
   *  @return array the key/value pairs restored to their former glory
   */
  protected function getMap($body){
    return unserialize(gzuncompress($body));
  }//method
  
  /**
   *  recursively clear an entire directory, files, folders, everything
   *  
   *  @since  8-25-10   
   *  @param  string  $path the starting path, all sub things will be removed
   */
  protected function removePath($path){
  
    // canary...
    if(empty($path)){ throw new InvalidArgumentException('$path was empty'); }//if
    if(!is_dir($path)){ return true; }//if
  
    $ret_bool = true;
    $path_iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
    foreach($path_iterator as $file){
      
      $file_path = $file->getRealPath();
      if($file->isDir()){
        
        if($this->clearPath($file_path)){ $ret_bool = rmdir($file_path); }//if
      
      }else{
    
        $ret_bool = unlink($file_path);
        
      }//if/else

    }//foreach
    
    // get rid of the passed in path...
    $ret_bool = rmdir($path);
    return $ret_bool;
    
  }//method
  
}//class     


