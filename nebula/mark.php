<?php

$c = file_get_contents("README.md");
if (!empty($_GET['callback'])) { echo $_GET['callback'] . '("'; } else { echo 'callback('; }
echo base64_encode($c);
echo '");';