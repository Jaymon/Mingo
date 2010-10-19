<?php

class test_spatial_orm extends mingo_orm {

  protected function start(){
  
    $this->schema->setSpatial('pt','type');
  
  
  }//method
  
}//class
