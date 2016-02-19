# plp
Test task


## Run application

$ php runner.php

## Run N threads with application

$ perl -e 'print "runner.php\n" x N' | xargs -P N -I {} php -f {}

## Run tests

$ phpunit --bootstrap tests/bootstrap.php tests/
