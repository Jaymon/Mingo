<?php

/**
 *  handle relational db abstraction for mingo for sqlite    
 *
 *  @version 0.2
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 3-19-10
 *  @package mingo 
 ******************************************************************************/
class mingo_db_sqlite extends mingo_db_sql {

  protected function start(){
  
    $this->setType(self::TYPE_SQLITE);
    
  }//method
  
}//class     
