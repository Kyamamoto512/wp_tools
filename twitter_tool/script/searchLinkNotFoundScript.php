<?php
	define("INCLUDE_PATH", realpath(dirname(__FILE__))."/../include/");
	require_once INCLUDE_PATH."config.php";
	require_once INCLUDE_PATH."dbconn_mysql.php";
	require_once INCLUDE_PATH."dbconn_mysql2.php";
	require_once INCLUDE_PATH."twitteroauth.php";
	require_once INCLUDE_PATH."tmhOAuth.php";
	error_reporting(E_ALL & ~E_NOTICE);
   	ini_set('display_errors',1);

   $db = new DBConnection();

   require_once INCLUDE_PATH."config-t.php";
   $db2 = new DBConnection2();

   $sql = "SELECT GROUP_CONCAT(post_id) id FROM db_twitter_tool.dtb_not_found_link";
   $id = $db->rsexec($sql)->line();
   $id=$id["id"]?$id["id"]:0;


   $sql = "SELECT
		post.post_title,post.ID,post.post_content
		,CONCAT('".HOME_URL."',slug,'/',post.post_name,'.html')  post_url
		,term_taxonomy_id
		,post.post_date
		,image.guid image_url
		,meta2.meta_value tweet_text
		FROM wp_posts post
		JOIN wp_postmeta meta ON post.ID = meta.post_id
		JOIN wp_posts image ON image.ID = meta.meta_value
		JOIN wp_term_relationships wtr ON post.ID = wtr.object_id
		JOIN wp_term_taxonomy category USING(term_taxonomy_id)
		JOIN wp_terms wt USING(term_id)
		JOIN wp_postmeta meta2 ON post.ID = meta2.post_id
		WHERE  post.post_type = 'post'
		AND post.post_status IN ('publish','future','private')
		AND meta.meta_key = '_thumbnail_id' AND taxonomy = 'category' AND meta2.meta_key = 'snapTW'
		AND term_taxonomy_id NOT IN(208) AND category.parent = 0
		AND post.ID NOT IN({$id})
		#AND post.ID = '221'
		GROUP BY post.ID
		ORDER BY post.ID DESC";

   	for($rs = $db2->rsexec($sql); $row = $rs->line(); $rs->next()){


		preg_match_all('/src="http:\/\/[^tiffoo][\w\d\/%#$&?()~_.=+-]+"/',$row["post_content"],$match);

		$match = $match[0];
		//var_dump($match);

		foreach ($match as $val){
			$url = "";
			preg_match('/http:\/\/[^tiffoo][\w\d\/%#$&?()~_.=+-]+/',$val,$url);
	   		$header_params = @get_headers($url[0]);
	   		//var_dump($header_params[0]);

			// ここはswitch文で各ステータスコードで分岐する方法でもいいと思います。
			if($header_params[0] === 'HTTP/1.0 404 Not Found') {
				$sql = "INSERT INTO db_twitter_tool.dtb_not_found_link(post_id,post_title,post_url)
				 VALUES('{$row["ID"]}','{$db->sqlenc($row["post_title"])}','{$db->sqlenc($row["post_url"])}')";
				$db->execute($sql);
			 	//echo $url[0]." IS NG\n";
			 	continue 2;
			}
		}
		//echo $row["ID"]." IS OK\n";

	}

	$db->close();

