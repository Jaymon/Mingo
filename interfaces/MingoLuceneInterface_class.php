<?php

/**
 *  mingo interface for interacting with Lucene
 *  
 *  @version 0.1
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 5-2-11
 *  @package mingo 
 ******************************************************************************/
class MingoLuceneInterface extends MingoInterface {

  /**
   *  everything is utf-8, I'm not even giving people a choice
   */        
  protected $charset = 'utf-8';

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

  public function __construct(){
    
    parent::__construct();
  
  }//method

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
    $path_last_char = mb_substr($path,-1);
    
    // make sure path doesn't end with a slash...
    if(($path_last_char === DIRECTORY_SEPARATOR) || ($path_last_char === '/')){
      $path = mb_substr($path,0,-1);
    }//if
    
    $this->setField('path',$path);
    
    return true;
    
  }//method
  
  /**
   *  @see  getTables()
   *  @return array
   */
  protected function _getTables($table = ''){
  
    $path[] = $this->getField('path');
    $path[] = empty($table) ? '*' : $table;
    
    return glob(join(DIRECTORY_SEPARATOR,$path),GLOB_ONLYDIR);
  
  }//method
  
  /**
   *  @see  getCount()   
   *  @return integer the count
   */
  protected function _getCount($table,MingoSchema $schema,MingoCriteria $where_criteria = null,array $limit){
  
  }//method
  
  /**
   *  @see  get()
   *  @return array   
   */
  protected function _get($table,MingoSchema $schema,MingoCriteria $where_criteria = null,array $limit){

  }//method
  
  /**
   *  @see  getOne()
   *  @return array
   */
  protected function _getOne($table,MingoSchema $schema,MingoCriteria $where_criteria = null){
  
  }//method
  
  /**
   *  @see  kill()
   *  @return boolean
   */
  protected function _kill($table,MingoSchema $schema,MingoCriteria $where_criteria){
  
  }//method
  
  /**
   *  insert $map into $table
   *  
   *  @param  string  $table  the table name
   *  @param  array|mingo_criteria  $map  the key/value map that will be added to $table
   *  @param  MingoSchema $schema the table schema   
   *  @return array the $map that was just saved, with the _id set               
   */
  protected function insert($table,array $map,MingoSchema $schema){
    
    $map['_id'] = $this->getUniqueId($table);
    $document = $this->normalizeMap($map,$schema);
    
    $lucene = $this->getLucene();
    $lucene->addDocument($document);
    
    return $map;
    
  }//method
  
  /**
   *  update $map from $table using $_id
   *  
   *  @param  string  $table  the table name
   *  @param  string  $_id the _id attribute from $map   
   *  @param  array $map  the key/value map that will be added to $table
   *  @param  MingoSchema $schema the table schema      
   *  @return array the $map that was just saved with _id set
   */
  protected function update($table,$_id,array $map,MingoSchema $schema){

  }//method
  
  /**
   *  @see  setIndex()
   *  
   *  @param  string  $table
   *  @param  mixed $index  an index this interface understands
   *  @param  MingoSchema $schema   
   *  @return boolean
   */
  protected function _setIndex($table,$index,MingoSchema $schema){
    
  }//method
  
  /**
   *  @see  getIndexes()
   *  @return array
   */
  protected function _getIndexes($table){
  
  }//method
  
  /**
   *  @see  killTable()
   *  @return boolean
   */
  protected function _killTable($table){

  }//method
  
  /**
   *  adds a table to the db
   *  
   *  http://www.mongodb.org/display/DOCS/Capped+Collections
   *      
   *  @see  setTable()   
   *  @return boolean
   */
  protected function _setTable($table,MingoSchema $schema){

  }//method
  
  /**
   *  @see  handleException()
   *  @return boolean false on failure to solve the exception, true if $e was successfully resolved
   */
  protected function _handleException(Exception $e,$table,MingoSchema $schema){
    
  }//method
  
  /**
   *  this should be used to take the generic $where_criteria and turn it into something
   *  the interface can use (eg, for a SQL interface, the $where_criteria would be turned
   *  into a valid SQL string).
   *  
   *  @param  MingoCriteria $where_criteria
   *  @return mixed return whatever you want, however you want to return it, whatever is easiest for you
   */
  protected function normalizeCriteria(MingoCriteria $where_criteria){
  
  }//method
  
  /**
   *  convert an array index map into something this interface understands
   *
   *  @since  5-2-11
   *  @return mixed whatever this interface will understand
   */
  protected function normalizeIndex(array $index_map,MingoSchema $schema){
    return $index_map;
  }//method
  
  /**
   *  get the actual lucene instance for the $table
   *  
   *  @param  string  $table
   *  @return Zend_Search_Lucene_Proxy         
   */
  protected function getLucene($table){
  
    $ret_instance = null;
    $path = array();
    $path[] = $this->getPath();
    $path[] = $table;
    $index_path = join(DIRECTORY_SEPARATOR,$path);
    
    // install the index if it doesn't already exist...
    if(file_exists($index)){
    
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
      $ret_instance->setMaxMergeDocs(intval($this->max_merged_docs));
      $ret_instance->setMaxBufferedDocs(intval($this->max_buffered_docs));
      $ret_instance->setMergeFactor(intval($this->merge_factor));
      
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
      
    }//if
  
    return $ret_instance;
  
  }//method
  
  protected function normalizeMap(array $map,MingoSchema $schema){
  
    $document = new Zend_Search_Lucene_Document();
    
    // add some fields that will be present in all documents...
    $document->addField(Zend_Search_Lucene_Field::unIndexed('_id',$map['_id'],$this->charset));
    $document->addField(Zend_Search_Lucene_Field::binary('body',$this->getBody($map)));
    
    // add all the indexes into the document...
    foreach($schema->getIndexes() as $index){
    
      foreach($index as $name => $options){
      
        // use array_key... to account for null values...
        if(array_key_exists($name,$map)){
      
          $document->addField(Zend_Search_Lucene_Field::UnIndexed($name,$map[$name],$this->charset));  
        
          // let's not try and add it twice...
          unset($map[$name]);
        
        }//if
        
      }//foreach
    
    }//foreach
  
    return $document;
  
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
    if(isset($map['row_id'])){ unset($map['row_id']); }//if
    if(isset($map['_id'])){ unset($map['_id']); }//if
    
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
  
}//class     

