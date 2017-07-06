<?php
	define("INCLUDE_PATH", realpath(dirname(__FILE__))."/../include/");
	require_once INCLUDE_PATH."config.php";
	require_once INCLUDE_PATH."dbconn_mysql.php";
	require_once INCLUDE_PATH."twitteroauth.php";

	error_reporting(E_ALL & ~E_NOTICE);

	$year  = date('Y');
	$month = date('m');
	$day   = date('d');
	$hour   = date('H')+9;
	$minute = date('i');

	$db = new DBConnection();
	$sql = "SELECT reply_id,reply_date,reply_hour,reply_minute FROM dtb_reply_info
			WHERE reply_date='".$db->sqlenc($year.'-'.$month.'-'.$day)."' AND reply_hour='".$db->sqlenc($hour)."'";
	for($rs = $db->rsexec($sql); $row = $rs->line(); $rs->next()){
		$reply_info[] = $row;
	}
	$db->close();

	if($reply_info[0]['reply_id']>0){
		foreach($reply_info as $key => $value){
			switch($minute){
				case 0:
					if(0==$value['reply_minute']){
						execReply($value['reply_id']);
					}
					break;
				case 10:
					if(10==$value['reply_minute']){
						execReply($value['reply_id']);
					}
					break;
				case 20:
					if(20==$value['reply_minute']){
						execReply($value['reply_id']);
					}
					break;
				case 30:
					if(30==$value['reply_minute']){
						execReply($value['reply_id']);
					}
					break;
				case 40:
					if(40==$value['reply_minute']){
						execReply($value['reply_id']);
					}
					break;
				case 50:
					if(50==$value['reply_minute']){
						execReply($value['reply_id']);
					}
					break;
				default:
					break;
			}
		}
	}

	function execReply($reply_id){

		// 重複防ぎ用文字
		$ary_kigou = array(0=>'（▽д▽）',1=>'(*^_^*)',2=>'（☆ω☆）',3=>'(*´⌒`*)',4=>'ヽ(･∀･)ﾉ',5=>'o(･ω･´o)',6=>'ヾ(●´Д｀●)ﾉﾞ',7=>'(≧ω≦)',8=>'d（≧∀≦）b',9=>'♪(●＾U＾●)');
		$kigou_cnt = count($ary_kigou);

		$db = new DBConnection();

		// リプライ情報取得
		$sql = "SELECT ri.*,ai.Consumer_key,ai.Consumer_secret,ai.Access_token,ai.Access_token_secret FROM dtb_reply_info ri
							LEFT JOIN dtb_account_info ai ON ri.account_id=ai.account_id
				WHERE reply_id=".$reply_id;
		for($rs = $db->rsexec($sql); $row = $rs->line(); $rs->next()){
			$reply_info = $row;
		}

		if($reply_info['reply_id']>0){
			$consumer_key = $reply_info['Consumer_key'];
			$consumer_secret = $reply_info['Consumer_secret'];
			$access_token = $reply_info['Access_token'];
			$access_token_secret = $reply_info['Access_token_secret'];
			$to = new TwitterOAuth($consumer_key,$consumer_secret,$access_token,$access_token_secret);

			// リプライ対象ユーザー取得
			$sql = "SELECT search_hit_id FROM dtb_reply_user_info WHERE reply_id=".$db->sqlenc($reply_info['reply_id']);
			for($rs = $db->rsexec($sql); $row = $rs->line(); $rs->next()){
				$reply_list[] = $row['search_hit_id'];
			}
			foreach($reply_list as $key => $value){

				// 対象ユーザーの情報を取得
				$sql = "SELECT shi.tweet_id,tai.screen_name FROM dtb_search_hit_info shi
								LEFT JOIN dtb_twitter_account_info tai ON shi.twitter_account_id=tai.twitter_account_id
						WHERE shi.search_hit_id=".$db->sqlenc($value);
				for($rs = $db->rsexec($sql); $row = $rs->line(); $rs->next()){
					$reply_user_info = $row;
				}
				print_r($reply_user_info);
				$message = $reply_info['reply_text'];
				$tmp_message = $reply_info['reply_text'];

				// 重複チェック用に過去10件のリプライ履歴を取得する
				$reply_text = array();
				$sql = "SELECT reply_text FROM dtb_reply_history_info WHERE account_id=".$reply_info['account_id']." ORDER BY regist_date DESC LIMIT 10 OFFSET 0";
				for($rs = $db->rsexec($sql); $row = $rs->line(); $rs->next()){
					$tmp_reply_text = $row['reply_text'];
					$tmp_reply_text = preg_replace('/^@.+?\s/', '', $tmp_reply_text);
					$reply_text[] = $tmp_reply_text;
				}
				//print_r($reply_text);
				//echo "\n\n";

				// 重複チェック
				$error_flg = 0;
				foreach($reply_text as $key2 => $value2){
					if($tmp_message == $value2){
						$error_flg++;
						break;
					}
				}

				if($error_flg > 0){
					for($i=0;$i<$kigou_cnt;$i++){
						$tmp_message = $reply_info['reply_text'].$ary_kigou[$i];
						$error_flg = 0;
						foreach($reply_text as $key2 => $value2){
							if($tmp_message == $value2){
								$error_flg++;
								break;
							}
						}
						if($error_flg == 0){
							$message = $tmp_message;
							break;
						}
					}
				}
				$message = "@".$reply_user_info['screen_name']." ".$message;
				//echo $message."\n";
				// リプライ実行
				$req = $to->OAuthRequest("https://api.twitter.com/1.1/statuses/update.json","POST",array("status"=>"$message","in_reply_to_status_id"=>$reply_user_info['tweet_id']));
				print_r($req);

				// リプライを履歴に追加
				$sql = "INSERT INTO dtb_reply_history_info (reply_id,account_id,search_hit_id,reply_text,regist_date)
										VALUES(
											'".$reply_id."',
											'".$reply_info['account_id']."',
											'".$value."',
											'".$message."',
											DATE_ADD(now(), INTERVAL 9 hour)
										)";
				$db->execute($sql);

				$already_id = 0;
				// リプライ済みフラグを立てる
				$sql = "SELECT already_id FROM dtb_already_info WHERE reply_id=".$reply_id." AND search_hit_id=".$value;
				for($rs = $db->rsexec($sql); $row = $rs->line(); $rs->next()){
					$already_id = $row['already_id'];
				}

				if($already_id > 0){
					$sql = "UPDATE dtb_already_info SET already_flg=1 WHERE already_id=".$already_id;
					$db->execute($sql);
				}else{
					$sql = "INSERT INTO dtb_already_info (reply_id,search_hit_id,already_flg)
												VALUES(
													'".$reply_id."',
													'".$value."',
													'1'
												)";
					$db->execute($sql);
				}
				sleep(5);
			}
		}

		$db->close();
	}

?>