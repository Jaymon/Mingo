<?php

class test_spatial_orm extends mingo_orm {

  protected function start(){
  
    $this->schema->setSpatial('pt','type');
    
    $field = new mingo_field('pt',mingo_field::TYPE_POINT);
    $this->schema->setField($field);
    
    $field = new mingo_field('type',mingo_field::TYPE_INT);
    $this->schema->setField($field);
  
  }//method
  
}//class
