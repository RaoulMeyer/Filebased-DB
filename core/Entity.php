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
        Database::save($this);
    }

    public function remove() {
        Database::remove($this);
    }

    public function beforeSave() {}

    public function afterSave() {}

    public function beforeRemove() {}

    public function afterRemove() {}

    public function beforeAddField($field) {}

    public function afterAddField($field) {}

    public function beforeRemoveField($field) {}

    public function afterRemoveField($field) {}
}