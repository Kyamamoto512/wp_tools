<?php
//DB操作ここから//
	function sub_connect(){
		try {
			global $pdo2;
			$pdo2 = new PDO('mysql:host='.DB2_HOST.';dbname='.DB2_NAME,DB2_USER,DB2_PSWD,
			array(PDO::ATTR_EMULATE_PREPARES => false,PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'));
		} catch (PDOException $e) {
			exit($e->getMessage());
		}
	}

	function sub_connect2(){
		try {
			global $pdo3;
			$pdo3 = new PDO('mysql:host='.DB3_HOST.';dbname='.DB3_NAME,DB3_USER,DB3_PSWD,
			array(PDO::ATTR_EMULATE_PREPARES => false,PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
		} catch (PDOException $e) {
			exit($e->getMessage());
		}
	}

	function test_connect(){
		try {
			global $pdo2;
			$pdo2 = new PDO('mysql:host='.DB4_HOST.';dbname='.DB4_NAME,DB4_USER,DB4_PSWD,
			array(PDO::ATTR_EMULATE_PREPARES => false,PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'));
		} catch (PDOException $e) {
			exit($e->getMessage());
		}
	}

	// 基本的にはこれ使う
	function getfetchAll($sql,$array=array(),&$pdo=0){
		if(empty($pdo)){
			global $pdo;
		}

		try {
			$sth = $pdo->prepare($sql);
			if(!$sth){
				throw new Exception("SQL Syntax Error:\"{$sql}\"");
			}
			$sth->execute($array);
			return $sth->fetchAll();
		} catch (Exception $e) {
			exit($e->getMessage());
		}
	}

	// 2列取得時に1列目がkey2列目がvalueになるように
	function getfetchKey($sql,$array=array(),&$pdo=0){
		if(empty($pdo)){
			global $pdo;
		}

		try {
			$sth = $pdo->prepare($sql);
			if(!$sth){
				throw new Exception("SQL Syntax Error:\"{$sql}\"");
			}
			$sth->execute($array);
			return $sth->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);
		} catch (Exception $e) {
			exit($e->getMessage());
		}
	}

	//1列のみ取得する用
	function getfetchCol($sql,$array=array(),&$pdo=0){
		if(empty($pdo)){
			global $pdo;
		}

		try {
			$sth = $pdo->prepare($sql);
			if(!$sth){
				throw new Exception("SQL Syntax Error:\"{$sql}\"");
			}
			$sth->execute($array);
			return $sth->fetchAll(PDO::FETCH_COLUMN,0);
		} catch (Exception $e) {
			exit($e->getMessage());
		}
	}

	//1行のみ取得する用
	function getfetchRow($sql,$array=array(),&$pdo=0){
		if(empty($pdo)){
			global $pdo;
		}

		try {
			$sth = $pdo->prepare($sql);
			if(!$sth){
				throw new Exception("SQL Syntax Error:\"{$sql}\"");
			}
			$sth->execute($array);
			return $sth->fetch(PDO::FETCH_ASSOC);
		} catch (Exception $e) {
			exit($e->getMessage());
		}
	}

	//１つ結果を取得する用
	function getfetchOne($sql,$array=array(),&$pdo=0){
		if(empty($pdo)){
			global $pdo;
		}

		try {
			$sth = $pdo->prepare($sql);
			if(!$sth){
				throw new Exception("SQL Syntax Error:\"{$sql}\"");
			}
			$sth->execute($array);
			return $sth->fetchColumn();
		} catch (Exception $e) {
			exit($e->getMessage());
		}
	}

	//データ更新系クエリー
	function setQuery($sql,$array=array(),&$pdo=0){
		if(empty($pdo)){
			global $pdo;
		}

		try {
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$pdo->beginTransaction();
			$sth = $pdo->prepare($sql);
			if(!$sth){
				throw new Exception("SQL Syntax Error:\"{$sql}\"");
			}
			foreach($array as $key => &$val){
				if($val===""){
					$val = null;
				}
				$sth->bindParam($key+1, $val);

			}
			$sth->execute();
			$pdo->commit();
		} catch (Exception $e) {
			$pdo->rollBack();
			echo $e->getMessage();
		}

		return 1;
	}
//DB操作ここまで//

	function convert_date($serial, $format = 'Y/m/d H:i'){
    	return gmdate($format, ($serial - 25569) * 60 * 60 * 24+1);
	}

	function h($str){
    	return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
	}

	function pre($str){
		echo "<pre>";
		echo var_dump($str);
		echo "</pre>";
	}