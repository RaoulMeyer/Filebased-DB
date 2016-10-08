<?php
/**
 * Created by PhpStorm.
 * User: Raoul
 * Date: 17/10/2015
 * Time: 11:36
 */

require('Autoload.php');

echo "Preparing benchmark...\n";

$collection = Database::get(new BenchmarkEntity());

$collection->truncate();

echo "Starting.\n";

$start = microtime(true);
for($i = 0; $i < 10000; $i++){
    $bench = new BenchmarkEntity();
    $bench->integer = rand(0, 1000);
    $bench->string = "test" . rand(0, 1000);
    $bench->time = microtime(true);
    $collection->save($bench);
}
$end = microtime(true);

echo 'Save 10000 random records: ' . ($end - $start) . "\n";

$start = microtime(true);
for($i = 0; $i < 10000; $i++) {
    $item = $collection->filter('id', rand(0, 1000))->get();
    $collection->clearCache();
}
$end = microtime(true);

echo 'Get 1000 records by primary key: ' . (($end - $start) / 10) . "\n";

$collection->clearCache();
$start = microtime(true);
for($i = 0; $i < 10000; $i++) {
    $item = $collection->filter('string', "test" . rand(0, 1000))->get();
    $collection->clearCache();
}
$end = microtime(true);

echo 'Get 1000 records by string key: ' . (($end - $start) / 10) . "\n";

$collection->clearCache();
$start = microtime(true);
for($i = 0; $i < 1000; $i++) {
    $item = $collection->sort('string')->get();
}
$end = microtime(true);

echo 'Get 1000 records and sort by string: ' . ($end - $start) . "\n";


$collection->clearCache();
$start = microtime(true);
for($i = 0; $i < 1000; $i++) {
    $items = $collection->sort('integer')->get();
}
$end = microtime(true);

echo 'Get 1000 records and sort by integer: ' . ($end - $start) . "\n";

$start = microtime(true);
$collection->truncate();
$end = microtime(true);

echo 'Truncate 1000 records: ' . ($end - $start) . "\n";

echo "Cleaning up... ";

$collection->deleteCollection();

echo "Done.";
