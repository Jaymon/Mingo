<?php
/**
 *  handy base class that abstracts away the connection setting using Symfony's Database
 *  objects   
 * 
 *  @abstract 
 *  @version 0.1
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 5-24-11
 *  @package mingo 
 ******************************************************************************/
abstract class sfMingoOrm extends MingoOrm {

  /**
   *  return the db object that this instance is using
   *  
   *  @see  setDb()   
   *  @return MingoInterface  an instance of the db object that will be used
   */
  public function getDb(){
    
    // canary...
    if(empty($this->db)){
    
      // get all the names of this class and all parents in order to find the right instance...
      $class = get_class($this);
      $parent_list = array();
      // via: http://us2.php.net/manual/en/function.get-parent-class.php#57548
      for($parent_list[] = $class; $class = get_parent_class($class); $parent_list[] = $class);
    
      // get the corresponding db connection...
      $db_manager = sfContext::getInstance()->getDatabaseManager();
      $db_handler = $db_manager->getDatabase('mingo');
      $this->setDb($db_handler->getInstance($parent_list));
    
    }//if
    
    return parent::getDb();
    
  }//method

}//class     
