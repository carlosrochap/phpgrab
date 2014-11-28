<?php
/**
 * Created by PhpStorm.
 * User: crocha
 * Date: 11/27/14
 * Time: 4:29 PM
 */

class Page {

    private $content;

    private $cookies;

    private function __construct($content, $cookies){
        $this->content = $content;
        $this->cookies = $cookies;
    }

    static public function create($content, $cookies) {
        return new self($content, $cookies);
    }

    public function getForm ($selector) {
        return Form::get($this->content, $selector);
    }

    public function postForm () {}

    public function getElement() {}

    public function save($file) {
        $path = isset($file) ? $file
            : time().'.html';

        file_put_contents($path, $this->content);
    }

    public function getCookie(){}

    public function getAllCookies()
    {
        return $this->cookies;
    }

    public function setCookie(){}



} 