<?php

class MailTest extends \App\Controller {

	static function init() {
		/** @var \Base $f3 */
		$f3 = \Base::instance();
		$f3->menu['/mail'] = 'Sugar Mailer';
		$f3->route('GET /mail','MailTest->view');
		$f3->route('POST /mail','MailTest->send');
		$f3->config(__DIR__.'/mailer_config.ini');
		\Mailer::initTracking();
	}

	function view(\Base $f3) {
		$ui_path = $f3->fixslashes(__DIR__.'/ui/');
		$f3->UI = ltrim(substr($ui_path,strlen($f3->ROOT.$f3->BASE)),'/');
	}

	function send(\Base $f3) {
		$email = $f3->get('POST.email');
		$ui_base = ltrim(substr($f3->fixslashes(__DIR__).'/',strlen($f3->ROOT.$f3->BASE)),'/');
		$f3->UI = $ui_base.'email/';

		$mail = new \Mailer('UTF-8');
		$mail->addTo($email);

		if ($f3->get('POST.type') == 1 || $f3->get('POST.type') == 3) {
			$mail->setText($f3->read($ui_base.'email/testmail.txt'));
		}
		if ($f3->get('POST.type') == 2 || $f3->get('POST.type') == 3) {
			$message = \Template::instance()->render('testmail.html');
			$message = $this->prefixImagePath($message,$ui_base.'ui/');
			$mail->setHTML($message);
		}
		if ($f3->exists('POST.attachment')) {
			$mail->attachFile($ui_base.'ui/images/We_Can_Do_It_square.jpg','wecandoit.jpg');
		}

		$success = $mail->send('Testmail');

		if ($f3->exists('POST.save')) {
			$mail->save(date('Y-m-d_H_i_').uniqid().'.eml');
		}
		$f3->set('mailer_send',true);
		$f3->set('mailer_success',$success);
		$this->view($f3);
	}

	static public function logError($mailer, $log) {
		$logger = new \Log('logs/smtp_'.date('Y_m_d').'.log');
		$logger->write($log);
	}

	static public function traceMail($hash) {
		mail(\Base::instance()->get('mailer.return_to'),'trace mail','trace this: '.$hash.' IP:'.\Base::instance()->ip());
	}

	static public function traceClick($target) {
		mail(\Base::instance()->get('mailer.return_to'),'trace click','trace click to: '.$target);
	}

	function prefixImagePath($data,$path) {
		/** @var \Base $f3 */
		$f3 = \Base::instance();
		$base_path = $f3->get('SCHEME').'://'.$f3->get('HOST').$f3->get('BASE').'/';
		$path = $base_path.$path.'images';
		return str_replace('src="images','src="'.$path,$data);
	}

}