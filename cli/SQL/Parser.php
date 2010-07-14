<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | Copyright (c) 2002-2004 Brent Cook                                        |
// +----------------------------------------------------------------------+
// | This library is free software; you can redistribute it and/or        |
// | modify it under the terms of the GNU Lesser General Public           |
// | License as published by the Free Software Foundation; either         |
// | version 2.1 of the License, or (at your option) any later version.   |
// |                                                                      |
// | This library is distributed in the hope that it will be useful,      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU    |
// | Lesser General Public License for more details.                      |
// |                                                                      |
// | You should have received a copy of the GNU Lesser General Public     |
// | License along with this library; if not, write to the Free Software  |
// | Foundation, Inc., 59 Temple Place, Suite 330,Boston,MA 02111-1307 USA|
// +----------------------------------------------------------------------+
// | Authors: Brent Cook <busterbcook@yahoo.com>                          |
// |          Jason Pell <jasonpell@hotmail.com>                          |
// |          Lauren Matheson <inan@canada.com>                           |
// |          John Griffin <jgriffin316@netscape.net>                     |
// +----------------------------------------------------------------------+
//
// $Id: Parser.php,v 1.23 2004/05/11 05:09:02 busterb Exp $
//

require_once 'PEAR.php';
require_once 'SQL/Lexer.php';

/**
 * A sql parser
 *
 * @author  Brent Cook <busterbcook@yahoo.com>
 * @version 0.5
 * @access  public
 * @package SQL_Parser
 */
class SQL_Parser
{
    var $lexer;
    var $token;

// symbol definitions
    var $functions = array();
    var $types = array();
    var $symbols = array();
    var $operators = array();
    var $synonyms = array();

    var $dialects = array("ANSI", "MySQL");

// {{{ function SQL_Parser($string = null)
    function SQL_Parser($string = null, $dialect = "ANSI") {
        $this->setDialect($dialect);

        if (is_string($string)) {
            $this->lexer = new Lexer($string, 1);
            $this->lexer->symbols =& $this->symbols;
        }
    }
// }}}

// {{{ function setDialect($dialect)
    function setDialect($dialect) {
        if (in_array($dialect, $this->dialects)) {
            include 'SQL/Dialect_'.$dialect.'.php';
            $this->types = array_flip($dialect['types']);
            $this->functions = array_flip($dialect['functions']);
            $this->operators = array_flip($dialect['operators']);
            $this->commands = array_flip($dialect['commands']);
            $this->synonyms = $dialect['synonyms'];
            $this->symbols = array_merge(
                $this->types,
                $this->functions,
                $this->operators,
                $this->commands,
                array_flip($dialect['reserved']),
                array_flip($dialect['conjunctions']));
        } else {
            return $this->raiseError('Unknown SQL dialect:'.$dialect);
        }
    }
// }}}
 
// {{{ getParams(&$values, &$types)
    function getParams(&$values, &$types) {
        $values = array();
        $types = array();
        while ($this->token != ')') {
            $this->getTok();
            if ($this->isVal() || ($this->token == 'ident')) {
                $values[] = $this->lexer->tokText;
                $types[] = $this->token;
            } elseif ($this->token == ')') {
                return false;
            } else {
                return $this->raiseError('Expected a value');
            }
            $this->getTok();
            if (($this->token != ',') && ($this->token != ')')) {
                return $this->raiseError('Expected , or )');
            }
        }
    }
// }}}

    // {{{ raiseError($message)
    function raiseError($message) {
        $end = 0;
        if ($this->lexer->string != '') {
            while (($this->lexer->lineBegin+$end < $this->lexer->stringLen)
               && ($this->lexer->string{$this->lexer->lineBegin+$end} != "\n")){
                ++$end;
            }
        }
        
        $message = 'Parse error: '.$message.' on line '.
            ($this->lexer->lineNo+1)."\n";
        $message .= substr($this->lexer->string, $this->lexer->lineBegin, $end)."\n";
        $length = is_null($this->token) ? 0 : strlen($this->lexer->tokText);
        $message .= str_repeat(' ', abs($this->lexer->tokPtr - 
                               $this->lexer->lineBegin - $length))."^";
        $message .= ' found: "'.$this->lexer->tokText.'"';

        return PEAR::raiseError($message);
    }
    // }}}

