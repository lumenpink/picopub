#!/usr/bin/env php
<?php
function picopub_errors($errno, $errstr)
{
    return !!preg_match('~^(Trying to access array offset on value of type null|Undefined array key)~', $errstr);
}

error_reporting(6135); // errors and warnings
set_error_handler('picopub_errors', E_WARNING);
include dirname(__FILE__) . "/include/version.php";

function add_apo_slashes($s)
{
    return addcslashes($s, "\\'");
}

function add_quo_slashes($s)
{
    $return = $s;
    $return = addcslashes($return, "\n\r\$\"\\");
    $return = preg_replace('~\0(?![0-7])~', '\\\\0', $return);
    $return = addcslashes($return, "\0");
    return $return;
}

function compile_file($match)
{
    global $project;
    $file = "";
    list(, $filenames, $callback) = $match;
    if ($filenames != "") {
        foreach (explode(";", $filenames) as $filename) {
            $file .= file_get_contents(dirname(__FILE__) . "/$project/$filename");
        }
    }
    if ($callback) {
        $file = call_user_func($callback, $file);
    }
    return '"' . add_quo_slashes($file) . '"';
}

function put_file($match)
{
    $return = file_get_contents(dirname(__FILE__) . "/$match[2]");
    $tokens = token_get_all($return); // to find out the last token
    return "?>\n$return" . (in_array($tokens[count($tokens) - 1][0], array(T_CLOSE_TAG, T_INLINE_HTML), true) ? "<?php" : "");
}

function php_shrink($input)
{
    global $VERSION;
    $special_variables = array_flip(array('$this', '$GLOBALS', '$_GET', '$_POST', '$_FILES', '$_COOKIE', '$_SESSION', '$_SERVER', '$http_response_header', '$php_errormsg'));
    $short_variables = array();
    $shortening = true;
    $tokens = token_get_all($input);

    // remove unnecessary { }
    //! change also `while () { if () {;} }` to `while () if () ;` but be careful about `if () { if () { } } else { }
    $shorten = 0;
    $opening = -1;
    foreach ($tokens as $i => $token) {
        if (in_array($token[0], array(T_IF, T_ELSE, T_ELSEIF, T_WHILE, T_DO, T_FOR, T_FOREACH), true)) {
            $shorten = ($token[0] == T_FOR ? 4 : 2);
            $opening = -1;
        } elseif (in_array($token[0], array(T_SWITCH, T_FUNCTION, T_CLASS, T_CLOSE_TAG), true)) {
            $shorten = 0;
        } elseif ($token === ';') {
            $shorten--;
        } elseif ($token === '{') {
            if ($opening < 0) {
                $opening = $i;
            } elseif ($shorten > 1) {
                $shorten = 0;
            }
        } elseif ($token === '}' && $opening >= 0 && $shorten == 1) {
            unset($tokens[$opening]);
            unset($tokens[$i]);
            $shorten = 0;
            $opening = -1;
        }
    }
    $tokens = array_values($tokens);

    foreach ($tokens as $i => $token) {
        if ($token[0] === T_VARIABLE && !isset($special_variables[$token[1]])) {
            $short_variables[$token[1]]++;
        }
    }

    arsort($short_variables);
    $chars = implode(range('a', 'z')) . '_' . implode(range('A', 'Z'));
    // preserve variable names between versions if possible
    $short_variables2 = array_splice($short_variables, strlen($chars));
    ksort($short_variables);
    ksort($short_variables2);
    $short_variables += $short_variables2;
    foreach (array_keys($short_variables) as $number => $key) {
        $short_variables[$key] = short_identifier($number, $chars); // could use also numbers and \x7f-\xff
    }

    $set = array_flip(preg_split('//', '!"#$%&\'()*+,-./:;<=>?@[]^`{|}'));
    $space = '';
    $output = '';
    $in_echo = false;
    $doc_comment = false; // include only first /**
    foreach ($tokens  as $i => $token) {
        if (!is_array($token)) {
            $token = array(0, $token);
        }
        if (
            isset($tokens[$i + 2][0]) &&
            isset($tokens[$i + 3][0]) &&
            isset($tokens[$i + 4][0]) &&
            $tokens[$i + 2][0] === T_CLOSE_TAG &&
            $tokens[$i + 3][0] === T_INLINE_HTML &&
            $tokens[$i + 4][0] === T_OPEN_TAG
            && strlen(add_apo_slashes($tokens[$i + 3][1])) < strlen($tokens[$i + 3][1]) + 3
        ) {
            $tokens[$i + 2] = array(T_ECHO, 'echo');
            $tokens[$i + 3] = array(T_CONSTANT_ENCAPSED_STRING, "'" . add_apo_slashes($tokens[$i + 3][1]) . "'");
            $tokens[$i + 4] = array(0, ';');
        }
        if ($token[0] == T_COMMENT || $token[0] == T_WHITESPACE || ($token[0] == T_DOC_COMMENT && $doc_comment)) {
            $space = "\n";
        } else {
            if ($token[0] == T_DOC_COMMENT) {
                $doc_comment = true;
                $token[1] = substr_replace($token[1], "* @version $VERSION\n", -2, 0);
            }
            if ($token[0] == T_VAR) {
                $shortening = false;
            } elseif (!$shortening) {
                if ($token[1] == ';') {
                    $shortening = true;
                }
            } elseif ($token[0] == T_ECHO) {
                $in_echo = true;
            } elseif ($token[1] == ';' && $in_echo) {
                if ($tokens[$i + 1][0] === T_WHITESPACE && $tokens[$i + 2][0] === T_ECHO) {
                    next($tokens);
                    $i++;
                }
                if ($tokens[$i + 1][0] === T_ECHO) {
                    // join two consecutive echos
                    next($tokens);
                    $token[1] = ','; // '.' would conflict with "a".1+2 and would use more memory //! remove ',' and "," but not $var","
                } else {
                    $in_echo = false;
                }
            } elseif ($token[0] === T_VARIABLE && !isset($special_variables[$token[1]])) {
                $token[1] = '$' . $short_variables[$token[1]];
            }
            if (isset($set[substr($output, -1)]) || isset($set[$token[1][0]])) {
                $space = '';
            }
            $output .= $space . $token[1];
            $space = '';
        }
    }
    return $output;
}

