<?php
	/* Monitoramento de Saldo de Conta Itaú - v1.0
		$_agencia = 0000;
		$_conta = 00000;
		$_conta_dig = 0;
		$_senha = 000000;
		$_email = 'fulano@siclano.com';
	*/
	
	$_agencia = 0000;
	$_conta = 00000;
	$_conta_dig = 0;
	$_senha = 000000;	
	$_email = 'email@mail.com';
	
	$_mail_host = '127.0.0.1';
	$_mail_port = 587;
	$_mail_secure = 'ssl'; // tls / ssl / null
	$_mail_user = '#usuario#';
	$_mail_pass = '#senha#';

	$cookie = bin2hex(openssl_random_pseudo_bytes(32, $cstrong));
	$tmp = '/tmp/'.md5('itau_saldo_'.$_agencia.'_'.$_conta);
	$agent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 9_1 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Version/9.0 Mobile/13B143 Safari/601.1';
	
	if(isset($argv[1]) && $argv[1] == 'reset'){ if(file_exists($tmp)){ unlink($tmp); } exit; }
	
	$html = goCurl('https://ww70.itau.com.br/M/Institucional/IncentivoAplicativo.aspx', ['cookie' => $cookie, 'agent' => $agent]);
	
	if(empty($html)){ echo 1; exit; }
	$out = []; preg_match_all('/[0-9A-Z]{80,80}/', $html, $out);
	if(sizeof($out) == 0 || sizeof($out[0]) == 0 || !preg_match('/^[0-9A-Z]{80,80}$/', $out[0][0])){ echo 2; exit; }
	
	$html = goCurl('https://ww70.itau.com.br/M/LoginPF.aspx?'.$out[0][0], ['cookie' => $cookie, 'agent' => $agent]);
	if(empty($html)){ echo 3; exit; }
	
	require __DIR__ . '/libs/simple_html_dom.php';
	
	$html = str_get_html($html);
	
	$inputs = [];
	foreach($html->find('input') as $h){
		$v = $h->value;
		$inputs[$h->name] = !$v ? '' : $v;
	}
	if(sizeof($inputs) <> 13){ echo 4; exit; }	
	$inputs['ctl00$ContentPlaceHolder1$txtAgenciaT'] = $_agencia;
	$inputs['ctl00$ContentPlaceHolder1$txtContaT'] = $_conta;
	$inputs['ctl00$ContentPlaceHolder1$txtDACT'] = $_conta_dig;
	$inputs['ctl00$ContentPlaceHolder1$txtPassT'] = $_senha;
	unset($inputs['ctl00$ContentPlaceHolder1$btnLogInT']);
	$inputs['ctl00$ContentPlaceHolder1$btnLogInT.x'] = 17;
	$inputs['ctl00$ContentPlaceHolder1$btnLogInT.y'] = 9;
	unset($inputs['id']);
	unset($inputs['op']);
	unset($inputs['ctl00$ContentPlaceHolder1$chkSalvarT']);
	//var_dump($inputs);
	
	$out = []; preg_match_all('/[0-9A-Z]{80,80}/', $html, $out);
	if(sizeof($out) == 0 || sizeof($out[0]) == 0 || !preg_match('/^[0-9A-Z]{80,80}$/', $out[0][0])){ echo 5; exit; }	
	$html = goCurl('https://ww70.itau.com.br/M/LoginPF.aspx?'.$out[0][0], ['cookie' => $cookie, 'agent' => $agent, 'post' => $inputs]);
	if(empty($html)){ echo 6; exit; }
	
	$html = str_get_html($html);
	foreach($html->find('td') as $td){
		//echo '=>'.$td->plaintext.'<=<br>';
		if(preg_match('/^R\$\ [0-9\.\,]{1,}$/', trim($td->plaintext))){
			$saldo = $td->plaintext;
			break;
		}
	}
	if(!isset($saldo)){ echo 7; exit; }
	
	$ant = file_exists($tmp) ? trim(file_get_contents($tmp)) : 'R$ 0,00';
	
	if($ant <> $saldo){
		file_put_contents($tmp, $saldo);
		
		require __DIR__ . '/libs/PHPMailer/PHPMailerAutoload.php';				
		$mail = new PHPMailer;
		$mail->isSMTP();
		$mail->Host = $_mail_host;
		$mail->Port = $_mail_port;
		$mail->SMTPAuth = false;
		if(!empty($_mail_secure)){
			$mail->SMTPAuth = true;
			$mail->SMTPSecure = $_mail_secure;
			$mail->Username = $_mail_user;
			$mail->Password = $_mail_pass;
		}	
		$mail->SMTPDebug = 0;
		$mail->CharSet = 'UTF-8';			
		//$mail->From = $from;
		$mail->FromName = 'Sistema de Monitoramento de Saldo de Conta';
		$mail->addAddress($_email);
		$mail->isHTML(true);
		$mail->Subject = 'Saldo da Conta: Ag.: '.$_conta.', CC: '.$_conta.'-'.$_conta_dig;
		$mail->Body = '<font family="Verdana">Saldo:<br><h1>'.$saldo.'</h1></font>';
		$mail->send();
	}
	
	function goCurl($url, $opts = null){
		$debug = false;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_NOBODY, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Alterado para 'false' (Problema certificado Claro)
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		if(!empty($opts)){
			foreach($opts as $op => $v){
				if($op == 'post'){
					curl_setopt($ch, CURLOPT_POST, true);
					curl_setopt($ch, CURLOPT_POSTFIELDS, (is_array($v) ? http_build_query($v) : $v));
				}elseif($op == 'ssl'){
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $v);
					curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
					curl_setopt($ch, CURLOPT_SSLVERSION, 3);
				}elseif($op == 'cookie'){
					curl_setopt($ch, CURLOPT_COOKIEFILE, sys_get_temp_dir().'/'.$v);
					curl_setopt($ch, CURLOPT_COOKIEJAR, sys_get_temp_dir().'/'.$v);
				}elseif($op == 'agent'){
					curl_setopt($ch, CURLOPT_USERAGENT, $v);
				}elseif($op == 'refer'){
					curl_setopt($ch, CURLOPT_REFERER, $v);
				}elseif($op == 'user'){
					curl_setopt($ch, CURLOPT_USERPWD, $v);
					curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
				}elseif($op == 'debug'){
					$debug = true;
				}elseif($op == 'json'){
					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
					curl_setopt($ch, CURLOPT_POSTFIELDS, (is_array($v) ? http_build_query($v) : $v));
					curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
						'Content-Type: application/json',                                                                                
						'Content-Length: ' . strlen($v))                                                                       
					);    
				}elseif($op == 'headers'){
					curl_setopt($ch, CURLOPT_HTTPHEADER, $v);    
				}elseif($op == 'binary'){
					curl_setopt($ch, CURLOPT_BINARYTRANSFER, $v);
				}
			}
		}
		$ret = curl_exec($ch);
		echo $debug === true ? curl_error($ch) : null;
		curl_close($ch);
		return $ret;
	}
?>