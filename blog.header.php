<?php

$use_cache=1;
$use_gzipcompression=1;

/* Including config and functions files */
require_once (dirname(__FILE__).'/b2config.php');
$b2blah = dirname(__FILE__).'/';
if ( (substr($b2inc,0,1)=='/') || (substr($b2inc,1,1)==':') ) {
	$b2blah='./';
}
require_once ($b2blah.$b2inc.'/b2vars.php');
require_once ($b2blah.$b2inc.'/b2functions.php');
require_once ($b2blah.$b2inc.'/b2template.functions.php');
require_once ($b2blah.$b2inc.'/xmlrpc.inc');
require_once ($b2blah.$b2inc.'/xmlrpcs.inc');

$b2varstoreset = array('m','p','posts','w','c','cat','withcomments','s','search','exact','sentence','poststart','postend','preview','debug','calendar','page','more');

	for ($i=0; $i<count($b2varstoreset); $i += 1) {
		$b2var = $b2varstoreset[$i];
		if (!isset($$b2var)) {
			if (empty($HTTP_POST_VARS[$b2var])) {
				if (empty($HTTP_GET_VARS[$b2var])) {
					$$b2var = '';
				} else {
					$$b2var = $HTTP_GET_VARS[$b2var];
				}
			} else {
				$$b2var = $HTTP_POST_VARS[$b2var];
			}
		}
	}

function addslashes_gpc($gpc) {
	if (!get_magic_quotes_gpc()) {
		$gpc = addslashes($gpc);
	}
	return($gpc);
}

/* Connecting to the db */
dbconnect();

/* Getting settings from db */
$posts_per_page = get_settings('posts_per_page');
$what_to_show = get_settings('what_to_show');
$archive_mode = get_settings('archive_mode');
$dateformat = stripslashes(get_settings('date_format'));
$timeformat = stripslashes(get_settings('time_format'));
$autobr = get_settings('AutoBR');
$time_difference = get_settings('time_difference');

/* First let's clear some variables */
$whichcat = '';
$whichauthor = '';
$result = '';
$where = '';
$limits = '';
$distinct = '';

if ($pagenow != 'b2edit.php') { timer_start(); }

if ($posts)
	$posts_per_page=$posts;

// if a month is specified in the querystring, load that month
if ($m != '') {
	$m = ''.intval($m);
	$where .= ' AND YEAR(post_date)='.substr($m,0,4);
	if (strlen($m)>5)
		$where .= ' AND MONTH(post_date)='.substr($m,4,2);
	if (strlen($m)>7)
		$where .= ' AND DAYOFMONTH(post_date)='.substr($m,6,2);
	if (strlen($m)>9)
		$where .= ' AND HOUR(post_date)='.substr($m,8,2);
	if (strlen($m)>11)
		$where .= ' AND MINUTE(post_date)='.substr($m,10,2);
	if (strlen($m)>13)
		$where .= ' AND SECOND(post_date)='.substr($m,12,2);

}

if ($w != '') {
	$w = ''.intval($w);
	$where .= ' AND WEEK(post_date,1)='.$w;
}

// if a post number is specified, load that post
if (($p != '') && ($p != 'all')) {
	$p = intval($p);
	$where = ' AND ID = '.$p;
}

// if a search pattern is specified, load the posts that match
if (isset($s)) {
	$s = addslashes_gpc($s);
	$search = ' AND (';
	// puts spaces instead of commas
	$s = preg_replace('/, +/', '', $s);
	$s = str_replace(',', ' ', $s);
	$s = str_replace('"', ' ', $s);
	$s = trim($s);
	if ($exact) {
		$n = '';
	} else {
		$n = '%';
	}
	if (!$sentence) {
		$s_array = explode(' ',$s);
		$search .= '(post_title LIKE \''.$n.$s_array[0].$n.'\') OR (post_content LIKE \''.$s_array[0].'\')';
		for ( $i = 1; $i < count($s_array); $i = $i + 1) {
			$search .= ' OR (post_title LIKE \''.$n.$s_array[$i].$n.'\') OR (post_content LIKE \''.$n.$s_array[$i].$n.'\')';
		}
		$search .= ' OR (post_title LIKE \''.$n.$s.$n.'\') OR (post_content LIKE \''.$n.$s.$n.'\')';
		$search .= ')';
	} else {
		$search = ' AND ((post_title LIKE \''.$n.$s.$n.'\') OR (post_content LIKE \''.$n.$s.$n.'\'))';
	}
}

