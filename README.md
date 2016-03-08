# plp
Simple task queue for run / Простая очередь задач на выполнение

## Run application

$ php runner.php

## Run N threads with application

$ perl -e 'print "runner.php\n" x N' | xargs -P N -I {} php -f {}

N - number of threads

## Run tests

$ phpunit --bootstrap tests/bootstrap.php tests/