    // {{{ isType()
    function isType() {
        return isset($this->types[$this->token]);
    }
    // }}}

    // {{{ isVal()
    function isVal() {
       return (($this->token == 'real_val') ||
               ($this->token == 'int_val') ||
               ($this->token == 'text_val') ||
               ($this->token == 'null'));
    }
    // }}}

    // {{{ isFunc()
    function isFunc() {
        return isset($this->functions[$this->token]);
    }
    // }}}

    // {{{ isCommand()
    function isCommand() {
        return isset($this->commands[$this->token]);
    }
    // }}}

    // {{{ isReserved()
    function isReserved() {
        return isset($this->symbols[$this->token]);
    }
    // }}}

    // {{{ isOperator()
    function isOperator() {
        return isset($this->operators[$this->token]);
    }
    // }}}

    // {{{ getTok()
    function getTok() {
        $this->token = $this->lexer->lex();
        //echo $this->token."\t".$this->lexer->tokText."\n";
    }
    // }}}

    // {{{ &parseFieldOptions()
    function parseFieldOptions()
    {
        // parse field options
        $namedConstraint = false;
        $options = array();
        while (($this->token != ',') && ($this->token != ')') &&
                ($this->token != null)) {
            $option = $this->token;
            $haveValue = true;
            switch ($option) {
                case 'constraint':
                    $this->getTok();
                    if ($this->token = 'ident') {
                        $constraintName = $this->lexer->tokText;
                        $namedConstraint = true;
                        $haveValue = false;
                    } else {
                        return $this->raiseError('Expected a constraint name');
                    }
                    break;
                case 'default':
                    $this->getTok();
                    if ($this->isVal()) {
                        $constraintOpts = array('type'=>'default_value',
                                                'value'=>$this->lexer->tokText);
                    } elseif ($this->isFunc()) {
                        $results = $this->parseFunctionOpts();
                        if (PEAR::isError($results)) {
                            return $results;
                        }
                        $results['type'] = 'default_function';
                        $constraintOpts = $results;
                    } else {
                        return $this->raiseError('Expected default value');
                    }
                    break;
                case 'primary':
                    $this->getTok();
                    if ($this->token == 'key') {
                        $constraintOpts = array('type'=>'primary_key',
                                                'value'=>true);
                    } else {
                        return $this->raiseError('Expected "key"');
                    }
                    break;
                case 'not':
                    $this->getTok();
                    if ($this->token == 'null') {
                        $constraintOpts = array('type'=>'not_null',
                                                'value' => true);
                    } else {
                        return $this->raiseError('Expected "null"');
                    }
                    break;
                case 'check':
                    $this->getTok();
                    if ($this->token != '(') {
                        return $this->raiseError('Expected (');
                    }
                    $results = $this->parseSearchClause();
                    if (PEAR::isError($results)) {
                        return $results;
                    }
                    $results['type'] = 'check';
                    $constraintOpts = $results;
                    if ($this->token != ')') {
                        return $this->raiseError('Expected )');
                    }
                    break;
                case 'unique':
                    $this->getTok();
                    if ($this->token != '(') {
                        return $this->raiseError('Expected (');
                    }
                    $constraintOpts = array('type'=>'unique');
                    $this->getTok();
                    while ($this->token != ')') {
                        if ($this->token != 'ident') {
                            return $this->raiseError('Expected an identifier');
                        }
                        $constraintOpts['column_names'][] = $this->lexer->tokText;
                        $this->getTok();
                        if (($this->token != ')') && ($this->token != ',')) {
                            return $this->raiseError('Expected ) or ,');
                        }
                    }
                    if ($this->token != ')') {
                        return $this->raiseError('Expected )');
                    }
                    break;
                case 'month': case 'year': case 'day': case 'hour':
                case 'minute': case 'second':
                    $intervals = array(
                                    array('month'=>0,
                                          'year'=>1),
                                    array('second'=>0,
                                          'minute'=>1,
                                          'hour'=>2,
                                          'day'=>3));
                    foreach ($intervals as $class) {
                        if (isset($class[$option])) {
                            $constraintOpts = array('quantum_1'=>$this->token);
                            $this->getTok();
                            if ($this->token == 'to') {
                                $this->getTok();
                                if (!isset($class[$this->token])) {
                                    return $this->raiseError(
                                        'Expected interval quanta');
                                }
                                if ($class[$this->token] >=
                                    $class[$constraintOpts['quantum_1']]) {
                                    return $this->raiseError($this->token.
                                        ' is not smaller than '.
                                        $constraintOpts['quantum_1']);
                                } 
                                $constraintOpts['quantum_2'] = $this->token;
                            } else {
                                $this->lexer->unget();
                            }
                            break;
                        }
                    }
                    if (!isset($constraintOpts['quantum_1'])) {
                        return $this->raiseError('Expected interval quanta');
                    }
                    $constraintOpts['type'] = 'values';
                    break;
                case 'null':
                    $haveValue = false;
                    break;
                default:
                    return $this->raiseError('Unexpected token '
                                        .$this->lexer->tokText);
            }
            if ($haveValue) {
                if ($namedConstraint) {
                    $options['constraints'][$constraintName] = $constraintOpts;
                    $namedConstraint = false;
                } else {
                    $options['constraints'][] = $constraintOpts;
                }
            }
            $this->getTok();
        }
        return $options;
    }
    // }}}

