<?php
/**
 *  allow Mingo cli support using Symfony default connection params
 *    
 *  @package    mingo
 *  @subpackage task
 *  @author     Jay Marcyes
 ******************************************************************************/
class MingoCliTask extends sfBaseTask
{
  /**
   * @see sfTask
   */
  protected function configure()
  {
    $this->addOptions(array(
      new sfCommandOption('application', null, sfCommandOption::PARAMETER_REQUIRED, 'The application name, usually something like "frontend" or "backend"','backend'),
      new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment to use, usually something like "dev"', null),
      new sfCommandOption('connection', null, sfCommandOption::PARAMETER_REQUIRED, 'The connection name, does not usually need to be messed with', 'mingo')
    ));
 
    $this->namespace = 'mingo';
    $this->name = 'cli';
    $this->briefDescription = 'Allow access to Mingo through the CLI';

  }//method

  /**
   * @see sfTask
   */
  protected function execute($arguments = array(), $options = array())
  {
    // initialize the database connection
    $db_manager = new sfDatabaseManager($this->configuration);
    $db = $db_manager->getDatabase($options['connection'] ? $options['connection'] : null)->getConnection();
    
    $mingo_lib_path = realpath(
      join(
        DIRECTORY_SEPARATOR,
        array(
          dirname(__FILE__),
          '..'
        )
      )
    );
    
    $mingo_exe = realpath(
      join(
        DIRECTORY_SEPARATOR,
        array(
          $mingo_lib_path,
          'cli',
          'mingo.php'
        )
      )
    );
    
    $command = sprintf(
      'php "%s" --interface=%s --db=%s --host=%s --username=%s --password="%s"',
      $mingo_exe,
      $db->getInterface(),
      $db->getDb(),
      $db->getHost(),
      $db->getUsername(),
      $db->getPassword()
    );
    
    passthru($command);
    
  }//method
  
}//class
