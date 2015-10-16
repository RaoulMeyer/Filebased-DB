<?php

/**
 * Created by PhpStorm.
 * User: Raoul
 * Date: 10/10/2015
 * Time: 17:16
 */
class Entity {
    public function getCollectionName() {
        return strtolower(get_class($this));
    }

    public function save() {
        Database::get($this)->save($this);
    }

    public function remove() {
        Database::get($this)->remove($this);
    }
}