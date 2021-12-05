<?php

use eftec\FileTextOne;

error_reporting(E_STRICT);
include '../vendor/autoload.php';

$filetextoone=new FileTextOne('csv','grades.csv');
$filetextoone->setCsvStyle(',','"',true,"\n");
echo "<pre>";
$filetextoone->toAll();
//var_dump($filetextoone->toAll());
var_dump($filetextoone->columnTypes);
echo "</pre>";

$obj=[];
$obj["Last name"]= "Alfalfa";
$obj["First name"]= "Aloysius";
$obj["SSN"]="123-45-6789";
$obj["Test1"]=40;
$obj["Test2"]=90;
$obj["Test3"]=100;
$obj["Test4"]=83;
$obj["Final"]=49;
$obj["Grade"]= "D-";

$filetextoone->insert($obj);