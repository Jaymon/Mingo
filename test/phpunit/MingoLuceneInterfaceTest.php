<?php

require_once('MingoInterfaceTest.php');

class MingoLuceneInterfaceTest extends MingoInterfaceTest {

  /**
   *  @return string  the database name
   */
  public function getDbName(){
    
    return sprintf(
      '%s_lucene',
      join(DIRECTORY_SEPARATOR,array(sys_get_temp_dir(),__CLASS__))
    );
    
  }//method

  public function testSetup(){
  
    $db = $this->getDb();
  
  }//method

  public function getDbInterface(){
  
    // include zend...
    MingoAutoload::addIncludePath('C:\Dropfolder\ZendFramework-1.11.5\library');
  
    return 'MingoLuceneInterface';
  
  }//method

}//class
