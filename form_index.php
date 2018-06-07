<?php

require_once("lib/forcecom/ilerna/classes/SfLead.php");
require_once("lib/forcecom/ilerna/classes/SfConfig.php");
require_once("config/config.inc.php");
 
function trimString($value)
{
    return trim($value);
}

function sanitizeName($value)
{
    $value = trimString($value);
    $value = mb_strtolower($value);
    $value = ucwords($value);
    return $value;
}

$args = array(
    "idshop" => [
        "filter" => FILTER_VALIDATE_INT,
        "options" => [
            "min_range" => 1,
            "max_range" => 90
        ]
    ],
    "Nombre" => [
        "filter" => FILTER_CALLBACK,
        "flag" => FILTER_FORCE_ARRAY,
        "options" => "sanitizeName"
    ],
    "Email" => FILTER_VALIDATE_EMAIL,
    "Telefono" => FILTER_VALIDATE_INT,
    "CP" => [
        "filter" => FILTER_VALIDATE_INT,
        "options" => [
            "min_range" => 00000,
            "max_range" => 99999
        ]
    ],
    "curs" => [
        "filter" => FILTER_CALLBACK,
        "flag" => FILTER_FORCE_ARRAY,
        "options" => "trimString"
    ],
    "cursid" => [
        "filter" => FILTER_VALIDATE_INT,
        "options" => [
            "min_range" => 1,
            "max_range" => 999
        ]
    ],
    "idioma" => FILTER_SANITIZE_STRING,
    "estudis" => [
        "filter" => FILTER_VALIDATE_INT,
        "options" => [
            "min_range" => 1,
            "max_range" => 999
        ]
    ],
    "urlbotiga" => FILTER_VALIDATE_URL,
    "source" => FILTER_VALIDATE_URL
    // Añadir nuevos campos conforme vayan añadiéndose al formulario.
);

$input = filter_input_array(INPUT_POST, $args, false);
if (!$input['CP']) {
    $input['CP'] = intval($_POST['CP']);
}

$idioma = empty($input['idioma']) ? array_push($errors, "idioma") : $input['idioma'];
$id_shop = isset($input['idshop']) ? $input['idshop'] : 0;
$base_url = $isLandingMadrid ? 'http://www.ilerna.es/fp-madrid/es' : 'http://www.ilerna.es/'.$idioma; // Extraer domínio de $_SERVER (pasado por filter_input_array, no usarlo tal cual!)


if($idioma =='es')
{
    $thankyou = "/fp-lleida/$idioma/content/formulario-enviado-38";
    $error_url = "/fp-lleida/$idioma/content/error-formulario-39";
}
else
{
    $thankyou = "/fp-lleida/$idioma/content/formulari-enviat-38";
    $error_url = "/fp-lleida/$idioma/content/error-formulari-39";
}  

$productionHost = preg_match("|(.*).ilerna.es|", $_SERVER["HTTP_HOST"]); // TODO: validate/sanitize $_SERVER
//Config file
if ($productionHost)
    //prod
    $file = '/home/online_ilerna_com/mail.ini';
else
    //dev
    $file = 'config/ini/mail.ini';


$errors = array();

$modalitat = empty($input["modalitat"]) ? 1 : $input["modalitat"];

if (file_exists($file)) {
    $ini_array = parse_ini_file($file, true);
    if ($productionHost) {
        $cfg_mail = $ini_array['ilerna_online']['mail'];        // WTF is this, esto esta justo en el siguiente par de líneas
        $cfg_pwd = $ini_array['ilerna_online']['password'];     // WTF is this
        if ($modalitat == 1) {
            $cfg_mail = $ini_array['ilerna_online']['mail'];
            $cfg_pwd = $ini_array['ilerna_online']['password'];
        }
        if ($modalitat == 16) {
            $cfg_mail = $ini_array['ilerna_lleida']['mail'];
            $cfg_pwd = $ini_array['ilerna_lleida']['password'];
        }
        if ($modalitat == 17) {
            $cfg_mail = $ini_array['ilerna_madrid']['mail'];
            $cfg_pwd = $ini_array['ilerna_madrid']['password'];
        }
    } else {
        $cfg_mail = $ini_array['test']['mail'];
        $cfg_pwd = $ini_array['test']['password'];
    }
} else {
    echo "hola no hi ha fitxer";
    //header("Location: " . $base_url . "/content/error-al-enviar-el-formulario-15");
}

$nom = empty($input["Nombre"]) ? array_push($errors, "nom") : $input["Nombre"];
$email = empty($input["Email"]) ? array_push($errors, "email") : $input["Email"];
$telefono = empty($input["Telefono"]) ? array_push($errors, "telefono") : $input["Telefono"];
$cp = empty($input["CP"]) ? array_push($errors, "cp") : $input["CP"];
$curs = empty($input['curs']) ? array_push($errors, "curs") : $input['curs'];
$cursid = (empty($input['cursid']) || $input["cursid"] == 0) ? array_push($errors, "cursid") : $input['cursid'];
if (key_exists('estudis', $input)) {
    $idCurs = (empty($input['estudis']) && is_null($input['estudis'])) ? $cursid : $input['estudis'];
} else {
    $idCurs = $cursid;
}

