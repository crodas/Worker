#!/bin/bash -x
cd `dirname $0`
rel r:setup --user=root mysql:dbname=workers db.rel model crodas\\Worker\\Engine\\PDO
