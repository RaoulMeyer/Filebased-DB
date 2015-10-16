<?php

/**
 * Created by PhpStorm.
 * User: Raoul
 * Date: 10/10/2015
 * Time: 17:15
 */
class Database {

    private static $collections = array();

    /**
     * @param $entity
     * @return Collection
     */
    public static function get($entity) {
        if(!$entity instanceof Entity) {
            return null;
        }

        if(!array_key_exists(get_class($entity), Database::$collections)) {
            Database::$collections[get_class($entity)] = new Collection($entity);
        }

        return Database::$collections[get_class($entity)];
    }
}