<?php

/**
 * Created by PhpStorm.
 * User: Raoul
 * Date: 10/10/2015
 * Time: 17:15
 */
class Database {

    public function get($entity) {
        if(!$entity instanceof Entity) {
            return null;
        }
        return new Collection($entity);
    }
}