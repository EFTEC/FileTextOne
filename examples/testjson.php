<?php



//ini_set('precision', 10);
//ini_set('serialize_precision', 10);


use Eftec\FileTextOne\FileTextOne;

include '../vendor/autoload.php';

$filetextoone=new FileTextOne('json','dolar.json');
$filetextoone->regionDateTime='Y-m-d\TH:i:s.u\Z';

$filecsv=new FileTextOne('csv','dolar.csv');
$filecsv->regionDateTime='Y-m-d\TH:i:s.u\Z';


$values=$filetextoone->toAll('serie');
$filecsv->insert($values);