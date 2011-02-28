<?php
/**
 *  configuration class to bind mingo to symfony
 *  
 *  this site helped me the most:
 *  @link http://www.symfony-project.org/jobeet/1_2/Propel/en/20
 *  
 *  extending symfony documentation:   
 *  @link http://www.symfony-project.org/book/1_2/17-Extending-Symfony   
 *
 *  honestly, I'm not sure what to put in the initialize() method, so it does nothing
 *  but it is nice to have it here in case I do think of something to put in it at
 *  a later time  
 *  
 *  @version 0.2
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 1-5-10
 *  @package mingo
 *  @subpackage symfony
 ******************************************************************************/    
class MingoPluginConfiguration extends sfPluginConfiguration
{
  public function initialize()
  {
    // use the debug toolbar...
    $this->dispatcher->connect('debug.web.load_panels', array(
      'MingoDebugToolbar',
      'listenToAddPanelEvent'
    ));
   
    return true;
  }
}