    // {{{ parseSearchClause()
    function parseSearchClause($subSearch = false)
    {
        $clause = array();
        // parse the first argument
        $this->getTok();
        if ($this->token == 'not') {
            $clause['neg'] = true;
            $this->getTok();
        }

        $foundSubclause = false;
        if ($this->token == '(') {
            $clause['arg_1']['value'] = $this->parseSearchClause(true);
            $clause['arg_1']['type'] = 'subclause';
            if ($this->token != ')') {
                return $this->raiseError('Expected ")"');
            }
            $foundSubclause = true;
        } else if ($this->isReserved()) {
            return $this->raiseError('Expected a column name or value');
        } else {
            $clause['arg_1']['value'] = $this->lexer->tokText;
            $clause['arg_1']['type'] = $this->token;
        }

        // parse the operator
        if (!$foundSubclause) {
            $this->getTok();
            if (!$this->isOperator()) {
                return $this->raiseError('Expected an operator');
            }
            $clause['op'] = $this->token;

            $this->getTok();
            switch ($clause['op']) {
                case 'is':
                    // parse for 'is' operator
                    if ($this->token == 'not') {
                        $clause['neg'] = true;
                        $this->getTok();
                    }
                    if ($this->token != 'null') {
                        return $this->raiseError('Expected "null"');
                    }
                    $clause['arg_2']['value'] = '';
                    $clause['arg_2']['type'] = $this->token;
                    break;
                case 'not':
                    // parse for 'not in' operator
                    if ($this->token != 'in') {
                        return $this->raiseError('Expected "in"');
                    }
                    $clause['op'] = $this->token;
                    $clause['neg'] = true;
                    $this->getTok();
                case 'in':
                    // parse for 'in' operator 
                    if ($this->token != '(') {
                        return $this->raiseError('Expected "("');
                    }

                    // read the subset
                    $this->getTok();
                    // is this a subselect?
                    if ($this->token == 'select') {
                        $clause['arg_2']['value'] = $this->parseSelect(true);
                        $clause['arg_2']['type'] = 'command';
                    } else {
                        $this->lexer->pushBack();
                        // parse the set
                        $result = $this->getParams($clause['arg_2']['value'],
                                                $clause['arg_2']['type']);
                        if (PEAR::isError($result)) {
                            return $result;
                        }
                    }
                    if ($this->token != ')') {
                        return $this->raiseError('Expected ")"');
                    }
                    break;
                case 'and': case 'or':
                    $this->lexer->unget();
                    break;
                default:
                    // parse for in-fix binary operators
                    if ($this->isReserved()) {
                        return $this->raiseError('Expected a column name or value');
                    }
                    if ($this->token == '(') {
                        $clause['arg_2']['value'] = $this->parseSearchClause(true);
                        $clause['arg_2']['type'] = 'subclause';
                        $this->getTok();
                        if ($this->token != ')') {
                            return $this->raiseError('Expected ")"');
                        }
                    } else {
                        $clause['arg_2']['value'] = $this->lexer->tokText;
                        $clause['arg_2']['type'] = $this->token;
                    }
            }
        }

        $this->getTok();
        if (($this->token == 'and') || ($this->token == 'or')) {
            $op = $this->token;
            $subClause = $this->parseSearchClause($subSearch);
            if (PEAR::isError($subClause)) {
                return $subClause;
            } else {
                $clause = array('arg_1' => $clause,
                                'op' => $op,
                                'arg_2' => $subClause);
            }
        } else {
            $this->lexer->unget();
        }
        return $clause;
    }
    // }}}

