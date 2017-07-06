<?php
	define("INCLUDE_PATH", realpath(dirname(__FILE__))."/../include/");
	require_once INCLUDE_PATH."config-t.php";
	require_once INCLUDE_PATH."dbconn_mysql2.php";
	error_reporting(E_ALL & ~E_NOTICE);
   	ini_set('display_errors',1);

   $db = new DBConnection2();

   $sql = "SELECT ID
			FROM wp_posts post
			WHERE post.post_type = 'post' AND post_status = 'future'
			AND ID NOT IN(SELECT post_id FROM wp_postmeta WHERE meta_key = 'wheredidtheycomefrom')";

   for($rs = $db->rsexec($sql); $row = $rs->line(); $rs->next()){

   		$sql ="SELECT
				CASE
						WHEN  MIN(count) > 2 THEN term_id
						ELSE GROUP_CONCAT(term_id)
				END term_id
				FROM wp_term_relationships tr
				JOIN wp_term_taxonomy tt USING(term_taxonomy_id)
				JOIN wp_terms tm USING(term_id)
				WHERE tt.taxonomy ='post_tag'
				AND object_id ={$row["ID"]}";
   		$tag_id = $db->rsexec($sql)->line();

   		if(empty($tag_id["term_id"]))continue;

   		$sql = "SELECT object_id
				FROM wp_term_relationships tr
				JOIN wp_term_taxonomy tt USING(term_taxonomy_id)
				JOIN wp_terms tm USING(term_id)
				WHERE tt.taxonomy ='post_tag'
				AND tm.term_id IN ({$tag_id["term_id"]})
				ORDER BY RAND() LIMIT 5";

		$post_id =null;
	   	for($rs2 = $db->rsexec($sql); $row2 = $rs2->line(); $rs2->next()){
	   		if($row["ID"]!=$row2["object_id"]){
	   			$post_id[] = $row2["object_id"];
	   		}

		}

		if(empty($post_id))continue;

   		$post_id =serialize($post_id);


   		$sql = "INSERT wp_postmeta(post_id,meta_key,meta_value) VALUES('{$row["ID"]}','wheredidtheycomefrom','{$db->sqlenc($post_id)}')";
   		$db->execute($sql);
   		//var_dump($sql);exit();

   }