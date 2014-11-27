<?php
/**
 * Created by PhpStorm.
 * User: crocha
 * Date: 11/21/14
 * Time: 4:21 PM
 */

#namespace PhpGrab\Base;

#use PhpGrab\Connection\Http;
#use PhpGrab\Html\Form;

class PhpGrab {

    /**
     * @var Http
     */
    private $http;
    /**
     * @var Form
     */
    private $form;

    public function __construct(Http $http, Form $form) {

        $this->http = $http;
        $this->form = $form;
    }

    public function getForm ($url) {
        return Form::get($this->http->get($url), ['id' => 'advanceSearch']);
    }

    public function postForm (Form $form) {
        //TODO
    }

} 