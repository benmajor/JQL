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
        'age'      => 63
    ],
    [
        'forename' => 'Joe',
        'surname'  => 'Bloggs',
        'email'    => 'jbloggs@example.com',
        'age'      => 45
    ]
]);

$query = new JQL( $json );

try
{
    print_r(
        $query->select([
                    'CONCAT_WS(\' \', forename, surname) AS name',
                    'email',
                    'age'
                ])
              ->where('surname == Major')
              ->order('surname', 'DESC')
              ->order('forename', 'ASC')
              ->limit(3,0)
              ->count()
    );
}
catch( Exception $e )
{
    die( $e->getMessage() );
}