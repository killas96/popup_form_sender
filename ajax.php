<?php
$config = array( // конфигурация для всплывающих окон с сообщением
	'default_error' => 'Непредвиденная ошибка! Попробуйте позднее', //ошибка по умолчанию
	'recaptcha_error' => 'Ошибка каптчи! Попробуйте позднее', //ошибка по умолчанию
	'default_success' => 'Спасибо за обращение!', //ошибка по умолчанию
	'secret_key' => 'секретный ключ',
	'callMe' => array( // тип формы
		'required' => array( // обязательные поля, ошибка(опционально), можно расширить до регулярок
			array(
				'name' => 'name', 
				'error' => 'Ошибка! Неверно заполнена имя',
				'check' => '' //для регулярок
			),
			array(
				'name' => 'phone', 
				'error' => 'Ошибка! Неверно заполнен телефон',
				'check' => '' //для регулярок
			),
			array(
				'name' => 'policy', 
				'error' => 'Ошибка! Неверно заполнено согласие с политикой обработки персональных данных',
				'check' => '' //для регулярок
			),
		),
		'from' => '', // от кого (опционально), по умолчанию из настроек Opencart
		'sender' => 'Aroma Interior', // имя отправителя
		'to' => 'емайл', // кому
		'subject' => 'Заказан обратный звонок', // тема письма
		'message' => array( // поля для сообщения (ключ - название)
			'name' => 'Имя', 
			'phone' => 'Телефон', 
			'from' => 'Страница запроса', 
		),		
	),
);

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
	$error = array();
	
	if(!isset($_REQUEST['g-recaptcha-response']) || empty($_REQUEST['g-recaptcha-response'])){
		exit($config['recaptcha_error']);
	}
	
	$url = "https://www.google.com/recaptcha/api/siteverify?secret=" . $config['secret_key'] . "&response=" . $_REQUEST['g-recaptcha-response'] . "&remoteip=" . $_SERVER['REMOTE_ADDR'];
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
    curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.16) Gecko/20110319 Firefox/3.6.16");
    $curlData = curl_exec($curl);
    curl_close($curl); 
    $curlData = json_decode($curlData, true);
    if($curlData['success']) {

    } else {
		exit($config['recaptcha_error']);
    }
	
	if(
		!isset($_REQUEST['fish']) 
		|| (isset($_REQUEST['fish']) && !empty($_REQUEST['fish']))
		|| !isset($_REQUEST['type'])
		|| (isset($_REQUEST['type']) && !isset($config[$_REQUEST['type']]))
	) {
		exit($config['default_error']);
	}
	
	if(isset($config[$_REQUEST['type']]['required']) && !empty($config[$_REQUEST['type']]['required']) && $required = $config[$_REQUEST['type']]['required']) {
		foreach($required as $r) {
			if(!isset($_REQUEST[$r['name']]) || empty($_REQUEST[$r['name']]))
				$error[] = $r['error'];
			if(isset($r['check']) && !empty($r['check'])) {
				 //для регулярок
			}
		}
		if(!empty($error)) {
			$error_text = '';
			foreach($error as $e) {
				$error_text .= $e . '</br>';
			}
			exit($error_text);
		} else {
			$message = '';
			foreach($config[$_REQUEST['type']]['message'] as $k => $n) {
				$message .= '<p><b>' . $n . '</b>: ' . $_REQUEST[$k] . '</p>';
			}
			// opencart start
			require_once('config.php');
			require_once(DIR_SYSTEM . 'startup.php');
			$configOC = new Config();
			// Database 
			$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
			// Store
			if (isset($_SERVER['HTTPS']) && (($_SERVER['HTTPS'] == 'on') || ($_SERVER['HTTPS'] == '1'))) {
				$store_query = $db->query("SELECT * FROM " . DB_PREFIX . "store WHERE REPLACE(`ssl`, 'www.', '') = '" . $db->escape('https://' . str_replace('www.', '', $_SERVER['HTTP_HOST']) . rtrim(dirname($_SERVER['PHP_SELF']), '/.\\') . '/') . "'");
			} else {
				$store_query = $db->query("SELECT * FROM " . DB_PREFIX . "store WHERE REPLACE(`url`, 'www.', '') = '" . $db->escape('http://' . str_replace('www.', '', $_SERVER['HTTP_HOST']) . rtrim(dirname($_SERVER['PHP_SELF']), '/.\\') . '/') . "'");
			}
			if ($store_query->num_rows) {
				$configOC->set('config_store_id', $store_query->row['store_id']);
			} else {
				$configOC->set('config_store_id', 0);
			}		
			// Settings
			$query = $db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE store_id = '0' OR store_id = '" . (int)$configOC->get('config_store_id') . "' ORDER BY store_id ASC");
			foreach ($query->rows as $setting) {
				if (!$setting['serialized']) {
					$configOC->set($setting['key'], $setting['value']);
				} else {
					$configOC->set($setting['key'], unserialize($setting['value']));
				}
			}
			if (!$store_query->num_rows) {
				$configOC->set('config_url', HTTP_SERVER);
				$configOC->set('config_ssl', HTTPS_SERVER);	
			}
			// config mail
			$mail = new Mail();
			$mail->protocol = $configOC->get('config_mail_protocol');
			$mail->parameter = $configOC->get('config_mail_parameter');
			$mail->smtp_hostname = $configOC->get('config_mail_smtp_hostname');
			$mail->smtp_username = $configOC->get('config_mail_smtp_username');
			$mail->smtp_password = html_entity_decode($configOC->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
			$mail->smtp_port = $configOC->get('config_mail_smtp_port');
			$mail->smtp_timeout = $configOC->get('config_mail_smtp_timeout');	
			// create mail
			$mail->setTo($config[$_REQUEST['type']]['to']);
			$mail->setFrom($config[$_REQUEST['type']]['from'] ? $config[$_REQUEST['type']]['from'] : $configOC->get('config_email'));
			$mail->setSender($config[$_REQUEST['type']]['sender']);
			$mail->setSubject($config[$_REQUEST['type']]['subject']);
			$mail->setHtml($message);
			$mail->send();
			// opencart end			
			exit($config['default_success']);
		}			
	}	

}
unset($config);
exit;
?>