    // {{{ parseFieldList()
    function parseFieldList()
    {
        $this->getTok();
        if ($this->token != '(') {
            return $this->raiseError('Expected (');
        }

        $fields = array();
        while (1) {
            // parse field identifier
            $this->getTok();
            if ($this->token == 'ident') {
                $name = $this->lexer->tokText;
            } elseif ($this->token == ')') {
                return $fields;
            } else {
                return $this->raiseError('Expected identifier');
            }

            // parse field type
            $this->getTok();
            if ($this->isType($this->token)) {
                $type = $this->token;
            } else {
                return $this->raiseError('Expected a valid type');
            }

            $this->getTok();
            // handle special case two-word types
            if ($this->token == 'precision') {
                // double precision == double
                if ($type == 'double') {
                    return $this->raiseError('Unexpected token');
                }
                $this->getTok();
            } elseif ($this->token == 'varying') {
                // character varying() == varchar()
                if ($type == 'character') {
                    $type == 'varchar';
                    $this->getTok();
                } else {
                    return $this->raiseError('Unexpected token');
                }
            }
            $fields[$name]['type'] = $this->synonyms[$type];
            // parse type parameters
            if ($this->token == '(') {
                $results = $this->getParams($values, $types);
                if (PEAR::isError($results)) {
                    return $results;
                }
                switch ($fields[$name]['type']) {
                    case 'numeric':
                        if (isset($values[1])) {
                            if ($types[1] != 'int_val') {
                                return $this->raiseError('Expected an integer');
                            }
                            $fields[$name]['decimals'] = $values[1];
                        }
                    case 'float':
                        if ($types[0] != 'int_val') {
                            return $this->raiseError('Expected an integer');
                        }
                        $fields[$name]['length'] = $values[0];
                        break;
                    case 'char': case 'varchar':
                    case 'integer': case 'int':
                        if (sizeof($values) != 1) {
                            return $this->raiseError('Expected 1 parameter');
                        }
                        if ($types[0] != 'int_val') {
                            return $this->raiseError('Expected an integer');
                        }
                        $fields[$name]['length'] = $values[0];
                        break;
                    case 'set': case 'enum':
                        if (!sizeof($values)) {
                            return $this->raiseError('Expected a domain');
                        }
                        $fields[$name]['domain'] = $values;
                        break;
                    default:
                        if (sizeof($values)) {
                            return $this->raiseError('Unexpected )');
                        }
                }
                $this->getTok();
            }

            $options = $this->parseFieldOptions();
            if (PEAR::isError($options)) {
                return $options;
            }

            $fields[$name] += $options;

            if ($this->token == ')') {
                return $fields;
            } elseif (is_null($this->token)) {
                return $this->raiseError('Expected )');
            }
        }
    }
    // }}}

