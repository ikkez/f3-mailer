# Sugar Mailer

This is a little mail plugin that contains:

  - SMTP plugin wrapper
  - easily send plain text, html or both text & html hybrid content mails
  - convenient methods to add one or multiple recipients 
  - encode special chars for mails with ISO charset
  - ping and jump methods for tracking read and click events in your html mails
  - save mails as files to disk


## Getting started

This plugin is configurable via [config file](https://github.com/ikkez/f3-mailer/blob/master/mailer_config.sample.ini):

```ini
[mailer]
; smtp config
smtp.host = smtp.domain.com
smtp.port = 25
smtp.user = info@domain.com
smtp.pw = 123456789!
; scheme could be SSL or TLS
smtp.scheme =

; optional mail settings
from_mail = noreply@domain.com
from_name = Mario Bros.
; mail to receive bounced mails
errors_to = bounce@domain.com
; used mail for replies to the sent mail
reply_to = info@domain.com

; handler for SMTP errors
on.failure = \Controller\Mail::logError
; handler for tracing opened mails
on.ping = \Controller\Mail::traceMail
; handler for redirecting jump links
on.jump = \Controller\Mail::traceClick
; automatically create jump links in all <a> tags
jumplinks = true
; path for storing mail dumps
storage_path = logs/mail/
```


## Usage

A little sample looks like this:

```php
function send_test($email, $title=null) {
	$mail = new \Mailer();
	$mail->addTo($email, $title);
	$mail->setText('This is a Test.');
	$mail->setHTML('This is a <b>Test</b>.');
	$mail->send('Test Mail Subject');
}
```

If you want, you can change the encoding type that is used for the email body and header when instantiating the mail object with a constructor argument:

```php
$mail = new \Mailer('ISO-8859-1');
$mail = new \Mailer('ISO-8859-15'); // default
$mail = new \Mailer('UTF-8'); 
```

## Tracking 

To initialize the tracking routes, call this before `$f3->run()`:

```php
$f3->config('mailer_config.ini');
// ...
Mailer::initTracking();
// ...
$f3->run();
```

To add the ping tracking pixel (1x1 transparent 8bit PNG), put this in your html mail body:

```html
<img src="http://mydomain.com/mailer-ping/AH2cjDWb.png" />
```

The file name should be a unique hash you can use to identify the recipient who read your mail.

The tracking methods could look like this:

```php
static public function logError($mailer, $log) {
	$logger = new \Log('logs/smtp_'.date('Y_m_d').'.log');
	$logger->write($log);
}

static public function traceMail($hash) {
	// your mail $hash is being read
}

static public function traceClick($target) {
	// someone clicked $target link
}
```

## Mock & Storage

In case you don't want to actually send the email, but just want to run a test flight and save the mail in a text file, you can mock the server dialog:

```php
$mail->send($subject, TRUE); // mock call 
$mail->save('newsletter.eml'); // save to file in 'mailer.storage_path' directory
$mail->reset();
```

If you want to keep using the object after a mock call, you need to reset the mailer and add recipients, content and attachments again.

The mail file includes all file attachments.

## Logging

You can log the full SMTP server dialog after sending the email. This could be useful for debugging purposes or as a sending confirmation. 

```php
$success = $mailer->send($subject);
$f3->write('SMTP_mail.log', $this->mailer->log());
```

**Notice:** By default, the log level is `verbose`, which means it also contains the mail body and attachments, which might eat up a lot of memory.
To reduce the log level, set `$log` to `TRUE` (dialog only) or `FALSE` (disabled) in:

```php
$mailer->send($subject, $mock, $log);
```

Keep in mind that when you write down mails to files, it can only store what was found in the SMTP log, hence it only works when logging level is `verbose`.

## Demo & Testing

There's a test bench available here: https://github.com/ikkez/f3-mailer/tree/test



## License

You are allowed to use this plugin under the terms of the GNU General Public License version 3 or later.

Copyright (C) 2019 Christian Knuth [ikkez]
