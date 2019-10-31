# Code - Runs callables with a timeout.

[![Build Status](https://travis-ci.org/crysalead/code.png?branch=master)](https://travis-ci.org/crysalead/code) [![Code Coverage](https://scrutinizer-ci.com/g/crysalead/code/badges/coverage.png?s=50b3c56bd62e6a14c1c15b7c7f5c26584ff2bf7a)](https://scrutinizer-ci.com/g/crysalead/code/)

## API

Runs a callable until a timeout is reached:

```php
declare(ticks = 1);

Code::run(function(){
    sleep(100);
}, 10);
```

Runs a callable in loop until a timeout is reached and the return value is `false`:

```php
// declare(ticks = 1); is optionnal when the callable is not blocking on spinning mode

Code::spin(function(){
    sleep(1);
    return false;
}, 10);
```