    // {{{ parseFunctionOpts()
    function parseFunctionOpts()
    {
        $function = $this->token;
        $opts['name'] = $function;
        $this->getTok();
        if ($this->token != '(') {
            return $this->raiseError('Expected "("');
        }
        switch ($function) {
            case 'count':
                $this->getTok();
                switch ($this->token) {
                    case 'distinct':
                        $opts['distinct'] = true;
                        $this->getTok();
                        if ($this->token != 'ident') {
                            return $this->raiseError('Expected a column name');
                        }
                    case 'ident': case '*':
                        $opts['arg'][] = $this->lexer->tokText;
                        break;
                    default:
                        return $this->raiseError('Invalid argument');
                }
                break;
            case 'concat':
                $this->getTok();
                while ($this->token != ')') {
                    switch ($this->token) {
                        case 'ident': case 'text_val':
                            $opts['arg'][] = $this->lexer->tokText;
                            break;
                        case ',':
                            // do nothing
                            break;
                        default:
                            return $this->raiseError('Expected a string or a column name');
                    }
                    $this->getTok();
                }
                $this->lexer->pushBack();
                break;
            case 'avg': case 'min': case 'max': case 'sum':
            default:
                $this->getTok();
                $opts['arg'] = $this->lexer->tokText;
                break;
        }
        $this->getTok();
        if ($this->token != ')') {
            return $this->raiseError('Expected ")"');
        }
 
        // check for an alias
        $this->getTok();
        if ($this->token == ',' || $this->token == 'from') {
            $this->lexer->pushBack();
        } elseif ($this->token == 'as') {
            $this->getTok();
            if ($this->token == 'ident' ) {
                $opts['alias'] = $this->lexer->tokText;
            } else {
                return $this->raiseError('Expected column alias');
            }
        } else {
            if ($this->token == 'ident' ) {
                $opts['alias'] = $this->lexer->tokText;
            } else {
                return $this->raiseError('Expected column alias, from or comma');
            }
        }
        return $opts;
    }
    // }}}

    // {{{ parseCreate()
    function parseCreate() {
        $this->getTok();
        switch ($this->token) {
            case 'table':
                $tree = array('command' => 'create_table');
                $this->getTok();
                if ($this->token == 'ident') {
                    $tree['table_names'][] = $this->lexer->tokText;
                    $fields = $this->parseFieldList();
                    if (PEAR::isError($fields)) {
                        return $fields;
                    }
                    $tree['column_defs'] = $fields;
//                    $tree['column_names'] = array_keys($fields);
                } else {
                    return $this->raiseError('Expected table name');
                }
                break;
            case 'index':
                $tree = array('command' => 'create_index');
                break;
            case 'constraint':
                $tree = array('command' => 'create_constraint');
                break;
            case 'sequence':
                $tree = array('command' => 'create_sequence');
                break;
            default:
                return $this->raiseError('Unknown object to create');
        }
        return $tree;
    }
    // }}}

    // {{{ parseInsert()
    function parseInsert() {
        $this->getTok();
        if ($this->token == 'into') {
            $tree = array('command' => 'insert');
            $this->getTok();
            if ($this->token == 'ident') {
                $tree['table_names'][] = $this->lexer->tokText;
                $this->getTok();
            } else {
                return $this->raiseError('Expected table name');
            }
            if ($this->token == '(') {
                $results = $this->getParams($values, $types);
                if (PEAR::isError($results)) {
                    return $results;
                } else {
                    if (sizeof($values)) {
                        $tree['column_names'] = $values;
                    }
                }
                $this->getTok();
            }
            if ($this->token == 'values') {
                $this->getTok();
                $results = $this->getParams($values, $types);
                if (PEAR::isError($results)) {
                    return $results;
                } else {
                    if (isset($tree['column_defs']) && 
                        (sizeof($tree['column_defs']) != sizeof($values))) {
                        return $this->raiseError('field/value mismatch');
                    }
                    if (sizeof($values)) {
                        foreach ($values as $key=>$value) {
                            $values[$key] = array('value'=>$value,
                                                    'type'=>$types[$key]);
                        }
                        $tree['values'] = $values;
                    } else {
                        return $this->raiseError('No fields to insert');
                    }
                }
            } else {
                return $this->raiseError('Expected "values"');
            }
        } else {
            return $this->raiseError('Expected "into"');
        }
        return $tree;
    }
    // }}}

