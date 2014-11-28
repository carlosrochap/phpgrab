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
$http = $container->get('Http');
//$form = $foo->getform('http://www.supercarros.com/');
//$form = $foo->getForm('http://www.supercarros.com/');
//, ['id' => 'advancesearch'];
$page = $http->get('http://www.supercarros.com/');
print_r($page);

/// workflow

/*
 * $http = $container->get('PhpGrab\Http');
 * $page = $http->get('http://url.com');
 *
 * $form = $page->getForm(['id' => 'sampleId']);
 *
 * $form['user'] = 'test';
 * $form['pass'] = '1245';
 *
 * $page->post($form)->saveResult('index.html'); // $page->postForm();
 *
 * $page = $page->post($form);
 * $page->save('yolo.html');
 */