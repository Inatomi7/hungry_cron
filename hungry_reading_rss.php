<?php
function hungry_reading_rss($site_domain) {

    $site_json = file_get_contents('http://common.fuuuuuuuck.com/json/site_master.json');
    $site_json = mb_convert_encoding($site_json , 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN');
    $site_json = json_decode( $site_json , true );

    $hungry_json = file_get_contents( ABSPATH . '../../json/hungry.json');
    $hungry_json = mb_convert_encoding($hungry_json , 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN');
    $hungry_json = json_decode( $hungry_json , true );

    // バックエンドサイトのサブドメインを抽出 -> $sub_domain[1]
    $server_domain = $_SERVER['HTTP_HOST'];
    preg_match('/(.*)\..*\.fuuuuuuuck\.com/', $server_domain , $sub_domain );

    $yes_keyword = $hungry_json[$sub_domain[1]]['yes']; //取得キーワード配列
    $no_keyword = $hungry_json[$sub_domain[1]]['no']; //除外キーワード配列
    $tab_keyword = $hungry_json[$sub_domain[1]]['tab']; //タブキーワード配列
    $wp_info = $hungry_json[$sub_domain[1]]['wp']; //wp配列
    $ftp_info = $hungry_json[$sub_domain[1]]['ftp']; //ftp配列

    //site_master.jsonチェック：収集先サイト内に、該当アンテナサイト情報があるか
    if( !in_array($sub_domain[1] , $site_json[$site_domain]['belong']) ){
        echo "マスターデータに登録がないため、収集を停止しました。";
        return;
    }

    $sd_hyphen = str_replace(".","-",  $site_domain ); // 文字列の『 . 』 -> 『 _ 』

    $hungry_rss_feed_url =  $site_json[$site_domain]['feedurl'];
    $hungry_rss_post_name = $sd_hyphen;//post_nameに使用する文字。ドメインの『 . 』 -> 『 _ 』
    $hungry_rss_number = $site_json[$site_domain]['feednumbers'];//フィードから一度に取得する数を投稿ユーザーのlastname から取得する

    require_once('autoloader.php');
    include_once(ABSPATH . WPINC . '/feed.php');
    include_once(ABSPATH . 'wp-admin/includes/taxonomy.php');//wp_create_categoryでカテゴリを追加するために必要

    $feed = new SimplePie();
    $feed->enable_cache(false);
    $feed->set_feed_url($hungry_rss_feed_url); // ここに解析したいURLを入れる
    $feed->init();
    $feed->handle_content_type();

    // フィードが生成されていない、または、フィードのエントリーが0の場合は関数終了
    if (is_wp_error($feed) || $feed->get_item_quantity() == 0) {
        echo "フィード情報なし";
        return;
    }

    $maxitems = $feed->get_item_quantity($hungry_rss_number); //　get_item_quantity()　の数値部分より最新から何件の記事を取得するか指定できる。
    $feed_items = $feed->get_items(0, $maxitems);

    $today = new Hungry_rss_today();
    $today->make_dir();

/*　サイト名からカスタムタクソノミーのタームIDを取得  --------------------------------------------------------------------*/
global $wpdb;
$query1 = <<<SQL
    SELECT term_id
    FROM {$wpdb->terms}
    WHERE name = %s
SQL;

$get_term_id = $wpdb->get_var($wpdb->prepare($query1,$site_json[$site_domain]['name']));//get_varはget_varは該当する行の最初の列だけを取得します。

/*　タームIDに紐付けされた記事IDから最も数の大きい(新しい)投稿ID（object_id）取得  ----------------------------------------------*/
global $wpdb;
$query2 = <<<SQL
    SELECT object_id
    FROM {$wpdb->term_relationships}
    WHERE term_taxonomy_id = %d
    ORDER BY object_id DESC
SQL;

$get_post_id = $wpdb->get_var($wpdb->prepare($query2,$get_term_id));//get_varはget_varは該当する行の最初の列だけを取得します。

/*　取得した投稿IDからpostmeta内の本来の投稿時間を取得  --------------------------------------------------------------------*/
global $wpdb;
$query3 = <<<SQL
     SELECT meta_value
     FROM {$wpdb->postmeta}
     WHERE post_id = %d
     AND meta_key = 'original_posttime'
SQL;

    $db_time = $wpdb->get_var($wpdb->prepare($query3,$get_post_id));//get_varはget_varは該当する行の最初の列だけを取得します。

    foreach ($feed_items as $item)://////////////////記事毎処理ここからループ1/////////////////////////////////////////////////////////

        $feed_time = $item->get_date("Y-m-d H:i:s");

           if ($feed_time > $db_time){  //該当サイトのRSSフィードの最新投稿日時が既存記事のoriginal_posttimeより新しいのであれば処理開始 //if05　　　　　　　　　　　　　　　

                $description_encode = mb_detect_encoding($item->get_description()); //ディスクリプションのエンコードを調べる
                if($description_encode != "UTF-8"){
                    // $item->get_description()は10進数の文字参照のため検索にひっかからないので実体参照化して検索対象とする
                    $get_description = mb_convert_encoding($item->get_description(), 'UTF-8', 'HTML-ENTITIES');
                }else{
                    $get_description = $item->get_description();
                }

                $get_description = preg_replace( "/<img(.+?)>/", "", $get_description );//イメージタグ除外
                $get_description = strip_tags($get_description);


                $content_encode = mb_detect_encoding($item->get_content()); //ディスクリプションのエンコードを調べる
                if($content_encode != "UTF-8"){
                    // $item->get_content()は10進数の文字参照のため検索にひっかからないので実体参照化して検索対象とする
                    $get_content = mb_convert_encoding($item->get_content(), 'UTF-8', 'HTML-ENTITIES');
                }else{
                    $get_content = $item->get_content();
                }

                $categorys = $item->get_categories(); //カテゴリ情報の複数系を配列に取得（ただしこの時点ては16進数の文字列）

                /* ここから 取得対象文字が含まれなない or 禁止文字文字含むはスキップ $yes_keyword $no_keyword -------------------- */

                $yes_match = 0 ;
                foreach ($yes_keyword as $key => $value) {

                        if(( mb_strpos( $item->get_title() , $value ) !== false ) ||
                           ( mb_strpos( $get_description , $value ) !== false ) )
                        {//||
                           /*( mb_strpos( $get_content , $value ) !== false )*/
                            $yes_match++;
                        }

                        foreach ((array)$categorys as $category){ //カテゴリの数だけ処理する
                            $category_title[$i] = $category->get_label();//カテゴリ情報を読める状態にする
                            if(( mb_strpos( $category_title[$i] , $value ) !== false )){//カテゴリの中に$valueがあれば
                                $yes_match++;
                            }
                        }

                }

                $no_match = 0 ;
                foreach ($no_keyword as $key => $value) {

                        if(( mb_strpos( $item->get_title() , $value ) !== false ) ||
                           ( mb_strpos( $get_description , $value ) !== false ) //||
                           /*( mb_strpos( $get_content , $value ) !== false )*/)
                        {
                            $no_match++;
                        }

                        foreach ((array)$categorys as $category){ //カテゴリの数だけ処理する (array)で強制的に配列にキャストする。カテゴリなしエラーを回避。
                            $category_title[$i] = $category->get_label();//カテゴリ情報を読める状態にする
                            if(( mb_strpos( $category_title[$i] , $value ) !== false )){//カテゴリの中に$valueがあれば
                                $no_match++;
                            }
                        }

                }

                if( $yes_match == 0 ){
                    echo "取得対象キーワードが含まれないため処理をスキップ。<br /><br />ーーーーーーーーーーーーーーーー<br /><br />"; 
                    continue;
                }

                if( $no_match != 0 ){
                    echo "取得除外キーワードが含まれていたため処理をスキップ。<br /><br />ーーーーーーーーーーーーーーーー<br /><br />"; 
                    continue;
                }

                /* ここまで 取得対象文字が含まれなない or 禁止文字文字含むはスキップ $yes_keyword $no_keyword -------- */


                /* ここから タブカテゴリー文字が含まれる場合、配列に入れる $tab_keyword ------------------------- */
                $tabcategorys ;
                foreach ($tab_keyword as $key => $value) {

                        if(( mb_strpos( $item->get_title() , $value ) !== false ) ||
                           ( mb_strpos( $get_description , $value ) !== false ) //||
                           /*( mb_strpos( $get_content , $value ) !== false )*/)
                        {
                            $tabcategorys[] = $value;
                        }

                        foreach ($categorys as $category){ //カテゴリの数だけ処理する
                            $category_title[$i] = $category->get_label();//カテゴリ情報を読める状態にする
                            if(( mb_strpos( $category_title[$i] , $value ) !== false )){//カテゴリの中に$valueがあれば
                                $tabcategorys[] = $value;
                            }
                        }
                }

                /* ここまで タブカテゴリー文字が含まれる場合、配列に入れる $tab_keyword ------------------------- */

                /* ここから 元記事でカテゴリー設定されてあった分のIDを$category_id配列に格納 */
                if($categorys){
                    $i = 0 ;
                    foreach ($categorys as $category){ //カテゴリの数だけ処理する
                        $category_title[$i] = $category->get_label();//カテゴリ情報を読める状態にする
                        $category_id[$i] = get_cat_ID($category_title[$i]);//上記カテゴリにIDがついてるかチェック
                        if ($category_id[$i] == 0) {//0であればIDがないということ＝未登録カテゴリ
                            $category_id[$i] = wp_create_category($category_title[$i], 0);//カテゴリを登録しつつカテゴリID情報を変数へ
                        }
                        $i++;
                    }
                }
                /* ここまで 元記事でカテゴリー設定されてあった分のIDを$category_id配列に格納 */

                /* ここから 元記事でタブカテゴリーワードにヒットした分のIDを$category_id配列に格納 */
                if($tabcategorys){
                    foreach ($tabcategorys as $key => $value) { //カテゴリの数だけ処理する
                        $category_title[$i] = $value ;
                        $category_id[$i] = get_cat_ID($value);//上記カテゴリにIDがついてるかチェック
                        if ($category_id[$i] == 0) {//0であればIDがないということ＝未登録カテゴリ
                            $category_id[$i] = wp_create_category($value, 0);//カテゴリを登録しつつカテゴリID情報を変数へ
                        }
                        $i++;
                    }
                }
                /* ここまで 元記事でタブカテゴリーワードにヒットした分のIDを$category_id配列に格納 */

                ?>

                <div>
                    <p><a href="<?php echo $item->get_permalink(); ?>"><?php echo $item->get_title(); ?></a></p>
                    <p><?php //echo $item->get_description(); ?></p>
                    <p>画像パス：
                    <?php
                    $first_img = ''; // 記事中の1枚目の画像を取得　http://2inc.org/blog/2012/07/15/1814/
                    if ( preg_match( '/<img.+?src=[\'"]([^\'"]+?)[\'"].*?>/msi', $item->get_content(), $matches ) ) {

                        $first_img = $matches[1];
                        echo $first_img . "<br />";

                    }else{

                        if ( preg_match( '/http:\/\/.*\.(jpeg|jpg)/', $item->get_description(), $matches ) ) {

                            $first_img = $matches[0];
                            echo $first_img . "<br />";

                        }else{
                            echo "画像が取得できなかったので処理をスキップ<br />";
                            continue;
                        }

                    }
                    ?>
                    </p>

                </div>

            <?php

                $post_time = $item->get_date("Ymd");
                $post_value = array(
                    'post_title' => $item->get_title(),// 投稿のタイトル。
                    'post_name' => $hungry_rss_post_name . $post_time, //投稿者のfirstnameの上から5文字を記事スラッグにする
                    'post_content' => $item->get_description(), // 投稿の本文。
                    'post_category' => $category_id, // カテゴリを登録。カテゴリーIDが入った配列の形でしか登録できない。
                    //'tags_input' => array("タグ1","タグ2"), // タグの名前(配列)。タグは文字列でそのまま登録が可能か？
                    'post_date' => $feed_time
                );

                $insert_id = wp_insert_post($post_value);//$insert_idには投稿のID（「wp_posts」テーブルの「ID」）が入る。 投稿に失敗した場合は0が返る。

                /* 元画像アップ */
                $hungry_img_name = $hungry_rss_post_name.'_'.$post_time.'_'.$insert_id.'.jpg';
                $hungry_rss_imag_path = $today->get_img_path().'/'.$hungry_img_name;//元画像アップ用パス
                hungry_image_up($hungry_rss_imag_path , $first_img); //元画像をアップロード
                /* 元画像アップ */

                /* 画像ファイルリサイズ */
                $hungry_rss_img_resize_path = $today->get_img_path().'/'.$hungry_img_name;//リサイズ処理用パス
                $hungry_image_size = filesize($hungry_rss_img_resize_path);
                $is_resize  = hungry_image_resize($hungry_rss_img_resize_path , $hungry_image_size);
                /* 画像ファイルリサイズ */

                if($insert_id) {//投稿に成功した場合
                    
                    wp_set_object_terms($insert_id, $site_json[$site_domain]['name'] , 'sitename'); //カスタムタクソノミーsitenameにtermを入れる
                    //wp_set_object_terms($insert_id, $tabcategorys , 'tab_category'); //カスタムタクソノミーtab_categoryにtermを入れる

                    update_post_meta($insert_id, 'url',$item->get_permalink());// キーが「url」のカスタムフィールドの値に記事urlを入れる
                    update_post_meta($insert_id, 'home_url',$item->get_base());// キーが「home_url」のカスタムフィールドの値にsiteurlを入れる
                    if($is_resize){
                        update_post_meta($insert_id, 'img_path', $today->get_img_local_path().'/'. $hungry_img_name . '.jpg');
                        $get_img_path = $today->get_img_local_path().'/'. $hungry_img_name . '.jpg' ;
                    }else{
                       update_post_meta($insert_id, 'img_path', $today->get_img_local_path().'/'. $hungry_img_name);
                       $get_img_path = $today->get_img_local_path().'/'. $hungry_img_name ;
                    }
                    update_post_meta($insert_id, 'original_posttime',$feed_time);// 元サイトの記事本来の投稿日時
                    update_post_meta($insert_id, 'original_gettime', $today->get_time());// 記事収集時間
                    //配列$post_valueに上書き用の値を追加、変更
                    update_post_meta($insert_id, 'status',0);// 外部wordpressへの投稿回数の初期値
                    $post_value['ID'] = $insert_id;// 下書きした記事のIDを渡す。
                    $post_value['post_status'] = 'publish';// 公開ステータスをこの時点で公開にする。
                    $insert_id2 = wp_insert_post($post_value);//上書き（投稿ステータスを公開に）

                }

                $hungry_post_set['postname'] = $hungry_rss_post_name . '-' . $post_time ;
                $hungry_post_set['title'] = $item->get_title() ;
                $hungry_post_set['sitename'] = $site_json[$site_domain]['name'] ;
                $hungry_post_set['post_url'] = $item->get_permalink() ;
                $hungry_post_set['img_path'] = $get_img_path ;
                $post_description = preg_replace( "/<img(.+?)>/", "", $item->get_description() );//イメージタグ除外
                $post_description = strip_tags($post_description);
                $hungry_post_set['post_contents'] = $post_description ;
                $hungry_post_time = strtotime($feed_time); //hungry_postで外部wordpressに投稿する際の誤差9時間を調整
                $hungry_post_time = $hungry_post_time - 32400 ;
                $hungry_post_time = date("Y-m-d H:i:s",$hungry_post_time);
                $hungry_post_set['posttime'] = $hungry_post_time;

                hungry_post_go($insert_id , $sub_domain , $wp_info , $ftp_info ,$hungry_post_set ,$category_title);

            }else{
             echo "<p>現在、これ以上の最新のフィード情報はありません。</p>";
             break;
           }//if05
            echo "<p>--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------</p>";
    endforeach;//ループ1ここまで
echo "<p>クロール & アップ終了</p>";

}


function hungry_image_up($img_path , $original_img_path){ //画像ファイルを自サイトへUP

        $options = array(
          'http' => array(
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 5.1; rv:13.0) Gecko/20100101 Firefox/13.0.1"
          ),
        );
        $context = stream_context_create($options);
        $data = file_get_contents($original_img_path ,false, $context);
        file_put_contents($img_path,$data);

}


function hungry_image_resize($fileName_in ,  $d_file_size) {

    $resize_result = false;

    # 元ファイルのファイル名を設定します。
    $fileName_on = $fileName_in;

    # 画像サイズを取得します
    $imageSize = getimagesize($fileName_on);
    $w = $imageSize[0];
    $h = $imageSize[1];

    //if( ($w > 220) || ($h > 150) ){//幅220超え or 高さ150超え

            $src_x = 0 ;
            $src_y = 0 ;
            $newW = 220 ;
            $newH = 150 ;

            if($w > ($h * 1.46)){//幅が広すぎる場合

                $baseH = 150 ;//リサイズ後の画像を置く下地の高さを指定
                $baseW = intval($baseH / $h * $w) ; //同じ比率で下地の幅を出す
                $src_x = ($w - ($h * 1.46)) * 0.5 ; //余分な幅の半分だけx座標をずらす値

            }elseif($w < ($h * 1.46)){//高すぎる場合

                $baseW = 220 ;///リサイズ後の画像を置く下地の幅を指定
                $baseH = intval($baseW / $w * $h) ;//同じ比率で下地の高さを出す
                $src_y = ($h - ($w * 0.68)) * 0.5 ; //余分な高さの半分だけy座標をずらす

            }

            # 再サンプリングを行ないます。
            $imgThumb = imagecreatetruecolor($newW, $newH); //ここに入れる数値がリサイズ後の画像のサイズになる

            $h_i_image = imagecreatefromjpeg($fileName_on);
            if ($h_i_image == "") {//もしも正しいjpeg画像ではないなら（おそらく元pngデータの画像の場合）
                $h_i_image = imagecreatefrompng($fileName_on);
            }

            # 成功した場合、imagecopyresampled()関数はtrueを返します。
            if (imagecopyresampled($imgThumb, $h_i_image, 0, 0, $src_x, $src_y, $baseW, $baseH, $w, $h)) {
            # サムネイルをブラウザに出力します。
              imagejpeg($imgThumb, $fileName_on .".jpg") ;
              //echo(number_format(memory_get_usage()).'Byte(作ってすぐ)<br />');
              echo $fileName_on . "：" . $d_file_size ."バイト(リサイズ前)<br />" ;
              echo $fileName_on . ".jpg" . "：" . filesize($fileName_on .".jpg") ."バイト(リサイズ後)<br />" ;
              $size_percent = (filesize($fileName_on . ".jpg") / $d_file_size) * 100 ;
              $size_percent = 100 - $size_percent;
              echo round($size_percent , 1) . "％圧縮成功！" ;
              
            }else{
              //echo $fileName_on . "：" . filesize($fileName_on) ."バイト(リサイズ実行できませんでした)<br />";
            }
                    
            # 画像を破棄しメモリを解放します。
            imagedestroy($h_i_image) ;
            imagedestroy($imgThumb) ;
            //echo(number_format(memory_get_usage()).'Byte(解放後)<br />');
            $resize_result = true;
    //}
            return $resize_result;
}

class Hungry_rss_today {

    private $year ;
    private $mon ;
    private $day ;
    private $hour ;
    private $min ;
    private $second ;
    private $today ;
    private $get_time ;
    private $wp_content_path ;
    private $uploads_path ;
    private $year_path ;
    private $mon_path ;
    private $day_path ;
    private $wp_content_dir_list ;
    private $uploads_dir_list ;
    private $year_dir_list ;
    private $mon_dir_list ;
    private $day_dir_list ;
    private $get_img_path ;
    private $hp_y_path ;
    private $hp_m_path ;
    private $hp_d_path ;

    public function __construct() {

        date_default_timezone_set('Asia/Tokyo');// タイムゾーンを東京に設定
        $this->today = getdate();//タイムスタンプから取得
        $this->year = $this->today['year'];
        $this->mon = $this->today['mon'];
        $this->mon = sprintf('%02d', $this->mon );//桁数を揃える 例：8 → 08
        $this->day = $this->today['mday'];
        $this->day = sprintf('%02d', $this->day );//桁数を揃える 例：8 → 08
        $this->hour = $this->today['hours'];
        $this->hour = sprintf('%02d', $this->hour );//桁数を揃える 例：8 → 08
        $this->min = $this->today['minutes'];
        $this->min = sprintf('%02d', $this->min );//桁数を揃える 例：8 → 08
        $this->second = $this->today['seconds'];
        $this->second = sprintf('%02d', $this->second );//桁数を揃える 例：8 → 08
        $this->get_time = $this->today['year']."-".$this->mon."-".$this->day." ".$this->hour.":".$this->min.":".$this->second;
        $this->wp_content_path = ABSPATH . 'wp-content/' ;//画像ファイル置き場のパス
        $this->uploads_path = ABSPATH . 'wp-content/uploads/' ;//画像ファイル置き場のパス
        $this->year_path = ABSPATH . 'wp-content/uploads/' . $this->year;
        $this->mon_path = ABSPATH . 'wp-content/uploads/' . $this->year . '/' . $this->mon;
        $this->day_path = ABSPATH . 'wp-content/uploads/' . $this->year . '/' . $this->mon . '/' . $this->day;
        $this->wp_content_dir_list = scandir($this->wp_content_path);//検索してディレクトリ名を配列に入れる
        $this->uploads_dir_list = scandir($this->uploads_path);//検索してディレクトリ名を配列に入れる
        $this->year_dir_list = scandir($this->year_path);//検索してディレクトリ名を配列に入れる
        $this->mon_dir_list = scandir($this->mon_path);//検索してディレクトリ名を配列に入れる
        $this->get_img_path =  ABSPATH . 'wp-content/uploads/' . $this->year . '/' . $this->mon . '/' . $this->day ;
        $this->get_img_local_path = '/wp-content/uploads/' . $this->year . '/' . $this->mon . '/' . $this->day ;

        $this->hp_y_path = '/wp-content/uploads/' . $this->year ;
        $this->hp_m_path = '/wp-content/uploads/' . $this->year . '/' . $this->mon ;
        $this->hp_d_path = '/wp-content/uploads/' . $this->year . '/' . $this->mon . '/' . $this->day;

    }

    public function make_dir() {

        // in_array(調べたいもの,配列変数) 配列変数の中に調べたいものがあるかチェックする
        if(!in_array( 'uploads',$this->wp_content_dir_list )){
            echo "uploadsディレクトリをディレクトリを作成。<br />";
            mkdir($this->uploads_path);
        }

        // in_array(調べたいもの,配列変数) 配列変数の中に調べたいものがあるかチェックする
        if(!in_array( $this->year,$this->uploads_dir_list )){
            echo "年ディレクトリ『" . $this->year . "』をディレクトリを作成。<br />";
            mkdir($this->year_path);
        }

        if(!in_array( $this->mon,$this->year_dir_list )){
            echo "月ディレクトリ『" . $this->mon . "』をディレクトリを作成。<br />";
            mkdir($this->mon_path);
        }

        if(!in_array( $this->day,$this->mon_dir_list )){
            echo "日ディレクトリ『" . $this->day . "』をディレクトリを作成。<br />";
            mkdir($this->day_path);
        }

    }

    public function get_time() { return $this->get_time; }
    public function get_img_path() { return $this->get_img_path; }
    public function get_img_local_path() { return $this->get_img_local_path; }

    public function get_y_path() { return $this->hp_y_path; }
    public function get_m_path() { return $this->hp_m_path; }
    public function get_d_path() { return $this->hp_d_path; }

}// class Hungry_rss_today 


function hungry_post_go(
    $insert_id , /* 投稿ID */
    $sub_domain , /* サブドメイン配列 */
    $wp_info , 
    $ftp_info ,
    $hungry_post_set ,
    $category_title
    ){

    require_once 'IXR_Library.php';
    
    //ワードプレスURLをセット
    $client = new IXR_Client('http://' . $sub_domain[1] . '.fuuuuuuuck.com/xmlrpc.php');

              //投稿パラメータセット
              $post_type = "wp.newPost";//投稿タイプ：新規投稿
              $blog_id = 1;//blog ID: 通常は１
              $post_author = $wp_info[0];//投稿者ID
              $user_name = $wp_info[1];//ユーザー名
              $password = $wp_info[2];//パスワード
              $hungry_post_set['postname'];
              $hungry_post_set['title'];
              $hungry_post_set['sitename'];
              $post_sitename[] = $hungry_post_set['sitename'];//投稿データが配列only なため配列に変換
              $post_status = "publish";//投稿状態（future:公開予定 publish:公開済み）
              $post_date = $hungry_post_set['posttime'];//公開時間

              //投稿
              $status = $client->query(
                $post_type, $blog_id, $user_name, $password,
                array(
                  'post_type' => 'post',
                  'post_name' => $hungry_post_set['postname'],
                  'post_author' => $post_author,
                  'post_date' => $post_date,
                  'post_status' => $post_status,
                  'post_title' => $hungry_post_set['title'],
                  'post_content' => $hungry_post_set['post_contents'],
                  'terms_names' => array(//配列only
                    'category' => $category_title,
                    'sitename' => $post_sitename
                  ),
                  'custom_fields' => array(
                    array('key' => 'post_url', 'value' => $hungry_post_set['post_url']),
                    array('key' => 'thumbnail_url', 'value' => "http://". $sub_domain[1] . ".hungry.fuuuuuuuck.com".$hungry_post_set['img_path'])
                  )
                )
              );

                //echo $hungry_post_set['post_contents'][1] ;

              //hp_image_upload_in_cron($hungry_post_set['img_path'],$ftp_info); //画像ftp投稿
              
              if($status){//投稿に成功した場合
                  
                  update_post_meta($insert_id, 'status', +1 ); // キーが「status」のカスタムフィールドの値にプラス1をする
              }

}

function hp_image_upload_in_cron($img_path ,$ftp_info){//画像ftp投稿

              $ftpValue = array(
                  'ftp_server' => $ftp_info[0],
                  'ftp_user_name' => $ftp_info[1],
                  'ftp_user_pass' => $ftp_info[2]
              );
              $remote_file = "wp/" . $img_path ;
              //ftpログインからの相対パスでなければならぬ  参考：http://d.hatena.ne.jp/takigawa401/20150427/1430121843
              $upload_file = ABSPATH . $img_path ;

              $connection = ftp_connect($ftpValue['ftp_server']);

              $login_result = ftp_login(
                  $connection,
                  $ftpValue['ftp_user_name'],
                  $ftpValue['ftp_user_pass']
              );

            ftp_pasv($connection, true);

            $img_post_patth = new Hungry_rss_today();

            ftp_mkdir($connection , '/wp/wp-content/uploads');
            ftp_mkdir($connection , '/wp' . $img_post_patth->get_y_path());
            ftp_mkdir($connection , '/wp' . $img_post_patth->get_m_path());
            ftp_mkdir($connection , '/wp' . $img_post_patth->get_d_path());


            $ftpResult = ftp_put($connection, $remote_file, $upload_file, FTP_BINARY, false);

            if (!$ftpResult) {
              throw new InternalErrorException('Something went wrong.');
            }else{
              echo "サムネイルアップ完了";
            }

            ftp_close($connection);

}

?>