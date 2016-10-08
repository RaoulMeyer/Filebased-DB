<?php
/**
 * Created by PhpStorm.
 * User: Raoul
 * Date: 19/1/2016
 * Time: 7:10
 */

require('Autoload.php');

// Save any object that extends the Entity class

$book = new Book();
$book->author = "Test";
$book->title = "Title!";
$book->year = 1994;
$book->pageAmount = 3;

Database::save($book);

// Get all books saved

Database::getAll($book);

// Remove a book

Database::remove($book);