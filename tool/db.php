<?php
$db = new SQLite3('pages.data');
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
$db->query('create table 
    content(
        id integer primary key autoincrement,
        pageid integer,
        content text,
        type text,
        datetime integer default 0
);');
    $info = $db->lastErrorMsg();
    echo $info;
?>