if (!empty($_POST["wordpressDescription"])) {
    $wDescription = $_POST['wordpressDescription'];
}

if (!empty($_POST["colectivo"])) {
    $colectivo = $_POST['colectivo'];
    $thankyou = "https://www.ilerna.es/es/content/formulario-enviado-colectivo-32";
}

//Check for errors
if (!empty($errors)) {
    header("Location: " . $base_url . "/content/error-al-enviar-el-formulario-15");
} else {
    define('DOCUMENT_ROOT', $_SERVER['DOCUMENT_ROOT']); // TODO: validate/sanitize $_SERVER
    include(DOCUMENT_ROOT . "/lib/phpmailer/class.phpmailer.php");
    include(DOCUMENT_ROOT . "/lib/phpmailer/class.smtp.php");
    require_once(DOCUMENT_ROOT . '/lib/mailchimp-api/MailChimp.class.php');


    $urlBotiga = $input['urlbotiga'];
    $ubvariant = "";
    $source = $input['source'];

    $subject = ($idioma == 'es' ? "Información sobre " . $curs : "Informació sobre " . $curs);
    $subjectn = "Información sobre " . $curs;

    if (isset($colectivo)) {
        $subjectn = "Información sobre " . $curs . " del colectivo " . $colectivo;
    }

    $email_from = $cfg_mail;

    $isCicle = true;
    $firstMail = false;
    $secondMail = false;
    $isMCAdded = false;


    $notificacio = new PHPMailer();
    $notificacio->IsSMTP();
    $notificacio->IsHTML(true);
    $notificacio->CharSet = 'UTF-8';
    //$notificacio->SMTPDebug  = 2;
    $notificacio->SMTPAuth = true;

    //Credencials
    $notificacio->SMTPSecure = "ssl";
    $notificacio->Host = "smtp.gmail.com";
    $notificacio->Port = 465;
    $notificacio->Username = $cfg_mail;
    $notificacio->Password = $cfg_pwd;

    //Parametres de Remitents
    $notificacio->AddReplyTo($email, $nom);
    $notificacio->SetFrom($email_from, $nom);
    $notificacio->Subject = $subjectn;
    $notificacio->AltBody = "El seu client de correu no suporta HTML";

    // Construccio del Body i assignacio a variable (body).
    if (isset($colectivo)) {
        $bodyn = file_get_contents(DOCUMENT_ROOT . '/themes/autumn/plantillamails/notificacio-colectivo.php');
        $bodyn = str_replace('[colectiu]', $colectivo, $bodyn);
    } else {
        $bodyn = file_get_contents(DOCUMENT_ROOT . '/themes/autumn/plantillamails/notificacio.php');
    }

    //Reemplacament de variables a la plantilla html.
    $bodyn = str_replace('[nom]', $nom, $bodyn);
    $bodyn = str_replace('[email]', $email, $bodyn);
    $bodyn = str_replace('[telf]', $telefono, $bodyn);
    $bodyn = str_replace('[codi]', $cp, $bodyn);
    $bodyn = str_replace('[lang]', $idioma, $bodyn);
    $bodyn = str_replace('[cicle]', $curs, $bodyn);
    $bodyn = str_replace('[cp]', $cp, $bodyn);

    //Utilitzacio de la funcio MsgHTML i utilitzacio de la variable body creada abans per composar el cos del missatge.
    $notificacio->MsgHTML($bodyn);
    //S'indica adressa electronica on s'envia el mail i el nom.
    if (isset($colectivo)) {
        $notificacio->AddAddress("colectivo@ilernaonline.com", 'ILERNA Online');
    } else {
        $notificacio->AddAddress($cfg_mail, 'ILERNA Online');
    }

    if (isset($colectivo)){
        $firstMail = ($notificacio->Send() != false);
    } else {
        $firstMail = true;
    }

//Email info
//Send info only when shop is Ilerna Online, Ilerna Lleida or Ilerna Madrid
    if ($id_shop == 1 || $id_shop == 16 || $id_shop == 17) {
        $mail = new PHPMailer();
        $mail->IsSMTP();
        $mail->IsHTML(true);
        $mail->CharSet = 'UTF-8';
        //$mail->SMTPDebug  = 2;
        $mail->SMTPAuth = true;

        //Credencials
        $mail->SMTPSecure = "ssl";
        $mail->Host = "smtp.gmail.com";
        $mail->Port = 465;
        $mail->Username = $cfg_mail;
        $mail->Password = $cfg_pwd;

        //Parametres de Remitents
        if ($modalitat == 16) {
            $mail->AddReplyTo($cfg_mail, 'ILERNA Lleida');
            $mail->SetFrom($email_from, 'ILERNA Lleida');
        } elseif ($modalitat == 17) {
            $mail->AddReplyTo($cfg_mail, 'ILERNA Madrid');
            $mail->SetFrom($email_from, 'ILERNA Madrid');
        } else {
            $mail->AddReplyTo($cfg_mail, 'ILERNA online');
            $mail->SetFrom($email_from, 'ILERNA Online');
        }
        $mail->Subject = $subject;
        $mail->AltBody = "El seu client de correu no suporta HTML";

        // Construccio del Body i assignacio a variable (body).
        if ($modalitat == 16) {
            $body = file_get_contents(DOCUMENT_ROOT . '/themes/autumn/plantillamails/' . $idioma . '_lleida.php');
        } elseif ($modalitat == 17) {
            $body = file_get_contents(DOCUMENT_ROOT . '/themes/autumn/plantillamails/es_madrid.php');
        } else {
            $body = file_get_contents(DOCUMENT_ROOT . '/themes/autumn/plantillamails/' . $idioma . '.php');
        }

        //Reemplacament de variables a la plantilla html.
        $body = str_replace('[nom]', $nom, $body);
        $body = str_replace('[email]', $email, $body);
        $body = str_replace('[telf]', $telefono, $body);
        $body = str_replace('[codi]', $cp, $body);
        $body = str_replace('[idCurs]', $idCurs, $body);
        $body = str_replace('[lang]', $idioma, $body);
        $body = str_replace('[cicle]', $curs, $body);
        $body = str_replace('[urlbotiga]', $urlBotiga, $body);

        //Utilitzacio de la funcio MsgHTML i utilitzacio de la variable body creada abans per composar el cos del missatge.
        $mail->MsgHTML($body);
        //S'indica adressa electronica on s'envia el mail i el nom.

        if ($modalitat == 16) {
            $mail->AddAddress($email, 'Ilerna Lleida');
        } elseif ($modalitat == 17) {
            $mail->AddAddress($email, 'Ilerna Madrid');
        } else {
            $mail->AddAddress($email, 'Ilerna Online');
        }

        $secondMail = ($mail->Send() != false);

    }

    //Mailchimp API
    $dataActual = date('Y-m-d H:i:s');
    $MailChimp = new MailChimp('fde0048712bbf856f3e679f3c4098b15-us8');
    $data = array(
        'id' => '7ac1fa8adb',
        'email' => array('email' => $email),
        'merge_vars' => array('FNAME' => $nom, 'PHONE' => $telefono, 'LANG' => strtoupper($idioma), 'CP' => $cp, 'DATASOLICI' => $dataActual, 'CICLEINTER' => $curs, 'UBVARIANT' => $ubvariant, 'URLPROD' => $source),
        'double_optin' => false,
        'update_existing' => true,
        'replace_interests' => false,
        'send_welcome' => false,
    );


    //Classlife Integration
    if (!isset($colectivo)) {
        ob_start();
        sendToClasslife($data);
        ob_get_clean();
    }


    $result = $MailChimp->call('lists/subscribe', $data);

    if (array_key_exists('status', $result)) {
        if ($result['status'] === 'error') {
            $isMCAdded = false;
        } else {
            $isMCAdded = true;
        }
    }

    //if (!isset($colectivo)) {
        $desiredProgramQuery = new DbQuery();
        $desiredProgramQuery->select("cl.name");
        $desiredProgramQuery->from("category", "c");
        $desiredProgramQuery->innerJoin("category_lang", "cl", "c.id_category = cl.id_category AND cl.id_lang = 1 AND cl.id_shop = {$id_shop}");
        $desiredProgramQuery->where(" c.id_category = {$cursid}");

        error_log($desiredProgramQuery->__toString());

        $desiredProgram = Db::getInstance()->getValue($desiredProgramQuery);

        SfConfig::loadCfg();

        $ownerId = SfConfig::getOwnerId($isLandingMadrid);

        $leadSource = "Web";
        if($source == "http://formacion.ilerna.es/"){
            $leadSource = "Adwords";
        }
        if($source == "Wordpress"){
            $leadSource = "Wordpress";
        }
        if (isset($colectivo)){
            $leadSource = "Colectivo";
        }

        $lead = array(
            "LastName" => $nom,
            "Email" => $email,
            "MobilePhone" => $telefono,
            "PostalCode" => $cp,
            "LeadSource" => $leadSource,
            "Estudio_interesado__c" => $desiredProgram,
            "Formulario_origen__c" => $isLandingMadrid ? "Madrid" : "General", // Esto tiene que cambiar a la que tengamos mas tiendas de Ilerna con gestion de leads desde SF
            "OwnerId" => $ownerId
        );
        try {
            $sfLead = new SfLead();
            $sfLead->insert([$lead]);
        } catch (Exception $e) {
                error_log("Salesforce Sync: Could not sync a Lead type object - Errmsg: ". $e->getMessage());
        }
    //}

//Condicions per enviaments correctes
    if ($isCicle && $firstMail) {
        header("Location: $thankyou");
    } else {
        header("Location: $error_url");
    }

}
