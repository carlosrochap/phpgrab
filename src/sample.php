<?php

include_once('../vendor/autoload.php');
use DI\ContainerBuilder;

/**
 * Created by PhpStorm.
 * User: crocha
 * Date: 11/19/14
 * Time: 5:18 PM
 */



$container = ContainerBuilder::buildDevContainer();

#$foo = $container->get('Connection\Http');
//$form = Form::get($foo->get('http://www.supercarros.com/'), ['id' => 'advanceSearch']);