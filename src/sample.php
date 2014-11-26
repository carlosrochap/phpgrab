<?php

include_once('../vendor/autoload.php');
//use di\Containerbuilder;
//use html\Form;

/**
 * created by phpstorm.
 * user: crocha
 * date: 11/19/14
 * time: 5:18 pm
 */



$container = DI\containerbuilder::builddevcontainer();
//new PhpGrab(new Http(), new Form());
//$foo = $container->get('PhpGrab\Base\PhpGrab');
$foo = $container->get('PhpGrab');
//$form = $foo->getform('http://www.supercarros.com/');
$form = $foo->getForm('http://www.supercarros.com/');
//, ['id' => 'advancesearch'];

print_r($form);