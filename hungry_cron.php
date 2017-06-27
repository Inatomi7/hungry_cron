<?php 

/*

Plugin Name: Hungry_CRON
Author:
Plugin URI:
Description:
Version: 1.0（アンテナバックエンド）
Author URI:
Domain Path:
Text Domain:

Creation Date: 2015/09/01
Modified: 

*/

add_action( 'admin_menu', 'hungry_cron_menu' );

function hungry_cron_menu() {
//ここにメニューを追加するためのの処理を記述
  add_menu_page( //codexを参照する
      __('Hungry CRON', 'hungry-cron-admin'),// titleタグに入る
      __('Hungry CRON', 'hungry-cron-admin'),// 管理画面左メニュータイトル
      'administrator',// 閲覧・使用の為の権限レベル管理者以外NG
      'hungry-cron-admin',
      'hungry_cron_admin'
  );

    add_submenu_page( //codexを参照する
        'hungry-cron-admin',
      __('Hungry CRON', 'hungry-cron-admin'),// titleタグに入る
      __('Hungry RSS', 'hungry-cron-admin'),// 管理画面左メニュータイトル
      'administrator',// 閲覧・使用の為の権限レベル管理者以外NG
      'hungry-cron-sub-admin',
      'hungry_cron_sub_admin'
  );

}

function hungry_cron_admin(){
?>
    <div class="wrap">
    <h2>Hungry CRON 管理画面</h2>
    <form action="" method="post" >
    <?php wp_nonce_field( 'my-nonce-key', 'hungry-cron-admin' ); // CSRF対策 ?>
    <input type="hidden" name="set" value="hungry_cron">
    <p><input type="submit" value=" スケジュールセット! " class="button button-primary"></p>
    </form>
    <?php 

        if($_POST){
            $cron_set = new Hungry_schedule_set();
            $cron_set -> hungry_cron_set();
            echo "<br />以上のサイトのスケジュールを新規セットしました。<br /><br />";
        }

        $hungry_schedule_get = new Hungry_schedule_get();//cron情報を取得するクラスのインスタンス
        $hungry_schedule_get -> hungry_cron_get('role=subscriber');

     ?>
    </div><!--wrap"-->
<?php

}//hungry_cron_admin


