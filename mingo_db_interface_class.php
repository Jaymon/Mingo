<?php

/**
 *  base class for all the interfaces that mingo_db can use   
 *
 *  if you want to make an interface for mingo, just create a class that extends
 *  this class and implement all the functions below.
 *  
 *  @notes
 *    - when implementing the interface, you don't really have to worry about error checking
 *      because mingo_db will do all the error checking before calling the functions from this
 *      class
 *    - throw any exception you want but mingo_db will catch any exceptions from all the abstract 
 *      methods and wrap them in a mingo_exception if they aren't already a mingo_exception.
 *    - in php 5.3 you can set default values for any of the abstract method params without
 *      an error being thrown, in php <5.3 the implemented method signatures have to match
 *      the abstract signature exactly 
 *    - there are certain reserved rows any implementation will have to deal with:
 *      - _id = the unique id assigned to a newly inserted row, this is a 24 character
 *              randomly created string, if you don't want to make your own, and there
 *              isn't an included one (like mongo) then you can use {@link getUniqueId()}
 *              defined in this class
 *      - row_id = this is an auto increment row, ie, the row number. This technically only
 *                 needs to be generated when the backend supports it and is set up (mongo ignores
 *                 it, mysql and sqlite set it) 
 *  
 *  @link http://www.php.net/manual/en/language.oop5.abstract.php
 *  
 *  @abstract 
 *  @version 0.2
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 12-16-09
 *  @package mingo 
 ******************************************************************************/
abstract class mingo_db_interface {

  /**
   *  holds all the connection information this class used
   *  
   *  @var  array associative array
   */
  protected $con_map = array();
  
  
  /**
   *  returns true if the passed in $index_type is spatial
   *  
   *  @since  10-18-10
   *  @param  string  $index_type
   *  @return boolean
   */
  protected function isSpatialIndexType($index_type){
    return $index_type === mingo_schema::INDEX_SPATIAL;
  }//method
  
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
    if(empty($miles)){ throw UnexpectedValueException('$miles should not be empty'); }//if
  
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
