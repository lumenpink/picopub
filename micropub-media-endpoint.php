<?php
/**
 * Minimal micropub media endpoint
 *
 * @author Christian Weiske <cweiske@cweiske.de>
 */
function error($code, $error, $description)
{
    header('HTTP/1.0 ' . $code);
    header('Content-type: application/json');
    echo json_encode(
        ['error' => $error, 'error_description' => $description]
    ) . "\n";
    exit(1);
}

if (!isset($_FILES['file'])) {
    error(400, 'invalid_request', 'file property missing');
}
$file = $_FILES['file'];
if (!is_int($file['error'])) {
    error(400, 'invalid_request', 'file not uploaded correctly');
}
if ($file['error'] != 0) {
    error(
        400, 'invalid_request',
        'file upload failed; php upload error' . $file['error']
    );
}

$reldir = '/micropub-media-endpoint/' . microtime(true) . '/';
if (!is_dir(__DIR__ . $reldir)) {
    $ok = mkdir(__DIR__ . $reldir, 0700, true);
    if (!$ok) {
        error(403, 'forbidden', 'Failed to create upload directory');
    }
}
if ($file['name'] == '') {
    $file['name'] = 'file.dat';
}
$relfile = $reldir . $file['name'];
$ok = move_uploaded_file($file['tmp_name'], __DIR__ . $relfile);
if (!$ok) {
    error(500, 'internal_error', 'Failed to move uploaded file');
}

$dir = dirname($_SERVER['PHP_SELF']);
header('HTTP/1.1 201 Created');
//RFC 7231 allows relative URIs in location header
header('Location: ' . str_replace('//', '/', $dir . $relfile));
?>
