<?php
require_once('../vendor/autoload.php');
require_once '../bootstrap-doctrine.php';
require_once('../TwitterAPIExchange.php');

if(isset($_GET['run'])){
	$sql = 'SELECT * FROM tweets';
	$res = $conn->query($sql);
	while($row = $res->fetch()) {
		$day = date('N', $row['date']);
		$hour = date('G', $row['date']);
		$conn->query('UPDATE tweets SET day = "'.$day.'", hour = "'.$hour.'" WHERE id = '.$row['id']);
	}
	exit;
}

switch($_GET['action']) {
	case 'mentalities':

		$aMentalities = ['positive', 'negative', 'animals', 'swear', 'football', 'alcohol', 'weather'];

		$aData = [];
		foreach ($aMentalities as $mentality) {

			$hours = 'select hour, count(*) as total from tweets WHERE id IN (SELECT tweet_id from mentality WHERE mentality = "' . $mentality . '")  GROUP BY hour ORDER BY hour ASC;';
			$resHours = $conn->query($hours);
			$aValues = '';
			while ($row = $resHours->fetch()) {
				$aValues.=','. $row['total'];
			}
			$values = array_map('intval', explode(',', $aValues));
			//Set Midnight (0) to the same value as 24
			$values[0] = $values[24];

			$aData[] = ['name' =>ucfirst($mentality), 'data' => $values];
		}

		header('Content-Type: application/json');
		echo json_encode($aData);
		break;

	case 'avgVol':
		$sql = "select FROM_UNIXTIME(`date`, '%Y.%m.%d') as ndate,
			    count(*) as totalTweets
				from tweets
				group by ndate DESC";
		$res = $conn->query($sql);

		$aData = [];
		while($row = $res->fetch()){
			$aDate = explode('.', $row['ndate']);
			//mktime ($hour = null, $minute = null, $second = null, $month = null, $day = null, $year = null, $is_dst = null)
			$aData[] = [_convertToUTC(mktime(0,0,0,$aDate[1],$aDate[2],$aDate[0])) * 1000, (int)$row['totalTweets']];
		}

		header('Content-Type: application/json');
		echo json_encode($aData);
		break;

	case 'percents':
		$from = strtotime('3000 minutes ago');

		$sql = "SELECT count(*) as totalTweets, mentality FROM `tweets` LEFT JOIN mentality ON tweets.id = tweet_id WHERE date > '".$from."' AND mentality != '' GROUP BY mentality;";
		$res = $conn->query($sql);
		$totalTweets = 0;
		$aResults['type'] = array('positive' => array('percent' => 0, 'total' => 0),
								  'negative'=> array('percent' => 0, 'total' => 0),
								  'alcohol'=> array('percent' => 0, 'total' => 0),
			 					  'swear'=> array('percent' => 0, 'total' => 0),
								  'animals'=> array('percent' => 0, 'total' => 0),
								  'football'=> array('percent' => 0, 'total' => 0));
		while($row = $res->fetch()){
			$totalTweets += $row['totalTweets'];
			$aResults['type'][$row['mentality']]['total'] = $row['totalTweets'];
		}

		//Work out the percentages
		foreach($aResults['type'] as $type => $value){
			$percent = number_format(($value['total']/$totalTweets)*100,2);
			$aResults['type'][$type]['percent'] = (int)$percent;
		}

		header('Content-Type: application/json');
		echo json_encode($aResults);
		break;

	case 'hours':

		$aMentalities = ['positive', 'negative', 'animals', 'swear', 'football', 'alcohol'];

		$aData = [];
		foreach ($aMentalities as $mentality) {
			$final = [];
			$hours = 'select hour, count(*) as total from tweets WHERE id IN (SELECT tweet_id from mentality WHERE mentality = "' . $mentality . '")  GROUP BY hour ORDER BY hour ASC;';
			$resHours = $conn->query($hours);
			$aValues = '';
			while ($row = $resHours->fetch()) {
				$aValues[$row['hour']] = (int)$row['total'];
			}
			$start = 1;
			while($start != 25){
				if(!isset($aValues[$start])){
					$aValues[$start] = 0;
				}
				$start ++;
			}
			$aValues[0] = $aValues[24];
			ksort($aValues);



			$aData[] = ['name' =>$mentality, 'data' => $aValues];
		}

		header('Content-Type: application/json');
		echo json_encode($aData, JSON_NUMERIC_CHECK);
		break;


	case 'totalCounts':
		$aMentalities = ['positive', 'negative', 'animals', 'swear', 'football', 'alcohol'];

		$aValues = [];
		$hour = 12;

		$today = strtotime($hour . ':00:00');
		$yesterday = strtotime('-1 day', $today);
		$lastWeek =  strtotime('-7 days', $today);

		$beginOfDay = strtotime("midnight", $lastWeek);
		$endOfDay   = strtotime("+1 day", $lastWeek);

		foreach ($aMentalities as $mentality) {
			$final = [];
			$yesterdaySQL = 'select count(*) as total from tweets WHERE date > "'.$yesterday.'" AND date < "'.$today.'" AND id IN (SELECT tweet_id from mentality WHERE mentality = "' . $mentality . '");';

			$resYest = $conn->query($yesterdaySQL);
			while ($row = $resYest->fetch()) {
				$aValues[$mentality]['yesterday'] = (int)$row['total'];
			}


			$lastWeekSQL = 'select count(*) as total from tweets WHERE date > "'.$beginOfDay.'" AND date < "'.$endOfDay.'" AND id IN (SELECT tweet_id from mentality WHERE mentality = "' . $mentality . '");';
			$resWeek = $conn->query($lastWeekSQL);
			while ($row = $resWeek->fetch()) {

				$aValues[$mentality]['lastWeek'] = (int)$row['total'];
			}

			$total = 'select count(*) as total from tweets WHERE id IN (SELECT tweet_id from mentality WHERE mentality = "' . $mentality . '");';

			$res = $conn->query($total);
			while ($row = $res->fetch()) {
				$aValues[$mentality]['total'] = (int)$row['total'];
			}


		}
		$aData['type'] = ['data' => $aValues];

		header('Content-Type: application/json');
		echo json_encode($aData, JSON_NUMERIC_CHECK);
		break;

	case 'tpmMent':
		$type = $_GET['type'];

		//Get last 20 minutes worth of tweets
		$from = strtotime('30 minutes ago');

		$sql = "select count(mentality) as total, mentality from tweets INNER JOIN mentality on mentality.tweet_id = tweets.id WHERE date > '".$from."' AND mentality =  '".$type."'  order by total desc;";
		$res = $conn->query($sql);
		$row = $res->fetch();
		/*if($row['total'] > '100'){
			$row['total'] = '100';
		}*/
		echo (int)$row['total'];

		break;

	case 'hours':
		$hours = 'select hour, count(*) as total from tweets GROUP BY hour ORDER BY hour ASC';
		$resHours = $conn->query($hours);
		$aRes['hours'] = [];
		$max = '';
		while($row = $resHours->fetch()){
			$aRes['hours'][]=$row;
			if($row['total'] > $max) {
				$max = $row['total'];
			}
		}
		$aRes['max'] = $max;
		echo json_encode($aRes);
		break;


	case 'hourMentality':
		$aMentalities = ['positive', 'negative', 'animals', 'swear', 'football', 'alcohol', 'weather'];

		$max = '';
		$mentality = $_GET['mentality'];
		$obj = new stdClass();
		$obj->label=ucfirst($mentality);
		$hours = 'select hour, count(*) as total from tweets WHERE id IN (SELECT tweet_id from mentality WHERE mentality = "'.$mentality.'")  GROUP BY hour ORDER BY hour ASC;';
		$resHours = $conn->query($hours);
		while($row = $resHours->fetch()){
			$obj->data[] = [$row['hour'],$row['total']];
		}

		header('Content-Type: application/json');
		echo json_encode($obj);
		break;

	case 'days':
		$aDays = ['1' => 'Monday', '2'=> 'Tuesday', '3' => 'Wednesday', '4' => 'Thursday', '5' => 'Friday', '6' => 'Saturday', '7' => 'Sunday'];
		$days = 'select day, count(*) as total from tweets GROUP BY day ORDER BY total DESC';
		$resDays = $conn->query($days);

		while($row = $resDays->fetch()){
			echo $aDays[$row['day']] . ' - ' .$row['total'] .' on this day<br/>';
		}

		break;

	case 'totals':
		$tweets = 'SELECT count(*) from tweets';
		$resTweets = $conn->query($tweets);
		$rowTweets = $resTweets->fetch();

		$hashes = 'SELECT count(*) from hashtags';
		$resHashes = $conn->query($hashes);
		$rowHashes = $resHashes->fetch();

		$users = 'SELECT count(*) from users';
		$resUsers = $conn->query($users);
		$rowUsers = $resUsers->fetch();

		$sql = 'SELECT date from tweets order by date ASC limit 1';
		$res = $conn->query($sql);
		$row = $res->fetch();
		$mins = round(abs(date('U') - $row['date']) / 60,2);


		$totalTweets =  $rowTweets['count(*)'];

		$mins = $mins;

		$tpm = $totalTweets / $mins;


		$last = $entityManager->getRepository('TweetBase')->findOneById(1);

		$lastRun = $last->getLastRun();

		if(date('j/m', $lastRun) == date('j/m'))
		{
			$ago = date('U') - $lastRun;
			$lastRun = 'Last Scrape : '. date('H:i', $lastRun). ' ('.$ago.' seconds ago)';
		}
		else
		{
			$lastRun = 'Too long ago!';
		}

		$aValues = ['tpm' => number_format($tpm,2), 'totalTweets' => number_format($totalTweets,0), 'totalHashes' => number_format($rowHashes['count(*)'],0), 'totalUsers'=> number_format($rowUsers['count(*)'], 0), 'lastRun' => $lastRun];

		echo json_encode($aValues);
		break;

	case 'tweets':
		$limit = $_GET['limit'];
		$next = $_GET['next'];

		if($next == 'undefined' || $next == '') {
			$next = '';
		}

		$sql = 'select t.id as mainId, t.date, t.longitude, t.lat, t.user_id as tweetUser, t.tweetId, t.tweet, m.mentality, m.tweet_id, u.twitterId as twitterName ,u.picture, u.name from tweets t
				LEFT JOIN users u ON t.user_id = u.id LEFT JOIN mentality m ON t.id = m.tweet_id';

		if(isset($_GET['order']) && $next != ''){
			$sql.= ' WHERE t.id < '. $next .' AND t.longitude != ""';
		}elseif($next != ''){
			$sql.= ' WHERE t.id > '.$next .' AND t.longitude != ""';

		}
		elseif(!isset($_GET['long']))
		{
			$sql .= ' WHERE t.longitude != \'\'';
		}

		$sql.= '  ORDER BY date desc limit 0,'.$limit;
		$tweet = $conn->query($sql);
		$aResults = [];
		$count = 0;
		while($row = $tweet->fetch())
		{
			if($count == 0){
				$aResults['id'] = $row['mainId'];
			}
			$row['date'] = date('jS F, H:i', $row['date']);

			//Highlight the relevant words
			$row['tweet'] = formatTweet($row['tweet'], $row['mentality']);
			$row['longitude'] = (float)$row['longitude'];
			$row['lat'] = (float)$row['lat'];
			$aResults[] = $row;
			$count++;
		}

		$aFinal['tweets'] = array_reverse($aResults);


		echo json_encode($aFinal, JSON_UNESCAPED_UNICODE);

		break;

	case 'getTodaysTwatter':
		$from = strtotime("today");

		$sql = 'select count(*) as totalTweets, twitterId, picture  FROM tweets LEFT JOIN users on user_id = users.id WHERE tweets.date > "'.$from.'" GROUP BY twitterId  ORDER BY totalTweets DESC LIMIT 0,10;';
		$top = $conn->query($sql);
		$string = '<ul class="dashboard-list">';
		$count = 1;
		while($trow = $top->fetch()){


			$sqlCount = 'select count(*) as totalTweets FROM tweets LEFT JOIN users on user_id = users.id WHERE twitterId = "'.$trow['twitterId'].'"';
			$res = $conn->query($sqlCount);
			$row = $res->fetch();

			$string.='<li class="tweets" id="583022993351294976"><a href="http://twitter.com/'.$trow['twitterId'].'" target="_blank"><img onerror="imgError(this);" user-id="'.$trow['twitterId'].'" class="dashboard-avatar" alt="'.$trow['twitterId'].'" src="'.$trow['picture'].'"></a><div class="clear"></div> <p style="font-size:110%;">'.$trow['twitterId'].' - '.$trow['totalTweets'].' tweets today <br/> '.$row['totalTweets'].' in total</p></li>';
			$count++;
		}
		$string.='</ul>';
		echo $string;
		break;

	case 'hashtag':
		$sql = 'select hashtag, count(hashtag) as used from hashtags group by hashtag ORDER BY used DESC LIMIT 20;';
		$hash = $conn->query($sql);
		$output = '<ul class="dashboard-list" id="hashtagList">';
		while($row = $hash->fetch()){
			$output.='<li style="min-height:50px;">Used <b>'.$row['used'].'</b> times: '.$row['hashtag'].'</li>';
		}

		$output.='</ul>';
		echo $output;
		break;

	case 'getCurrent':
		$from = strtotime('30 minutes ago');

		$sql = "select count(mentality) as total, mentality from tweets INNER JOIN mentality on mentality.tweet_id = tweets.id  WHERE date > '".$from."' GROUP BY mentality order by total desc LIMIT 0,1;";

		$res = $conn->query($sql);
		$row = $res->fetch();


		$sqlHash = "select count(hashtag) as total, hashtag from tweets INNER JOIN hashtags on hashtags.tweet_id = tweets.id  WHERE date > '".$from."'  GROUP BY hashtag order by total desc LIMIT 0,1;";

		$resHash = $conn->query($sqlHash);
		$rowHash = $resHash->fetch();
		$status = $row['mentality'];
		if($row['mentality'] == 'swear'){
			$status = ' swearing';
		}
		if($row['mentality'] == 'weather'){
			$status = ' discussing the weather';
		}

		if($row['mentality'] == 'football'){
			$status = ' talking football';
		}

		if($row['mentality'] == 'animals'){
			$status = ' talking animals';
		}

		if($row['mentality'] == 'positive'){
			$status = '<span style="color:#1ABB9C;"> positive</span>';
		}

		if($row['mentality'] == 'negative'){
			$status = '<span style="color:#E74C3C;"> negative</span>';
		}
		$rowHash['hashtag'] = str_replace('#','',$rowHash['hashtag']);
		echo '<h1 style="font-size:40px; font-weight:200;">Right now, we are mostly <b>'.$status.'</b> and talking mainly about <b>#</b><b id="hashNow">'.$rowHash['hashtag'].'</b><br/></h1><span id="hashExample" style="font-size:14px;"></span>';
		break;

	case 'getRandom':
		$hash = '#'.$_GET['hash'];
		$sql = "select * from tweets WHERE id IN (select tweet_id FROM hashtags where hashtag = '".$hash."' order by rand()) limit 0,1";
		$res = $conn->query($sql);
		$row = $res->fetch();
		$row['tweet'] = iconv('UTF-8', 'UTF-8//IGNORE', $row['tweet']);

		echo '"'.$row['tweet'].'"';

		break;


	case 'updateImage':
		$userName = $_GET['twitterId'];
		checkImages($userName, $conn);
		break;

	case 'listTweets':
		$type = $_GET['type'];
		$limit = 100;

		$sql = 'SELECT t.id as mainId, t.date, t.longitude, t.lat, t.user_id as tweetUser, t.tweetId, t.tweet, m.mentality, m.tweet_id, u.twitterId as twitterName ,u.picture, u.name from tweets t
				LEFT JOIN users u ON t.user_id = u.id
				LEFT JOIN mentality m ON t.id = m.tweet_id
				WHERE m.mentality = "'.$type.'"';

		$sql.= '  ORDER BY date desc limit 0,'.$limit;
		$tweet = $conn->query($sql);
		$aResults = [];
		$count = 0;
		while($row = $tweet->fetch())
		{
			if($count == 0){
				$aResults['id'] = $row['mainId'];
			}
			$row['date'] = date('jS F, H:i', $row['date']);

			//Highlight the relevant words
			$row['tweet'] = formatTweet($row['tweet'], $row['mentality']);
			$aResults[] = $row;
			$count++;
		}

		$aFinal['tweets'] = array_reverse($aResults);


		echo json_encode($aFinal, JSON_UNESCAPED_UNICODE);
		break;

	case 'getDay':
		echo date('l');
		break;
}

