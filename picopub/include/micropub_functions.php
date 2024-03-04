<?php

function not_implemented($action)
{
    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
    die("Feature not yet implemented: " . $action);
}
function quit($status, $error, $description)
{
    header($_SERVER['SERVER_PROTOCOL'] . ' ' . $status . ' ' . $error);
    echo $description;
    exit;
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
