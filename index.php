<?php

use BenMajor\JQL\JQL;

require 'src/JQL.php';
require 'src/QueryException.php';
require 'src/SyntaxException.php';

$json = [
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
        'age'      => null
    ]
];

$query = new JQL( $json );

try
{
    
    print_r(
        $query->setTimezone('Europe/Paris')
              ->select(['forename'])->select(['CURTIME()'])
              ->where('tags IS NOT EMPTY')
              ->fetch()
    );
    
    /*print_r(
        $query->update([ 'forename' => 'UPPER(forename)' ])
              ->update([ 'surname'  => 'PREPEND(surname, \'Mr. \')'])
              ->where('tags CONTAINS father AND forename = chris')
              ->order('surname', 'ASC')
              ->fetch()
    );*/
}
catch( Exception $e )
{
    die( $e->getMessage() );
}