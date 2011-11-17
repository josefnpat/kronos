<?php
// The context stream doesn't work, as it's disabled by kronos, so we need to use curl.
// Thanks http://davidwalsh.name/ (David Walsh) for letting me mutilate this code.
// Source: http://davidwalsh.name/execute-http-post-php-curl

include('config.php');//usernamepasswordmuch?
//set POST variables
$url = 'https://finweb.rit.edu/kronos/apps/timecardreview/';
$fields = array(
  'j_username'=>urlencode($username),
  'j_password'=>urlencode($password)
);

//url-ify the data for the POST
$fields_string = "";
foreach($fields as $key=>$value) {
  $fields_string .= $key.'='.$value.'&';
}
rtrim($fields_string,'&');

//open connection
$ch = curl_init();
curl_setopt($ch,CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)"); // bitchin.
curl_setopt($ch,CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch,CURLOPT_URL,$url);
curl_setopt($ch,CURLOPT_POST,count($fields));
curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);

//execute post
$result = curl_exec($ch);

//close connection
curl_close($ch);
