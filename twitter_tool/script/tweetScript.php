<?php
	define("INCLUDE_PATH", realpath(dirname(__FILE__))."/../include/");
	require_once INCLUDE_PATH."config.php";
	require_once INCLUDE_PATH."dbconn_mysql.php";
	require_once INCLUDE_PATH."twitteroauth.php";
	require_once INCLUDE_PATH."tmhOAuth.php";
	error_reporting(E_ALL & ~E_NOTICE);
   ini_set('display_errors',1);

	$db = new DBConnection();


	//テスト環境用
	$sql = "SELECT tweet_id,tweet_text,tweet_time,image_path,Consumer_key,Consumer_secret,Access_token,Access_token_secret,cycle_flag,cycle_time,face_flag,ti.face_icon_id,face_icon,error_count,error_date_log
			FROM dtb_tweet_info ti INNER JOIN dtb_account_info ai ON  ti.account_id=ai.account_id LEFT JOIN dtb_face_icon_info fii ON ti.face_icon_id = fii.face_icon_id
				WHERE
					(NOW()  >= tweet_time AND tweet_time > NOW()  - INTERVAL 10 MINUTE)
				AND
				 	((cycle_flag = 1 AND (tweet_time >= start_date AND tweet_time <= end_date + INTERVAL 10 MINUTE)) OR cycle_flag = 0) ";

	/*
	$sql = "SELECT tweet_id,tweet_text,tweet_time,image_path,Consumer_key,Consumer_secret,Access_token,Access_token_secret,cycle_flag,cycle_time,face_flag,ti.face_icon_id,face_icon,error_count,error_date_log
			FROM dtb_tweet_info ti INNER JOIN dtb_account_info ai ON  ti.account_id=ai.account_id LEFT JOIN dtb_face_icon_info fii ON ti.face_icon_id = fii.face_icon_id
				WHERE
					(NOW() + INTERVAL 9 HOUR >= tweet_time AND tweet_time > NOW() + INTERVAL 9 HOUR - INTERVAL 1 MINUTE)
				AND
				 	((cycle_flag = 1 AND (tweet_time >= start_date AND tweet_time <= end_date + INTERVAL 1 MINUTE)) OR cycle_flag = 0) ";
	*/

	for($rs = $db->rsexec($sql); $row = $rs->line(); $rs->next()){
		$tweet_info[] = $row;
	}

	//var_dump($tweet_info);

	if(!empty($tweet_info)){
		foreach($tweet_info as $val){

			$twConf = array(
		    'consumer_key'    => $val['Consumer_key'],
		    'consumer_secret' => $val['Consumer_secret'],
		    'user_token'      => $val['Access_token'],
		    'user_secret'     => $val['Access_token_secret'],
		    'curl_ssl_verifypeer' => false
			);

			$message = $val['tweet_text'];
			//重複投稿を避けるための文末に顔文字をランダムでつける処理
			if(!empty($val['face_flag']) && $val['face_flag']==1){
				$message .= $val['face_icon'];
				while($val['face_icon_id'] == $other_face_icon_id = rand(1,10)){}
				$sql ="UPDATE dtb_tweet_info SET face_icon_id =".$other_face_icon_id." WHERE tweet_id =".$val['tweet_id'];
				$db->execute($sql);

			}

			//画像あり投稿処理
			$tmhOAuth = new tmhOAuth($twConf);
			$endpoint="";
			$params="";
			if(!empty($val['image_path'])){
				$image = DOCROOT_PATH."img/upload/".$val['image_path'];

				$endpoint = $tmhOAuth->url('1.1/statuses/update_with_media');
				$imageName  = basename($image);
				$params = array(
				    'media[]'  => "@{$image};type=image/jpeg;filename={$imageName}",
				    'status'   => "{$message}"
				);
			//画像なし投稿処理
			}else{
			    $endpoint = $tmhOAuth->url('/1.1/statuses/update');
			    $params = array('status' => "{$message}");
			}



			//投稿がされたかの確認処理
		    $code = $tmhOAuth->request('POST', $endpoint, $params, true, true);



			if ($tmhOAuth->response["code"] == 200){ // $codeにもステータスは返ってきます
				//var_dump($tmhOAuth->response["response"]);

				$send_error_flag =" send_error_flag = 0";
				$sql = "UPDATE dtb_tweet_info SET ".$send_error_flag." WHERE tweet_id =".$val['tweet_id'];
				$db->execute($sql);
				$error_flag_for_cycle = TRUE; //定期ツイートの判定用の変数

			} elseif($val['error_count'] < 5) {
				var_dump($tmhOAuth->response["error"]);

				if($val['error_count']==0){
					$error_date_log = ", error_date_log = '".$val['tweet_time']."'";
				}


				$send_error_flag =", send_error_flag = 1";
				$error_count = ", error_count=".++$val['error_count'];

				if($val['error_count']==5){
					$error_date_log .= ",error_log ='".$db->sqlenc($tmhOAuth->response["error"])."'";
				}

				$sql = "UPDATE dtb_tweet_info SET tweet_time = tweet_time + INTERVAL 10 MINUTE ".$send_error_flag.$error_count.$error_date_log." WHERE tweet_id =".$val['tweet_id'];
				$db->execute($sql);
				$error_flag_for_cycle = FALSE;
				//var_dump($sql);
			}

			//定期ツイート処理
			if($val['cycle_flag']==1 && ($val['error_count'] >= 5 || $error_flag_for_cycle)){
				$sql ="UPDATE dtb_tweet_info SET tweet_time = tweet_time + INTERVAL ".$val['cycle_time']." MINUTE - INTERVAL ".$val['error_count']." MINUTE, error_count = 0 WHERE tweet_id =".$val['tweet_id'];
				$db->execute($sql);
				//var_dump($sql);
			}
		}
	}
	$db->close();
?>
