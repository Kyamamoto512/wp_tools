<?php
	//エラーを表示
	ini_set( 'display_errors', 1 );
	//読み込み色々
	$root_path = realpath(dirname(__FILE__))."/../html/";
	require_once($root_path.'/../include/config.php');
	//DB接続
	try {
		global $pdo;
		$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME,DB_USER,DB_PSWD,
		array(PDO::ATTR_EMULATE_PREPARES => false,PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
	} catch (PDOException $e) {
		exit('database translation error ');
	}

	require_once($root_path.'/../include/functions.php');
