decode(<?php
$c = file_get_contents("README.md");
echo '"' . base64_encode($c) . '"';
?>)
YouShallNotPass
alert("1")