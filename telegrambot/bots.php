<?php
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');

function apiRequestWebhook($method, $parameters) {
	if (!is_string($method)) {
	error_log("Method name must be a string\n");
	return false;
	}

	if (!$parameters) {
	$parameters = array();
	} else if (!is_array($parameters)) {
	error_log("Parameters must be an array\n");
	return false;
	}

	$parameters["method"] = $method;

	header("Content-Type: application/json");
	echo json_encode($parameters);
	return true;
}

function exec_curl_request($handle) {
	$response = curl_exec($handle);

	if ($response === false) {
	$errno = curl_errno($handle);
	$error = curl_error($handle);
	error_log("Curl returned error $errno: $error\n");
	curl_close($handle);
	return false;
	}

	$http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
	curl_close($handle);

	if ($http_code >= 500) {
	// do not wat to DDOS server if something goes wrong
	sleep(10);
	return false;
	} else if ($http_code != 200) {
	$response = json_decode($response, true);
	error_log("Request has failed with error {$response['error_code']}: {$response['description']}\n");
	if ($http_code == 401) {
		throw new Exception('Invalid access token provided');
	}
	return false;
	} else {
	$response = json_decode($response, true);
	if (isset($response['description'])) {
		error_log("Request was successfull: {$response['description']}\n");
	}
	$response = $response['result'];
	}

	return $response;
}

function apiRequest($method, $parameters) {
	if (!is_string($method)) {
	error_log("Method name must be a string\n");
	return false;
	}

	if (!$parameters) {
	$parameters = array();
	} else if (!is_array($parameters)) {
	error_log("Parameters must be an array\n");
	return false;
	}

	foreach ($parameters as $key => &$val) {
	// encoding to JSON array parameters, for example reply_markup
	if (!is_numeric($val) && !is_string($val)) {
		$val = json_encode($val);
	}
	}
	$url = API_URL.$method.'?'.http_build_query($parameters);

	$handle = curl_init($url);
	curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($handle, CURLOPT_TIMEOUT, 60);

	return exec_curl_request($handle);
}

function apiRequestJson($method, $parameters) {
	if (!is_string($method)) {
	error_log("Method name must be a string\n");
	return false;
	}

	if (!$parameters) {
	$parameters = array();
	} else if (!is_array($parameters)) {
	error_log("Parameters must be an array\n");
	return false;
	}

	$parameters["method"] = $method;

	$handle = curl_init(API_URL);
	curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($handle, CURLOPT_TIMEOUT, 60);
	curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
	curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

	return exec_curl_request($handle);
}

function processMessage($message) {
	
	
	// process incoming message
	$user = $message['from'];
	$message_id = $message['message_id'];
	$chat_id = $message['chat']['id'];
	
	$db = file_get_contents('data/'.prefix.'-results.json');
	$dbArray = json_decode($db);
	$data[] = $message;
	array_push($dbArray, $data);
	$jsonData = json_encode($dbArray);
	file_put_contents('data/'.prefix.'-results.json', $jsonData);
	
	$sentences = file_get_contents('sentences/'.character);
	$sentences = json_decode($sentences);
	
	
	if (isset($message['text'])) {
	// incoming text message
		$text = strtolower( $message['text'] );

		if (strpos($text, "/start") === 0) {
		
			$sentenceNum = 1;		
			newMsg($sentenceNum, $chat_id, $user, $sentences);
	
		} else {
			$previousMessage = getUserPreviousMessage($chat_id);
		
			if ($previousMessage->replies){
				$validOption = false;
				$i = 0;
				foreach ($previousMessage->replies as $reply){
					$i++;
					$option = $reply->reply;
					
					if (strpos($text, $option) == 0) {
						//apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'debug: '.$replie->goto));
						newMsg($reply->goto, $chat_id, $user, $sentences);
						$validOption = true;
						break;
					}
					
				}
				if (!$validOption) {
					apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'I unfortunately couldn\'t understand your message :('));
				}
			}
		}
	} else {
		apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'I understand only text messages'));
	}
	
}


