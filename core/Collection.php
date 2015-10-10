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

    private $filters = array();
    private $cache = array();

    public function __construct(Entity $entity) {
        $this->entity = $entity;

        if(!$this->fileExists('./data/meta/' . $entity->getCollectionName())) {
            throw new Exception();
        }

        $meta = explode("\n", $this->openFile('./data/meta/' . $entity->getCollectionName()));

        $this->fields = explode(";", $meta[0]);
        $this->index = explode(";", $meta[1]);
    }

    public function get() {
        $entityClass = get_class($this->entity);
        if(empty($this->filters)) {
            $data = array();
            $files = scandir('./data/collections/' . $this->entity->getCollectionName());

            foreach($files as $file) {
                if($file === '.' || $file === '..') {
                    continue;
                }
                $item = new $entityClass;
                $itemData = explode("||", $this->openFile('./data/collections/' . $this->entity->getCollectionName() . '/' . $file));
                foreach ($itemData as $key => $field) {
                    $item->{trim($this->fields[$key])} = $field;
                }
                $data[] = $item;
            }

            return $data;
        }

        if(!empty($this->filters[$this->fields[0]])) {
            $item = new $entityClass;

            if(!$this->fileExists('./data/collections/' . $this->entity->getCollectionName() . '/' . $this->filters[$this->fields[0]])) {
                return null;
            }

            $itemData = explode("||", $this->openFile('./data/collections/' . $this->entity->getCollectionName() . '/' . $this->filters[$this->fields[0]]));

            foreach ($itemData as $key => $field) {
                $item->{trim($this->fields[$key])} = $field;
            }

            $this->filters = array();

            return $item;
        }

        $filteredKeys = array();
        foreach($this->filters as $field => $value) {
            if(!$this->fileExists('./data/index/' . $this->entity->getCollectionName() . '/' . $field . '/' . $value)) {
                continue;
            }
            $indexData = explode(";", $this->openFile('./data/index/' . $this->entity->getCollectionName() . '/' . $field . '/' . $value));

            if(empty($filteredKeys)) {
                $filteredKeys = $indexData;
            } else {
                $filteredKeys = array_intersect($filteredKeys, $indexData);
            }
        }

        $data = array();

        foreach($filteredKeys as $file) {
            $item = new $entityClass;
            $itemData = explode("||", $this->openFile('./data/collections/' . $this->entity->getCollectionName() . '/' . $file));
            foreach ($itemData as $key => $field) {
                $item->{trim($this->fields[$key])} = $field;
            }
            $data[] = $item;
        }

        $this->filters = array();

        return $data;
    }

    public function filter($field, $value) {
        $this->filters[$field] = $value;

        return $this;
    }

    public function save(Entity $entity) {
        $data = array();

        foreach($this->fields as $field) {
            $data[] = $entity->$field;
        }

        $rawData = implode('||', $data);

        $this->saveFile('./data/collections/' . $this->entity->getCollectionName() . '/' . $entity->{$this->fields[0]}, $rawData);

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

        $metaData = implode(';', $this->fields) . "\n" . implode(';', $this->index);
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
            if($this->fileExists('./data/index/' . $entity->getCollectionName() . '/' . $index . '/' . $entity->$index)) {
                $data = "||" . $entity->{$this->fields[0]};
                $this->saveFile('./data/index/' . $index . '/' . $entity->$index, $data, FILE_APPEND);
            } else {
                $data = $entity->{$this->fields[0]};
                $this->saveFile('./data/index/' . $entity->getCollectionName() . '/' . $index . '/' . $entity->$index, $data);
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
        $this->cache = array();
    }

    private function fileExists($path) {
        if(isset($this->cache[$path])) {
            return true;
        }
        return file_exists($path);
    }
}