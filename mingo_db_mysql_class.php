<?php

/**
 *  handle relational db abstraction for mingo for MySQL   
 *
 *  @version 0.2
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 3-19-10
 *  @package mingo 
 ******************************************************************************/
class mingo_db_mysql extends mingo_db_sql {

  protected function start(){
  
    $this->setType(self::TYPE_MYSQL);
    
  }//method
  
}//class     
