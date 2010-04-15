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
  public function getTitle()
  {
    $db_manager = sfContext::getInstance()->getDatabaseManager();
    $db = $db_manager->getDatabase('mingo')->getConnection();
    $query_list = $db->getQueries();
  
    return sprintf(
      '<img src="%s/database.png" alt="Mingo queries" /> Mingo (%s)',
      $this->webDebug->getOption('image_root_path'),
      count($query_list)
    ); 
  }
 
  public function getPanelTitle()
  {
    return 'Mingo Queries';
  }
 
  public function getPanelContent()
  {
    $db_manager = sfContext::getInstance()->getDatabaseManager();
    $db = $db_manager->getDatabase('mingo')->getConnection();
    $query_list = $db->getQueries();
    
    $ret_lines = array();
    foreach($query_list as $query)
    {
      $ret_lines[] = sprintf('<li>%s</li>',$query);
    }//method
   
    return sprintf(
      '<ol>%s</ol>',
      join("\r\n",$ret_lines)
    );
    
  }//method
  
  static public function listenToAddPanelEvent(sfEvent $event)
  {
    $event->getSubject()->setPanel('mingo', new self($event->getSubject()));
  }
  
}//class
