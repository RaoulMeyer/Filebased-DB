<?php

/**
 * Created by PhpStorm.
 * User: Raoul
 * Date: 10/10/2015
 * Time: 17:15
 */
class Collection {

    private $entity;
    private $fields;
    private $index;
    private $autoincrement;

    private $filters = array();
    private $joins = array();

    private $cache = array();
    private $cacheLimit = 100000;
    private $itemCache = array();
    private $itemCacheLimit = 10000;

    private $limit = 0;
    private $offset = 0;
    private $sort;
    private $sortDirectionAscending = true;

    private $collectionRemoved = false;

    public function __construct(Entity $entity) {
        $this->entity = $entity;

        if(!$this->fileExists('./data/meta/' . $entity->getCollectionName())) {
            $this->generateCollection($entity);
        }

        $meta = explode("\n", $this->openFile('./data/meta/' . $entity->getCollectionName()));

        $this->fields = explode(";", $meta[0]);
        $this->index = explode(";", $meta[1]);
        $this->autoincrement = $meta[2];
        if(Settings::getSetting('db_cache_limit') !== null) {
            $this->cacheLimit = Settings::getSetting('db_cache_limit');
        }
        if(Settings::getSetting('db_item_cache_limit') !== null) {
            $this->itemCacheLimit = Settings::getSetting('db_item_cache_limit');
        }
    }

    private function generateCollection(Entity $entity) {
        $this->createEntityDirs();

        $entityFields = get_object_vars($entity);

        $this->autoincrement = 1;

        foreach ($entityFields as $field => $value) {
            $this->fields[] = $field;
            $this->index[] = $field;
            $this->createIndexDir($field);
        }

        $this->saveMeta();
    }

    public function get() {
        $entityClass = get_class($this->entity);
        if(empty($this->filters)) {
            return $this->getFullCollection($entityClass);
        }

        if(!empty($this->filters[$this->fields[0]])) {
            return $this->getPrimaryIndexCollection($entityClass);
        }

        return $this->getFilterCollection($entityClass);
    }

    private function getFullCollection($entityClass) {
        $data = array();
        $files = scandir('./data/collections/' . $this->entity->getCollectionName());

        $itemNumber = 0;
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $itemNumber++;
            if($itemNumber <= $this->offset) {
                continue;
            }
            if($this->limit !== 0 && $itemNumber > $this->offset + $this->limit) {
                break;
            }

            $item = $this->getItemById($file, $entityClass);

            $item = $this->joinItem($item);

            $data[] = $item;
        }

        if(!empty($this->sort)) {
            $sort = $this->sort;
            $direction = $this->sortDirectionAscending;
            usort($data,
                function($a, $b) use (&$sort, &$direction) {
                    if($direction) {
                        return strcmp($a->$sort, $b->$sort);
                    } else {
                        return strcmp($b->$sort, $a->$sort);
                    }
                }
            );
        }

        $this->cleanupQuery();

