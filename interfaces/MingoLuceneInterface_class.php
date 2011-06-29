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

    // set limit, 0 is unlimited...
    return count($this->find($table['lucene'],$where_criteria));
    
  }//method
  
  /**
   *  @see  get()
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  mixed $where_criteria the where criteria ran through {@link normalizeCriteria())      
   *  @return array   
   */
  protected function _get($table,$where_criteria){

    // set limit, 0 is unlimited...
    Zend_Search_Lucene::setResultSetLimit($where_criteria['limit'][0]);

    $list = $this->find($table['lucene'],$where_criteria);
    
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
  
    $list = $this->find($table['lucene'],$where_criteria);
    
    foreach($list as $i => $hit){
      $table['lucene']->delete($hit->id);
    }//foreach
  
    return true;
  
  }//method
  
  /**
   *  @see  getQuery()
   *  @param  mixed $query  a query the interface can understand
   *  @param  array $options  any options for this query
   *  @return mixed      
   */
  protected function _getQuery($query,array $options = array()){
  
    $query = Zend_Search_Lucene_Search_QueryParser::parse($query);
    $table = $this->normalizeTable($options['table']);
    
    $where_criteria = array();
    $where_criteria['query'] = $query;
    $where_criteria['limit'] = array(0,0);
    
    return $this->find($table['lucene'],$where_criteria);
  
  }//method
  
  /**
   *  insert $map into $table
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}
   *  @param  array  $map  the key/value map that will be added to $table  
   *  @return array the $map that was just saved, with the _id set               
   */
  protected function insert($table,array $map){
    
    if(empty($map['_id'])){ $map['_id'] = $this->getUniqueId(); }//if
    $document = $this->normalizeMap($table['table'],$map);

    $precount = $table['lucene']->numDocs();
    ///$precount = $table['lucene']->count();
    
    $table['lucene']->addDocument($document);
    $table['lucene']->commit(); // http://framework.zend.com/manual/en/zend.search.lucene.advanced.html
    ///$postcount = $table['lucene']->count();
    $postcount = $table['lucene']->numDocs();
    
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

    // delete the old as per:
    // http://framework.zend.com/manual/en/zend.search.lucene.best-practice.html#zend.search.lucene.best-practice.unique-id
    // http://framework.zend.com/manual/en/zend.search.lucene.index-creation.html#zend.search.lucene.index-creation.document-updating
    $term = new Zend_Search_Lucene_Index_Term($_id,'_id');
    $id_list  = $table['lucene']->termDocs($term);
    foreach($id_list as $id){
      $table['lucene']->delete($id);
    }//foreach

    $map['_id'] = $_id;
    return $this->insert($table,$map);

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
    return $table['table']->getIndexes();
  
  }//method
  
  /**
   *  @see  killTable()
   *  
   *  @param  mixed $table  the table ran through {@link normalizeTable()}      
   *  @return boolean
   */
  protected function _killTable($table){
  
    $table_name = $table['table']->getName();
    if(isset($this->con_db[$table_name])){
      $this->con_db[$table_name]->__destruct();
      unset($this->con_db[$table_name]);
    }//if

    return $this->removePath(join(DIRECTORY_SEPARATOR,array($this->getField('path'),$table_name)));
    
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

    try{
      
      // install the index if it doesn't already exist...
      if(is_dir($index_path)){
  
        $ret_instance = Zend_Search_Lucene::open($index_path);
      
      }else{
      
        $ret_instance = Zend_Search_Lucene::create($index_path);
      
      }//if/else
      
    }catch(Zend_Search_Lucene_Exception $e){
    
      clearstatcache();
      usleep(10);
      $ret_instance = Zend_Search_Lucene::create($index_path);
      
    }//try/catch 
  
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
    $document->addField(Zend_Search_Lucene_Field::unStored('_id',$map['_id']));
    $document->addField(Zend_Search_Lucene_Field::binary('body',$this->getBody($map)));
    
    // add all the indexes into the document...
    foreach($table->getIndexes() as $index){
    
      foreach($index as $name => $options){
      
        // use array_key... to account for null values...
        if(array_key_exists($name,$map)){
      
          $val = null;
          if(is_array($map[$name])){
            $val = join(' ',$map[$name]);
          }else{
            $val = $map[$name];
          }//if/else
      
          $document->addField(Zend_Search_Lucene_Field::UnStored($name,$val));  
        
          // let's not try and add it twice...
          unset($map[$name]);
        
        }//if
        
      }//foreach
    
    }//foreach

    return $document;
  
  }//method
  
  /**
   *  turn the table into something the interface can understand
   *  
   *  @param  MingoTable  $table 
   *  @return mixed return whatever you want, however you want to return it, whatever is easiest for you
   */
  protected function normalizeTable(MingoTable $table){
    
    $ret_map = array();
    $ret_map['lucene'] = $this->getLucene($table);
    $ret_map['table'] = $table;
  
    return $ret_map;
    
  }//method
  
  /**
   *  this should be used to take the generic $where_criteria and turn it into something
   *  the interface can use (eg, for a SQL interface, the $where_criteria would be turned
   *  into a valid SQL string).
   *  
   *  currently not supported: 'near'
   *      
   *  @link http://lucene.apache.org/java/2_4_0/queryparsersyntax.html
   *      
   *  @param  MingoTable  $table    
   *  @param  MingoCriteria $where_criteria   
   *  @return mixed return whatever you want, however you want to return it, whatever is easiest for you
   */
  protected function normalizeCriteria(MingoTable $table,MingoCriteria $where_criteria = null){
    
    $ret_map = array();
    $ret_map['where_criteria'] = $where_criteria;
    $ret_map['limit'] = array(0,0);
    
    $query = new Zend_Search_Lucene_Search_Query_Boolean();
    
    if($where_criteria !== null){
      
      // add all the required search keys and their values...
      $criteria_where = $where_criteria->getWhere();
    
      foreach($criteria_where as $name => $map){
      
        $where_sql = '';
        $where_val = array();
        $required = true;
      
        if(is_array($map)){
        
          $command = $where_criteria->getCommand('in');
          if(isset($map[$command])){
            $subquery = $this->handleMulti($name,$map[$command],true);
            $required = true;
          }//if 
        
          // according to: http://lucene.apache.org/java/2_4_0/queryparsersyntax.html
          // Lucene cannot do nin queries without something before it (eg, foo:1 NOT foo(2 3) but
          // (NOT foo(2 3) doesn't work)
          $command = $where_criteria->getCommand('nin');
          if(isset($map[$command])){
            $subquery = $this->handleMulti($name,$map[$command],false);
            $required = false;
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
        
        $query->addSubquery($subquery,$required);
        
      }//foreach
      
      // limit offset...
      $offset = $where_criteria->getOffset();
      $ret_map['limit'] = array($where_criteria->getLimit() + $offset,$offset);
      
      // caution from docs:
      // Please use caution when using a non-default search order; 
      // the query needs to retrieve documents completely from an index, 
      // which may dramatically reduce search performance.
      // http://framework.zend.com/manual/en/zend.search.lucene.searching.html#zend.search.lucene.searching.sorting
      if($where_criteria->hasSort()){
        
        $criteria_sort = $where_criteria->getSort();
        $sort_list = array();
        
        // build the sort sql...
        foreach($criteria_sort as $name => $direction){
        
          $sort_list[] = $name;
          $sort_list[] = SORT_REGULAR;
          ///$sort_list[] = SORT_STRING; ///SORT_NUMERIC;
          $sort_list[] = ($direction > 0) ? SORT_ASC : SORT_DESC;
        
        }//foreach
        
        $ret_map['sort'] = $sort_list;
        
      }//if
      
    }//if
    
    $ret_map['query'] = $query;
    
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
  
    /* $subquery = new Zend_Search_Lucene_Search_Query_MultiTerm();
              
    foreach($val as $v){
      $subquery->addTerm(
        new Zend_Search_Lucene_Index_Term($v,$name)
        ///$required
      );
    }//foreach */
    
    $subquery = new Zend_Search_Lucene_Search_Query_Boolean();
    foreach($val as $v){
      $subquery->addSubquery(
        new Zend_Search_Lucene_Search_Query_Term(
          new Zend_Search_Lucene_Index_Term($v,$name)
          ///$required
        )
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
  protected function getBody(array $map){ return gzcompress(serialize($map)); }//method
  
  /**
   *  opposite of {@link getBody()}
   *  
   *  between version .1 and .2 this changed from json to serialize because of the
   *  associative arrays becoming stdObjects problem   
   *      
   *  @param  string  $body the getBody() compressed string, probably returned from a db call
   *  @return array the key/value pairs restored to their former glory
   */
  protected function getMap($body){ return unserialize(gzuncompress($body)); }//method
  
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
  
  /**
   *
   *  @since  5-24-11
   */        
  protected function find(Zend_Search_Lucene_Proxy $lucene,array $where_criteria){
  
    $ret_list = array();
    Zend_Search_Lucene::setResultSetLimit($where_criteria['limit'][0]);
    
    if(empty($where_criteria['sort'])){
    
      $ret_list = $lucene->find($where_criteria['query']);
    
    }else{
    
      // http://framework.zend.com/manual/en/zend.search.lucene.searching.html#zend.search.lucene.searching.sorting
      $args = $where_criteria['sort'];
      array_unshift($args,$where_criteria['query']);
      
      out::e($args);
      
      $ret_list = call_user_func_array(array($lucene,'find'),$args);
    
    }//if/else
    
    return $lucene->find($where_criteria['query']);
  
  }//method
  
}//class     


