<?php
$tests = array(
array(
'sql' => 'insert into dogmeat (\'horse\', \'hair\') values (2, 4)',
'expect' => array(
        'command' => 'insert',
        'table_names' => array(
            0 => 'dogmeat'
            ),
        'column_names' => array(
            0 => 'horse',
            1 => 'hair'
            ),
        'values' => array(
            0 => array(
                'value' => 2,
                'type' => 'int_val'
                ),
            1 => array(
                'value' => 4,
                'type' => 'int_val'
                )
            )
        )
),
array(
'sql' => 'inSERT into dogmeat (horse, hair) values (2, 4)',
'expect' => array(
        'command' => 'insert',
        'table_names' => array(
            0 => 'dogmeat'
            ),
        'column_names' => array(
            0 => 'horse',
            1 => 'hair'
            ),
        'values' => array(
            0 => array(
                'value' => 2,
                'type' => 'int_val'
                ),
            1 => array(
                'value' => 4,
                'type' => 'int_val'
                )
            )
        )
),
array(
'sql' => 'INSERT INTO mytable (foo, bar, baz) VALUES (NOW(), 1, \'text\')',
'expect' => 'Parse error: Expected , or ) on line 1
INSERT INTO mytable (foo, bar, baz) VALUES (NOW(), 1, \'text\')
                                               ^ found: "("'

),
);
?>
