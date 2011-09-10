<?php
/**
 *  allow Mingo cli support using Symfony default connection params
 *    
 *  @package    mingo
 *  @subpackage task
 *  @author     Jay Marcyes
 ******************************************************************************/
class MingoCliTask extends sfBaseTask {

  /**
   * @see sfTask
   */
  protected function configure(){
  
    $this->addOptions(array(
      new sfCommandOption('application', null, sfCommandOption::PARAMETER_REQUIRED, 'The application name, usually something like "frontend" or "backend"','frontend'),
      new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment to use, usually something like "dev"',null),
      new sfCommandOption('connection', null, sfCommandOption::PARAMETER_REQUIRED, 'The connection name, does not usually need to be messed with', 'mingo'),
      new sfCommandOption('orm', null, sfCommandOption::PARAMETER_REQUIRED, 'The ORM base you want to use', 'MingoOrm')
    ));
 
    $this->namespace = 'mingo';
    $this->name = 'cli';
    $this->briefDescription = 'Allow access to Mingo through the CLI';

  }//method

  /**
   * @see sfTask
   */
  protected function execute($arguments = array(), $options = array()){
    
    // canary...
    if(empty($options['env'])){
      throw new InvalidArgumentException('You need to pass in --env="..." option!');
    }//if
  
    // initialize the database connection
    $db_manager = new sfDatabaseManager($this->configuration);
    $connection_map = $db_manager->getDatabase($options['connection'] ? $options['connection'] : null)->getConnection();
    $db = $connection_map[$options['orm']];
    
    $mingo_lib_path = realpath(
      join(
        DIRECTORY_SEPARATOR,
        array(
          dirname(__FILE__),
          '..'
        )
      )
    );
    
    $mingo_cli = join(
      DIRECTORY_SEPARATOR,
      array(
        $mingo_lib_path,
        'extlib',
        'cli',
        'mingo.php'
      )
    );
    
    $command = sprintf(
      'php "%s" --interface=%s --name=%s --host="%s" --username="%s" --password="%s"',
      $mingo_cli,
      get_class($db),
      $db->getName(),
      $db->getHost(),
      $db->getUsername(),
      $db->getPassword()
    );
    
    echo sprintf('Running: %s',$command),PHP_EOL;
    
    passthru($command);
    
  }//method
  
}//class
