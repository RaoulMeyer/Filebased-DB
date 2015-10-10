<?php
/**
 * Created by PhpStorm.
 * User: Raoul
 * Date: 10/10/2015
 * Time: 17:54
 */

function __autoload($class) {
    $paths = array('./', './core/', './entities/');
    foreach($paths as $path) {
        if(file_exists($path . $class . '.php')) {
            include($path . $class . '.php');
            return;
        }
    }
}

$db = new Database();
$collection = $db->get(new User());

//$collection->addField('password');
//
//foreach($collection->get() as $item) {
//    $item->password = md5($item->username);
//    $collection->save($item);
//}
//
//$collection->addIndex('password');
//
//die();

//for($i = 1; $i < 1000; $i++) {
//    $user = new User();
//    $user->id = $i;
//    $user->username = 'test' . $i;
//    $user->email = 'wow' . $i . '@test.nl';
//
//    $collection->save($user);
//}

//$collection->addIndex('email');

$start = microtime(true);

for($i = 0; $i < 1000000; $i++) {
    $item = $collection->filter('username', 'test' . rand(1, 1000))->get();
}
$end = microtime(true);

echo 'TIME: ' . ($end - $start);
