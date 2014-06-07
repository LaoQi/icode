<?php

$db  = new SQLite3('pages.data');
$db->query('create table 
    page(
        id integer primary key autoincrement,
        title text,
        datetime integer,
        changetime integer,
        style text,
        content text,
        istemp integer
);');
echo $db->lastErrorMsg();
$db->query('create table 
    content(
        id integer primary key autoincrement,
        pageid integer,
        content text,
        type text,
        datetime integer default 0
);');
echo $db->lastErrorMsg();
$now = time();
$db->query("insert into page(id, title, datetime, style, istemp) values(10086, 'manage-page', {$now}, 'normal', 1);");
echo $db->lastErrorMsg();
?>
