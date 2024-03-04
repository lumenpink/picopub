<?php

/** Picopub - Very smol Micropub and Webmention Server
 * @link https://picopub.2lp.in/
 * @author Lumen Pink (https://lumen.pink/)
 * @license CC0-1.0 (https://creativecommons.org/publicdomain/zero/1.0/)
 * 
 * This software uses a lot of code from ADMINER (https://www.adminer.org/) 
 * from Jakub Vrána Jakub, https://www.vrana.cz/
 */

include "include/version.php";
include "include/functions.php";
include "include/micropub_functions.php";
include "include/request.php";

$mysite = 'https://lumen.pink/'; // Change this to your website.
$token_endpoint = 'https://tokens.indieauth.com/token';


$_HEADERS = array();
foreach (getallheaders() as $name => $value) {
    $_HEADERS[$name] = $value;
}

if (!isset($_HEADERS['Authorization'])) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 401 Unauthorized');
    echo 'Missing "Authorization" header.';
    exit;
}
if (!isset($_POST['h'])) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
    echo 'Missing "h" value.';
    exit;
}

$options = array(
    CURLOPT_URL => $token_endpoint,
    CURLOPT_HTTPGET => TRUE,
    CURLOPT_USERAGENT => $mysite,
    CURLOPT_TIMEOUT => 5,
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_HEADER => FALSE,
    CURLOPT_HTTPHEADER => array(
        'Content-type: application/x-www-form-urlencoded',
        'Authorization: ' . $_HEADERS['Authorization']
    )
);

$curl = curl_init();
curl_setopt_array($curl, $options);
$source = curl_exec($curl);
curl_close($curl);

parse_str($source, $values);

if (!isset($values['me'])) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
    echo 'Missing "me" value in authentication token.';
    exit;
}
if (!isset($values['scope'])) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
    echo 'Missing "scope" value in authentication token.';
    exit;
}
if (substr($values['me'], -1) != '/') {
    $values['me'] .= '/';
}
if (substr($mysite, -1) != '/') {
    $mysite .= '/';
}
if (strtolower($values['me']) != strtolower($mysite)) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
    echo 'Mismatching "me" value in authentication token.';
    exit;
}
if (!stristr($values['scope'], 'post')) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
    echo 'Missing "post" value in "scope".';
    exit;
}
if (!isset($_POST['content'])) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
    echo 'Missing "content" value.';
    exit;
}

/* Everything's cool. Do something with the $_POST variables
   (such as $_POST['content'], $_POST['category'], $_POST['location'], etc.)
   e.g. create a new entry, store it in a database, whatever. */

if (strtolower($_SERVER['CONTENT_TYPE']) == 'application/json' || strtolower($_SERVER['HTTP_CONTENT_TYPE']) == 'application/json') {
    $request = Request::createFromJSONObject(json_decode(file_get_contents('php://input'), true));
} else {
    $request = Request::createFromPostArray($_POST);
}
if ($request->error) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request - ' . $request->error_property);
    echo $request->error_description;
}

switch ($request->action):
    case 'delete':
        setPublishedPost($request->url, FALSE);
        break;
    case 'undelete':
        setPublishedPost($request->url, TRUE);
        break;
    case 'update':
        not_implemented($request->action);
        // update($request, $photo_urls);
        break;
    default:
        create($request, $photo_urls);
        break;
endswitch;












header($_SERVER['SERVER_PROTOCOL'] . ' 201 Created');
header('Location: ' . $mysite);
exit;
