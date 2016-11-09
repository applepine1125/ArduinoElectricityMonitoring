#!/usr/bin/php

<?php
require ("./twitteroauth/autoload.php");
use Abraham\TwitterOAuth\TwitterOAuth;
date_default_timezone_set('Asia/Tokyo');

/*ファイルポインタをオープン*/
$file = fopen("log.csv", "a+");

/*変数宣言*/
$url = "http://api.thingspeak.com/channels/48323/feed.json?average=daily&timezone=Asia/Tokyo";
$json = file_get_contents($url);

if (!$json){
	echo "json get error";

}else{
	$json = mb_convert_encoding($json, 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN');
	$json_arr = json_decode($json,true);
	$today_w_ave = $json_arr["feeds"][0]["field1"];
	$arr = array();
	$count = fline($file);
	rewind($file);


	/*今日の電力量取得*/
	if($today_w_ave == NULL){
		echo "today_kwh get error";
	}else if(!$file){
		/*エラー処理*/
		echo "logfile error";
	}else{
		$today = date("Y/m/d");
		$today_kwh = $today_w_ave * 24 / 1000;
		/*浮動小数点を丸める*/
		$today_kwh = round($today_kwh, 2);
		$tweetmsg = "{$today} の消費電力量: {$today_kwh} kWh ";

		/*csvに書き込み*/
		$today_kwh_arr = array(date("Y"), date("m"), date("d"),$today_kwh);
		fputcsv($file, $today_kwh_arr);
		rewind($file);


		/*昨日の電力量取得、月別電力量計算*/
		/* CSVファイルを配列へ */
		while( $data = fgetcsv($file) ){
			$arr[] = $data;
		}
		/*昨日の電力量追記*/
		$yesterday_kwh = (float) $arr[$count-1][3];
		/*浮動小数点を丸める*/
		$yesterday_kwh = round($yesterday_kwh, 2);
		$kwh_diff = abs($today_kwh - $yesterday_kwh);
		if($today_kwh > $yesterday_kwh){
			$tweetmsg .= "昨日との差: + {$kwh_diff}　kWh ";
		}else if($today_kwh < $yesterday_kwh){
			$tweetmsg .= "昨日との差: - {$kwh_diff}　kWh ";
		}else if($today_kwh == $yesterday_kwh){
			$tweetmsg .= "昨日との差: +- {$kwh_diff}　kWh ";
		}

		/*月の変わり目判別*/
		if($arr[$count-1][1] != $today_kwh_arr[1]){
			/*先月の日数取得*/
			$lastmonth_days = cal_days_in_month(CAL_GREGORIAN, $arr[$count-2][1], $arr[$count-2][0]);
			for($i = 1; $i <= $lastmonth_days; $i++){
				$lastmonth_kwh += (float) $arr[$count-$i][3];
			}
			$lastmonth_kwh = round($lastmonth_kwh, 2);
			$tweetmsg .= "先月の使用電力量: {$lastmonth_kwh} kWh https://thingspeak.com/channels/48323 #ThingSpeak";
		}else{
			$tweetmsg .= "https://thingspeak.com/channels/48323 #ThingSpeak";
		}

		/*debug*/
		var_dump($tweetmsg);
	}





	/*ツイート*/
	$consumer_key = "insert key";
	$consumer_secret = "insert key";
	$access_token = "insert key";
	$access_token_secret = "insert key";

	$connection = new TwitterOAuth($consumer_key, $consumer_secret, $access_token, $access_token_secret);
	$statues = $connection->post("statuses/update", array("status" => $tweetmsg));
	var_dump($statues);
}
/* ファイルポインタをクローズ */
fclose($file);

/*独自関数の宣言*/
function fline(&$fp) {
	for($total=0; fgets($fp); $total++);
		return $total;
}
?>
