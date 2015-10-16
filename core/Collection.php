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

    public function __construct(Entity $entity) {
        $this->entity = $entity;

        if(!$this->fileExists('./data/meta/' . $entity->getCollectionName())) {
            $this->generateCollection($entity);
        }

        $meta = explode("\n", $this->openFile('./data/meta/' . $entity->getCollectionName()));

        $this->fields = explode(";", $meta[0]);
        $this->index = explode(";", $meta[1]);
        $this->autoincrement = $meta[2];
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

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $item = new $entityClass;
            $itemData = explode("||", $this->openFile('./data/collections/' . $this->entity->getCollectionName() . '/' . $file));
            foreach ($itemData as $key => $field) {
                $item->{trim($this->fields[$key])} = $field;
            }

            $item = $this->joinItem($item);

            $data[] = $item;
        }

        return $data;
    }

    private function getPrimaryIndexCollection($entityClass) {
        $item = new $entityClass;

        if (!$this->fileExists('./data/collections/' . $this->entity->getCollectionName() . '/' . ($this->filters[$this->fields[0]]))) {
            return null;
        }

        $itemData = explode("||", $this->openFile('./data/collections/' . $this->entity->getCollectionName() . '/' . ($this->filters[$this->fields[0]])));

        foreach ($itemData as $key => $field) {
            $item->{trim($this->fields[$key])} = $field;
        }

        $item = $this->joinItem($item);

        $this->filters = array();

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

        foreach ($filteredKeys as $file) {
            $item = new $entityClass;
            $itemData = explode("||", $this->openFile('./data/collections/' . $this->entity->getCollectionName() . '/' . $file));
            foreach ($itemData as $key => $field) {
                $item->{trim($this->fields[$key])} = $field;
            }

            $item = $this->joinItem($item);

            $data[] = $item;
        }

        $this->filters = array();

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
        $this->saveMeta();
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
        $this->removeFile('./data/collections/' . $this->entity->getCollectionName());
        $this->removeFile('./data/index/' . $this->entity->getCollectionName());

        $this->createEntityDirs();
    }

}