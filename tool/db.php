<?php
$db = new SQLite3('data.db');
    $db->query('create table tablename(id integer primary key,type integer,num real,content text,cron integer,title text,cronInfo text);');
    $info = $db->lastErrorMsg();
    echo $info;
?>