// category stuff
if ((!isset($cat)) || ($cat == 'all') || ($cat == '0')) {
	$whichcat='';
} else {
	$cat = ''.urldecode($cat).'';
	$cat = addslashes_gpc($cat);
	if (stristr($cat,'-')) {
		$eq = '!=';
		$andor = 'AND';
		$cat = explode('-',$cat);
		$cat = $cat[1];
	} else {
		$eq = '=';
		$andor = 'OR';
	}
	$cat_array = explode(' ',$cat);
	$whichcat .= ' AND post_category '.$eq.' '.$cat_array[0];
	for ($i = 1; $i < (count($cat_array)); $i = $i + 1) {
		$whichcat .= ' '.$andor.' post_category '.$eq.' '.$cat_array[$i];
	}
}

// author stuff
if ((!isset($author)) || ($author == 'all') || ($cat == '0')) {
	$whichauthor='';
} else {
	$author = intval($author);
	if (stristr($author, '-')) {
		$eq = '!=';
		$andor = 'AND';
		$author = explode('-', $author);
		$author = $author[1];
	} else {
		$eq = '=';
		$andor = 'OR';
	}
	$author_array = explode(' ', $author);
	$whichauthor .= ' AND post_author '.$eq.' '.$author_array[0];
	for ($i = 1; $i < (count($author_array)); $i = $i + 1) {
		$whichauthor .= ' '.$andor.' post_author '.$eq.' '.$author_array[$i];
	}
}

$where .= $search.$whichcat.$whichauthor;

if ((!isset($order)) || ((strtoupper($order) != 'ASC') && (strtoupper($order) != 'DESC'))) {
	$order='DESC';
}

// order by stuff
if (!isset($orderby)) {
	$orderby='date '.$order;
} else {
	$orderby = urldecode($orderby);
	$orderby = addslashes_gpc($orderby);
	$orderby_array = explode(' ',$orderby);
	$orderby = $orderby_array[0].' '.$order;
	if (count($orderby_array)>1) {
		for ($i = 1; $i < (count($orderby_array)); $i = $i + 1) {
			$orderby .= ',post_'.$orderby_array[$i].' '.$order;
		}
	}
}

if ((!$m) && (!$p) && (!$w) && (!$s) && (!$poststart) && (!$postend)) {
	if ($what_to_show == 'posts') {
		$limits = ' LIMIT '.$posts_per_page;
	} elseif ($what_to_show == 'days') {
		$lastpostdate = get_lastpostdate();
		$lastpostdate = mysql2date('Y-m-d 00:00:00',$lastpostdate);
		$lastpostdate = mysql2date('U',$lastpostdate);
		$otherdate = date('Y-m-d H:i:s', ($lastpostdate - (($posts_per_page-1) * 86400)));
		$where .= ' AND post_date > \'$otherdate\'';
	}
}

if ((isset($poststart)) && (isset($postend)) && ($postend > $poststart)) {
	$poststart = intval($poststart);
	$postend = intval($postend);
	$posts = $postend - $poststart;
	$limits = ' LIMIT '.$poststart.','.$posts;
}

if (($m) || ($p) || ($w) || ($s)) {
	$limits = '';
}

if ($p == 'all') {
	$where = '';
}

$now = date('Y-m-d H:i:s',(time() + ($time_difference * 3600)));

if ($pagenow != 'b2edit.php') {
	$where .= ' AND post_date < \''.$now.'\' AND post_category > 0';
	$distinct = 'DISTINCT';
	if ($use_gzipcompression) {
		// gzipping the output of the script
		gzip_compression();
	}
}

$request = " SELECT $distinct * FROM $tableposts WHERE 1=1".$where." ORDER BY post_$orderby $limits";

if ($preview) {
	$request = 'SELECT 1-1'; // dummy mysql query for the preview
	// little funky fix for IEwin, rawk on that code
	$is_winIE = ((preg_match('/MSIE/',$HTTP_USER_AGENT)) && (preg_match('/Win/',$HTTP_USER_AGENT)));
	if (($is_winIE) && (!isset($IEWin_bookmarklet_fix))) {
		$preview_content =  preg_replace('/\%u([0-9A-F]{4,4})/e',  "'&#'.base_convert('\\1',16,10).';'", $preview_content);
	}
}

//echo $request;
$result = mysql_query($request);
?>