        return $data;
    }

    private function getPrimaryIndexCollection($entityClass) {
        if (!$this->fileExists('./data/collections/' . $this->entity->getCollectionName() . '/' . ($this->filters[$this->fields[0]]))) {
            return null;
        }

        $item = $this->getItemById($this->filters[$this->fields[0]], $entityClass);

        $item = $this->joinItem($item);

        $this->cleanupQuery();

        return array($item);
    }

    private function getFilterCollection($entityClass) {
        $filteredKeys = array();
        foreach ($this->filters as $field => $value) {
            if (!$this->fileExists('./data/index/' . $this->entity->getCollectionName() . '/' . $field . '/' . md5($value))) {
                return array();
            }
            $indexData = explode(";", $this->openFile('./data/index/' . $this->entity->getCollectionName() . '/' . $field . '/' . md5($value)));

            if (empty($filteredKeys)) {
                $filteredKeys = $indexData;
            } else {
                $filteredKeys = array_intersect($filteredKeys, $indexData);
            }
        }

        $data = array();
        $itemNumber = 0;

        foreach ($filteredKeys as $file) {
            $itemNumber++;
            if($itemNumber <= $this->offset) {
                continue;
            }
            if($this->limit !== 0 && $itemNumber > $this->offset + $this->limit) {
                break;
            }

            $item = $this->getItemById($file, $entityClass);

            $item = $this->joinItem($item);

            $data[] = $item;
        }

        if(!empty($this->sort)) {
            $sort = $this->sort;
            $direction = $this->sortDirectionAscending;
            usort($data,
                function($a, $b) use (&$sort, &$direction) {
                    if($direction) {
                        return strcmp($a->$sort, $b->$sort);
                    } else {
                        return strcmp($b->$sort, $a->$sort);
                    }
                }
            );
        }

        $this->cleanupQuery();

        return $data;
    }

    public function filter($field, $value) {
        $this->filters[$field] = $value;

        return $this;
    }

    public function join($collection, $leftField, $rightField) {
        $this->joins[] = array(
                            'collection' => $collection,
                            'left' => $leftField,
                            'right' => $rightField
                        );

        return $this;
    }

    public function save(Entity $entity) {
        $update = !empty($entity->id);
        if($this->autoincrement !== -1 && property_exists($entity, 'id') && !$update) {
            $entity->id = $this->autoincrement;
            $this->autoincrement++;
        }
        $data = array();

        foreach($this->fields as $field) {
            $data[] = $entity->$field;
        }

        $rawData = implode('||', $data);

        $this->saveFile('./data/collections/' . $this->entity->getCollectionName() . '/' . $entity->{$this->fields[0]}, $rawData);

        if($update) {
            $this->cleanupIndex($entity);
        }
        $this->saveIndex($entity);
    }

    public function addField($name) {
        $this->fields[] = $name;
        $this->saveMeta();
    }

    public function addIndex($field) {
        if(in_array($field, $this->index)) {
            return;
        }
        $this->index[] = $field;
        $this->saveMeta();
        $this->createIndexDir($field);
        foreach($this->get() as $item) {
            $this->saveIndex($item, array($field));
        }
    }

    public function removeIndex($field) {
        if(!in_array($field, $this->index)) {
            return;
        }
        $this->removeIndexData($field);
        foreach($this->index as $key => $index) {
            if($index === $field) {
                unset($this->index[$key]);
            }
        }
        $this->saveMeta();
    }

    private function removeIndexData($field) {
        $files = scandir('./data/index/' . $this->entity->getCollectionName() . '/' . $field);

        foreach($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $this->removeFile('./data/index/' . $this->entity->getCollectionName() . '/' . $field . '/' . $file);
        }

        rmdir('./data/index/' . $this->entity->getCollectionName());
    }

    private function saveMeta() {
        foreach ($this->fields as $key => $field) {
            if(empty($field)) {
                unset($this->fields[$key]);
            }
        }
        foreach ($this->index as $key => $field) {
            if(empty($field)) {
                unset($this->index[$key]);
            }
        }

        $metaData = implode(';', $this->fields) . "\n" . implode(';', $this->index) . "\n" . $this->autoincrement;
        $this->saveFile('./data/meta/' . $this->entity->getCollectionName(), $metaData);
    }

    private function saveIndex(Entity $entity, $indices = array()) {
        if(empty($indices)) {
            $indices = $this->index;
        }
        foreach($indices as $index) {
            if(empty($index)) {
                continue;
            }
            if($this->fileExists('./data/index/' . $entity->getCollectionName() . '/' . $index . '/' . md5($entity->$index))) {
                $data = ";" . $entity->{$this->fields[0]};
                $this->saveFile('./data/index/' . $entity->getCollectionName() . '/' . $index . '/' . md5($entity->$index), $data, FILE_APPEND);
            } else {
                $data = $entity->{$this->fields[0]};
                $this->saveFile('./data/index/' . $entity->getCollectionName() . '/' . $index . '/' . md5($entity->$index), $data);
            }
        }
    }

    private function createIndexDir($field) {
        mkdir('./data/index/' . $this->entity->getCollectionName() . '/' . $field);
    }

    private function openFile($path) {
        if(isset($this->cache[$path])) {
            return $this->cache[$path];
        } else {
            $data = file_get_contents($path);
            $this->cache[$path] = $data;
            $this->checkCache();
            return $data;
        }
    }

    private function saveFile($path, $data, $options = null) {
        file_put_contents($path, $data, $options);
        unset($this->cache[$path]);
    }

    private function fileExists($path) {
        if(isset($this->cache[$path])) {
            return true;
        }
        return file_exists($path);
    }

    private function createEntityDirs() {
        mkdir('./data/collections/' . $this->entity->getCollectionName());
        mkdir('./data/index/' . $this->entity->getCollectionName());
    }

    public function disableAutoincrement() {
        $this->autoincrement = -1;
    }

    public function setAutoincrement($value = 1) {
        $this->autoincrement = $value;
    }

    public function __destruct() {
        if(!$this->collectionRemoved) {
            $this->saveMeta();
        }
    }

    private function cleanupIndex(Entity $entity) {
        foreach($this->index as $index) {
            if(empty($index)) {
                continue;
            }
            if($this->fileExists('./data/index/' . $entity->getCollectionName() . '/' . $index . '/' . md5($entity->$index))) {
                $data = explode(';', $this->openFile('./data/index/' . $entity->getCollectionName() . '/' . $index . '/' . md5($entity->$index)));

                $data = array_filter(array_diff($data, array($entity->{$this->fields[0]})));

                if(count($data) === 0) {
                    $this->removeFile('./data/index/' . $entity->getCollectionName() . '/' . $index . '/' . md5($entity->$index));
                } else {
                    $this->saveFile('./data/index/' . $entity->getCollectionName() . '/' . $index . '/' . md5($entity->$index), implode(';', $data));
                }
            }
        }
    }

    private function cleanupData(Entity $entity) {
        $this->removeFile('./data/collections/' . $entity->getCollectionName() . '/' . $entity->{$this->fields[0]});
    }

    private function removeFile($path) {
        unlink($path);
    }

    public function remove($entity) {
        $this->cleanupIndex($entity);
        $this->cleanupData($entity);
    }

    public function getCollectionName() {
        return $this->entity->getCollectionName();
    }

    private function joinItem($item) {
        if (!empty($this->joins)) {
            foreach ($this->joins as $join) {
                $joinItems = $join['collection']->filter($join['right'], $item->{$join['left']})->get();
                if (count($joinItems) > 1) {
                    $item->{$join['collection']->getCollectionName()} = $joinItems;
                } else {
                    $item->{$join['collection']->getCollectionName()} = array();
                }
            }
            return $item;
        }
        return $item;
    }

    public function truncate() {
        $items = $this->getFullCollection(get_class($this->entity));

        foreach($items as $item) {
            $this->remove($item);
        }
    }

    public function limit($limit) {
        $this->limit = $limit;
        return $this;
    }

    public function offset($offset) {
        $this->offset = $offset;
        return $this;
    }

    public function sort($field, $direction = 'ASC') {
        $this->sort = $field;
        $this->sortDirectionAscending = $direction === 'ASC';

        return $this;
    }

    private function cleanupQuery() {
        $this->filters = array();
        $this->joins = array();
        $this->limit = 0;
        $this->offset = 0;
        $this->sort = null;
        $this->sortDirectionAscending = true;
    }

    public function clearCache() {
        $this->cache = array();
    }

    public function deleteCollection() {
        $this->truncate();
        rmdir('./data/collections/' . $this->entity->getCollectionName());

        foreach ($this->index as $field) {
            rmdir('./data/index/' . $this->entity->getCollectionName() . '/' . $field);
        }
        rmdir('./data/index/' . $this->entity->getCollectionName());

        $this->removeFile('./data/meta/' . $this->entity->getCollectionName());

        $this->collectionRemoved = true;
    }

    private function checkCache() {
        if(count($this->cache) > $this->cacheLimit && count($this->cache) % 10 === 0) {
            $removeCount = count($this->cache) - $this->cacheLimit;
            foreach ($this->cache as $key => $item) {
                unset($this->cache[$key]);
                $removeCount--;
                if($removeCount <= 0) {
                    return;
                }
            }
        }
    }

    private function getItemById($id, $entityClass) {
        if(!empty($this->itemCache[$id])) {
            return $this->itemCache[$id];
        } else {
            $item = new $entityClass;
            $itemData = explode("||", $this->openFile('./data/collections/' . $this->entity->getCollectionName() . '/' . $id));
            foreach ($itemData as $key => $field) {
                $item->{trim($this->fields[$key])} = $field;
            }
            $this->itemCache[$id] = $item;
            $this->checkItemCache();
            return $item;
        }
    }

    private function checkItemCache() {
        if(count($this->itemCache) > $this->itemCacheLimit && count($this->itemCache) % 10 === 0) {
            $removeCount = count($this->itemCache) - $this->itemCacheLimit;
            foreach ($this->itemCache as $key => $item) {
                unset($this->itemCache[$key]);
                $removeCount--;
                if($removeCount <= 0) {
                    return;
                }
            }
        }
    }

}