    // {{{ parseUpdate()
    function parseUpdate() {
        $this->getTok();
        if ($this->token == 'ident') {
            $tree = array('command' => 'update');
            $tree['table_names'][] = $this->lexer->tokText;
        } else {
            return $this->raiseError('Expected table name');
        }
        $this->getTok();
        if ($this->token != 'set') {
            return $this->raiseError('Expected "set"');
        }
        while (true) {
            $this->getTok();
            if ($this->token != 'ident') {
                return $this->raiseError('Expected a column name');
            }
            $tree['column_names'][] = $this->lexer->tokText;
            $this->getTok();
            if ($this->token != '=') {
                return $this->raiseError('Expected =');
            }
            $this->getTok();
            if (!$this->isVal($this->token)) {
                return $this->raiseError('Expected a value');
            }
            $tree['values'][] = array('value'=>$this->lexer->tokText,
                                      'type'=>$this->token);
            $this->getTok();
            if ($this->token == 'where') {
                $clause = $this->parseSearchClause();
                if (PEAR::isError($clause)) {
                    return $clause;
                }
                $tree['where_clause'] = $clause;
                break;
            } elseif ($this->token != ',') {
                return $this->raiseError('Expected "where" or ","');
            }
        }
        return $tree;
    }
    // }}}

    // {{{ parseDelete()
    function parseDelete() {
        $this->getTok();
        if ($this->token != 'from') {
            return $this->raiseError('Expected "from"');
        }
        $tree = array('command' => 'delete');
        $this->getTok();
        if ($this->token != 'ident') {
            return $this->raiseError('Expected a table name');
        }
        $tree['table_names'][] = $this->lexer->tokText;
        $this->getTok();
        if ($this->token != 'where') {
            return $this->raiseError('Expected "where"');
        }
        $clause = $this->parseSearchClause();
        if (PEAR::isError($clause)) {
            return $clause;
        }
        $tree['where_clause'] = $clause;
        return $tree;
    }
    // }}}

    // {{{ parseDrop()
    function parseDrop() {
        $this->getTok();
        switch ($this->token) {
            case 'table':
                $tree = array('command' => 'drop_table');
                $this->getTok();
                if ($this->token != 'ident') {
                    return $this->raiseError('Expected a table name');
                }
                $tree['table_names'][] = $this->lexer->tokText;
                $this->getTok();
                if (($this->token == 'restrict') ||
                    ($this->token == 'cascade')) {
                    $tree['drop_behavior'] = $this->token;
                }
                $this->getTok();
                if (!is_null($this->token)) {
                    return $this->raiseError('Unexpected token');
                }
                return $tree;
                break;
            case 'index':
                $tree = array('command' => 'drop_index');
                break;
            case 'constraint':
                $tree = array('command' => 'drop_constraint');
                break;
            case 'sequence':
                $tree = array('command' => 'drop_sequence');
                break;
            default:
                return $this->raiseError('Unknown object to drop');
        }
        return $tree;
    }
    // }}}

