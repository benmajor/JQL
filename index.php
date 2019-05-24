<?php

use BenMajor\JQL\JQL;

require 'src/JQL.php';
require 'src/QueryException.php';
require 'src/SyntaxException.php';

$json = json_encode([
    [
        'forename' => 'Ben',
        'surname'  => 'Major',
        'email'    => 'ben.major88@gmail.com',
        'age'      => 31
    ],
    [
        'forename' => 'Dave',
        'surname'  => 'Aaronson',
        'email'    => 'dave.aaron@gmail.com',
        'age'      => 41
    ],
    [
        'forename' => 'Chris',
        'surname'  => 'Major',
        'email'    => 'chris.major@example.com',
        'age'      => 63,
        'tags'     => [
            'dad', 'father'
        ]
    ],
    [
        'forename' => 'Joe',
        'surname'  => 'Bloggs',
        'email'    => 'jbloggs@example.com',
        'age'      => 45.587
    ]
]);

$query = new JQL( $json );

try
{
    /*
    print_r(
        $query->select([
                    'forename',
                    'surname',
                    'RAND(155) AS random',
                    'CURDATE() AS date',
                    'DAYNAME(2019-01-02) AS formatted'
                ])
              ->where('tags NOT IN(hello)')
              ->fetch()
    );*/
    
    print_r(
        $query->update([ 'forename' => 'UPPER(forename)' ])
              ->where('tags CONTAINS father')
              ->saveAsFile('test.json')
    );
}
catch( Exception $e )
{
    die( $e->getMessage() );
}