function newMsg($sentenceNum, $chat_id, $user, $sentences){
	$senddb = file_get_contents('data/'.prefix.'-sendmessages.json');
	$senddb = json_decode($senddb);
	$sentence = $sentences[$sentenceNum];
	$newText = '';
	$msg = [];
	
	
	$replies = array();
	if ($sentence->replies){
		foreach($sentence->replies as $reply){
			array_push($replies, $reply->reply);
		}
	}
	
	
	if($sentence->multiple){
		
		$i = 0;
		foreach($sentence->sentence as $singleSentence){
			
			if ($sentence->contentTypes){
				//apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => $sentence->contentTypes[$i]));
				
				$contentType = $sentence->contentTypes[$i];
				
				
				if ($contentType == 'location'){
					$lat = explode(',', $singleSentence)[0];
					$lon = explode(',', $singleSentence)[1];
					$msg = [
						"chat_id" => $chat_id,
						"latitude" => $lat,
						"longitude" => $lon,
						'reply_markup' => array(
							'keyboard' => array($replies),
							'one_time_keyboard' => true,
							'resize_keyboard' => true
						)
					];
					apiRequest("sendLocation", $msg);
					//sleep(8);
				} else if ($contentType == 'image') {
					$msg = [
						"chat_id" => $chat_id,
						"photo" => $singleSentence,
						'reply_markup' => array(
							'keyboard' => array($replies),
							'one_time_keyboard' => true,
							'resize_keyboard' => true
						)
					];
					apiRequest("sendPhoto", $msg);
					//sleep(5);

				} else if ($contentType == 'video') {
					$msg = [
						"chat_id" => $chat_id,
						"video" => $singleSentence,
						'reply_markup' => array(
							'keyboard' => array($replies),
							'one_time_keyboard' => true,
							'resize_keyboard' => true
						)
					];
					apiRequest("sendVideo", $msg);
					sleep(5);
				} else if ($contentType == 'audio') {
					$msg = [
						"chat_id" => $chat_id,
						"voice" => $singleSentence,
						'reply_markup' => array(
							'keyboard' => array($replies),
							'one_time_keyboard' => true,
							'resize_keyboard' => true
						)
					];
					apiRequest("sendVoice", $msg);
				sleep(5);
				} else {
					
					$newSentence = '';
					
					if ($sentence->personification && $i == 0) {
						$newSentence = 'Hey ' . $user['first_name'] . ', ';
					}
					$newSentence .= $singleSentence;
					
					$msg = [
						"chat_id" => $chat_id,
						"text"	=> $newSentence,
						"sentence"=> $sentenceNum,
						'reply_markup' => array(
							'keyboard' => array($replies),
							'one_time_keyboard' => true,
							'resize_keyboard' => true
						),
						"disable_web_page_preview" => true
					];
					apiRequest("sendMessage", $msg);
					sleep(str_word_count($singleSentence) / 5.5);
				}
				
			}
			
			
			//sleep(2);
			$i++;
		}
	} else {
		
		if ($sentence->personification) {
			$newText = 'Hey ' . $user['first_name'] . '. ';
		}
		
		$newText .= $sentence->sentence;
		$msg = [
			"chat_id" => $chat_id,
			"text"		=> $newText,
			"sentence"=> $sentenceNum,
			'reply_markup' => array(
				'keyboard' => array($replies),
				'one_time_keyboard' => true,
				'resize_keyboard' => true
			)
		];
		
		apiRequest("sendMessage", $msg);
	}
	
	
	$newMsg[] = [
		'chat_id'	=> $chat_id,
		'sentence' => $sentenceNum,
		'replies'	=> $sentence->replies
	];
	array_push($senddb, $newMsg);
	$msgData = json_encode($senddb);
	file_put_contents('data/'.prefix.'-sendmessages.json', $msgData);
}

function getUserPreviousMessage($chat_id) {
	
	$senddb = file_get_contents('data/'.prefix.'-sendmessages.json');
	$senddb = json_decode($senddb);
	
	$i = 0;
	$chatExists = false;
	$objNum;
	
	foreach($senddb as $msg){
		if ($msg[0]->chat_id == $chat_id){
			$chatExists = true;
			$objNum = $msg[0];
		} else if ($objNum === null && !$chatExists) {
			$objNum = 0;
		}
		$i++;
	}
	
	return $objNum;
	
	/*
foreach($send as $obj){
		if ($dbArray[$i][0]->chat->id == $chat_id){
			//apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'succes '.$chat_id));
			$lastMessage = 'Message = '.$dbArray[$i][0]->text;
			$chatExists = true;
			$objNum = $i;
		} else if ($biert === null && !$chatExists) {
			$biert = 'shit = '. $chat_id;
		}
		$i++;
	}
*/
}

define('WEBHOOK_URL', 'https://my-site.example.com/secret-path-for-webhooks/');

if (php_sapi_name() == 'cli') {
	// if run from console, set or delete webhook
	apiRequest('setWebhook', array('url' => isset($argv[1]) && $argv[1] == 'delete' ? '' : WEBHOOK_URL));
	exit;
}


$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
	// receive wrong update, must not happen
	exit;
}

if (isset($update["message"])) {
	processMessage($update["message"]);
}