    // {{{ parseSelect()
    function parseSelect($subSelect = false) {
        $tree = array('command' => 'select');
        $this->getTok();
        if (($this->token == 'distinct') || ($this->token == 'all')) {
            $tree['set_quantifier'] = $this->token;
            $this->getTok();
        }
        if ($this->token == '*') {
            $tree['column_names'][] = '*';
            $this->getTok();
        } elseif ($this->token == 'ident' || $this->isFunc()) {
            while ($this->token != 'from') {
                if ($this->token == 'ident') {
                    $prevTok = $this->token;
                    $prevTokText = $this->lexer->tokText;
                    $this->getTok();
                    if ($this->token == '.') {
                        $columnTable = $prevTokText;
                        $this->getTok();
                        $prevTok = $this->token;
                        $prevTokText = $this->lexer->tokText;
                    } else {
                        $columnTable = '';
                    }

                    if ($prevTok == 'ident') {
                        $columnName = $prevTokText;
                    } else {
                        return $this->raiseError('Expected column name');
                    }

                    if ($this->token == 'as') {
                        $this->getTok();
                        if ($this->token == 'ident' ) {
                            $columnAlias = $this->lexer->tokText;
                        } else {
                            return $this->raiseError('Expected column alias');
                        }
                    } elseif ($this->token == 'ident') {
                        $columnAlias = $this->lexer->tokText;
                    } else {
                        $columnAlias = '';
                    }

                    $tree['column_tables'][] = $columnTable;
                    $tree['column_names'][] = $columnName;
                    $tree['column_aliases'][] = $columnAlias;
                    if ($this->token != 'from') {
                        $this->getTok();
                    }
                    if ($this->token == ',') {
                        $this->getTok();
                    }
                } elseif ($this->isFunc()) {
                    if (!isset($tree['set_quantifier'])) {
                        $result = $this->parseFunctionOpts();
                        if (PEAR::isError($result)) {
                            return $result;
                        }
                        $tree['set_function'][] = $result;
                        $this->getTok();

                        if ($this->token == 'as') {
                            $this->getTok();
                            if ($this->token == 'ident' ) {
                                $columnAlias = $this->lexer->tokText;
                            } else {
                                return $this->raiseError('Expected column alias');
                            }
                        } else {
                            $columnAlias = '';
                        }
                    } else {
                        return $this->raiseError('Cannot use "'.
                                $tree['set_quantifier'].'" with '.$this->token);
                    }
                } elseif ($this->token == ',') {
                    $this->getTok();
                } else {
                        return $this->raiseError('Unexpected token "'.$this->token.'"');
                }
            }
        } else {
            return $this->raiseError('Expected columns or a set function');
        }
        if ($this->token != 'from') {
            return $this->raiseError('Expected "from"');
        }
        $this->getTok();
        while ($this->token == 'ident') {
            $tree['table_names'][] = $this->lexer->tokText;
            $this->getTok();
            if ($this->token == 'ident') {
                $tree['table_aliases'][] = $this->lexer->tokText;
                $this->getTok();
            } elseif ($this->token == 'as') {
                $this->getTok();
                if ($this->token == 'ident') {
                    $tree['table_aliases'][] = $this->lexer->tokText;
                } else {
                    return $this->raiseError('Expected table alias');
                }
                $this->getTok();
            } else {
                $tree['table_aliases'][] = '';
            }
            if ($this->token == 'on') {
                $clause = $this->parseSearchClause();
                if (PEAR::isError($clause)) {
                    return $clause;
                }
                $tree['table_join_clause'][] = $clause;
            } else {
                $tree['table_join_clause'][] = '';
            }
            if ($this->token == ',') {
                $tree['table_join'][] = ',';
                $this->getTok();
            } elseif ($this->token == 'join') {
                $tree['table_join'][] = 'join';
                $this->getTok();
            } elseif (($this->token == 'cross') ||
                        ($this->token == 'inner')) {
                $join = $this->lexer->tokText;
                $this->getTok();
                if ($this->token != 'join') {
                    return $this->raiseError('Expected token "join"');
                }
                $tree['table_join'][] = $join.' join';
                $this->getTok();
            } elseif (($this->token == 'left') ||
                        ($this->token == 'right')) {
                $join = $this->lexer->tokText;
                $this->getTok();
                if ($this->token == 'join') {
                    $tree['table_join'][] = $join.' join';
                } elseif ($this->token == 'outer') {
                        $join .= ' outer';
                    $this->getTok();
                    if ($this->token == 'join') {
                        $tree['table_join'][] = $join.' join';
                    } else {
                        return $this->raiseError('Expected token "join"');
                    }
                } else {
                    return $this->raiseError('Expected token "outer" or "join"');
                }
                $this->getTok();
            } elseif ($this->token == 'natural') {
                $join = $this->lexer->tokText;
                $this->getTok();
                if ($this->token == 'join') {
                    $tree['table_join'][] = $join.' join';
                } elseif (($this->token == 'left') ||
                            ($this->token == 'right')) {
                        $join .= ' '.$this->token;
                    $this->getTok();
                    if ($this->token == 'join') {
                        $tree['table_join'][] = $join.' join';
                    } elseif ($this->token == 'outer') {
                        $join .= ' '.$this->token;
                        $this->getTok();
                        if ($this->token == 'join') {
                            $tree['table_join'][] = $join.' join';
                        } else {
                            return $this->raiseError('Expected token "join" or "outer"');
                        }
                    } else {
                        return $this->raiseError('Expected token "join" or "outer"');
                    }
                } else {
                    return $this->raiseError('Expected token "left", "right" or "join"');
                }
                $this->getTok();
            } elseif (($this->token == 'where') ||
                        ($this->token == 'order') ||
                        ($this->token == 'limit') ||
                        (is_null($this->token))) {
                break;
            }
        }
        while (!is_null($this->token) && (!$subSelect || $this->token != ')')
               && $this->token != ')') {
            switch ($this->token) {
                case 'where':
                    $clause = $this->parseSearchClause();
                    if (PEAR::isError($clause)) {
                        return $clause;
                    }
                    $tree['where_clause'] = $clause;
                    break;
                case 'order':
                    $this->getTok();
                    if ($this->token != 'by') {
                        return $this->raiseError('Expected "by"');
                    }
                    $this->getTok();
                    while ($this->token == 'ident') {
                        $col = $this->lexer->tokText;
                        $this->getTok();
                        if (isset($this->synonyms[$this->token])) {
                            $order = $this->synonyms[$this->token];
                            if (($order != 'asc') && ($order != 'desc')) {
                                return $this->raiseError('Unexpected token');
                            }
                            $this->getTok();
                        } else {
                            $order = 'asc';
                        }
                        if ($this->token == ',') {
                            $this->getTok();
                        }
                        $tree['sort_order'][$col] = $order;
                    }
                    break;
                case 'limit':
                    $this->getTok();
                    if ($this->token != 'int_val') {
                        return $this->raiseError('Expected an integer value');
                    }
                    $length = $this->lexer->tokText;
                    $start = 0;
                    $this->getTok();
                    if ($this->token == ',') {
                        $this->getTok();
                        if ($this->token != 'int_val') {
                            return $this->raiseError('Expected an integer value');
                        }
                        $start = $length;
                        $length = $this->lexer->tokText;
                        $this->getTok();
                    }
                    $tree['limit_clause'] = array('start'=>$start,
                                                  'length'=>$length);
                    break;
                case 'group':
                    $this->getTok();
                    if ($this->token != 'by') {
                        return $this->raiseError('Expected "by"');
                    }
                    $this->getTok();
                    while ($this->token == 'ident') {
                        $col = $this->lexer->tokText;
                        $this->getTok();
                        if ($this->token == ',') {
                            $this->getTok();
                        }
                        $tree['group_by'][] = $col;
                    }
                    break;
                default:
                    return $this->raiseError('Unexpected clause');
            }
        }
        return $tree;
    }
    // }}}

    // {{{ parse($string)
    function parse($string = null)
    {
        if (is_string($string)) {
            // Initialize the Lexer with a 3-level look-back buffer
            $this->lexer = new Lexer($string, 3);
            $this->lexer->symbols =& $this->symbols;
        } else {
            if (!is_object($this->lexer)) {
                return $this->raiseError('No initial string specified');
            }
        }

        // get query action
        $this->getTok();
        switch ($this->token) {
            case null:
                // null == end of string
                return $this->raiseError('Nothing to do');
            case 'select':
                return $this->parseSelect();
            case 'update':
                return $this->parseUpdate();
            case 'insert':
                return $this->parseInsert();
            case 'delete':
                return $this->parseDelete();
            case 'create':
                return $this->parseCreate();
            case 'drop':
                return $this->parseDrop();
            default:
                return $this->raiseError('Unknown action :'.$this->token);
        }
    }
    // }}}
}
?>
