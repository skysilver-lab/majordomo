#!/bin/bash
#
# Вспомогательный shell-скрипт для резервного копирования через PHP-скрипт
# (для архивирования тех каталогов, на которые у www-data нет права на чтение).
#
# Чтобы дать пользователю www-data возможность запускать этот скрипт от root,
# необходимо в /etc/sudoers добавить строку www-data ALL=(root) NOPASSWD: /путь_до_скрипта/backup.sh
#
# Принимает три аргумента:
#  $1 - параметры команды tar
#  $2 - выходной файл архива tar.gz
#  $3 - входной каталог для архивирования
#
# Запустить из PHP-скрипта можно так: 
# $cmd = "sudo /var/www/backup.sh -czpPf $filename $dirpath";
# $out = exec($cmd);
#
# Copyright (C) 2014-2015 Agaphonov Dmitri aka skysilver [mailto:skysilver.da@gmail.com]

if [ -n "$1" ]
then
 if [ -n "$2" ]
 then
  if [ -n "$3" ]
  then
	tar $1 $2 $3
	chown www-data:www-data $2
	chmod 666 $2
  fi
 fi
fi

exit 0
