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

   $sql = "SELECT * FROM db_twitter_tool.dtb_".SITE_NAME."_category";
   $category_list = array();
    for($rs = $db->rsexec($sql); $row = $rs->line(); $rs->next()){
		$category_list[$row["category_id"]]=$row["account_id"];
	}

	$sql = "SELECT GROUP_CONCAT(post_id) id_list FROM db_twitter_tool.dtb_tweet_post
			WHERE posted_date > NOW() - INTERVAL 2 DAY";
	$published_post = $db->rsexec($sql)->line();


	$published_post = !empty($published_post["id_list"])?$published_post["id_list"]:0;

   $sql = "SELECT DISTINCT
		post.post_title,post.ID
		,CONCAT('".HOME_URL."',post.post_name,'.html')  post_url
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
		AND post.post_date < NOW()
		AND post.post_date > NOW()  - INTERVAL 2 DAY
		AND post.post_status = 'publish'
		AND meta.meta_key = '_thumbnail_id' AND taxonomy = 'category' AND meta2.meta_key = 'snapTW'  #AND post.ID =58523
		AND term_taxonomy_id NOT IN(208) AND category.parent = 0
		AND post.ID NOT IN ({$published_post})
		#AND post.post_modified > NOW() - INTERVAL 5 DAY
		GROUP BY meta2.meta_value";

   	for($rs = $db2->rsexec($sql); $row = $rs->line(); $rs->next()){
   			$tmp = "";
   			foreach (unserialize(unserialize($row["tweet_text"])) as $val){
			 	if(strlen($val["SNAPformat"])>strlen($tmp)){
			 		$tmp = $val["SNAPformat"];
			 	}
		 	 }

		 	 if(empty($tmp)){
			 	 foreach (unserialize(unserialize($row["tweet_text"])) as $val){
	   				//var_dump($val);
				 	if($val["do"]==="1"){
				 		$tmp = $val["msgFormat"];
				 	}
			 	 }
		 	 }

		 	 $row["tweet_text"] = preg_replace('/%URL%|%SURL%/',$row["post_url"],str_replace('%TITLE%',$row["post_title"],$tmp));


		  /*
		 $sql = "SELECT post_id FROM dtb_tweet_post
					WHERE post_id = '{$row["ID"]}'";
		 $post_id = $db->rsexec($sql)->line();
		 $post_id = $post_id["post_id"];

		 if($post_id==$row["ID"])continue;
		 */;

		 $sql = "SELECT * FROM db_twitter_tool.dtb_account_info WHERE account_id IN('{$category_list[$row["term_taxonomy_id"]]}',0) ORDER BY account_id DESC";


		 for($account_data = $db->rsexec($sql); $account_info = $account_data->line(); $account_data->next()){


			 $twConf = array(
			    'consumer_key'    => $account_info['Consumer_key'],
			    'consumer_secret' => $account_info['Consumer_secret'],
			    'user_token'      => $account_info['Access_token'],
			    'user_secret'     => $account_info['Access_token_secret'],
			    'curl_ssl_verifypeer' => false
				);

				$message = preg_replace('/html\?[a-z0-9_]+/','html',$row['tweet_text']);
				echo $message;

				$tmhOAuth = new tmhOAuth($twConf);

				//画像ありは廃止
				if(0&&!empty($row['image_url'])){
					$image = $row['image_url'];
					$file = file_get_contents($row['image_url']);

					$endpoint = $tmhOAuth->url('1.1/statuses/update_with_media');
					$imageName  = basename($image);
					$imagesize = getimagesize( $row['image_url'] );
					$params = array(
					    'media[]'  => "{$file};type={$imagesize['mime']};filename={$imageName}",
					    'status'   => $message
					);
					//var_dump($file);
				//画像なし投稿処理
				}else{
				    $endpoint = $tmhOAuth->url('/1.1/statuses/update');
				    $params = array('status' => $message);
				}

			for($error_count = 0;$error_count<3;$error_count++){
				//投稿がされたかの確認処理
			    $code = $tmhOAuth->request('POST', $endpoint, $params, true, true);

		   		if ($tmhOAuth->response["code"] == 200){ // $codeにもステータスは返ってきます
					break;
				}else{
					echo $tmhOAuth->response["code"];
					sleep(10);
				}
			}
		}

		$row["tweet_text"] = str_replace('【新着記事】','',$row["tweet_text"]);
		 $sql = "INSERT IGNORE INTO db_twitter_tool.dtb_tweet_post(post_id,tweet_text,account_id,image_path,posted_date)
				 VALUES('{$row["ID"]}','{$db->sqlenc($row["tweet_text"])}','{$category_list[$row["term_taxonomy_id"]]}','{$row["image_url"]}','{$row["post_date"]}')";
		$db->execute($sql);

	}

