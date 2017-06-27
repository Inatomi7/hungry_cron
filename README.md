# hungry_cron
アンテナサイト用 wordpressプラグイン

hungry_cron.php
 各登録サイトの自動収集時間の間隔を管理

hungry_reading_rss.php

 ＜役割＞
 ・記事情報のバックwordpressへの登録<br>
 ・記事情報のフロントwordpressへの登録
 ・サムネイルのトリミング加工
 ・サムネイルをFTPUP

 ＜関数別＞
 ・function hungry_reading_rss()  記事収集時に実行
 ・function hungry_image_up() 元画像をアップロード
 ・function hungry_image_resize() 元画像を220px x 150px の比率で中央からトリミング
 ・class Hungry_rss_today 画像UP用のディレクトリを日付データから作成 （勉強のためclass使用）
 ・function hungry_post_go xmlrpc.phpを利用してフロントwordpressへ投稿
 ・function hp_image_upload_in_cron フロントwordpressのディレクトリにへ画像UP
