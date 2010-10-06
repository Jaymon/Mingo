<?php

// declare a mingo autoloader we can use...
include(
  join(
    DIRECTORY_SEPARATOR,
    array(
      join(DIRECTORY_SEPARATOR,array(dirname(__FILE__),'..')),
      'mingo_autoload_class.php'
    )
  )
);
mingo_autoload::register();


class mingo_test extends PHPUnit_Framework_TestCase {

}//class
