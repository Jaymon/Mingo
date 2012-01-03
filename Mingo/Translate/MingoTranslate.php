<?php

/**
 *  holds methods to convert MingoCriteria objects to other formats for querying  
 * 
 *  @version 0.1
 *  @author Jay Marcyes
 *  @since 01-2-2012
 *  @package mingo 
 ******************************************************************************/
class MingoTranslate {

  /**
   *  hold the table instance
   *  
   *  @var  \MingoTable
   */
  protected $table = null;

  /**
   *  hold the criteria instance
   *  
   *  @var  \MingoCriteria
   */
  protected $criteria = null;

  /**
   *  create new instance
   *  
   *  @param  \MingoTable $table   
   *  @param  \MingoCriteria  $criteria
   */
  public function __construct(MingoTable $table,MingoCriteria $criteria = null){
  
    $this->table = $table;
    
    if(!empty($criteria)){
      
      $this->criteria = $criteria;
      $criteria->normalizeFields($table);
      
    }//if
    
    ///$this->translate();
  
  }//method
  
  /**
   *  get the internal criteria instance
   *  
   *  @return \MingoCriteria
   */
  public function getCriteria(){ return $this->criteria; }//method
  
  /**
   *  get the internal table instance
   *  
   *  @return \MingoTable
   */
  public function getTable(){ return $this->table; }//method
  
  /**
   *  get the translated value
   *
   *  @return mixed whatever the interface needs to query
   */
  ///public protected function getQuery(){ return $this->getCriteria(); }//method

  /**
   *  this will do the actual conversion from one format to the other
   *
   *  @return void
   */
  protected function translate(){}//method

}//class   
