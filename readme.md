# Sugar Mailer


### Test Bench

![Image](https://dl.dropboxusercontent.com/u/3077539/_linked/sugar_mailer.png)

To add the tests to the fatfree-dev testing bench:

```php
// Mailer Tests
$f3->concat('AUTOLOAD',',sugar/Mailer/lib/,sugar/Mailer/test/');
\MailTest::init();
```