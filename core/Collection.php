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
    private $basedir = './';

    private $filters = array();
    private $joins = array();

    private $cache = array();
    private $cacheLimit = 100000;
    private $itemCache = array();
    private $itemCacheEnabled = true;
    private $itemCacheLimit = 10000;

    private $limit = 0;
    private $offset = 0;
    private $sort;
    private $sortDirectionAscending = true;

    private $collectionRemoved = false;

    private $settings = array(
        'db_cache_limit' => 'cacheLimit',
        'db_item_cache_enabled' => 'itemCacheEnabled',
        'db_item_cache_limit' => 'itemCacheLimit',
        'db_base_dir' => 'basedir',
    );


    /**
     * Load a new Collection
     *
     * @param Entity $entity The Entity for which a Collection should be loaded
     */
    public function __construct(Entity $entity) {
        $this->entity = $entity;

        if(!$this->fileExists('data/meta/' . $entity->getCollectionName())) {
            $this->generateCollection($entity);
        }

        $meta = explode("\n", $this->openFile('data/meta/' . $entity->getCollectionName()));

        $this->fields = explode(";", $meta[0]);
        $this->index = explode(";", $meta[1]);
        $this->autoincrement = $meta[2];

        $this->loadAllSettings();
    }

    /**
     * Generate a new Collection based on the Entity data
     *
     * @param Entity $entity The Entity for which a new Collection should be generated
     */
    private function generateCollection(Entity $entity) {
	    $this->createBasicDirs();
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

    /**
     * Load all settings from the settings.cfg file
     */
    private function loadAllSettings() {
        foreach ($this->settings as $name => $field) {
            $this->loadSetting($name, $field);
        }
    }

    /**
     * Load a setting from the settings.cfg file
     *
     * @param string $settingName Name of the setting
     * @param string $field Field of this class to save the setting in
     */
    private function loadSetting($settingName, $field) {
        if (($value = Settings::getSetting($settingName)) !== null) {
            $this->$field = $value;
        }
    }

    /**
     * Get all items from the collection
     * Items are filtered by all filters set and sorted as specified
     * After getting all items, both filters and sort values are cleared
     *
     * @return array An array containing all data
     */
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

    /**
     * Retrieve the full collection, possibly sorting it
     *
     * @param string $entityClass Class name of the entity
     *
     * @return array An array containing the full collection
     */
    private function getFullCollection($entityClass) {
        $data = array();
        $files = scandir($this->getBasedir() . 'data/collections/' . $this->entity->getCollectionName());

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

        if (!empty($this->sort)) {
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

    /**
     * Get a single item using the primary identifier as a filter
     *
     * @param string $entityClass Class name of the entity
     *
     * @return array An array containing a single element which matches the filtered primary identifier
     */
    private function getPrimaryIndexCollection($entityClass) {
        if (!$this->fileExists('data/collections/' . $this->entity->getCollectionName() . '/' . ($this->filters[$this->fields[0]]))) {
            return null;
        }

        $item = $this->getItemById($this->filters[$this->fields[0]], $entityClass);

        $item = $this->joinItem($item);

        $this->cleanupQuery();

        return array($item);
    }

    /**
     * Get all items matching the filters given
     *
     * @param string $entityClass Class name of the entity
     *
     * @return array An array containing all elements matching the filters given
     */
    private function getFilterCollection($entityClass) {
        $filteredKeys = array();
        foreach ($this->filters as $field => $value) {
            if (!$this->fileExists('data/index/' . $this->entity->getCollectionName() . '/' . $field . '/' . md5($value))) {
                return array();
            }
            $indexData = explode(";", $this->openFile('data/index/' . $this->entity->getCollectionName() . '/' . $field . '/' . md5($value)));

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

    /**
     * Add a filter to the list of filters
     *
     * @param string $field Field to be filtered
     * @param mixed $value Value to filter by
     *
     * @return Collection $this Returns the collection
     */
    public function filter($field, $value) {
        $this->filters[$field] = $value;

        return $this;
    }

    /**
     * Add a join to the list of joins
     *
     * @param Collection $collection The collection to join with
     * @param string $leftField The name of the field from the current Collection which should be used to join
     * @param string $rightField The name of the field from the joined Collection which should be used to join
     * @return Collection $this Returns the collection
     */
    public function join($collection, $leftField, $rightField) {
        $this->joins[] = array(
                            'collection' => $collection,
                            'left' => $leftField,
                            'right' => $rightField
                        );

        return $this;
    }

    /**
     * Save an item to the Collection
     *
     * @param Entity $entity The Entity which should be saved
     */
    public function save(Entity $entity) {
        $entity->beforeSave();

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

	    if ($update) {
		    $this->cleanupUpdatedIndex($entity, $this->getItemById($entity->id, get_class($entity)));
	    }

        $this->saveFile('data/collections/' . $this->entity->getCollectionName() . '/' . $entity->{$this->fields[0]}, $rawData);

        if($update) {
            $this->cleanupIndex($entity);
        }

        $this->saveIndex($entity);

        $this->itemCache[$entity->id] = $entity;

        $entity->afterSave();
    }

    /**
     * Add a field to the config of the Collection
     *
     * @param string $name Field name
     */
    public function addField($name) {
        $this->fields[] = $name;
        $this->saveMeta();
        $this->clearCache();
    }

    /**
     * Add an index to a field that already exists
     *
     * @param string $field Existing field name
     */
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

    /**
     * Remove an index from a field that already exists
     *
     * @param string $field Existing field name
     */
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

    /**
     * Remove all data associated with an index
     *
     * @param string $field Existing field name
     */
    private function removeIndexData($field) {
        $files = scandir($this->getBasedir() . 'data/index/' . $this->entity->getCollectionName() . '/' . $field);

        foreach($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $this->removeFile('data/index/' . $this->entity->getCollectionName() . '/' . $field . '/' . $file);
        }

        rmdir($this->getBasedir() . 'data/index/' . $this->entity->getCollectionName());
    }

    /**
     * Save the config of the Collection
     */
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
        $this->saveFile('data/meta/' . $this->entity->getCollectionName(), $metaData);
    }

    /**
     * Rebuild one or more indices for a single Entity
     *
     * @param Entity $entity The entity for which the indices should be rebuild
     * @param array $indices An array of index names which should be rebuild. Defaults to all indices.
     */
    private function saveIndex(Entity $entity, $indices = array()) {
        if(empty($indices)) {
            $indices = $this->index;
        }
        foreach($indices as $index) {
            if(empty($index)) {
                continue;
            }
            if($this->fileExists('data/index/' . $entity->getCollectionName() . '/' . $index . '/' . md5($entity->$index))) {
                $data = ";" . $entity->{$this->fields[0]};
                $this->saveFile('data/index/' . $entity->getCollectionName() . '/' . $index . '/' . md5($entity->$index), $data, FILE_APPEND);
            } else {
                $data = $entity->{$this->fields[0]};
                $this->saveFile('data/index/' . $entity->getCollectionName() . '/' . $index . '/' . md5($entity->$index), $data);
            }
        }
    }

    /**
     * Create the directory for an index
     *
     * @param string $field Field name
     */
    private function createIndexDir($field) {
        mkdir($this->getBasedir() . 'data/index/' . $this->entity->getCollectionName() . '/' . $field);
    }

    /**
     * Open a file by path
     * This method caches already opened files and checks cache status
     *
     * @param string $path File path
     *
     * @return string The raw data as a string
     */
    private function openFile($path) {
        $path = $this->getBasedir() . $path;
        if(isset($this->cache[$path])) {
            return $this->cache[$path];
        } else {
            $data = file_get_contents($path);
            $this->cache[$path] = $data;
            $this->checkCache();
            return $data;
        }
    }

    /**
     * Save data to a file by path
     * This method clears the cache for any file saved
     *
     * @param string $path File path
     * @param string $data Data to be saved to file
     * @param int $options Optional flags for file_put_contents
     */
    private function saveFile($path, $data, $options = null) {
        $path = $this->getBasedir() . $path;
        file_put_contents($path, $data, $options);
        if (isset($this->cache[$path])) {
            unset($this->cache[$path]);
        }
    }

    /**
     * Check if a file exists by path
     * Uses cache to determine if a file exists
     *
     * @param string $path File path
     *
     * @return bool File existing
     */
    private function fileExists($path) {
        $path = $this->getBasedir() . $path;
        if (isset($this->cache[$path])) {
            return true;
        }
        return file_exists($path);
    }

	/**
	 * Create basic directories
	 */
	private function createBasicDirs() {
        $base = $this->getBasedir();
		if (!is_dir($base . 'data')) {
			mkdir($base . 'data');
		}
		if (!is_dir($base . 'data/collections')) {
			mkdir($base . 'data/collections');
		}
		if (!is_dir($base . 'data/index')) {
			mkdir($base . 'data/index');
		}
		if (!is_dir($base . 'data/meta')) {
			mkdir($base . 'data/meta');
		}
	}

    /**
     * Create directories for the Collection
     */
    private function createEntityDirs() {
        mkdir($this->getBasedir() . 'data/collections/' . $this->entity->getCollectionName());
        mkdir($this->getBasedir() . 'data/index/' . $this->entity->getCollectionName());
    }

    /**
     * Enable auto increment
     */
    public function enableAutoincrement() {
        $this->setAutoincrement();
    }

    /**
     * Disable auto increment
     */
    public function disableAutoincrement() {
        $this->autoincrement = -1;
    }

    /**
     * Set the auto increment value. Any positive value enables auto increment.
     *
     * @param int $value New auto increment value. Defaults to 1, which enables auto increment.
     */
    public function setAutoincrement($value = 1) {
        $this->autoincrement = $value;
    }

    /**
     * Save config on destruct
     */
    public function __destruct() {
        if(!$this->collectionRemoved) {
            $this->saveMeta();
        }
    }

    /**
     * Clear all index values for an Entity
     *
     * @param Entity $entity Entity for which to clear index values
     */
    private function cleanupIndex(Entity $entity) {
        foreach($this->index as $index) {
            if(empty($index)) {
                continue;
            }
            if($this->fileExists('data/index/' . $entity->getCollectionName() . '/' . $index . '/' . md5($entity->$index))) {
                $data = explode(';', $this->openFile('data/index/' . $entity->getCollectionName() . '/' . $index . '/' . md5($entity->$index)));

                $data = array_filter(array_diff($data, array($entity->{$this->fields[0]})));

                if(count($data) === 0) {
                    $this->removeFile('data/index/' . $entity->getCollectionName() . '/' . $index . '/' . md5($entity->$index));
                } else {
                    $this->saveFile('data/index/' . $entity->getCollectionName() . '/' . $index . '/' . md5($entity->$index), implode(';', $data));
                }
            }
        }
    }

    /**
     * Clear all data for an Entity
     *
     * @param Entity $entity Entity for which to clear data
     */
    private function cleanupData(Entity $entity) {
        if (!empty($entity->{$this->fields[0]})) {
            $this->removeFile('data/collections/' . $entity->getCollectionName() . '/' . $entity->{$this->fields[0]});
        }
    }

    /**
     * Remove a file
     * This method clears the cache for any file removed
     *
     * @param string $path File path
     */
    private function removeFile($path) {
        $path = $this->getBasedir() . $path;
        unlink($path);

        if (isset($this->cache[$path])) {
            unset($this->cache[$path]);
        }
    }

    /**
     * Remove an entity from the Collection
     *
     * @param Entity $entity Entity to be removed
     */
    public function remove(Entity $entity) {
        $entity->beforeRemove();

        if (empty($entity->{$this->fields[0]})) {
            return;
        }

        $this->cleanupIndex($entity);
        $this->cleanupData($entity);

        unset($this->itemCache[$entity->id]);

        $entity->afterRemove();
    }


    /**
     * Wrapper for $this->entity->getCollectionName()
     *
     * @return string Name of Collection as set in Entity class
     */
    public function getCollectionName() {
        return $this->entity->getCollectionName();
    }

    /**
     * Actually join the item with all joins set
     *
     * @param Entity $item Item to join
     *
     * @return Entity Same item but now with all joined fields added
     */
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

    /**
     * Truncate the Collection
     * This removes all data
     */
    public function truncate() {
        $items = $this->getFullCollection(get_class($this->entity));

        foreach($items as $item) {
            $this->remove($item);
        }

        $this->clearCache();
    }

    /**
     * Limit the amount of items which are returned
     *
     * @param int $limit Limit amount
     *
     * @return Collection $this The current Collection
     */
    public function limit($limit) {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Offset the items which are returned
     *
     * @param int $offset Offset amount
     *
     * @return Collection $this The current Collection
     */
    public function offset($offset) {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Sort by a field in a certain direction
     *
     * @param string $field Field name
     * @param string $direction Direction to sort. ASC for ascending, anything else for descending. Defaults to ascending.
     *
     * @return Collection $this The current Collection
     */
    public function sort($field, $direction = 'ASC') {
        $this->sort = $field;
        $this->sortDirectionAscending = $direction === 'ASC';

        return $this;
    }

    /**
     * Reset all parameters set for current query
     */
    private function cleanupQuery() {
        $this->filters = array();
        $this->joins = array();
        $this->limit = 0;
        $this->offset = 0;
        $this->sort = null;
        $this->sortDirectionAscending = true;
    }

    /**
     * Fully clear the cache
     */
    public function clearCache() {
        $this->cache = array();
        $this->itemCache = array();
    }

    /**
     * Delete this Collection, removing all data and the config
     */
    public function deleteCollection() {
        $this->truncate();
        rmdir($this->getBasedir() . 'data/collections/' . $this->entity->getCollectionName());

        foreach ($this->index as $field) {
            rmdir($this->getBasedir() . 'data/index/' . $this->entity->getCollectionName() . '/' . $field);
        }
        rmdir($this->getBasedir() . 'data/index/' . $this->entity->getCollectionName());

        $this->removeFile('data/meta/' . $this->entity->getCollectionName());

        $this->clearCache();

        $this->collectionRemoved = true;
    }

    /**
     * Validate cache, clearing all cached items which exceed the max amount of cached items
     */
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

    /**
     * Get an item by primary identifier.
     * Caches the Entity objects for better performance
     *
     * @param int $id Primary identifier
     * @param string $entityClass Entity name
     *
     * @return Entity The (cached) Entity object
     */
    private function getItemById($id, $entityClass) {
        if($this->itemCacheEnabled && !empty($this->itemCache[$id])) {
            return $this->itemCache[$id];
        } else {
            $item = new $entityClass;
            $itemData = explode("||", $this->openFile('data/collections/' . $this->entity->getCollectionName() . '/' . $id));
            foreach ($itemData as $key => $field) {
                $item->{trim($this->fields[$key])} = $field;
            }
            if ($this->itemCacheEnabled) {
                $this->itemCache[$id] = $item;
                $this->checkItemCache();
            }
            return $item;
        }
    }

    /**
     * Validate cache, clearing all cached items which exceed the max amount of cached items
     */
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

	private function cleanupUpdatedIndex(Entity $newEntity, Entity $oldEntity)
	{
		foreach($this->index as $index) {
			if(empty($index)) {
				continue;
			}
			if ($oldEntity->$index != $newEntity->$index) {
				if($this->fileExists('data/index/' . $oldEntity->getCollectionName() . '/' . $index . '/' . md5($oldEntity->$index))) {
					$data = explode(';', $this->openFile('./data/index/' . $oldEntity->getCollectionName() . '/' . $index . '/' . md5($oldEntity->$index)));

					$data = array_filter(array_diff($data, array($oldEntity->{$this->fields[0]})));

					if(count($data) === 0) {
						$this->removeFile('data/index/' . $oldEntity->getCollectionName() . '/' . $index . '/' . md5($oldEntity->$index));
					} else {
						$this->saveFile('data/index/' . $oldEntity->getCollectionName() . '/' . $index . '/' . md5($oldEntity->$index), implode(';', $data));
					}
				}
			}
		}
	}

    /**
     * Get the basedir set
     *
     * @return string Basedir
     */
    private function getBasedir() {
        return $this->basedir;
    }

}