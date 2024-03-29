<?php

# Licensed under a CC0 1.0 Universal (CC0 1.0) Public Domain Dedication
# http://creativecommons.org/publicdomain/zero/1.0/

require_once 'vendor/autoload.php';
include_once './postinsertion.php';

use p3k\Micropub\Request;

$mysite = 'https://jacobhall.net/'; // Change this to your website.
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
/* 
if (!isset($_POST['h'])) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
    echo 'Missing "h" value.';
    exit;
}
*/
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
if (!stristr($values['scope'], 'create')) {
	header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
	echo 'Missing "post" value in "scope".';
	exit;
}
/*
if (!isset($_POST['content'])) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
    echo 'Missing "content" value.';
    exit;
}
*/
/* Everything's cool. Do something with the $_POST variables
   (such as $_POST['content'], $_POST['category'], $_POST['location'], etc.)
   e.g. create a new entry, store it in a database, whatever. */


if (strtolower($_SERVER['CONTENT_TYPE']) == 'application/json' || strtolower($_SERVER['HTTP_CONTENT_TYPE']) == 'application/json') {
	$request = Request::createFromJSONObject(json_decode(file_get_contents('php://input'), true));
} else {
	$request = Request::createFromPostArray($_POST);
}
if ($request->error) {
	quit(400, $request->error_property, $request->error_description);
}

function not_implemented($action)
{
	header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
	die("Feature not yet implemented: " . $action);
}

# From https://github.com/skpy/micropub/blob/master/inc/content.php
# this function accepts the properties of a post and
# tries to perform post type discovery according to
# https://indieweb.org/post-type-discovery
# returns the MF2 post type
function post_type_discovery($properties)
{
	$vocab = [
		'rsvp' => 9,
		'in-reply-to' => 9,
		'repost-of' => 9,
		'like-of' => 9,
		'bookmark-of' => 5,
		'photo' => 9,
	];
	foreach (array_keys($vocab) as $type) {
		if (isset($properties[$type])) {
			return $vocab[$type];
		}
	}
	# articles have titles, which Micropub defines as "name"
	# ...except I don't want that to be the behavior on my site. if there's a title, it's just a note with a title.
	# 
	/*if (isset($properties['name'])) {
        return 1;
    }
	*/
	# no other match?  Must be a note.
	return 2;
}

function make_html($input)
{
	$output = trim($input);
	$output = ("<p>\n" . $output . "\n</p>");
	$output = preg_replace("/\n\n+/i", "\n</p>\n<p>\n", $output);
	return $output;
}

// We'll be better about handling photos another day :)
$photo_urls = array();

function normalize_properties($properties)
{
	$props = [];
	foreach ($properties as $k => $v) {
		# we want the "photo" property to be an array, even if it's a
		# single element.  Our Hugo templates require this.
		if ($k == 'photo') {
			$props[$k] = $v;
		} elseif (is_array($v) && count($v) === 1) {
			$props[$k] = $v[0];
		} else {
			$props[$k] = $v;
		}
	}
	return $props;
}


// Much of this code borrowed from https://github.com/skpy/micropub/blob/master/inc/content.php
function create($request, $photos = [])
{

	$mf2 = $request->toMf2();
	# make a more normal PHP array from the MF2 JSON array
	$properties = normalize_properties($mf2['properties']);

	# pull out just the content, so that $properties can be front matter
	# NOTE: content may be in ['content'] or ['content']['html'].
	# NOTE 2: there may be NO content!
	if (isset($properties['content'])) {
		if (is_array($properties['content']) && isset($properties['content']['html'])) {
			$content = $properties['content']['html'];
		} else {
			$content = make_html($properties['content']);
		}
	} else {
		$content = '';
	}
	# ensure that the properties array doesn't contain 'content'
	unset($properties['content']);

	if (!empty($photos)) {
		# add uploaded photos to the front matter.
		if (!isset($properties['photo'])) {
			$properties['photo'] = $photos;
		} else {
			not_implemented("photo uploads");
			// $properties['photo'] = array_merge($properties['photo'], $photos);
		}
	}
	if (!empty($properties['photo'])) {
		//	$properties['thumbnail'] = preg_replace('#-' . $config['max_image_width'] . '\.#', '-200.', $properties['photo']);
	}

	# figure out what kind of post this is.
	$properties['posttype'] = post_type_discovery($properties);
	// echo ("post type is: ". $properties['posttype']);

	if (isset($properties['post-status'])) {
		if ($properties['post-status'] == 'draft') {
			$properties['published'] = false;
		} else {
			$properties['published'] = true;
		}
		unset($properties['post-status']);
	} else {
		# explicitly mark this item as published
		$properties['published'] = true;
	}

	// echo ("published: ". $properties['published']);
	$insertionData = [
		"posttype" => $properties['posttype'],
		"posttitle" => NULL,
		"content" => $content,
		"published" => $properties['published'],
		"bookmarkof" => NULL,
	];
	if (isset($properties["category"])) {
		if (is_array($properties["category"])) {
			$categories = $properties["category"];
		} else {
			$categories = array();
			array_push($categories, $properties["category"]);
		}
	} else {
		$categories = array();
	}

	if (isset($properties["bookmark-of"])) {
		$insertionData["bookmarkof"] = $properties["bookmark-of"];
	}
	if (isset($properties["name"])) {
		$insertionData["posttitle"] = $properties["name"];
	}

	insertPost("entries", $insertionData, $categories);
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
