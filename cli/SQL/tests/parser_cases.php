<?php
require_once 'SQL/Parser.php';
require_once 'PHPUnit.php';
require_once 'Var_Dump.php';

class SqlParserTest extends PHPUnit_TestCase {
    // contains the object handle of the parser class
    var $parser;
    var $dumper;

    //constructor of the test suite
    function SqlParserTest($name) {
        $this->PHPUnit_TestCase($name);
    }

    function setUp() {
        $this->parser = new Sql_parser();
        $this->dumper = new Var_Dump(
            array('displayMode'=> VAR_DUMP_DISPLAY_MODE_TEXT));
    }

    function tearDown() {
        unset($this->parser);
    }

    function runTests($tests) {
        foreach ($tests as $number=>$test) {
            $result = $this->parser->parse($test['sql']);
            $expected = $test['expect'];
            $message = "\nSQL: {$test['sql']}\n";
            if (PEAR::isError($result)) {
                $result = $result->getMessage();
                $message .= "\nError:\n".$result;
            } else {
                $message .= "\nExpected:\n".$this->dumper->display($expected);
                $message .= "\nResult:\n".$this->dumper->display($result);
            }
            $message .= "\n*********************\n";
            $this->assertEquals($expected, $result, $message, $number);
        }
    }

    function testSelect() {
        include 'select.php';
        $this->runTests($tests);
    }

    function testUpdate() {
        include 'update.php';
        $this->runTests($tests);
    }

    function testInsert() {
        include 'insert.php';
        $this->runTests($tests);
    }

    function testDelete() {
        include 'delete.php';
        $this->runTests($tests);
    }

    function testDrop() {
        include 'drop.php';
        $this->runTests($tests);
    }

    function testCreate() {
        include 'create.php';
        $this->runTests($tests);
    }
}
