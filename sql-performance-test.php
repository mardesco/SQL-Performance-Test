<?php
//sql-performance-test.php

echo '<h1>SQL Performance Testing.</h1>';

$host = 'localhost';
$user = 'dbusername';//username
$pass = 'dbpassword';//password
$db = 'dbname';//database

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die('Connect Error (' . $mysqli->connect_errno . ') '
            . $mysqli->connect_error);
}

echo "<p>Connected to " . $mysqli->host_info . "</p>";

/*
This script assumes that you have already created the test table:

create table test (
id mediumint unsigned not null auto_increment, 
name varchar(240) null, 
data bigint null, 
primary key (id) 
);
*/


echo '<h2>First, escaped strings.</h2>';

$time1 = microtime(true);

for($i=0; $i<2000; $i++){

// we're synthesizing a 240-character username.
$tmp = openssl_random_pseudo_bytes(120, $strong);

if(!$tmp){die('your server does not support openssl');}
if(!$strong){die('the system used a weak algorithm');}

$str = bin2hex($tmp);

$name = $mysqli->escape_string($str);

$query = "INSERT INTO test (name) VALUES ('$name')";
$result = $mysqli->query($query);
if(!$result){
	die('Could not complete the query.');
}

// we're not actually using this at the moment, but for argument's sake:
$insert_id = $mysqli->insert_id;

$query = "SELECT id FROM test WHERE name LIKE '%$name%'";

$result = $mysqli->query($query);

if(!$result){
	die('Could not complete the query.');
}

while($row = $result->fetch_array()){//does this require MYSQLI_ASSOC as an argument?  Or is that the default?
	$id = $row['id'];
}

$result->close();//only works when result returns data, eg select.

if($id != $insert_id){
	echo "<p>This is interesting, got $id but expected $insert_id.  </p>";
}



$num = $mysqli->escape_string(mt_rand());

$query = "UPDATE test SET data = '$num' WHERE id = '$id' LIMIT 1";

$result = $mysqli->query($query);

if(!$result){
	die('Could not complete the query.');
}


	
}// end "escaped string" loop


$mysqli->close();

$time2 = microtime(true);

$elapsed1 = $time2 - $time1;

printf('<p>Completed %d iterations in %f seconds.</p>', $i, $elapsed1);

echo '<h2>Now for the prepared statements.</h2>';

echo "<p>From the manual:</p><blockquote>A prepared statement executed only once causes more client-server round-trips than a non-prepared statement.</blockquote><cite>http://www.php.net/manual/en/mysqli.quickstart.prepared-statements.php</cite>";

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die('Connect Error (' . $mysqli->connect_errno . ') '
            . $mysqli->connect_error);
}

echo "<p>Connected to " . $mysqli->host_info . "</p>";

$time3 = microtime(true);

// For prepared statements, we're defining all the statements before the loop.

$insert_stmt = $mysqli->prepare("INSERT INTO test (name) VALUES (?)");
if(!$insert_stmt){
	die($mysqli->error);
}

if(!($select_stmt = $mysqli->prepare("SELECT id FROM test WHERE name LIKE ?"))){
	die( "Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error  );
}

$update_stmt = $mysqli->prepare("UPDATE test SET data = ? WHERE id = ? LIMIT 1");

// that's all of our statements.

// begin loop.


for($i=0; $i<2000; $i++){

// we're synthesizing a 240-character username.
$tmp = openssl_random_pseudo_bytes(120, $strong);

if(!$tmp){die('your server does not support openssl');}
if(!$strong){die('the system used a weak algorithm');}

$str = bin2hex($tmp);

// don't have to escape a string for prepared statements!

$insert_stmt->bind_param("s", $str);


if(!$insert_stmt->execute()){
	die('Could not complete the query. ' . $insert_stmt->error);
}

// we're not actually using this at the moment, but again for argument's sake:
$insert_id = $mysqli->insert_id;

$name = '%'.$str.'%';

if (!$select_stmt->bind_param("s", $name)) {
    die( "Binding parameters failed: (" . $select_stmt->errno . ") " . $select_stmt->error );
}

$select_stmt->execute();
$result = $select_stmt->get_result();
//$row = $result->fetch_assoc();


while($row = $result->fetch_assoc()){//does this require MYSQLI_ASSOC as an argument?  Or is that the default?
	$id = $row['id'];
}


if($id != $insert_id){
	echo "<p>This is interesting, got $id but expected $insert_id.  </p>";
}


$num = mt_rand();


if(!($update_stmt->bind_param("ii", $num, $id))){
	die( "Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error );
}


if(!$update_stmt->execute()){
	die('Could not complete the query. ' . $update_stmt->error);
}





}// end prepared statement loop

$insert_stmt->close();
$select_stmt->close();
$update_stmt->close();


$mysqli->close();

$time4 = microtime(true);

$elapsed2 = $time4 - $time3;

printf('<p>Completed %d iterations in %f seconds.</p>', $i, $elapsed2);

$relativity = $elapsed2 > $elapsed1 ? 'longer' : 'less';
$diff = $elapsed2 - $elapsed1;

printf('<p>Prepared statements took %f seconds %s than simple escaped strings.</p>', $diff, $relativity);

?>