if (!function_exists("each")) {
    function each(&$arr)
    {
        $key = key($arr);
        next($arr);
        return $key === null ? false : array($key, $arr[$key]);
    }
}

function short_identifier($number, $chars)
{
    $return = '';
    while ($number >= 0) {
        $return .= $chars[$number % strlen($chars)];
        $number = floor($number / strlen($chars)) - 1;
    }
    return $return;
}

$file = file_get_contents(dirname(__FILE__) . "/index.php");
$file = preg_replace_callback('~\b(include|require) "([^"]*)";~', 'put_file', $file);
$file = str_replace("\r", "", $file);
$file = str_replace('<?php echo script_src("static/editing.js"); ?>' . "\n", "", $file);
$file = preg_replace('~\s+echo script_src\("\.\./externals/jush/modules/jush-(textarea|txt|js|\$jush)\.js"\);~', '', $file);
$file = str_replace('<link rel="stylesheet" type="text/css" href="../externals/jush/jush.css">' . "\n", "", $file);
$file = preg_replace_callback("~compile_file\\('([^']+)'(?:, '([^']*)')?\\)~", 'compile_file', $file); // integrate static files
$replace = 'preg_replace("~\\\\\\\\?.*~", "", ME) . "?file=\1&version=' . $VERSION . '"';
$file = preg_replace("~<\\?php\\s*\\?>\n?|\\?>\n?<\\?php~", '', $file);
$file = php_shrink($file);

$filename = 'picopub' . (preg_match('~-dev$~', $VERSION) ? "" : "-$VERSION") . ".php";
file_put_contents($filename, $file);
echo "$filename created (" . strlen($file) . " B).\n";
