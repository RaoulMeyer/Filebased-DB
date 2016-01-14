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
     * Get Collection
     * Caches collections by Entity
     *
     * @param Entity $entity Entity for which to get the Collection
     *
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