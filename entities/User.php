<?php

/**
 * Created by PhpStorm.
 * User: Raoul
 * Date: 10/10/2015
 * Time: 17:43
 */
class User implements Entity {

    public $id;
    public $username;
    public $email;
    public $password;

    public function getCollectionName() {
        return 'user';
    }
}