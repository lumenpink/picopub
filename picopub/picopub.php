<?php
/** Picopub - Very smol Micropub and Webmention Server
 * @link https://picopub.2lp.in/
 * @author Lumen Pink (https://lumen.pink/)
 * @license CC0-1.0 (https://creativecommons.org/publicdomain/zero/1.0/)
 * 
 * This software uses a lot of code from ADMINER (https://www.adminer.org/) 
 * from Jakub Vrána Jakub, https://www.vrana.cz/
 * @version 0.0.1-dev
*/$b="0.0.1-dev";function
get_version(){return
isset($b)?$b:'zero';}class
Request{private$c;private$k;private$i;private$g=[];private$d=[];private$j=['replace'=>[],'add'=>[],'delete'=>[],];public
static
function
create($p){if(is_object($p))return
self::createFromJSONObject($p);if(is_array($p)){if(isset($p['h']))return
self::createFromPostArray($p);if(isset($p['type']))return
self::createFromJSONObject($p);}if(is_string($p))return
self::createFromString($p);return
new
P3kError('invalid_input',null,'Input could not be parsed as either JSON or form-encoded');}public
static
function
createFromString($w){$q=@json_decode($w,true);if($q)return
self::createFromJSONObject($q);parse_str($w,$o);if($o)return
self::createFromPostArray($o);return
new
P3kError('invalid_input',null,'Input could not be parsed as either JSON or form-encoded');}public
static
function
createFromJSONObject($p){$v=new
Request();if(is_object($p))$p=json_decode(json_encode($p,JSON_FORCE_OBJECT),true);else
if(!is_array($p))return
new
P3kError('invalid_input',null,'Input was not an array.');if(isset($p['type'])){if(!is_array($p['type']))return
new
P3kError('invalid_input','type','Property type must be an array of Microformat vocabularies');$v->_action='create';$v->_type=$p['type'];if(!isset($p['properties'])||!is_array($p['properties']))return
new
P3kError('invalid_input','properties','In JSON format, all properties must be specified in a properties object');$t=$p['properties'];foreach($t
as$r=>$x){if(!is_array($x)||!isset($x[0]))return
new
P3kError('invalid_input',$r,'Values in JSON format must be arrays, even when there is only one value');if(substr($r,0,3)=='mp-')$v->_commands[$r]=$x;else$v->_properties[$r]=$x;}}elseif(isset($p['action'])){if(!isset($p['url']))return
new
P3kError('invalid_input','url','Micropub actions require a URL property');$v->_action=$p['action'];$v->_url=$p['url'];if($p['action']=='update'){foreach(array_keys($v->_update)as$l){if(isset($p[$l])){if(!is_array($p[$l]))return
new
P3kError('invalid_input',$l,'Invalid syntax for update action');foreach($p[$l]as$s=>$x){if($s!='delete'&&!is_array($x))return
new
P3kError('invalid_input',$l.'.'.$s,'All values in update actions must be arrays');}$v->_update[$l]=$p[$l];}}}}else
return
new
P3kError('invalid_input',null,'No Micropub request data was found in the input');return$v;}public
static
function
createFromPostArray($a){$v=new
Request();if(isset($a['h'])){$v->_action='create';$v->_type=['h-'.$a['h']];unset($a['h']);unset($a['access_token']);if(isset($a['action']))return
new
P3kError('invalid_input','action','Cannot specify an action when creating a post');foreach($a
as$r=>$x){if(is_array($x)&&!isset($x[0]))return
new
P3kError('invalid_input',$r,'Values in form-encoded input can only be numeric indexed arrays');if(is_array($x)&&isset($x[0])&&is_array($x[0]))return
new
P3kError('invalid_input',$r,'Nested objects are not allowed in form-encoded requests');if(!is_array($x))$x=[$x];if(substr($r,0,3)=='mp-')$v->_commands[$r]=$x;else$v->_properties[$r]=$x;}}elseif(isset($a['action'])){if($a['action']=='update')return
new
P3kError('invalid_input','action','Micropub update actions require using the JSON syntax');if(!isset($a['url']))return
new
P3kError('invalid_input','url','Micropub actions require a URL property');$v->_action=$a['action'];$v->_url=$a['url'];}else
return
new
P3kError('invalid_input',null,'No Micropub request data was found in the input');return$v;}public
function
toMf2(){return['type'=>$this->_type,'properties'=>$this->_properties];}public
function
__get($r){switch($r){case'action':return$this->_action;case'commands':return$this->_commands;case'properties':return$this->_properties;case'update':return$this->_update;case'url':return$this->_url;case'error':return
false;}return
null;}}class
P3kError{private$f;private$h;private$e;public
function
__construct($n,$u,$m){$this->_error=$n;$this->_property=$u;$this->_description=$m;}public
function
toArray(){return['error'=>$this->_error,'error_property'=>$this->_property,'error_description'=>$this->_description,];}public
function
toMf2(){return$this->toArray();}public
function
__toString(){return
json_encode($this->toArray());}public
function
__get($r){switch($r){case'error':return$this->_error;case'error_property':return$this->_property;case'error_description':return$this->_description;}throw
new
Exception('A Micropub error occurred, and you attempted to access the Error object as though it was a successful request. You should check that the object returned was an error and handle it properly.');}}$l=new
Request();var_dump($l);