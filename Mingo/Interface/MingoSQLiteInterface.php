<?php

/**
 *  handle relational db abstraction for mingo for sqlite    
 *
 *  SQLite has a limit of 500 values in an IN (...) query, just something to be
 *  aware of, see #7: http://www.sqlite.org/limits.html 
 *  
 *  @version 0.4
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 3-19-10
 *  @package mingo 
 ******************************************************************************/
class MingoSQLiteInterface extends MingoRDBMSInterface {
  
  /**
   *  get the dsn connection string that PDO will use to connect to the backend
   *   
   *  @since  10-18-10
   *  @param  string  $name the database name
   *  @param  string  $host the host
   *  @return string  the dsn         
   */
  protected function getDsn($name,$host){
  
    // for sqlite: PRAGMA encoding = "UTF-8"; from http://sqlite.org/pragma.html only good on db creation
    // http://stackoverflow.com/questions/263056/how-to-change-character-encoding-of-a-pdo-sqlite-connection-in-php
  
    return sprintf('sqlite:%s',$name);
  
  }//method
  
}//class     