function hungry_cron_sub_admin(){ //hungry_cron_sub_admin
?>
    <div class="wrap">
    <h2>Hungry RSS 管理画面</h2>
    <?php

    $site_json = file_get_contents('http://common.fuuuuuuuck.com/json/site_master.json');
    $site_json = mb_convert_encoding($site_json , 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN');
    $site_json = json_decode( $site_json , true );

    $slg_inst = new Site_list_get();
    $site_list = $slg_inst->site_list_get();

    ?>
    <br />
    <p>クロールするサイトを選択</p>

    <form action="" method="post" >
    <?php wp_nonce_field( 'my-nonce-key', 'hungry-rss-admin' ); // CSRF対策 ?>
    <select name="feed_site">

<?php

    foreach ($site_list as $key => $value) {

        if($value == $_POST['feed_site']){
        ?>
                <option value="<?php echo $value; ?>" selected ><?php echo $site_json[$value]['name']; ?></option>
        <?php }else{ ?>
                <option value="<?php echo $value; ?>"><?php echo $site_json[$value]['name']; ?></option>
        <?php } ?>

    <?php } ?>
    </select>
    <p><input type="submit" value=" クロールする " class="button button-primary"></p>
    </form>

    <?php

    if($_POST){
        echo "<p>クロール開始</p>";
        $site_domain = $_POST['feed_site'];
        require_once 'hungry_reading_rss.php';
        hungry_reading_rss($site_domain);
    }
    ?>
    </div>
<?php
}// hungry_cron_sub_admin


class Site_list_get {

    private $site_json ;
    private $server_domain ;
    private $sub_domain ;
    private $site_list ;

    public function site_list_get() { //アンテナに登録されたドメイン情報を受け取り$site_list配列に入れる

        $this->site_json = file_get_contents('http://common.fuuuuuuuck.com/json/site_master.json');
        $this->site_json = mb_convert_encoding($this->site_json , 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN');
        $this->site_json = json_decode( $this->site_json , true );

        // バックエンドサイトのサブドメインを抽出 -> $sub_domain[1]
        $this->server_domain = $_SERVER['HTTP_HOST'];
        preg_match('/(.*)\..*\.fuuuuuuuck\.com/', $this->server_domain , $this->sub_domain );

        /* $this->site_list配列にスケジュールセットするサイトドメインを入れる */
        $i = 0 ;
        foreach ($this->site_json as $key => $value) {
            if(in_array($this->sub_domain[1], $value['belong'])){
                $this->site_list[$i] = $key ;
                $i++ ;
            }
        }

        return $this->site_list ;

    }

}

/*  
    cron設定において大事なポイント

    ①スケジュールの有無をチェック：wp_next_scheduled（スケジュール名,渡す引数) 
    設定されているCRONジョブの実行時刻を返す、なければfalseを返す
    ！注意：セット時に引数を指定いた場合、必ず引数まで指定する、wp_cronはスケジュール名と引数のセットでユニークのため

    ②スケジュールを削除を削除する場合：wp_clear_scheduled_hook(スケジュール名,渡す引数)
    if(wp_next_scheduled（スケジュール名,渡す引数)){ wp_clear_scheduled_hook(スケジュール名,渡す引数) }
*/

class Hungry_schedule_set {

    private $site_list;
    private $cron_name;
    private $cron_seconds;
    private $slg_inst;

    public function hungry_cron_set(){

        $this->slg_inst = new Site_list_get();
        $this->site_list = $this->slg_inst->site_list_get();

        $this->cron_seconds = 0 ;
        foreach ($this->site_list as $key => $value) {

            $this->cron_name = str_replace(".","_",  $value ); // 文字列の『 . 』 -> 『 _ 』
            if ( !wp_next_scheduled( $this->cron_name , array(  $value )))
            {
               //スケジュール登録 wp_schedule_event(初回実行時間(現在からの秒数) , 間隔（登録されてあるものより指定）, スケジュール名 , 関数に渡す引数  )
               wp_schedule_event( time() + 60 + $this->cron_seconds , 'hourly' , $this->cron_name , array(  $value ));
               $this->cron_seconds = $this->cron_seconds + 300 ;
               echo  $value . "<br />" ;
            }

        }

    }

}//class hungry_schedule_set


new Hungry_add_action();//add_actionを実行するクラスのインスタンス

class Hungry_add_action {

    private $site_list ;
    private $cron_name ;
    private $slg_ins ;

    public function __construct(){

        $this->slg_inst = new Site_list_get();
        $this->site_list = $this->slg_inst->site_list_get();

        foreach ($this->site_list as $key => $value) {

            $this->cron_name = str_replace(".","_", $value ); // 文字列の『 . 』 -> 『 _ 』
            add_action( $this->cron_name , 'hungry_cron_action' );

        }

    }//function __construct

}//class Hungry_schedule_action


class Hungry_schedule_get {

    private $site_list ;
    private $cron_name ;
    private $schedule ;
    private $time ;
    private $time_date ;
    private $no_schedule ;
    private $slg_inst ; 
    public function hungry_cron_get(){

        $this->slg_inst = new Site_list_get();
        $this->site_list = $this->slg_inst->site_list_get();

        echo "<br />";
        foreach ($this->site_list as $key => $value) {

            $this->cron_name = str_replace(".","_", $value ); // 文字列の『 . 』 -> 『 _ 』
            $this->schedule = wp_get_schedule( $this->cron_name , array( $value ) );
            $this->time = wp_next_scheduled( $this->cron_name , array( $value ) );

            if($this->time != false){
                $this->time_date = date("Y/m/d H:i ( s秒 )" , ($this->time) + 32400);
                echo $this->schedule . "　：　次回は " . $this->time_date . "　： " . $value . "<br />" ;
            }else{
                $this->no_schedule[$key] = $value ; //スケジュールセットされていないサイトを配列に入れる
            }

        }

        if( $this->no_schedule != null){
            echo "<br /><br />以下のサイトはスケジュールセットされていません。<br /><br />";
            foreach ($this->no_schedule as $key => $value) {
                echo $value . "<br />";
            }
        }

    }

}//class hungry_schedule_get


function hungry_cron_action($site_domain) {//hungry_cron_action
    require_once 'hungry_reading_rss.php';
    hungry_reading_rss($site_domain);
}//Hhungry_cron_action


class My_Taxonomy {
  //パーマリンク設定を空更新しなければ一覧ページが404エラーになる
  function __construct() {
    // initアクションのフック
    add_action( 'init', array( $this, 'my_init' ), 10 );
  }

  function my_init() {
    // カスタムタクソノミーの登録
    register_taxonomy(
      'sitename', 'post', array(
        'public'            => true,
        'hierarchical'      => false,
        'show_admin_column' => true,
        'query_var' => 'sitename',
        'rewrite' => $rewrite['sitename'],
        'show_ui' => true,
        '_builtin' => true,
        'labels' => array(
          'name'         => 'サイト',
          'add_new_item' => 'サイトの新規追加',
          'edit_item'    => 'サイトの編集',
        ),
      )
    );

  }

} // class end

new My_Taxonomy();