function formatTweet($text, $type)
{
	$handle = fopen(ROOT_DIRECTORY."negative.csv", "r");
	$aNegative = fgetcsv($handle);

	$handle = fopen(ROOT_DIRECTORY."positive.csv", "r");
	$aPositive = fgetcsv($handle);

	$aTypes = ['positive'     => array_merge($aPositive, [':-)', ':)', '(:', '😄','😂','😀','😀', '🤗','👍']),
		'negative'      => array_merge($aNegative, [':-(', ':(', '):', '💔','😔', '😓', '👎	' ]),
		'animals'      => ['cat', 'dog', 'rabbit', 'bunny', 'puppy', 'kitten','fish', '🐱', '🐰','🐩', '🐶'],
		'swear'    => ['fuck', 'shit', 'crap', 'bollocks', 'arse', 'cock', 'cunt', 'fucking', 'arse'],
		'football' => ['epl', 'EURO2016', 'eng', 'wal','⚽️'],
		'alcohol'    => ['pub', 'beer', 'wine', 'cider', 'drunk', 'IPA', 'guiness', 'lager', 'cocktail', 'ale', 'vodka', 'whiskey', 'rum', '🍸','🍺', '🍻', '🍷'],
		'weather'  => ['rain', 'sun', 'sunny', 'winter','ice', 'freeze', 'fog', 'freezing', 'wind', 'snow', 'summer', 'thunder', 'hail']];
	//Colors
	$aCols = ['positive' => 'success', 'negative' => 'warning', 'animals' => 'primary', 'swear' =>'danger', 'football' => 'success', 'alcohol' => 'warning', 'weather' => 'info', 'hash' => 'default'];

	$aWords = explode(' ', $text);
	$output = '';

	foreach($aWords as $word){
		$word = strtolower($word);
		if(@in_array($word, $aTypes[$type])){
			$word = '<span class="label-default label label-'.$aCols[$type].'">'.$word.'</span>';
		}
		else
		{
			$wordTest = str_replace('#', '',$word);
			if(@in_array($wordTest, $aTypes[$type])){
				$word = '<span class="label-default label label-'.$aCols[$type].'">#'.$word.'</span>';
			}
			elseif(@in_array(substr($word, 0, -1), $aTypes[$type])){
				$word = '<span class="label-default label label-'.$aCols[$type].'">'.$word.'</span>';
			}
		}

		if(stristr($word, '#')){
			$word = '<span class="label-default label label-hash">'.$word.'</span>';
		}

		if(stristr($word, '@')) {
			$word = '<a href="http://twitter.com/'.$word.'" target="_blank">'.$word.'</a>';
		}

		$word = str_replace('##', '#', $word);
		$output.=$word.' ';
	}
	$output = utf8_decode($output);

	//$output = utf8_decode(emoji_unified_to_html($output));😂
	$output = iconv('UTF-8', 'UTF-8//IGNORE', $output);

	return $output;
}

