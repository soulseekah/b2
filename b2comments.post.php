<?php

# if you want to change the paths here, remember to put your new path BEFORE $b2inc,
#  like this: "b2/$b2inc/b2functions.php"

require("b2config.php");
include("$b2inc/b2vars.php");
include("$b2inc/b2functions.php");

dbconnect();

$author = $HTTP_POST_VARS["author"];
$email = $HTTP_POST_VARS["email"];
$url = $HTTP_POST_VARS["url"];
$comment = $HTTP_POST_VARS["comment"];
$original_comment = $comment;
$autobr = $HTTP_POST_VARS["comment_autobr"];
$comment_post_ID = $HTTP_POST_VARS["comment_post_ID"];

if (($require_name_email) && ($email == "" || $email == "@") || $author == "" || $author == "name") { //original fix by Dodo
	echo "Error: please fill the required fields (name, email)";
	exit;
}
if ($comment == "comment" || $comment == "") {
	echo "Error: please type a comment";
	exit;
}

$user_ip = $REMOTE_ADDR;
$user_domain = gethostbyaddr($user_ip);
$time_difference = get_settings("time_difference");
$now = date("Y-m-d H:i:s",(time() + ($time_difference * 3600)));

$author = strip_tags($author);
$email = strip_tags($email);
if (strlen($email) < 6) {
	$email = "";
}
$url = strip_tags($url);
if (strlen($url) < 7) {
	$url = "";
}
$comment = strip_tags($comment,$comment_allowed_tags);
$comment = balanceTags($comment);
$comment = convert_chars($comment);
$comment = format_to_post($comment);

$comment_author = $author;
$comment_author_email = $email;
$comment_author_url = $url;

$author = addslashes($author);
$email = addslashes($email);
$url = addslashes($url);

/* flood-protection */
$query = "SELECT * FROM $tablecomments WHERE comment_author_IP='$user_ip' ORDER BY comment_date DESC LIMIT 1";
$result = mysql_query($query);
$ok=1;
if (!empty($result)) {
	while($row = mysql_fetch_object($result)) {
		$then=$row->comment_date;
	}
	$time_lastcomment=mysql2date("U","$then");
	$time_newcomment=mysql2date("U","$now");
	if (($time_newcomment - $time_lastcomment) < 30)
		$ok=0;
}
/* end flood-protection */

if ($ok) {

	$query = "INSERT INTO $tablecomments VALUES ('0','$comment_post_ID','$author','$email','$url','$user_ip','$now','$comment','0')";
	$result = mysql_query($query);
	if (!$result)
		die ("There is an error with the database, it can't store your comment...<br>Contact the <a href=\"mailto:$admin_email\">webmaster</a>");

	if ($comments_notify) {

		$notify_message  = "New comment on your post #$comment_post_ID.\r\n\r\n";
		$notify_message .= "author : $comment_author (IP: $user_ip , $user_domain)\r\n";
		$notify_message .= "e-mail : $comment_author_email\r\n";
		$notify_message .= "url    : $comment_author_url\r\n";
		$notify_message .= "comment: \n".stripslashes($original_comment)."\r\n\r\n";
		$notify_message .= "You can see all comments on this post there: \r\n";
		$notify_message .= "$siteurl/$blogfilename?p=$comment_post_ID&c=1\r\n\r\n";

		$postdata = get_postdata($comment_post_ID);
		$authordata = get_userdata($postdata["Author_ID"]);
		$recipient = $authordata["user_email"];
		$subject = "comment on post #$comment_post_ID \"".$postdata["Title"]."\"";

		@mail($recipient, $subject, $notify_message, "From: b2@$SERVER_NAME\r\n"."X-Mailer: b2 $b2_version - PHP/" . phpversion());
		
	}

	if ($email == "") {
		$email = " "; // this to make sure a cookie is set for 'no email'
	}
	if ($url == "") {
		$url = " "; // this to make sure a cookie is set for 'no url'
	}
	setcookie("comment_author",$author, time()+30000000);
	setcookie("comment_author_email",$email, time()+30000000);
	setcookie("comment_author_url",$url, time()+30000000);

	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header("Cache-Control: no-cache, must-revalidate");
	header("Pragma: no-cache");
	$location = ((isset($redirect_to) && ($redirect_to != ''))) ? $redirect_to : $HTTP_SERVER_VARS["HTTP_REFERER"];
	header("Location: $location");

} else {
	die("Sorry, you can only post a new comment every 30 seconds");
}

?>