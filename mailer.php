<?php

/**
 * Sugar Mailer
 *
 * The contents of this file are subject to the terms of the GNU General
 * Public License Version 3.0. You may not use this file except in
 * compliance with the license. Any of the license terms and conditions
 * can be waived if you get permission from the copyright holder.
 *
 * Copyright (c) 2020 ~ ikkez
 * Christian Knuth <ikkez0n3@gmail.com>
 * https://github.com/ikkez/F3-Sugar/
 *
 * @version 1.2.2
 * @date: 14.01.2020
 */

class Mailer {

	/** @var SMTP */
	protected $smtp;

	protected
		$recipients =[],
		$message = [],
		$charset;

	static $EOL="\r\n";

	/**
	 * create mailer instance working with a specific charset
	 * usually one of these:
	 *      ISO-8859-1
	 *      ISO-8859-15
	 *      UTF-8
	 * @param string $enforceCharset
	 */
	public function __construct($enforceCharset='UTF-8') {
		$this->charset = $enforceCharset;
		$this->reset();
	}

	/**
	 * initialize SMTP plugin
	 */
	public function initSMTP() {
		/** @var \Base $f3 */
		$f3 = \Base::instance();
		$this->smtp = new \SMTP(
			$f3->get('mailer.smtp.host'),
			$f3->get('mailer.smtp.port'),
			$f3->get('mailer.smtp.scheme'),
			$f3->get('mailer.smtp.user'),
			$f3->get('mailer.smtp.pw'));
		if (!$f3->devoid('mailer.errors_to',$errors_to))
			$this->setErrors($errors_to);
		if (!$f3->devoid('mailer.reply_to',$reply_to))
			$this->setReply($reply_to);
		if (!$f3->devoid('mailer.from_mail',$from_mail)) {
			if ($f3->devoid('mailer.from_name',$from_name))
				$from_name = NULL;
			$this->setFrom($from_mail, $from_name);
		}
	}

	/**
	 * encode special chars if possible
	 * @param $str
	 * @return mixed
	 */
	protected function encode($str) {
		if (empty($str) || $this->charset == 'UTF-8')
			return $str;
		if (extension_loaded('iconv'))
			$out = @iconv("UTF-8", $this->charset."//IGNORE", $str);
		if (!isset($out) || !$out)
			$out = extension_loaded('mbstring')
				? mb_convert_encoding($str,$this->charset,"UTF-8")
				: utf8_decode($str);
		return $out ?: $str;
	}

	/**
	 * encode and split header strings
	 * @param $str
	 * @return string
	 */
	protected function encodeHeader($str) {
		if (extension_loaded('iconv')) {
			$out = iconv_mime_encode('Subject', $str,
				['input-charset' => 'UTF-8', 'output-charset' => $this->charset]);
			$out = substr($out, strlen('Subject: '));
		} elseif(extension_loaded('mbstring')) {
			mb_internal_encoding('UTF-8');
			$out = mb_encode_mimeheader($str, $this->charset, 'B', static::$EOL, strlen('Subject: '));
		} else
			$out = wordwrap($str,65,static::$EOL);
		return $out;
	}

	/**
	 * build email with title string
	 * @param $email
	 * @param null $title
	 * @return string
	 */
	protected function buildMail($email, $title=null) {
		return ($title?'"'.$this->encodeHeader($title).'" ':'').'<'.$email.'>';
	}

	/**
	 * set encoded header value
	 * @param $key
	 * @param $val
	 */
	public function set($key, $val) {
		$this->smtp->set($key, $this->encode($val));
	}

	/**
	 * set message sender
	 * @param $email
	 * @param null $title
	 */
	public function setFrom($email, $title=null) {
		$this->set('From', $this->buildMail($email,$title));
	}

	/**
	 * add a direct recipient
	 * @param $email
	 * @param null $title
	 */
	public function addTo($email, $title=null) {
		$this->recipients['To'][$email] = $title;
	}

	/**
	 * add a carbon copy recipient
	 * @param $email
	 * @param null $title
	 */
	public function addCc($email, $title=null) {
		$this->recipients['Cc'][$email] = $title;
	}

	/**
	 * add a blind carbon copy recipient
	 * @param $email
	 * @param null $title
	 */
	public function addBcc($email, $title=null) {
		$this->recipients['Bcc'][$email] = $title;
	}
	
	/**
	 * set reply-to field respected by most email clients
	 * @param $email
	 * @param null $title
	 */
	public function setReply($email, $title=null) {
		$this->set('Reply-To', $this->buildMail($email,$title));
	}

	/**
	 * set receipient for bounce error mails
	 * @param $email
	 * @param null $title
	 */
	public function setErrors($email, $title=null) {
		$this->set('Sender', $this->buildMail($email,$title));
	}

	/**
	 * reset recipients if key was given, or restart whole smtp plugin
	 * @param null $key
	 */
	public function reset($key=null) {
		if ($key) {
			$key = ucfirst($key);
			$this->smtp->clear($key);
			if (isset($this->recipients[$key]))
				unset($this->recipients[$key]);
		} else {
			$this->recipients = [];
			$this->initSMTP();
		}
	}

	/**
	 * set message in plain text format
	 * @param $message
	 */
	public function setText($message) {
		$this->setContent($message,'text/plain');
	}

