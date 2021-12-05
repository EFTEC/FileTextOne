<?php

use Eftec\FileTextOne\FileTextOne;

include '../vendor/autoload.php';

$values=[["id"=>"hello","field"=>20],["id"=>"hello","field"=>20]];

$fto=new FileTextOne('json','new.json');

$fto->insert($values);
