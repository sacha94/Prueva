<?php
if(!isset($_POST['email'])) {
	header("Location: http://www.ilernafponline.com/content/error-al-enviar-el-formulario-15");
}
else{

define('DOCUMENT_ROOT',	$_SERVER['DOCUMENT_ROOT']);
include(DOCUMENT_ROOT."/lib/phpmailer/class.phpmailer.php"); 
include(DOCUMENT_ROOT."/lib/phpmailer/class.smtp.php"); 
require_once(DOCUMENT_ROOT.'/lib/mailchimp-api/MailChimp.class.php');

function clean_string($string) {
  $bad = array("content-type","bcc:","to:","cc:","href");
  return str_replace($bad,"",$string);
}

$nom = clean_string($_POST['nombre_y_apellidos']); 
$telefono = clean_string($_POST['telefono']);
$email = clean_string($_POST['email']);

$curs = clean_string($_POST['curs']);
$cursid = clean_string($_POST['cursid']);
$referal = '';
$cp = '';
$urlBotiga = $_POST['urlbotiga'];
$ubvariant = "adwords";
$source = $_POST['source'];
$idioma = $_POST['idioma'];
$thankyou = "http://www.ilerna.es/content/formulario-enviado-6"; // thank you page
$error_url = "http://www.ilerna.es/content/error-al-enviar-el-formulario-15";

$subject = ($idioma == 'es' ? "Información sobre ".$curs : "Informació sobre ".$curs);
$subjectn = "Información sobre ".$curs;

$email_from = "online@ilerna.com";

$isCicle = true;
$firstMail = false;
$secondMail = false;
$isMCAdded = false;

/* 
 * Notificacio a ILERNA per email
 */
 
$notificacio = new PHPMailer(); //Crea un objecte/instancia.
$notificacio->IsSMTP(); // enviament per protocol SMTP
$notificacio->IsHTML(true);
$notificacio->CharSet = 'UTF-8';
//Parametres d enviament (prepara l'objecte). (Si no es definen, s utilitzan els valors de per defecte).
//$notificacio->SMTPDebug  = 2; //Habilita el SMTPDebug per test.
$notificacio->SMTPAuth= true; //Habilita la autenticació SMPT.

//Credencials
$notificacio->SMTPSecure = "ssl";
$notificacio->Host = "smtp.gmail.com";
$notificacio->Port = 465;
$notificacio->Username = "online@ilerna.com";
$notificacio->Password = "Online1820";
 
//Parametres de Remitents
$notificacio->AddReplyTo($email, $nom);
$notificacio->SetFrom($email_from, $nom);
$notificacio->Subject = $subjectn;
$notificacio->AltBody ="El seu client de correu no suporta HTML";//Missatge d'advertencia pels usuaris que no utilitzan un client HTML.

// Construccio del Body i assignacio a variable (body).
$bodyn=file_get_contents(DOCUMENT_ROOT.'/themes/autumn/plantillamails/notificacio.php');

//Reemplacament de variables a la plantilla html.
$bodyn = str_replace('[nom]',$nom,$bodyn);
$bodyn = str_replace('[email]',$email,$bodyn);
$bodyn = str_replace('[telf]',$telefono,$bodyn);
$bodyn = str_replace('[codi]',$cp,$bodyn);
$bodyn = str_replace('[lang]',$idioma,$bodyn);
$bodyn = str_replace('[cicle]',$curs,$bodyn);

//Utilitzacio de la funcio MsgHTML i utilitzacio de la variable body creada abans per composar el cos del missatge.
$notificacio->MsgHTML($bodyn);
//S'indica adressa electronica on s'envia el mail i el nom.
$notificacio->AddAddress('online@ilerna.com','ILERNA online');


if($notificacio->Send()){
	$firstMail = true;
}else{
	$firstMail = false;
}


/* 
 * Email info
 */
	$mail = new PHPMailer(); //Crea un objecte/instancia.
	$mail->IsSMTP(); // enviament per protocol SMTP
	$mail->IsHTML(true);
	$mail->CharSet = 'UTF-8';
	//$mail->SMTPDebug  = 2; //Habilita el SMTPDebug per test.
	$mail->SMTPAuth= true; //Habilita la autenticació SMPT.

	//Credencials
	$mail->SMTPSecure = "ssl";
	$mail->Host = "smtp.gmail.com";
	$mail->Port = 465;
	$mail->Username = "online@ilerna.com";
	$mail->Password = "Online1820";
	 
	//Parametres de Remitents
	$mail->AddReplyTo('online@ilerna.com', 'ILERNA online');	
	$mail->SetFrom($email_from, 'ILERNA online');
	$mail->Subject = $subject;
	$mail->AltBody ="El seu client de correu no suporta HTML";//Missatge d'advertencia pels usuaris que no utilitzan un client HTML.

	// Construccio del Body i assignacio a variable (body).
	$body=file_get_contents(DOCUMENT_ROOT.'/themes/autumn/plantillamails/'.$idioma.'.php');

	//Reemplacament de variables a la plantilla html.
	$body = str_replace('[nom]',$nom,$body);
	$body = str_replace('[email]',$email,$body);
	$body = str_replace('[telf]',$telefono,$body);
	$body = str_replace('[idCurs]',$cursid,$body);
	$body = str_replace('[lang]',$idioma,$body);
	$body = str_replace('[cicle]',$curs,$body);
	$body = str_replace('[urlbotiga]',$urlBotiga,$body);

	//Utilitzacio de la funcio MsgHTML i utilitzacio de la variable body creada abans per composar el cos del missatge.
	$mail->MsgHTML($body);
	//S'indica adressa electronica on s'envia el mail i el nom.
	$mail->AddAddress($email,'Ilerna online');



	if($mail->Send()){
		$secondMail = true;
	}else{
		$secondMail = false;
	}
	
/* 
 * Us de API de Mailchimp
 */
	//Mailchimp API
	$dataActual = date('Y-m-d H:i:s');
	$MailChimp = new MailChimp('fde0048712bbf856f3e679f3c4098b15-us8');
	$data = array(
					'id'                => '7ac1fa8adb',
					'email'             => array('email'=>$email),
					'merge_vars'        => array('FNAME'=>$nom, 'PHONE'=>$telefono, 'LANG'=>strtoupper($idioma), 'CP'=>$cp, 'DATASOLICI'=>$dataActual, 'CICLEINTER'=>$curs, 'UBVARIANT'=>$ubvariant, 'URLPROD'=>$source, 'MMERGE20'=>$referal ),
					'double_optin'      => false,
					'update_existing'   => true,
					'replace_interests' => false,
					'send_welcome'      => false,
				);
	
	//Classlife Integration
	ob_start();
	sendToClasslife($data);
	ob_get_clean();
	
	$result = $MailChimp->call('lists/subscribe', $data);
	if (array_key_exists('status', $result)) {
		if($result['status']==='error'){
			$isMCAdded = false;
		}
		else{
			$isMCAdded = true;
		}
	}


	//Condicions per enviaments correctes
	if($isCicle && $firstMail && $secondMail){
		header("Location: $thankyou");
	}
	else{
		header("Location: $error_url");
	}

}

	function sendToClasslife($fields){

		$config = array(
			'token' => '237f3ksdjayi23423fa_234',
			'service' => 'ilerna',
			'perform' => 'suscribeLead',
			'email' => $fields['email']['email']
		); $fields = array_merge($config,$fields['merge_vars']);

		$url = 'http://ilerna.classlife.education/app/ajax.php';
		foreach($fields as $key=>$value) { $fields_string .= $key.'='.urlencode($value).'&'; } rtrim($fields_string, '&');

		file_get_contents($url.'?'.$fields_string);

		return true;
	}


die();
?>