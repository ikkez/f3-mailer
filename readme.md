# Sugar Mailer


To add the tests to the fatfree-dev testing bench:

```php
// Mailer Tests
$f3->concat('AUTOLOAD',',sugar/Mailer/lib/,sugar/Mailer/test/');
\MailTest::init();
```