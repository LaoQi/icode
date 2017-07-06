#!/bin/bash

echo "database backup start at "`date +%T-%x`
DB_USER="root"
DB_PASS=""

BACKUP_FILE="/backups/"`date +%Y%m%d`

db=("db1" "db2" "db3")
for D in ${db[@]};do
    #mysqldump -u$DB_USER -p$DB_PASS -h 127.0.0.1 --default-character-set=utf8 $D > $BACKUP_FILE"_"$D
    echo `date +%T` $D
    mysqldump -u$DB_USER --default-character-set=utf8 $D > $BACKUP_FILE"_"$D
done
echo "database backup finish at "`date +%T-%x`
echo "clean old file"

find /backups/ -type f -mtime +5 | xargs rm -vf
echo "finish"