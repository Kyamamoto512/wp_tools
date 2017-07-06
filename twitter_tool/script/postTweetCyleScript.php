<?php
	define("INCLUDE_PATH", realpath(dirname(__FILE__))."/../include/");
	require_once INCLUDE_PATH."config.php";
	require_once INCLUDE_PATH."dbconn_mysql.php";
	require_once INCLUDE_PATH."twitteroauth.php";
	require_once INCLUDE_PATH."tmhOAuth.php";
	error_reporting(E_ALL & ~E_NOTICE);
   	ini_set('display_errors',1);

   $db = new DBConnection();

   $sql = "SELECT *
			FROM dtb_".SITE_NAME."_category
			JOIN dtb_post_log USING(account_id)
			JOIN dtb_account_info ai USING(account_id)
			WHERE FIND_IN_SET(HOUR(NOW()),tweet_hour)";

   	for($rs = $db->rsexec($sql); $row = $rs->line(); $rs->next()){

		 if(!empty($row["post_tweet_log"])){
		 	$where = " AND post_id NOT IN({$row["post_tweet_log"]})";
		 }

		 $sql = "SELECT *
					FROM dtb_tweet_post tp
					WHERE tp.tweet_flag=1 ";

		 $sql .=$row["account_id"]!=9?"AND account_id = '{$row["account_id"]}'":"";

		 $sql .=	" {$where}
					AND posted_date < NOW() - INTERVAL 5 DAY
					#AND 0
					ORDER BY RAND() LIMIT 1";

		 //echo $sql;exit();

		$tweet_info = $db->rsexec($sql)->line();

		$last_flag = 0;
		if(empty($tweet_info)){

			$sql = "SELECT *
					FROM dtb_tweet_post tp
					WHERE tp.tweet_flag=1 ";

			$sql .=	$row["account_id"]!=9?" AND account_id = '{$row["account_id"]}' ":"";

			$sql .= "AND posted_date < NOW() - INTERVAL 5 DAY
					ORDER BY RAND() LIMIT 1";
			$tweet_info = $db->rsexec($sql)->line();

			$last_flag = 1;
		}
		//var_dump($tweet_info);exit();

	   	if(!empty($row["post_tweet_log"])&&empty($last_flag)){
		 	$set = "CONCAT(post_tweet_log,',','{$tweet_info["post_id"]}')";
		 }else{
		 	$set ="'{$tweet_info["post_id"]}'";
		 }

			 $twConf = array(
			    'consumer_key'    => $row['Consumer_key'],
			    'consumer_secret' => $row['Consumer_secret'],
			    'user_token'      => $row['Access_token'],
			    'user_secret'     => $row['Access_token_secret'],
			    'curl_ssl_verifypeer' => false
				);

				$message = $tweet_info['tweet_text'];

				if($row['account_id']=='9')
					$message = preg_replace('/html\?[a-z0-9_]+/','html',$message);

				//echo $message;
				//画像あり投稿処理
				$tmhOAuth = new tmhOAuth($twConf);

				//画像ありは廃止
				if(0&&!empty($tweet_info['image_path'])){
					$image = $tweet_info['image_path'];
					$file = file_get_contents($tweet_info['image_path']);

					$endpoint = $tmhOAuth->url('1.1/statuses/update_with_media');
					$imageName  = basename($image);
					$imagesize = getimagesize( $tweet_info['image_path'] );
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

				$error_count = 0;
			while ($error_count < 7){
				//投稿がされたかの確認処理
			    $code = $tmhOAuth->request('POST', $endpoint, $params, true, true);

		   		if ($tmhOAuth->response["code"] == 200){ // $codeにもステータスは返ってきます
					break;
				}elseif($error_count>5){
					$endpoint = $tmhOAuth->url('/1.1/statuses/update');
				    $params = array('status' => $message);
				    $tmhOAuth->request('POST', $endpoint, $params, true, true);
				    $error_count++;
					break;
				}else{
					//echo $error_count;
					sleep(5);
					$error_count++;
				}
			}


		 $sql="UPDATE dtb_post_log
				SET post_tweet_log = {$set}
				WHERE account_id = '{$row["account_id"]}'";
		 //echo $sql;exit();
		 $db->execute($sql);

	}