	/**
	 * set message in HTML text format
	 * @param $message
	 */
	public function setHTML($message) {
		$f3 = \Base::instance();
		// we need a clean template instance for extending it one-time
		$tmpl = new \Template();
		// create traceable jump links
		if ($f3->exists('mailer.jumplinks',$jumplink) && $jumplink)
			$tmpl->extend('a', function($node) use($f3, $tmpl) {
				if (isset($node['@attrib'])) {
					$attr = $node['@attrib'];
					unset($node['@attrib']);
				} else
					$attr = [];
				if (isset($attr['href'])) {
					if (!$f3->exists('mailer.jump_route',$ping_route))
						$ping_route = '/mailer-jump';
					$attr['href'] = $f3->get('SCHEME').'://'.$f3->get('HOST').$f3->get('BASE').
						$ping_route.'?target='.urlencode($attr['href']);
				}
				$params = '';
				foreach ($attr as $key => $value)
					$params.=' '.$key.'="'.$value.'"';
				return '<a'.$params.'>'.$tmpl->build($node).'</a>';
			});
		$message = $tmpl->build($tmpl->parse($message));
		$this->setContent($message,'text/html');
	}

	/**
	 * set message contents by mime type
	 * @param string $data message data
	 * @param string $mime the mime type
	 * @param null $charset
	 */
	public function setContent($data, $mime, $charset=NULL) {
		if (!$charset)
			$charset=$this->charset;
		$this->message[$mime] = [
			'content'=>$data,
			'type'=>$mime.'; charset='.$charset
		];
	}

	/**
	 * add a file attachment
	 * @param $path
	 * @param null $alias
	 * @param null $cid
	 */
	public function attachFile($path, $alias=null, $cid=null) {
		$this->smtp->attach($path,$alias,$cid);
	}

	/**
	 * send message
	 * @param $subject
	 * @param bool $mock
	 * @param bool|string $log log level [false,true,'verbose']
	 * @return bool
	 */
	public function send($subject, $mock=false, $log='verbose') {
		foreach ($this->recipients as $key => $rcpts) {
			$mails = [];
			foreach ($rcpts as $mail=>$title)
				$mails[] = $this->buildMail($mail,$title);
			$this->set($key,implode(', ',$mails));
		}
		$this->smtp->set('Subject', $this->encodeHeader($this->encode($subject)));
		$body = '';
		$hash=uniqid(NULL,TRUE);
		$multipart = count($this->message) > 1;
		if ($multipart)
			$this->smtp->set('Content-Type', 'multipart/alternative; boundary="'.$hash.'"');
		foreach ($this->message as $msg) {
			if ($multipart) {
				$body .= '--'.$hash.static::$EOL;
				$body .= 'Content-Type: '.$msg['type'].static::$EOL.static::$EOL;
			} else
				$this->smtp->set('Content-Type', $msg['type']);
			$body .= $msg['content'].static::$EOL.static::$EOL;
		}
		if ($multipart)
			$body .= '--'.$hash.'--'.static::$EOL;
		$success = $this->smtp->send($this->encode($body),$log,$mock);
		$f3 = \Base::instance();
		if (!$success && $f3->exists('mailer.on.failure',$fail_handler))
			$f3->call($fail_handler,[$this,$this->smtp->log()]);
		return $success;
	}

	/**
	 * save the send mail to disk
	 * @param $filename
	 */
	public function save($filename) {
		$f3 = \Base::instance();
		$lines = explode("\n",$this->smtp->log());
		$start = false;
		$out = '';
		for($i=0,$max=count($lines);$i<$max;$i++) {
			if (!$start && preg_match('/^354.*?$/',$lines[$i],$matches)) {
				$start=true;
				continue;
			} elseif (preg_match('/^250.*?$\s^QUIT/m',
				$lines[$i].($i+1 < $max ? "\n".$lines[$i+1] : ''),$matches))
				break;
			if ($start)
				$out.=$lines[$i]."\n";
		}
		if ($out) {
			$path = $f3->get('mailer.storage_path');
			if (!is_dir($path))
				mkdir($path,0777,true);
			$f3->write($path.$filename,$out);
		}
	}

	/**
	 * expose smtp log
	 * @return mixed
	 */
	public function log() {
		return $this->smtp->log();
	}

	/**
	 * receive and proceed message ping
	 * @param Base $f3
	 * @param $params
	 */
	static public function ping(\Base $f3, $params) {
		$hash = $params['hash'];
		// trigger ping event
		if ($f3->exists('mailer.on.ping',$ping_handler))
			$f3->call($ping_handler,[$hash]);
		$img = new \Image();
		// 1x1 transparent 8bit PNG
		$img->load(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMA'.
			'AAAl21bKAAAABGdBTUEAALGPC/xhBQAAAANQTFRFAAAAp3o92gAAAAF0U'.
			'k5TAEDm2GYAAAAKSURBVAjXY2AAAAACAAHiIbwzAAAAAElFTkSuQmCC'));
		$img->render();
	}

	/**
	 * track clicked link and reroute
	 * @param Base $f3
	 */
	static public function jump(\Base $f3, $params) {
		$target = $f3->get('GET.target');
		// trigger jump event
		if ($f3->exists('mailer.on.jump',$jump_handler))
			$f3->call($jump_handler,[$target,$params]);
		$f3->reroute(urldecode($target));
	}

	/**
	 * init routing
	 */
	static public function initTracking() {
		/** @var \Base $f3 */
		$f3 = \Base::instance();
		if (!$f3->exists('mailer.ping_route',$ping_route))
			$ping_route = '/mailer-ping/@hash.png';
		$f3->route('GET '.$ping_route,'\Mailer::ping');

		if (!$f3->exists('mailer.jump_route',$jump_route))
			$jump_route = '/mailer-jump';
		$f3->route('GET '.$jump_route,'\Mailer::jump');
	}

}
