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
    public static function getCollection($entity) {
        if(!$entity instanceof Entity) {
            return null;
        }

        if(!array_key_exists(get_class($entity), Database::$collections)) {
            Database::$collections[get_class($entity)] = new Collection($entity);
        }

        return Database::$collections[get_class($entity)];
    }

    /**
     * Get all data from a Collection
     *
     * @param Entity $entity Entity for which to get data
     *
     * @return array Array of all Entities found
     */
    public static function getAll($entity) {
        $collection = self::getCollection($entity);

        return $collection->get();
    }

    /**
     * Get all data from a Collection with filters
     *
     * @param Entity $entity Entity for which to get data
     * @param array $filters Array of field => value pairs to filter by
     *
     * @return array Array of all Entities found
     */
    public static function get($entity, $filters = array()) {
        $collection = self::getCollection($entity);

        foreach($filters as $field => $value) {
            $collection->filter($field, $value);
        }

        return $collection->get();
    }

    /**
     * Save an Entity
     *
     * @param Entity $entity Entity to save
     */
    public static function save($entity) {
        $collection = self::getCollection($entity);
        $collection->save($entity);
    }

    /**
     * Remove an Entity
     *
     * @param Entity $entity Entity to remove
     */
    public static function remove($entity) {
        $collection = self::getCollection($entity);
        $collection->remove($entity);
    }
}