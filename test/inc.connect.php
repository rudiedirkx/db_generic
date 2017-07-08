<?php

// Init
error_reporting(E_ALL & ~E_STRICT);
header('Content-type: text/plain');
$start = microtime(1);

if ( isset($_GET['mysql']) ) {
	require '../db_mysql.php';
	$db = db_mysql::open(array('user' => $_GET['user'], 'pass' => $_GET['pass'], 'db' => $_GET['db']));
}
if ( isset($_GET['mysql2']) ) {
	require '../db_mysql_pdo.php';
	$db = db_mysql_pdo::open(array('user' => $_GET['user'], 'pass' => $_GET['pass'], 'db' => $_GET['db']));
}
else {
	require '../db_sqlite.php';
	$db = db_sqlite::open(array('database' => './stuff.sqlite3'));
}
