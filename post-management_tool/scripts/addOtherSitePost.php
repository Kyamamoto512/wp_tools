<?php
	define("INCLUDE_PATH", realpath(dirname(__FILE__))."/../include/");
	require_once INCLUDE_PATH."include.php";
	require_once INCLUDE_PATH."Feed.php";
	require_once INCLUDE_PATH.'phpQuery.php';

	define("DEBUG",false );

	//テスト環境接続
	//test_connect();

	//本番環境接続
	sub_connect();

	//error_reporting(E_ALL & ~E_NOTICE);

	global $pdo2;

   	$date_format = "Y-m-d H:i:s";

	$feedList = array();

	//id,サイト名、タグ名、feedURL、親カテゴリの順番でfeed情報をDBから取得

	$sql = "SELECT feed_id,site_name,tag,feed_url,category_id FROM feeds WHERE status = 1";

	$feedList = getFetchAll($sql);


	foreach ( (array)$feedList as $val ){

		list($feedID,$siteName,$tagName,$url,$catID) = $val;

		$feed = new Feed ;

		$rss = $feed->loadRss( $url );

		foreach( $rss->item as $item ){
			$pubDate = strtotime($item->pubDate);

			if($pubDate-time()+60*60*2 < 0 ){
				if(DEBUG)echo 1;
				continue;
			}

			$title = $item->title;
			$content = $item->{"content:encoded"};
			$link = $item->link;


			preg_match_all('/<p>.*?<\/p>|<h2.*?<\/h2>|<h3>.*?<\/h3>|<a .*?<\/a>/s',$content,$matches);

			//var_dump($matches);
			//exit;

			$content = '';
			$noHtmlContent = '';
			foreach ((array)$matches[0] as $match_val) {

				if(preg_match('/<h2>|<h3>/',$match_val) && mb_strlen($noHtmlContent,"UTF-8") > 200 ){
					break;
				}

				$match_val = str_replace('<h2','<h3',$match_val);
				$match_val = str_replace('</h2>','</h3>',$match_val);

				$content .= $match_val;
				$noHtmlContent .= strip_tags($match_val);

				if(mb_strlen($noHtmlContent,"UTF-8") > 370){
					break;
				}
			}

			$content = img_ssl($content);

			//うまく情報が取れていなかったら飛ばす
			if(empty($content) || empty($title) || empty($link) ){
				if(DEBUG)echo 2;
				continue;
			}

			$content .= " [otherSite name=".base64_encode($siteName)." link={$link} ]";


			//既に投稿されている可能性も無きにしも非ずなので一応
			$sql = "SELECT 1 FROM wp_posts WHERE post_content LIKE ?";

			if(!!getFetchOne($sql,array('%'.$link.'%'),$pdo2)){
				if(DEBUG)echo 3;
				continue;
			}

			//イメージ画像の取得
			$img = get_image($link);

			if(empty($img)){
				if(DEBUG)echo 4;
				continue;
			}


			$pubDateGmt = $pubDate - 60*60*9;

			$pubDate = date($date_format,$pubDate);
			$pubDateGmt = date($date_format,$pubDateGmt);

			//親記事の挿入

			$sql = "INSERT INTO wp_posts (
						post_title,
						post_content,
						post_date,
						post_date_gmt,
						post_status,
						ping_status,
						comment_status,
						post_name,
						post_modified,
						post_modified_gmt,
						post_type
					) VALUES (
						?,
						?,
						?,
						?,
						'publish',
						'closed',
						'closed',
						?,
						?,
						?,
						'post'
					)";
			$postName = strrev(substr(time(),-7,7)).rand(100,999);
			$param = array(
				$title,
				$content,
				$pubDate,
				$pubDateGmt,
				$postName,
				$pubDate,
				$pubDateGmt
			);

			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			//$pdo->beginTransaction();

			setQuery($sql,$param,$pdo2);

			//親記事のID取得
			$sql = "SELECT LAST_INSERT_ID()";

			$post_id = getfetchOne($sql,array(),$pdo2);

			if(!$post_id){
				if(DEBUG)echo 5;
				continue;
			}

			//postmetaの挿入
			$sql = "INSERT INTO wp_postmeta(post_id,meta_key,meta_value) VALUES (?,'otherSite',?)";

			$param = array(
				$post_id,
				$tagName
			);

			setQuery($sql,$param,$pdo2);

			//タグとカテゴリー登録
			$sql = "INSERT INTO wp_term_relationships(object_id,term_taxonomy_id)
					VALUES (?,
						(SELECT term_taxonomy_id FROM wp_terms JOIN wp_term_taxonomy USING(term_id) WHERE name = ?)
					),(?,
						(SELECT term_taxonomy_id FROM wp_terms JOIN wp_term_taxonomy USING(term_id) WHERE wp_terms.term_id = ?)
					)";

			$param = array(
				$post_id,
				$tagName,
				$post_id,
				$catID
			);

			setQuery($sql,$param,$pdo2);

			//イメージ情報の登録
			$sql = "INSERT INTO wp_postmeta(post_id,meta_key,meta_value) VALUES (?,'thumbnail',?)";

			$param = array(
				$post_id,
				HOME_URL."/ssl/img/?url=".urlencode($img)

			setQuery($sql,$param,$pdo2);

			//viewsのテーブルの挿入
			$sql = "INSERT INTO wp_popularpostsdata(postid,day,pageviews) (SELECT ID,post_date,1 FROM wp_posts WHERE ID =?)";
			setQuery($sql,array($post_id),$pdo2);

			$insert_flag = true;

			if(DEBUG)break;
		}

		//記事カウントの更新
		if(!empty($insert_flag)){

			$sql = "UPDATE wp_term_taxonomy JOIN wp_terms USING(term_id) SET
					count = (SELECT cnt FROM (
					  SELECT COUNT(*) cnt FROM wp_terms term
						JOIN wp_term_taxonomy tax ON term.term_id = tax.term_id
						JOIN wp_term_relationships tr ON tr.term_taxonomy_id = tax.term_taxonomy_id
						JOIN wp_posts po ON po.ID = tr.object_id
						WHERE name LIKE ? AND post_status = 'publish' AND post_type = 'post'
					 ) tmp
					) WHERE name LIKE ?";

			setQuery($sql,array($tagName,$tagName),$pdo2);

		}

		$insert_flag = false;
	}

//アイキャッチ画像をスクレイピングしてくる用
function get_image($link){
	$html = file_get_contents($link);
	$doc = phpQuery::newDocument($html);

	$img = $doc["meta[property='og:image']"]->attr("content");

	if(empty($img)){
		$img = $doc["figure.eyecatch img"]->attr("src");
	}

	if(empty($img)){
		$img = $doc["figure.eyecatch img:eq(0)"]->attr("src");
	}

	if(empty($img)){
		foreach ( (array)$doc["figure.eyecatch"] as $val ){
			$img = pq($val)->find("img")->attr("src");

			break;
		}
	}

	return $img;
}

//画像のSSL化
function img_ssl($content){

	if(!preg_match_all('/<img .*?\/>/',$content,$matches))return $content;

	$matches[0] = array_unique($matches[0]);

	foreach ((array)$matches[0] as $val){
		if(preg_match('/src="(.*?)"/',$val,$matches2) && !preg_match('/^https/',$matches2[1])  ){
			$content = str_replace($matches2[1],"/wp-includes/ssl/img/?url=".urlencode($matches2[1]),$content);
		}
	}

	return $content;
}
