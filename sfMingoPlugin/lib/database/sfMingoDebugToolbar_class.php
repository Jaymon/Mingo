<?php
/**
 *  add a memcache toolbar panel to the debug toolbar
 *  
 *  http://www.symfony-project.org/more-with-symfony/1_4/en/07-Extending-the-Web-Debug-Toolbar  
 *  
 *  @version 0.2
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 3-31-10
 *  @project plancast
 *  @subpackage debug
 ******************************************************************************/
class sfMingoDebugToolbar extends sfWebDebugPanel
{
  public function getTitle(){
  
    $query_list = $this->getQueries();
  
    return sprintf(
      '<img src="%s/database.png" alt="Mingo queries" /> Mingo (%s)',
      $this->webDebug->getOption('image_root_path'),
      count($query_list)
    ); 
  }
 
  public function getPanelTitle(){
    return 'Mingo Queries';
  }
 
  public function getPanelContent(){
  
    $query_list = $this->getQueries();
    
    $ret_lines = array();
    foreach($query_list as $query){
    
      if(is_array($query)){
        $query = json_encode($query);
      }//if
      
      $ret_lines[] = sprintf('<li>%s</li>',$query);
      
    }//method
   
    return sprintf(
      '<ol>%s</ol>',
      join("\r\n",$ret_lines)
    );
    
  }//method
  
  static public function listenToAddPanelEvent(sfEvent $event){
    $event->getSubject()->setPanel('mingo', new self($event->getSubject()));
  }//method
  
  /**
   *  get all the queries from all the mingo connections
   *  
   *  @since  9-27-10   
   *  @return array
   */
  protected function getQueries(){
  
    $ret_list = array();
  
    $db_manager = sfContext::getInstance()->getDatabaseManager();
    $connection_map = $db_manager->getDatabase('mingo')->getConnection();
    foreach($connection_map as $key => $db){
    
      $ret_list = array_merge($ret_list,$db->getQueries());
      
    }//foreach
  
    return $ret_list;
  
  }//method
  
}//class