function _convertToUTC($timeStamp) {
	return (int)$timeStamp + (int)date('Z', $timeStamp);
}

function checkImages($userName, $conn)
{
	$settings = array(
		'oauth_access_token' => "182281489-KW48l0kJ6QYPbvGK0N33wxEdPFpd15m60XMfTvYa",
		'oauth_access_token_secret' => "XbC2rzFi889WWln71Ha49a4luD9HjCYWqYkLVDQtGv9FJ",
		'consumer_key' => "0nVdJZE9RhbPpBMZZZ27w",
		'consumer_secret' => "XFQvZQsxHMTqhkM4nnOB3qJYWtU3oEDnZzAB96C0"
	);

	$getfield = '';
	$aGetfield = array("screen_name" => $userName);
	foreach($aGetfield as $k => $v){
		$getfield.=$k.'='.$v.'&';
	}

	$getfield = rtrim($getfield, "&");


	$twitter = new TwitterAPIExchange($settings);
	$url    = 'https://api.twitter.com/1.1/users/show.json';

	$method = 'GET';
	$data   = $twitter->request($url, $method, $getfield);


	$data = (json_decode($data));
	$image =  $data->profile_image_url;


	$sql = "UPDATE users SET picture = '".$image."' WHERE twitterId = '".$userName."'";
	$conn->query($sql);
	echo $image;
}
?>
