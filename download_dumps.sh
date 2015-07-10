#!/bin/bash

DEST="dumps"

if [ ! -d $DEST ]; then
	mkdir $DEST
fi

for LANG in en it; do

    for TABLE in categorylinks page pagelinks; do
        URL="https://dumps.wikimedia.org/"$LANG"wikisource/latest/"$LANG"wikisource-latest-$TABLE.sql.gz"
        curl -o $DEST"/"$LANG"_"$TABLE".sql.gz" $URL
    done

done
