# wp_tools

本来はプライベートで使ってるもので公開予定はなかったのですが・・・
一部見られたくないものはgitignoreで外しております。

## 概要

### post-management_tool wpの記事関連のツール (管理画面は非公開。バックエンド部分だけ一部公開)
* addOtherSitePost.php・・・wpで作られたサイトのRSSから情報を取得し自サイトのDBに登録して記事を自動作成。

### twitter_tool twitter関連のツール (管理画面は非公開。バックエンド部分だけ一部公開)
* tweetScript.php・・・ツイッター予約投稿機能の投稿処理部分
* replyScript.php・・・ツイッターの特定のワードに反応して自動リプライする機能
* postTweetScript.php・・・wpの予約投稿を更改されたときにツイートする機能。SNAPが不調だった為、SNAPのDBだけ利用。
* postTweetCyleScript.php・・・wpの公開記事を一定時間ごとにランダムでツイートさせる機能。
* simiarPost.php・・・wpのsimiarPostのプラグインは初期状態だとなにもでないので、同タグorカテから初期記事を自動登録。
