# hungry_cron
アンテナサイト用 wordpressプラグイン<br>

 ＜hungry_cron.php＞<br>
 <br>
 （役割）<br>
 各登録サイトの自動収集の時間管理<br>
 収集実行の着火点<br>
<br>
（関数別）<br>
・class Hungry_schedule_get()<br>
　wordpressのcron上に登録されたデータを管理画面表示のために収集（勉強のためclass使用）<br>
・hungry_cron_action() 記事収集時に実行 → hungry_reading_rss.phpの実行用
<br>
<br>
<br>
 ＜hungry_reading_rss.php＞<br>
<br>
 （役割）<br>
 ・記事情報のバックwordpressへの登録<br>
 ・記事情報のフロントwordpressへの登録<br>
 ・サムネイルのトリミング加工<br>
 ・サムネイルをFTPUP<br>
<br>
 （関数別）<br>
 ・function hungry_reading_rss()  記事収集時に実行<br>
 ・function hungry_image_up() 元画像をアップロード<br>
 ・function hungry_image_resize() 元画像を220px x 150px の比率で中央からトリミング<br>
 ・class Hungry_rss_today 画像UP用のディレクトリを日付データから作成 （勉強のためclass使用）<br>
 ・function hungry_post_go xmlrpc.phpを利用してフロントwordpressへ投稿<br>
 ・function hp_image_upload_in_cron フロントwordpressのディレクトリにへ画像UP<br>
