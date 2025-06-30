<?php

// Ermittelt allgemein wichtige Parameter und bindet vieles von vendor ein

error_reporting(E_ALL);
ini_set('display_errors', 1);
//require_once __DIR__ . '/../../catchup_config/config.php';
require_once __DIR__ . '/../../backend/_init.php';

Logging::log(null, null, Device::getDevice());

// Statistik

// Web
$userAgentParser = new UserAgentString();
$userAgentParser->includeAndroidName = true;
$userAgentParser->includeWindowsName = true;
$userAgentParser->includeMacOSName = true;
if(isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT'] != null){
    $userAgentParser->parseUserAgentString($_SERVER['HTTP_USER_AGENT']);
}

$browsername = empty($userAgentParser->browsername) ? null : $userAgentParser->browsername;
$browserBetriebssystem = empty($userAgentParser->osname) ? null : $userAgentParser->osname;
$browserversion = empty($userAgentParser->browserversion) ? null : $userAgentParser->browserversion;
if(!isset($userAgentParser->type)){
    $userAgentParser->type = "Unbekannt";
}
$hardware = $userAgentParser->type; // PC, mobile, or bot*/

$piKey = isset($_GET['piKey']) ? $_GET['piKey'] : null;
if(!isset($piKey)){
    exit($statusIF);
}

// Nur für interne Zwecke
$piId = Produktinstanz::getForKey($piKey)->produktinstanzId;
if(is_null($piId)){
    exit("Fehlerhafte Eingabe: piKey");
}

$kartenOfflineSpeichern = isset($_GET['kartenOfflineSpeichern']) ? $_GET['kartenOfflineSpeichern'] : 0;
if(is_null($kartenOfflineSpeichern) || $kartenOfflineSpeichern == ""){
    $kartenOfflineSpeichern = 0;
}


$isApp = isset($_GET['isApp']) ? $_GET['isApp'] : 0;
$betriebssystem = isset($_GET['betriebssystem']) ? $_GET['betriebssystem'] : null;
if($isApp && !isset($betriebssystem)){
    $betriebssystem = "iOS";
} else if(!$isApp){
    $betriebssystem = "NichtNativ";
}

// Default: Media Docks
$defaultCampusKey = "epMvtcstgO7eRT5iB1390Rif7agSdSkqmR8XqpvVyLYq31NFb6";
$campusKey = isset($_GET['cKey']) ? $_GET['cKey'] : $defaultCampusKey;
if(is_null($campusKey) || $campusKey == ""){
    $campusKey = $defaultCampusKey;
}

if($piKey === "UKBONN-L9JwjY3seBQkChmEq1zQ3BAiqNtAKlRkERKRnriAxf2K4G2"){
    $campusKey = "GiDJFg6uhUqkc973fJgnKvvaIdrVYPuNsM7UT8ztRkFyyPfRWB";
}

// campusId für interne Zwecke ermitteln
$statement = $pdo->prepare("SELECT id FROM NAVIGATION_CAMPUS 
	WHERE campusKey = :campusKey;");
$isQueryOk = $statement->execute(array('campusKey' => $campusKey));
if(!$isQueryOk){
    ErrorHandler::exitWithSqlError(__FILE__, $statement, null);
}
$campusId = $statement->fetch(PDO::FETCH_ASSOC);
if(empty($campusId)){
    exit("Fehlerhafte Eingabe: cKey");
}
$campusId = $campusId['id'];

$dev = isset($_GET['dev']) ? $_GET['dev'] : 0;

$ip_Adr = "";
if( array_key_exists( 'REMOTE_ADDR', $_SERVER ) ) {
    $ip_Adr = $_SERVER['REMOTE_ADDR'];
    $components = explode(".", $ip_Adr);
    if(sizeof($components) == 4){
        array_pop($components);
        $components[] = "00";
        $ip_Adr = implode(".", $components);
    }else{
        $ip_Adr = "";
    }
}
$isTerminal = isset($_GET["isTerminal"]) ? $_GET["isTerminal"] : 0;
$hasRotation = isset($_GET["hasRotation"]) ? $_GET["hasRotation"] : 0;
// Hex Color für die Einrichtungslabel im Link angeben -> sonst fallback grau
$einrichtungLabelColor = isset($_GET["einrichtungLabelColor"]) ? $_GET["einrichtungLabelColor"] : 0;

// Session erstellen
$statement = $pdo->prepare("INSERT INTO NAVIGATION_SESSIONS (sessionKey, produktinstanzId, campusId, typ, anfrageIP, erstelltAm) VALUES (:sessionKey, :produktinstanzId, :campusId, :typ, :anfrageIP, NOW());");
$sessionKey = getUniqueSessionKey($pdo);

if($hardware == "unknown"){
    $hardware = "App";
}else if($isTerminal){
    $hardware = "Terminal";
}

$isQueryOk = $statement->execute(array('sessionKey' => $sessionKey, 'produktinstanzId' => $piId, 'campusId' => $campusId, 'typ' => $hardware, 'anfrageIP' => $ip_Adr));
if(!$isQueryOk){
    ErrorHandler::exitWithSqlError(__FILE__, $statement, null);
}

if(isset($_GET['zielEinrId']) || isset($_GET['zielPOIId'])){
    $tempZielEinrId = isset($_GET['zielEinrId']) ? $_GET['zielEinrId'] : null;
    $tempZielPOIId = isset($_GET['zielPOIId']) ? $_GET['zielPOIId'] : null;
    $statement = $pdo->prepare("INSERT INTO NAVIGATION_SESSION_NAVIGATION (sessionId, zielEinrichtungId, zielPOIId, zielAdresseId, erstelltAm) VALUES ((SELECT id FROM NAVIGATION_SESSIONS WHERE sessionKey = :sessionKey), :zielEinrichtungId, :zielPOIId, :zielAdresseId, NOW());");

    $zielAdresseId = null;
    if(isset($_GET['zielPOIId']) && intval($_GET['zielPOIId']) < 0){
        $zielAdresseId = -1 * intval($_GET['zielPOIId']);
        $tempZielPOIId = null;
    }

    $isQueryOk = $statement->execute(array('sessionKey' => $sessionKey, 'zielEinrichtungId' => $tempZielEinrId, 'zielPOIId' => $tempZielPOIId, 'zielAdresseId' => $zielAdresseId));
    /*if(!$isQueryOk){
        ErrorHandler::errorEmail(__FILE__, $statement, null);
    }*/
}

$produktinstanz = Produktinstanz::getForKey($piKey);
if(!isset($produktinstanz)){
    echo "Fehlerhafte Eingabe";
    ErrorHandler::errorEmail(__FILE__, "produktinstanzDetails == null für piKey $piKey und campusKey $campusKey - iFrame-Daten konnten nicht geladen werden");
    return;
}

/**
 *	Gibt einen einzigartigen String zurück
 *
 *	@param $pdo			Datenbankverbindung
 *
 *	@return string	Unique String
 */
function getUniqueSessionKey($pdo){
    while(true){
        $newKey = Util::getRandomString(191);

        $statement = $pdo->prepare("SELECT * FROM NAVIGATION_SESSIONS 
			WHERE sessionKey = BINARY :newKey;");
        $isQueryOk = $statement->execute(array('newKey' => $newKey));
        if(!$isQueryOk){
            ErrorHandler::exitWithSqlError(__FILE__, $statement, null);
        }
        $ergebnisse = $statement->fetchAll(PDO::FETCH_ASSOC);
        $count = count($ergebnisse);
        if($count == 0){
            return $newKey;
        }
    }
}

// PHP Cookie
/*$cookieVorhanden = 0;
$cookieName = "catchup_sbs_" . $piKey;
$cookieValue = "Einfuehrung angezeigt";
if(!isset($_COOKIE[$cookieName])) {
    setcookie($cookieName, $cookieValue, time() + (86400 * 7), "/"); // 86400 = 1 day
} else {
	$cookieVorhanden = 1;
	echo "Cookie '" . $cookieName . "' is set!<br/>";
    echo "Value is: " . $_COOKIE[$cookieName] . "<br/>";
}

exit("cookieVorhanden: " . $cookieVorhanden);*/
// Custom Design

require_once __DIR__ .'/design/' . $piKey . '/php/ersatz.php';
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8"/>
    <title><?php echo $produktinstanz->produktbezeichnungDesKunden;?></title>
    <!-- Default -->
    <?php echo !$isTerminal ? "<!--" : "";?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <?php echo !$isTerminal ? "-->" : "";?>
    <!-- Terminal -->
    <?php echo $isTerminal ? "<!--" : "";?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <?php echo $isTerminal ? "-->" : "";?>

    <link rel="icon" href="design/<?php echo $piKey;?>/img/favicon.png">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image"/>
    <meta name="twitter:site" content="@catchupapps"/>
    <meta name="twitter:creator" content="@catchupapps"/>
    <meta name="twitter:title" content="<?php echo $produktinstanz->produktbezeichnungDesKunden;?> by Catchup Applications" />
    <meta name="twitter:description" content="Mit browserbasierter Indoor-Navigation ans Ziel finden" />
    <meta name="twitter:image" content="<?php echo "https://" . $domain . "/"?>iFrame/SbS/design/<?php echo $piKey;?>/img/Twitterpic-main.jpg" />
    <meta name="twitter:url" content="<?php echo "https://" . $domain . "/"?>iFrame/SbS/index.php?piKey=<?php echo $piKey . '&cKey=' . $campusKey;?>"/><!-- hier nicht https://twitter.com/catchupapps eingeben, weil dann im WhatsApp als Domain twitter.com angegeben wird -->
    <?php
    if(false && $piKey == "VITOS-WbLguwe6WnplOZk7ikBAa7hEkxK3jpKRXT2fQE5eA3CSNfKLgi"){
        $zielEinrId = isset($_GET['zielEinrId']) ? $_GET['zielEinrId'] : "null";
        $zielPOIId = isset($_GET['zielPOIId']) ? $_GET['zielPOIId'] : "null";
        $appId = 1435146834;
        echo '<meta name="apple-itunes-app" content="app-id=' . $appId . ', affiliate-data=myAffiliateData, app-argument=vitosnavigation://zielPOIId=' . $zielPOIId . '&zielEinrId=' . $zielEinrId . '">';
    }
    ?>

    <meta property="og:locale" content="de_DE" />
    <meta property="og:site_name" content="<?php echo $produktinstanz->produktbezeichnungDesKunden;?> by Catchup Applications"/>
    <meta property="og:title" content="<?php echo $produktinstanz->produktbezeichnungDesKunden;?> by Catchup Applications"/>
    <meta property="og:description" content="Mit Indoor-Navigation ans Ziel finden"/>
    <meta property="og:image" content="<?php echo "https://" . $domain . "/"?>iFrame/SbS/design/<?php echo $piKey;?>/img/ogimage.jpg"/>
    <meta property="og:url" content="<?php echo "https://" . $domain . "/"?>iFrame/SbS/index.php?piKey=<?php echo $piKey . '&cKey=' . $campusKey;?>"/>

    <script type="text/javascript" src='jsR/mapbox-gl-js/v1.7.0/mapbox-gl.js'></script>
    <script src="jsR/three/three.min.js"></script>
    <script src="jsR/three/GLTFLoader.js"></script>
    <link href='cssR/mapbox-gl-js/v1.7.0/mapbox-gl.css' rel='stylesheet' />
    <link rel='stylesheet' type="text/css" href='css/sbs_shared.css?v=<?php echo $webinterfaceVersion;?>'/>
    <link rel='stylesheet' type="text/css" href='design/<?php echo $piKey;?>/css/colors.css?v=<?php echo $webinterfaceVersion;?>'/>

    <!--<script src='js/route.js'></script>-->
    <script type="text/javascript" src='utilR/navigation/mathematik/geometrie/Vektor3D.js?v=<?php echo $webinterfaceVersion;?>'></script>
    <script type="text/javascript" src='utilR/navigation/mathematik/graphentheorie/Knoten.js?v=<?php echo $webinterfaceVersion;?>'></script>
    <script type="text/javascript" src='utilR/navigation/mathematik/graphentheorie/Kante.js?v=<?php echo $webinterfaceVersion;?>'></script>
    <script type="text/javascript" src='utilR/navigation/mathematik/graphentheorie/Graph.js?v=<?php echo $webinterfaceVersion;?>'></script>
    <script type="text/javascript" src='utilR/navigation/mathematik/graphentheorie/Pfad.js?v=<?php echo $webinterfaceVersion;?>'></script>
    <script type="text/javascript" src='utilR/navigation/mathematik/graphentheorie/Dijkstra.js?v=<?php echo $webinterfaceVersion;?>'></script>
    <script type="text/javascript" src='utilR/navigation/mathematik/graphentheorie/Polyline.encoded.js?v=<?php echo $webinterfaceVersion;?>'></script>
    <script type="text/javascript" src='utilR/navigation/mathematik/navigation/Anweisung.js?v=<?php echo $webinterfaceVersion;?>'></script>
    <script type="text/javascript" src='utilR/navigation/mathematik/navigation/Adresse.js?v=<?php echo $webinterfaceVersion;?>'></script>
    <script type="text/javascript" src='utilR/navigation/Sperrung.js?v=<?php echo $webinterfaceVersion;?>'></script>
    <script type="text/javascript" src='utilR/ziel/Ziel.js?v=<?php echo $webinterfaceVersion;?>'></script>
    <script type="text/javascript" src="js/view.js?v=<?php echo $webinterfaceVersion;?>"></script>
    <script type="text/javascript" src="js/qr.js?v=<?php echo $webinterfaceVersion;?>"></script>
    <script type="text/javascript" src="js/lokalisierung.js?v=<?php echo $webinterfaceVersion;?>"></script>

    <script type="text/javascript" src='utilR/navigation/mathematik/navigation/Eingang.js?v=<?php echo $webinterfaceVersion;?>'></script>
    <script type="text/javascript" src='utilR/navigation/mathematik/navigation/Einrichtung.js?v=<?php echo $webinterfaceVersion;?>'></script>
    <script type="text/javascript" src='utilR/navigation/mathematik/navigation/POI.js?v=<?php echo $webinterfaceVersion;?>'></script>
    <script type="text/javascript" src='utilR/navigation/mathematik/navigation/WC.js?v=<?php echo $webinterfaceVersion;?>'></script>

    <script type="text/javascript" src='utilR/navigation/Attribut.js?v=<?php echo $webinterfaceVersion;?>'></script>
    <script type="text/javascript" src='utilR/graphen/Transformator.js?v=<?php echo $webinterfaceVersion;?>'></script>

    <script type="text/javascript" src="jsR/angular/angular.1.5.7.min.js"></script>
    <script type="text/javascript" src="jsR/jquery/jquery-3.1.0.min.js"></script>
    <script type="text/javascript" src="jsR/promise/promise-7.0.4.min.js"></script>
    <script type="text/javascript" src="jsR/promise/promise-done-7.0.4.min.js"></script>
    <link rel="stylesheet" media="screen" href="sbsFonts/font.css?v=<?php echo $webinterfaceVersion;?>" type="text/css" />
    <link rel="stylesheet" media="screen" href="sbsFonts/opensans.css?v=<?php echo $webinterfaceVersion;?>" type="text/css" />
    <script type="text/javascript" src="jsR/moment.js-2.21.0/moment.min.js?v=<?php echo $webinterfaceVersion;?>"></script>

    <!-- Webapp -->
    <link rel="apple-touch-icon" href="design/<?php echo $produktinstanz->produktinstanzKey;?>/img/shortcut.png?v=<?php echo $webinterfaceVersion;?>"/>
    <link rel="apple-touch-startup-image" href="design/<?php echo $produktinstanz->produktinstanzKey;?>/img/launch.png?v=<?php echo $webinterfaceVersion;?>"/>
    <meta name="apple-mobile-web-app-title" content="<?php echo $produktinstanz->produktbezeichnungDesKunden;?>"/>
    <meta name="apple-mobile-web-app-capable" content="yes"/>
    <meta name="apple-mobile-web-app-status-bar-style" content="translucent"/>
    <meta name="apple-mobile-web-app-capable" content="yes"/>

    <link rel='stylesheet' type="text/css" href='css/rating.css?v=<?php echo $webinterfaceVersion;?>'/>

    <?php echo $isApp ? "" : "<!--";?>
    <link rel='stylesheet' type="text/css" href='css/app.css?v=<?php echo $webinterfaceVersion;?>'></link>
    <?php echo $isApp ? "" : "-->";?>

    <?php echo $betriebssystem == "Android" ? "" : "<!--";?>
    <link rel='stylesheet' type="text/css" href='css/android.css?v=<?php echo $webinterfaceVersion;?>'></link>
    <?php echo $betriebssystem == "Android" ? "" : "-->";?>

    <script type="text/javascript" src="js/rating.js"></script>

    <!-- Hier Javascript für QR Photo BTN -->
    <script src="jsR/qrCodeReader/qrcode/ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js" type="text/javascript"></script>
    <script type="text/javascript" src="jsR/qrCodeReader/qrcode/labs.qryptal.net/jsqrcode/src/grid.js"></script>
    <script type="text/javascript" src="jsR/qrCodeReader/qrcode/labs.qryptal.net/jsqrcode/src/version.js"></script>
    <script type="text/javascript" src="jsR/qrCodeReader/qrcode/labs.qryptal.net/jsqrcode/src/detector.js"></script>
    <script type="text/javascript" src="jsR/qrCodeReader/qrcode/labs.qryptal.net/jsqrcode/src/formatinf.js"></script>
    <script type="text/javascript" src="jsR/qrCodeReader/qrcode/labs.qryptal.net/jsqrcode/src/errorlevel.js"></script>
    <script type="text/javascript" src="jsR/qrCodeReader/qrcode/labs.qryptal.net/jsqrcode/src/bitmat.js"></script>
    <script type="text/javascript" src="jsR/qrCodeReader/qrcode/labs.qryptal.net/jsqrcode/src/datablock.js"></script>
    <script type="text/javascript" src="jsR/qrCodeReader/qrcode/labs.qryptal.net/jsqrcode/src/bmparser.js"></script>
    <script type="text/javascript" src="jsR/qrCodeReader/qrcode/labs.qryptal.net/jsqrcode/src/datamask.js"></script>
    <script type="text/javascript" src="jsR/qrCodeReader/qrcode/labs.qryptal.net/jsqrcode/src/rsdecoder.js"></script>
    <script type="text/javascript" src="jsR/qrCodeReader/qrcode/labs.qryptal.net/jsqrcode/src/gf256poly.js"></script>
    <script type="text/javascript" src="jsR/qrCodeReader/qrcode/labs.qryptal.net/jsqrcode/src/gf256.js"></script>
    <script type="text/javascript" src="jsR/qrCodeReader/qrcode/labs.qryptal.net/jsqrcode/src/decoder.js"></script>
    <script type="text/javascript" src="jsR/qrCodeReader/qrcode/labs.qryptal.net/jsqrcode/src/QRCode.js"></script>
    <script type="text/javascript" src="jsR/qrCodeReader/qrcode/labs.qryptal.net/jsqrcode/src/findpat.js"></script>
    <script type="text/javascript" src="jsR/qrCodeReader/qrcode/labs.qryptal.net/jsqrcode/src/alignpat.js"></script>
    <script type="text/javascript" src="jsR/qrCodeReader/qrcode/labs.qryptal.net/jsqrcode/src/databr.js"></script>

    <!-- Intro -->
    <link rel='stylesheet prefetch' href='jsR/bootstrap/3.2.0/css/bootstrap.min.css?v=<?php echo $webinterfaceVersion;?>'></link>
    <script src='jsR/bootstrap/3.2.0/js/bootstrap.min.js?v=<?php echo $webinterfaceVersion;?>'></script>
    <!-- Safari Pinch Problem -->
    <script>document.addEventListener('gesturestart', function(e) { e.preventDefault() });</script>

    <!-- Terminal less -->
    <style>.terminal-elem{display:none;}</style>

    <?php echo !$isTerminal ? "<!--" : "";?>

    <?php
    //Das passende CSS für diese Instanz finden
    $terminalStyleURL = "css/terminal.less";
    if($isTerminal){
        if(file_exists(__DIR__ .'/design/' . $piKey . '/css/terminal.less')){
            $terminalStyleURL = 'design/' . $piKey . '/css/terminal.less';
        }
    }
    ?>
    <link rel='stylesheet/less' type="text/css" href='<?php echo $terminalStyleURL;?>?v=<?php echo $webinterfaceVersion;?>'/>
    <?php echo !$isTerminal ? "-->" : "";?>

    <link rel='stylesheet/less' type="text/css" href='design/<?php echo $piKey;?>/css/new-sbs.less?v=<?php echo $webinterfaceVersion;?>'/>
    <script type="text/javascript">var less=less||{};less.env='development';</script>
    <script type="text/javascript" src="js/less.min.js"></script>
    <script type="text/javascript" src='js/index.js?v=<?php echo $webinterfaceVersion;?>'></script>

    <!-- Virtual Keyboard -->
    <link rel="stylesheet" href="js/simple-keyboard/keyboard.css?v=<?php echo $webinterfaceVersion;?>"></link>
    <script src="js/simple-keyboard/keyboard.js?v=<?php echo $webinterfaceVersion;?>"></script>

    <!-- CSS Suche -->
    <style>
        html,
        body {
            overflow: auto;
        }
        body {
            height: 100%;
            position:absolute;
            overflow: hidden;
            top:0;
            -webkit-overflow-scrolling: touch;
            bottom:0;
            left: 0;
            right: 0;
        }

        * {box-sizing: border-box;}

        #myInput {
            background-image: url('/png/searchicon.png');
            background-position: 10px 12px;
            line-height: 18px;
            background-size: contain;
            background-repeat: no-repeat;
            width: calc(100% - 40px);
            padding-left: 20px;
            font-size: 16px;
            padding: 15px;
            height: 50px;
            border: none;
            position: relative;
            margin: 12px 20px;
            border-radius: 30px;
            background:#FFF;
            color: #6e6e6e;
            -webkit-box-shadow: 0px 2px 12px 0px rgba(0,0,0,0.2);
            -moz-box-shadow: 0px 2px 12px 0px rgba(0,0,0,0.2);
            box-shadow: 0px 2px 12px 0px rgba(0,0,0,0.2);
        }
        #myInput:focus{
            -webkit-box-shadow: 0px 2px 6px 0px rgba(0,0,0,0.2);
            -moz-box-shadow: 0px 2px 6px 0px rgba(0,0,0,0.2);
            box-shadow: 0px 2px 6px 0px rgba(0,0,0,0.2);
        }
        .suche{
            background: #FFF;
            z-index: 999;
        }

        #maoOverlay{display: block !important;}

        #header-carousel{height: 100%;}
        .carousel-inner{height: 100%;}
        #header-carousel .carousel-inner .item { background-size: cover; background-position: center; width: 100%; height: 100%; position: absolute;bottom: 0;top: 0; }
        .carousel-control i { margin-top: 170px; }
        .slide {}
        .title {
            font-size: 50px;
            font-weight: bold;
            text-align: center;
            opacity: 0;
            position: absolute;
            width: 100%;
            top: 0;
        }
        .text {}
        .more {
            background: #e2393e;
            font-weight: bold;
            width: 200px;
            text-align: center;
            line-height: 4;
            position: absolute;
            bottom: 0;
            left: calc(50% - 100px);
            opacity: 0;
            cursor: pointer;
            -webkit-transition: all 0.3s ease;
            -webkit-transition: all 0.3s ease;
            -webkit-transition: all 0.3s ease;
            -webkit-transition: all 0.3s ease;
            -webkit-transition: all 0.3s ease;
        }
        .more:hover {
            -webkit-box-shadow: none;
            -moz-box-shadow:    none;
            box-shadow:         none;
            margin-bottom: -3px;
        }
        .slide, .title, .text { -webkit-transition: all 0.5s ease; -moz-transition: all 0.5s ease; -ms-transition: all 0.5s ease; -o-transition: all 0.5s ease; transition: all 0.5s ease; }
        .carousel-control.right{right: 0; left: auto; background: none;}
        .carousel-control.left{left: 0; right: auto; background: none;}

        button.startbutton.menue-btn{position: absolute;bottom: 20px;left: 0;right: 0;}
        button.startbutton.menue-btn.skip{position: absolute;bottom: 0;left: 0;right: 0;}

        .toast {
            width: calc(100% - 40px);
            padding-left: 10px;
            padding-right: 10px;
            height: auto;
            line-height: auto;
            margin-left: 20px;
            margin-right: 20px;
            position: absolute;
            z-index: 10000099999999;
            left: 0;
            bottom: calc(50% - 40px);;
            text-align: center;
        }
        .toastinner{
            display:inline-block;
            border-radius: 4px;
            -webkit-box-shadow: 0px 0px 24px -1px rgba(56, 56, 56, 0.4));
            -moz-box-shadow: 0px 0px 24px -1px rgba(56, 56, 56, 0.4));
            box-shadow: 0px 0px 24px -1px rgba(56, 56, 56, 0.4);
            background-color: rgba(0, 0, 0, 0.5);
            color: #F0F0F0;
            font-size: 14px;
            padding: 12px 7px;
        }

        /* FileSupport  */

        div.FileSupport-wrapper{
            z-index: -9999999999999999999999999999999999;
            height: 100%;
            width: 100%;
            opacity: 0;
        }
        div.FileSupport-shield{
            z-index: -9999999999999999999999999999999999;
            height: 100%;
            width: 100%;
            opacity: 0;
        }
        div.FileSupport-content{
            z-index: -9999999999999999999999999999999999;
            height: 100%;
            width: 100%;
            opacity: 0;
        }
        img.FileSupport-img{
            z-index: -9999999999999999999999999999999999;
            height: 0px;
            width: 0px;
            opacity: 0;
        }

        /* Mapbox - Sperrungen und Bilder anzeigen  16.12.19*/

        .mapboxgl-popup{
            z-index: 9999;
        }
        .mapboxgl-popup-close-button{
            height: 20px;
            width: 20px;
            top:-10px;
            right:-10px;
            background-color: #494949 !important;
            color: #fff;
            border-radius: 50%;
        }
        .mapboxgl-popup-close-button:hover{
            background-color: #3e3e3e !important;
        }

        .mbpopupimg{
            max-width: 200px;
        }

    </style>
</head>
<body ontouchstart="">
<?php
require_once __DIR__ . '/../../backend/app/med/getProduktinstanzDatenNeu.php';
$datenPreset = DatenPreset::construct(DatenPreset::PRESET_JSON_WEBINTERFACE, $piId , 3);
$jsonAlleDaten = $dev ? json_encode(Daten::getCampusdaten($datenPreset, $campusId)) : getVeroeffentlichteProduktinstanzdaten($piKey, $campusKey, 3, true);
?>
<script>
    var LOG = <?php $test = isset($_GET["test"]) ? $_GET['test'] : 0;if($test){echo 1;}else if($domain == CU_SYS_PROD){echo 0;}else{echo 0;}?>;
    var dev = <?php echo $dev;?>;
    var isApp = <?php echo $isApp;?>;
    var betriebssystem = "<?php echo $betriebssystem;?>";
    var piKey = "<?php echo $piKey;?>";
    var isTerminal = <?php echo $isTerminal;?>;
    var hasRotation = <?php echo $hasRotation;?>;
    var einrichtungLabelColor = "#<?php echo $einrichtungLabelColor ?>";
    var isTest = <?php echo isset($_GET["test"]) ? $_GET['test'] : 0;?>;
    var campusKey = "<?php echo $campusKey;?>";
    var sessionKey = "<?php echo $sessionKey;?>";
    var kartenOfflineSpeichern = <?php echo $kartenOfflineSpeichern;?>;
    var produktbezeichnungDesKunden = "<?php echo $produktinstanz->produktbezeichnungDesKunden;?>";
    var mapboxAccessToken = "<?php echo $produktinstanz->mapboxAccessToken;?>";
    var defaultBearing = <?php echo $_GET["bearing"] ?? "null";?>;
    if(Math.sinh == null){kartenOfflineSpeichern = 0;}
    LOG && console.log("UserAgent: " + '<?php echo json_encode($userAgentParser) ?>');
    <?php echo "var basicData = JSON.parse(" . json_encode($jsonAlleDaten) . ");"; ?>
    var wichtigeTiles = [];
    <?php
    if($kartenOfflineSpeichern == 1){
        $statement = $pdo->prepare("SELECT id, zoomLevel, x, y FROM `NAVIGATION_MAPTILE_URLS` WHERE `tileVorhanden` = 1 AND `tileIstNichtLeer` = 1 AND zoomLevel <= 19 and campusId = :campusId;");
        $isQueryOk = $statement->execute(array('campusId' => $campusId));
        if(!$isQueryOk){
            ErrorHandler::exitWithSqlError(__FILE__, $statement, null);
        }
        $ergebnisse = $statement->fetchAll(PDO::FETCH_ASSOC);
        $wichtigeTiles = $ergebnisse;
    }else{
        $wichtigeTiles = [];
    }
    ?>
    wichtigeTiles = <?php echo json_encode($wichtigeTiles);?>;
    var eingeschraenketeFunktionalitaet = <?php
        $isChrome = $userAgentParser->chrome;$browserversion = intval($userAgentParser->browserversion);$eingeschraenketeFunktionalitaet = 0;if($isChrome){if($browserversion < 40){$eingeschraenketeFunktionalitaet = 1;}else{$eingeschraenketeFunktionalitaet = 0;}}
        echo $eingeschraenketeFunktionalitaet;
        ?>;
    var etageUp = '<?php getEtageUp($piKey); ?>';
    var etageDown = '<?php getEtageDown($piKey); ?>';
    var isAndroidApp = <?php if(!preg_match('/iOS/', $userAgentParser->osname) && $isApp){echo 1;}else{echo 0;}?>;


</script>

<div id="loadingAnimation" style="display: block;" class="<?php if($kartenOfflineSpeichern){echo "off";}?>"><div class="uil-ring-css" style="transform:scale(0.6);"><div></div><span class=""></span></div></div>
<div class='toast' style='display:none'><span class="toastinner"></span></div>
<section class="nojavascript" style="display: none;"><div class="js-cnt">Bitte aktivieren Sie Javascript, um die Step-by-Step Navigation zu verwenden.</div></section>

<!-- Share WRAPPER -->

<div class="sharewrapper overlaywrapper off">
    <div class="wrap-one">
        <div class="wrap-two">
            <span class="starttext shareheader">Wie möchten Sie teilen?</span>
            <ul class="sharelist">
                <li id="email" onclick="onShareClicked('mail');" title="Per Email senden"></li>
                <?php
                if($userAgentParser->type == "Mobil" || $isApp){
                    echo '<li id="sms" onclick="onShareClicked(\'sms\');" title="Per SMS senden"></li>';
                    echo '<li id="whatsapp" onclick="onShareClicked(\'whatsapp\');" title="Per WhatsApp senden"></li>';
                }
                ?>
                <li id="twitter" onclick="onShareClicked('twitter');" title="Auf Twitter teilen"></li>
                <?php
                if(!preg_match('/iOS/', $userAgentParser->osname) && !$isApp){//wenn os == iOS kein Link-Kopieren möglich
                    echo '<li id="clipboard" class="js-emailcopybtn" onclick="onShareClicked(\'copy\');" title="Link kopieren"></li>';
                }
                ?>
            </ul>
            <button class="startbutton menue-btn" onclick="blendesharewrapperViewAus();">Zurück</button>
        </div>
    </div>
</div>

<!-- OfflineModus WRAPPER -->

<div class="OfflineModuswrapper overlaywrapper <?php
if(!$kartenOfflineSpeichern || (isset($_COOKIE["catchup_sbs_MapOfflineGespeichert_" . $campusId])) || $besondersEingeschraenketeFunktionalitaet){
    echo "off";
}
?>" id="offlineWrapperId">
    <div class="wrap-one">
        <div class="wrap-two">
					<span class="starttext">
						<span class="firsttext">Lade Daten für den <br>Offlinemodus</span>
						<object id="Offline-svg" class="offlinesvg" data="design/<?php echo $piKey;?>/img/offline.svg" type="image/svg+xml"></object><br>
						<div class="loading"><div class="offlineprogress"><div class="offlineprogress_bar"></div></div></div><br>
						<span id="offline_prog_status">0%</span>
						<button class="QRAbbruch" onclick="onDownloadAbrrechenButtonClicked();" style="width: auto">Überspringen</button>
					</span>
        </div>
    </div>
</div>

<!-- Burgermenu -->

<div class="burgermenuwrapper overlaywrapper off">
    <div class="wrap-one">
        <button class="burger aus" onclick="blendeBurgerMenuAus();"></button>
        <?php getBurgerHeader($piKey); ?>

        <iframe src="https://www.catchup-apps.com/impressum" class="terminal-elem"></iframe>
        <div class="wrap-two">
            <ul class="burgerlist">
                <?php echo $isTerminal ? "<!---" : "";?>
                <li id="QrBurgerList" <?php echo $isApp ? 'onclick="callNativeApp();"' : ""?>>
                    <label class="custom-file-upload burger">
                        QR-Code scannen
                        <?php echo (!$isTerminal && $isApp) ? "<!--" : "";?>
                        <input type="file" name="photo" id="photo2" onchange="onPhotoChanged(this);" value="" capture="camera" accept="image/*;capture=camera" onclick="blendeBurgerMenuAus();">
                        <?php echo (!$isTerminal && $isApp) ? "-->" : "";?>
                    </label>
                </li>
                <li id="DddBurgerList" class="disabledLi" onclick="toggle3DModus(this.id);"><a id="ddd-text-btn">Zum 3D-Modus wechseln</a></li>
                <li id="RatingBurgerList" class="disabledLi" onclick="toggleRatingView(this.id);"><a id="rating-btn">Bewertung abgeben</a></li>
                <li id="share" onclick="blendesharewrapperViewEin();"><a id="share-btn">Ziel teilen</a></li>
                <?php echo $isTerminal ? "-->" : "";?>
            </ul>
            <ul class="CuUl">
                <li class="CuList"><a href="<?php echo $piId == 18 ? 'https://www.klinikum-oldenburg.de/impressum/' : 'https://www.catchup-apps.com/impressum'?>">Impressum</a> | <a href="<?php echo $piId == 18 ? 'https://www.klinikum-oldenburg.de/datenschutz/' : 'https://www.catchup-apps.com/rechtliches/datenschutz/naviApp.html'?>">Datenschutz</a> |<a href="https://www.catchup-apps.com/agbs.html">AGB</a> </li>
                <li class="CuList"><a href="https://www.catchup-apps.com">powered by <img class="BurgerCuLogo" src="design/<?php echo $piKey;?>/img/Catchup-Logo-powered.png"/></a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Infowrapper -->
<div id="infoWrapId" class="infowrapper overlaywrapper off">
    <div class="wrap-one">
        <div class="wrap-two">
            <span class="starttext">Bei der Routenberechnung werden zahlreiche Kriterien berücksichtigt, z.B. eine ggf. vorhandene Bewegungseinschränkung, Öffnungszeiten von Eingängen uvm. - es kann deshalb sein, dass die angezeigte Route nicht die Kürzeste, sondern eine für Ihre Navigation optimierte Route ist.</span>
            <button class="skip-btn" onclick="zeigeInfoboxAn(false);">Verstanden</button>
        </div>
    </div>
</div>

<!-- Popup WRAPPER -->

<div class="popupwrapper overlaywrapper off">
    <div class="wrap-one">
        <div class="wrap-two">
            <span class="starttext">Möchten Sie die Live-Navigation in der App starten?</span>
            <button class="menue-btn" onclick="onOeffneInAppstor();">App benutzen</button>
            <button class="skip-btn" onclick="onInAppOeffnenAbbrechenClikced();">Abbrechen</button>
        </div>
    </div>
</div>

<div class="lokalisierunNichtVerfuegbarWrapper overlaywrapper off">
    <div class="wrap-one">
        <div class="wrap-two">
            <span id="lokalisierunNichtVerfuegbarWrapperTitelId" class="starttext shareheader">Lokalisierung nicht möglich</span>
            <button class="startbutton menue-btn" onclick="blendeLokalisierungNichtVerfuegbarPopupwrapperViewAus();">OK</button>
        </div>
    </div>
</div>

<!-- PANORAMA WRAPPER -->

<div class="panoramawrapper overlaywrapper off">
    <div class="wrap-one">
        <div class="wrap-two">
            <span class="starttext">Bitte drehen Sie Ihr Handy</span>
            <button class="startbutton menue-btn" onclick="blendePanoramawrapperViewAus();">Weiter</button>
        </div>
    </div>
</div>

<!-- QREingabewrapper WRAPPER -->

<div class="QREingabewrapper overlaywrapper off">
    <div class="wrap-one">
        <div class="wrap-two">
					<span class="starttext">
						<span class="firsttext">Der QR-Code hat nicht funktioniert?<p>Geben Sie einfach die sechs Zeichen unter dem QR-Code manuell ein.</p></span>
						<span class="secondtext off">Leider hat die Eingabe nicht funktioniert.<p>Geben Sie Ihn einfach noch einmal manuell ein.</p></span>
						<object class="qrscansvg" data="design/<?php echo $piKey;?>/img/QR-Scan.svg" type="image/svg+xml"></object>
						<input id="qrinputfield" class="QREingabe" placeholder="Code eingeben" onkeyup="onQREingabeChanged();" /><br>
						<button class="QREingabe" onclick="uebernehmeManuellenQRCode();">Eingabe</button>
						<button class="QRAbbruch" onclick="blendeQREingabewrapperAus();">Abbruch</button>
					</span>
        </div>
    </div>
</div>

<!-- BROWSER WRAPPER -->

<div id="browserwrapperId" class="browserwrapper overlaywrapper off" style="z-index: 999999999999;">
    <div class="wrap-one">
        <div class="wrap-two">
            <span id="browserWrapperLabelId" class="starttext">Ihr Browser wird nicht unterstützt.<p>Um die Step-by-Step-Navigation zu verwenden, benutzen Sie bitte eine aktuelle Version von Google Chrome, Apple Safari, Mozilla Firefox oder Microsoft Edge</p></span>
            <!-- <button class="startbutton menue-btn browsersupport-btn" onclick="blendeBrowserwrapperViewAus();">Trotzdem fortfahren</button> -->
        </div>
    </div>
</div>

<!-- IMPRESSUM WRAPPER -->

<div class="impressumwrapper overlaywrapper off">
    <div class="wrap-one">
        <button id="zielWaehlenButton" class="impress" onclick="blendeImpresswrapperViewAus();"></button>
        <div class="wrap-two">
            <span class="starttext">Inhaltlich Verantwortlich:</span>
            <img class="logo-header-impress" src="png/catchup-applications-w.png" alt="Catchup Applications KG"></img>
            <span class="impresstext">Catchup Applications KG<br>Glockengießerwall 26<br>20095 Hamburg<br><br><p><b>Handelsregistereintrag:</b><br><br>HRA 125782<br>St.Nr..: <!-- &zwj; prevent from detecting as phone number-->&zwj;22 &zwj;281 &zwj;32 &zwj;459<br>USt.IdNr.: DE &zwj;300 &zwj;664 &zwj;275</span>

            <span class="starttext">Rechtliches:</span>
            <span class="impresstext"><a class="impress-a" href="https://www.catchup-apps.com/impressum">Impressum</a><br><a class="impress-a" href="https://www.catchup-apps.com/datenschutz-khapp">Datenschutz</a><br><a class="impress-a" href="https://www.catchup-apps.com/agbs.html">AGB</a></span>
        </div>
    </div>
</div>

<?php echo $isTerminal ? "<!--" : "";?>
<section class="suche active js-hide">
    <div id='auswahlNameDiv'>
        <button class="burger"onclick="blendeBurgerMenuEin();"></button>
        <?php getHeader($piKey); ?>
        <div class="searchfieldbox suchsection">
            <div class="hoehepunkt">
                <input type="text" id="myInput" onkeyup="onKeyUpZielInput();" onFocus="window.scrollTo(0, 0);" placeholder="Suchen Sie eine Einrichtung" title="Einrichtung suchen">
                <label class="custom-file-upload">
                    <input type="file" name="photo" id="photo1" onchange="onPhotoChanged(this);" class="photonone" value="" capture="camera" accept="image/*;capture=camera">
                </label>
            </div>
        </div>
    </div>
    <div class="sc-trigger" id="sc-trigger" onclick="onSucheSchliessenClicked();auswahllisteAnzeigen(false);"><img class="sc-triggerbutton" src="img/abort-w.svg"/></div>
    <ul id="myUL" class="terminal-ul-fix"></ul>
</section>
<?php echo $isTerminal ? "-->" : "";?>

<?php echo !$isTerminal ? "<!--" : "";?>
<section id="terminalsuche" class="terminalsuche">
    <div id="searchlist-header" class="searchlist-header">
        <span class="searchheader">Finden Sie Ihr Ziel</span>
    </div>
    <button id="keyboard-push-normal" onclick="showVirtualKeyboard(0);" class="terminal-elem keyboard-push-btn" ></button>
    <button id="keyboard-push-wheelchair" onclick="showVirtualKeyboard(1);" class="terminal-elem keyboard-push-btn"></button>

    <div id="terminal-searchbar" class="terminal-elem terminal-searchbar open">
        <div class="terminalkeyboard-wrapper">
            <div class="terminal-search-wrapper">
                <button id="" onClick="virtualKeyboardClear();" class="terminal-search-clear-btn" onclick=""></button>
                <input type="text" id="myInput" onkeyup="onKeyUpZielInput();" onFocus="window.scrollTo(0, 0);" placeholder="Suchen Sie eine Einrichtung" title="Einrichtung suchen">
                <button id="terminalSearchHideButton" onClick="hideVirtualKeyboard();" class="terminal-search-hide-btn" onclick="showVirtualKeyboard(0);"></button>
            </div>
            <div class="simple-keyboard"></div>
        </div>
    </div>
    <ul id="myUL" class="terminal-ul-fix"></ul>
</section>
<?php echo !$isTerminal ? "-->" : "";?>


<!-- <section class="sectionseperator hide js-hide"></section> -->

<section class="karte js-hide">
    <div id='maoOverlay'></div>
    <div id='zielNameDiv' class="">
        <button class="burger" onclick="blendeBurgerMenuEin();"></button>
        <div class="zielroute-cnt">
            <div id="rmcar" class="zielroute-child active" onclick="didSelectRouteMode(7);">
                <div class="zielroute-child-pic car"></div>
                <span class="zielroute-child-txt">Autofahrer</span>
            </div>
            <div id="rmped" class="zielroute-child" onclick="didSelectRouteMode(8);">
                <div class="zielroute-child-pic man"></div>
                <span class="zielroute-child-txt">Fußgänger</span>
            </div>
            <div id="rmbike" class="zielroute-child" onclick="didSelectRouteMode(9);">
                <div class="zielroute-child-pic bike"></div>
                <span class="zielroute-child-txt">Radfahrer</span>
            </div>
            <div id="rmdis" class="zielroute-child" onclick="didSelectRouteMode(10);">
                <div class="zielroute-child-pic wheel"></div>
                <span class="zielroute-child-txt">Bewegungs-<br>eingeschränkt</span>
            </div>
        </div>
        <div class="zielroute-location-cnt" <?php echo $isTerminal ? 'style="display:none;"' : ""; ?>>
            <span class="zielroute-location-title">Start</span>
            <input id='startnameH2' readonly class="adress-input empty" placeholder="Start auswählen..." onclick="onStartWaehlen();"></input>
            <label class="custom-file-upload empty" <?php echo $isTerminal ? 'style="display:none;"' : '';?>>
                <input type="file" name="photo" id="photo2" onchange="onPhotoChanged(this);" value="" capture="camera" accept="image/*;capture=camera" onclick="blendeBurgerMenuAus();">
            </label>
        </div>
        <div class="zielroute-location-cnt">
            <span class="zielroute-location-title">Ziel</span>
            <input id='zielnameH2' readonly class="adress-input" placeholder="Ziel auswählen..." onclick="onZielWaehlen();"></input>
            <label class="custom-file-upload empty" <?php echo $isTerminal ? 'style="display:none;"' : '';?>>
                <input type="file" name="photo" id="photo2" onchange="onPhotoChanged(this);" value="" capture="camera" accept="image/*;capture=camera" onclick="blendeBurgerMenuAus();">
            </label>
        </div>
        <div id='anweisungDiv'>
            <h3 id='anweisungText'>Berechne Route...</h3>
            <div id='etage' class="headmenue-btn" style="font-size:11px;></div>
            <div id='ortung' class="headmenue-btn" onclick="getGPSLocation();"></div>
        </div>
    </div>
    <!-- ETAGEN WRAPPER -->
    <div class="etagenwrapper overlaywrapper off">
        <div class="wrap-one">
            <div class="wrap-two">
                <span id="etagen-cnt" class="starttext etage">CNT</span>
                <div id="etagenwechsel-updown" class="img-cnt"></div>
                <button class="startbutton menue-btn" onclick="onEtagenwechselOKClicked();">Weiter</button>
            </div>
        </div>
    </div>
    <!-- BEWERTUNG WRAPPER -->
    <div class="ratingwrapper overlaywrapper off">
        <div class="wrap-one">
            <div class="wrap-two">
                <div class="rating-cnt-wrapper">
                    <div class="demo-container clip-marker">
                        <h1>Wie hat Ihnen die Navigation gefallen?</h1>
                        <div class="rating-control">
                            <div class="rating-option" rating="1" selected-fill="#FFA98D">
                                <div class="icon" style="transform: scale(1);">
                                    <svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="100%" height="100%" viewBox="0 0 50 50">
                                        <path d="M50,25 C50,38.807 38.807,50 25,50 C11.193,50 0,38.807 0,25 C0,11.193 11.193,0 25,0 C38.807,0 50,11.193 50,25" class="base" fill="#FFFFFF"></path>
                                        <path d="M25,31.5 C21.3114356,31.5 17.7570324,32.4539319 14.6192568,34.2413572 C13.6622326,34.7865234 13.3246514,36.0093483 13.871382,36.9691187 C14.4181126,37.9288892 15.6393731,38.2637242 16.5991436,37.7169936 C19.1375516,36.2709964 22.0103269,35.5 25,35.5 C27.9896731,35.5 30.8610304,36.2701886 33.4008564,37.7169936 C34.3606269,38.2637242 35.5818874,37.9288892 36.128618,36.9691187 C36.6753486,36.0093483 36.3405137,34.7880878 35.3807432,34.2413572 C32.2429676,32.4539319 28.6885644,31.5 25,31.5 Z" class="mouth" fill="#6F9965"></path>
                                        <path d="M30.6486386,16.8148522 C31.1715727,16.7269287 31.2642212,16.6984863 31.7852173,16.6140137 C32.3062134,16.529541 33.6674194,16.3378906 34.5824585,16.1715729 C35.4974976,16.0052551 35.7145386,15.9660737 36.4964248,15.8741891 C36.6111841,15.9660737 36.7220558,16.0652016 36.8284271,16.1715729 C37.7752853,17.118431 38.1482096,18.4218859 37.9472002,19.6496386 C37.8165905,20.4473941 37.4436661,21.2131881 36.8284271,21.8284271 C35.26633,23.3905243 32.73367,23.3905243 31.1715729,21.8284271 C29.8093655,20.4662198 29.6350541,18.3659485 30.6486386,16.8148522 Z" class="right-eye" fill="#6F9965"></path>
                                        <path d="M18.8284271,21.8284271 C20.1906345,20.4662198 20.3649459,18.3659485 19.3513614,16.8148522 C18.8284273,16.7269287 18.7357788,16.6984863 18.2147827,16.6140137 C17.6937866,16.529541 16.3325806,16.3378906 15.4175415,16.1715729 C14.5025024,16.0052551 14.2854614,15.9660737 13.5035752,15.8741891 C13.3888159,15.9660737 13.2779442,16.0652016 13.1715729,16.1715729 C12.2247147,17.118431 11.8517904,18.4218859 12.0527998,19.6496386 C12.1834095,20.4473941 12.5563339,21.2131881 13.1715729,21.8284271 C14.73367,23.3905243 17.26633,23.3905243 18.8284271,21.8284271 Z" class="left-eye" fill="#6F9965"></path>
                                    </svg>
                                </div>
                                <div class="label" style="transform: translateY(0px); color: rgb(171, 178, 182);">Schlecht</div>
                                <div class="touch-marker"></div>
                            </div>
                            <div class="rating-option" rating="2" selected-fill="#FFC385">
                                <div class="icon" style="transform: scale(1);">
                                    <svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="100%" height="100%" viewBox="0 0 50 50">
                                        <path d="M50,25 C50,38.807 38.807,50 25,50 C11.193,50 0,38.807 0,25 C0,11.193 11.193,0 25,0 C38.807,0 50,11.193 50,25" class="base" fill="#FFFFFF"></path>
                                        <path d="M25,31.9996 C21.7296206,31.9996 18.6965022,32.5700242 15.3353795,33.7542598 C14.2935825,34.1213195 13.7466,35.2634236 14.1136598,36.3052205 C14.4807195,37.3470175 15.6228236,37.894 16.6646205,37.5269402 C19.617541,36.4865279 22.2066846,35.9996 25,35.9996 C28.1041177,35.9996 31.5196849,36.5918872 33.0654841,37.4088421 C34.0420572,37.924961 35.2521232,37.5516891 35.7682421,36.5751159 C36.284361,35.5985428 35.9110891,34.3884768 34.9345159,33.8723579 C32.7065288,32.6948667 28.6971052,31.9996 25,31.9996 Z" class="mouth" fill="#6F9965"></path>
                                        <path d="M30.7014384,16.8148522 C30.8501714,16.5872449 31.0244829,16.3714627 31.2243727,16.1715729 C32.054483,15.3414625 33.1586746,14.9524791 34.2456496,15.0046227 C34.8805585,15.7858887 34.945378,15.8599243 35.5310714,16.593811 C36.1167648,17.3276978 36.1439252,17.3549194 36.5988813,17.9093628 C37.0538374,18.4638062 37.2801558,18.7149658 38,19.6496386 C37.8693903,20.4473941 37.496466,21.2131881 36.8812269,21.8284271 C35.3191298,23.3905243 32.7864699,23.3905243 31.2243727,21.8284271 C29.8621654,20.4662198 29.6878539,18.3659485 30.7014384,16.8148522 Z" class="right-eye" fill="#6F9965"></path>
                                        <path d="M18.8284271,21.8284271 C20.1906345,20.4662198 20.3649459,18.3659485 19.3513614,16.8148522 C19.2026284,16.5872449 19.0283169,16.3714627 18.8284271,16.1715729 C17.9983168,15.3414625 16.8941253,14.9524791 15.8071502,15.0046227 C15.1722413,15.7858887 15.1074218,15.8599243 14.5217284,16.593811 C13.9360351,17.3276978 13.9088746,17.3549194 13.4539185,17.9093628 C12.9989624,18.4638062 12.772644,18.7149658 12.0527998,19.6496386 C12.1834095,20.4473941 12.5563339,21.2131881 13.1715729,21.8284271 C14.73367,23.3905243 17.26633,23.3905243 18.8284271,21.8284271 Z" class="left-eye" fill="#6F9965"></path>
                                    </svg>
                                </div>
                                <div class="label" style="transform: translateY(0px); color: rgb(171, 178, 182);">Mäßig</div>
                                <div class="touch-marker"></div>
                            </div>
                            <div class="rating-option" rating="3">
                                <div class="icon" style="transform: scale(1);">
                                    <svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="100%" height="100%" viewBox="0 0 50 50">
                                        <path d="M50,25 C50,38.807 38.807,50 25,50 C11.193,50 0,38.807 0,25 C0,11.193 11.193,0 25,0 C38.807,0 50,11.193 50,25" class="base" fill="#FFFFFF"></path>
                                        <path d="M25.0172185,32.7464719 C22.4651351,33.192529 19.9789584,33.7240143 17.4783686,34.2837667 C16.4004906,34.5250477 15.7222686,35.5944568 15.9635531,36.6723508 C16.2048377,37.7502449 17.2738374,38.4285417 18.3521373,38.1871663 C20.8031673,37.6385078 23.2056119,37.1473427 25.7416475,36.6803253 C28.2776831,36.2133079 30.8254642,35.7953113 33.3839467,35.4267111 C34.4772031,35.2692059 35.235822,34.2552362 35.0783131,33.1619545 C34.9208042,32.0686729 33.89778,31.3113842 32.8135565,31.4675881 C30.2035476,31.8436117 27.6044107,32.2700339 17.4783686,34.2837667 Z" class="mouth" fill="#6F9965"></path>
                                        <path d="M30.6486386,16.8148522 C30.7973716,16.5872449 30.9716831,16.3714627 31.1715729,16.1715729 C32.0016832,15.3414625 33.1058747,14.9524791 34.1928498,15.0046227 C35.0120523,15.0439209 35.8214759,15.3337764 36.4964248,15.8741891 C36.6111841,15.9660737 36.7220558,16.0652016 36.8284271,16.1715729 C37.7752853,17.118431 38.1482096,18.4218859 37.9472002,19.6496386 C37.8165905,20.4473941 37.4436661,21.2131881 36.8284271,21.8284271 C35.26633,23.3905243 32.73367,23.3905243 31.1715729,21.8284271 C29.8093655,20.4662198 29.6350541,18.3659485 30.6486386,16.8148522 Z" class="right-eye" fill="#6F9965"></path>
                                        <path d="M18.8284271,21.8284271 C20.1906345,20.4662198 20.3649459,18.3659485 19.3513614,16.8148522 C19.2026284,16.5872449 19.0283169,16.3714627 18.8284271,16.1715729 C17.9983168,15.3414625 16.8941253,14.9524791 15.8071502,15.0046227 C14.9879477,15.0439209 14.1785241,15.3337764 13.5035752,15.8741891 C13.3888159,15.9660737 13.2779442,16.0652016 13.1715729,16.1715729 C12.2247147,17.118431 11.8517904,18.4218859 12.0527998,19.6496386 C12.1834095,20.4473941 12.5563339,21.2131881 13.1715729,21.8284271 C14.73367,23.3905243 17.26633,23.3905243 18.8284271,21.8284271 Z" class="left-eye" fill="#6F9965"></path>
                                    </svg>
                                </div>
                                <div class="label" style="transform: translateY(0px); color: rgb(171, 178, 182);">Okay</div>
                                <div class="touch-marker"></div>
                            </div>
                            <div class="rating-option" rating="4">
                                <div class="icon" style="transform: scale(1);">
                                    <svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="100%" height="100%" viewBox="0 0 50 50">
                                        <path d="M50,25 C50,38.807 38.807,50 25,50 C11.193,50 0,38.807 0,25 C0,11.193 11.193,0 25,0 C38.807,0 50,11.193 50,25" class="base" fill="#FFFFFF"></path>
                                        <path d="M25,35 C21.9245658,35 18.973257,34.1840075 16.3838091,32.6582427 C15.4321543,32.0975048 14.2061178,32.4144057 13.64538,33.3660605 C13.0846422,34.3177153 13.401543,35.5437517 14.3531978,36.1044896 C17.5538147,37.9903698 21.2054786,39 25,39 C28.7945214,39 32.4461853,37.9903698 35.6468022,36.1044896 C36.598457,35.5437517 36.9153578,34.3177153 36.35462,33.3660605 C35.7938822,32.4144057 34.5678457,32.0975048 33.6161909,32.6582427 C31.026743,34.1840075 28.0754342,35 25,35 Z" class="mouth" fill="#6F9965"></path>
                                        <path d="M30.6486386,16.8148522 C30.7973716,16.5872449 30.9716831,16.3714627 31.1715729,16.1715729 C32.0016832,15.3414625 33.1058747,14.9524791 34.1928498,15.0046227 C35.0120523,15.0439209 35.8214759,15.3337764 36.4964248,15.8741891 C36.6111841,15.9660737 36.7220558,16.0652016 36.8284271,16.1715729 C37.7752853,17.118431 38.1482096,18.4218859 37.9472002,19.6496386 C37.8165905,20.4473941 37.4436661,21.2131881 36.8284271,21.8284271 C35.26633,23.3905243 32.73367,23.3905243 31.1715729,21.8284271 C29.8093655,20.4662198 29.6350541,18.3659485 30.6486386,16.8148522 Z" class="right-eye" fill="#6F9965"></path>
                                        <path d="M18.8284271,21.8284271 C20.1906345,20.4662198 20.3649459,18.3659485 19.3513614,16.8148522 C19.2026284,16.5872449 19.0283169,16.3714627 18.8284271,16.1715729 C17.9983168,15.3414625 16.8941253,14.9524791 15.8071502,15.0046227 C14.9879477,15.0439209 14.1785241,15.3337764 13.5035752,15.8741891 C13.3888159,15.9660737 13.2779442,16.0652016 13.1715729,16.1715729 C12.2247147,17.118431 11.8517904,18.4218859 12.0527998,19.6496386 C12.1834095,20.4473941 12.5563339,21.2131881 13.1715729,21.8284271 C14.73367,23.3905243 17.26633,23.3905243 18.8284271,21.8284271 Z" class="left-eye" fill="#6F9965"></path>
                                    </svg>
                                </div>
                                <div class="label" style="transform: translateY(0px); color: rgb(171, 178, 182);">Gut</div>
                                <div class="touch-marker"></div>
                            </div>
                            <div class="rating-option" rating="5">
                                <div class="icon" style="transform: scale(0);">
                                    <svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="100%" height="100%" viewBox="0 0 50 50">
                                        <path d="M50,25 C50,38.807 38.807,50 25,50 C11.193,50 0,38.807 0,25 C0,11.193 11.193,0 25,0 C38.807,0 50,11.193 50,25" class="base" fill="#FFFFFF"></path>
                                        <path d="M25.0931396,31 C22.3332651,31 16.6788329,31 13.0207,31 C12.1907788,31 11.6218259,31.4198568 11.2822542,32.0005432 C10.9061435,32.6437133 10.8807853,33.4841868 11.3937,34.17 C14.4907,38.314 19.4277,41 24.9997,41 C30.5727,41 35.5097,38.314 38.6067,34.17 C39.0848493,33.5300155 39.0947422,32.7553501 38.7884086,32.1320187 C38.4700938,31.4843077 37.8035583,31 36.9797,31 C34.3254388,31 28.6616205,31 25.0931396,31 Z" class="mouth" fill="#6F9965"></path>
                                        <path d="M30.6486386,16.8148522 C30.7973716,16.5872449 30.9716831,16.3714627 31.1715729,16.1715729 C32.0016832,15.3414625 33.1058747,14.9524791 34.1928498,15.0046227 C35.0120523,15.0439209 35.8214759,15.3337764 36.4964248,15.8741891 C36.6111841,15.9660737 36.7220558,16.0652016 36.8284271,16.1715729 C37.7752853,17.118431 38.1482096,18.4218859 37.9472002,19.6496386 C37.8165905,20.4473941 37.4436661,21.2131881 36.8284271,21.8284271 C35.26633,23.3905243 32.73367,23.3905243 31.1715729,21.8284271 C29.8093655,20.4662198 29.6350541,18.3659485 30.6486386,16.8148522 Z" class="right-eye" fill="#6F9965"></path>
                                        <path d="M18.8284271,21.8284271 C20.1906345,20.4662198 20.3649459,18.3659485 19.3513614,16.8148522 C19.2026284,16.5872449 19.0283169,16.3714627 18.8284271,16.1715729 C17.9983168,15.3414625 16.8941253,14.9524791 15.8071502,15.0046227 C14.9879477,15.0439209 14.1785241,15.3337764 13.5035752,15.8741891 C13.3888159,15.9660737 13.2779442,16.0652016 13.1715729,16.1715729 C12.2247147,17.118431 11.8517904,18.4218859 12.0527998,19.6496386 C12.1834095,20.4473941 12.5563339,21.2131881 13.1715729,21.8284271 C14.73367,23.3905243 17.26633,23.3905243 18.8284271,21.8284271 Z" class="left-eye" fill="#6F9965"></path>
                                    </svg>
                                </div>
                                <div class="label" style="transform: translateY(9px); color: rgb(49, 59, 63);">Sehr Gut</div>
                                <div class="touch-marker"></div>
                            </div>
                            <div class="current-rating" style="transform: translateX(400%);">
                                <div class="svg-wrapper"><svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="55px" height="55px" viewBox="0 0 50 50"><path d="M50,25 C50,38.807 38.807,50 25,50 C11.193,50 0,38.807 0,25 C0,11.193 11.193,0 25,0 C38.807,0 50,11.193 50,25" class="base" fill="rgba(255,216,133,1)"></path><path d="M25.0931396,31 C22.3332651,31 16.6788329,31 13.0207,31 C12.1907788,31 11.6218259,31.4198568 11.2822542,32.0005432 C10.9061435,32.6437133 10.8807853,33.4841868 11.3937,34.17 C14.4907,38.314 19.4277,41 24.9997,41 C30.5727,41 35.5097,38.314 38.6067,34.17 C39.0848493,33.5300155 39.0947422,32.7553501 38.7884086,32.1320187 C38.4700938,31.4843077 37.8035583,31 36.9797,31 C34.3254388,31 28.6616205,31 25.0931396,31 Z" class="mouth" fill="#655F52"></path><path d="M30.6486386,16.8148522 C30.7973716,16.5872449 30.9716831,16.3714627 31.1715729,16.1715729 C32.0016832,15.3414625 33.1058747,14.9524791 34.1928498,15.0046227 C35.0120523,15.0439209 35.8214759,15.3337764 36.4964248,15.8741891 C36.6111841,15.9660737 36.7220558,16.0652016 36.8284271,16.1715729 C37.7752853,17.118431 38.1482096,18.4218859 37.9472002,19.6496386 C37.8165905,20.4473941 37.4436661,21.2131881 36.8284271,21.8284271 C35.26633,23.3905243 32.73367,23.3905243 31.1715729,21.8284271 C29.8093655,20.4662198 29.6350541,18.3659485 30.6486386,16.8148522 Z" class="right-eye" fill="#655F52"></path><path d="M18.8284271,21.8284271 C20.1906345,20.4662198 20.3649459,18.3659485 19.3513614,16.8148522 C19.2026284,16.5872449 19.0283169,16.3714627 18.8284271,16.1715729 C17.9983168,15.3414625 16.8941253,14.9524791 15.8071502,15.0046227 C14.9879477,15.0439209 14.1785241,15.3337764 13.5035752,15.8741891 C13.3888159,15.9660737 13.2779442,16.0652016 13.1715729,16.1715729 C12.2247147,17.118431 11.8517904,18.4218859 12.0527998,19.6496386 C12.1834095,20.4473941 12.5563339,21.2131881 13.1715729,21.8284271 C14.73367,23.3905243 17.26633,23.3905243 18.8284271,21.8284271 Z" class="left-eye" fill="#655F52"></path></svg></div>
                                <div class="touch-marker"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <textarea id="freitextangabe" name="text" cols="35" rows="4" placeholder="Möchten Sie uns noch etwas mitteilen?"></textarea>
                <button class="menue-btn" onclick="onRatingAbschickenClicked()" >Abschicken</button>
                <button class="menue-btn negativ" onclick="blendeRatingViewAus()">Abbrechen</button>
            </div>
        </div>
    </div>
    <style>
        .mapboxgl-popup {
            max-width: 400px;
            font: 12px/20px 'Helvetica Neue', Arial, Helvetica, sans-serif;
        }
    </style>
    <div class="mapwrapper"><div id='map'></div></div>
    <div id='interaktionDiv'>
        <div id="zielInfoDivId" class="route-info-wrapper" style="display:none;">
            <span id="zielInfoDivZielname" class="route-info-header">Sekretariat Neurochirurgie</span>
            <span id="zielInfoDivZielAdr" class="route-info-sub">Gebäude 6 | EG | Raum 123</span>
        </div>
        <div class="interact-level-wrapper">
            <div class="interact-level-push" onclick="onEtageVorschauClicked(1);">+</div>
            <div class="interact-level-current">Ebene 1</div>
            <div class="interact-level-push" onclick="onEtageVorschauClicked(-1);">-</div>
        </div>

        <div class="next-back-btnspace">
            <button id='zurueck' type="button" class="btn-left" onclick="onZurueck();"><img class="btn-img" src="design/<?php echo $piKey;?>/img/left.png"/></button>
            <button id='weiter' type="button" class="btn-right" onclick="onWeiter();"><img class="btn-img" src="design/<?php echo $piKey;?>/img/right.png"/></button>
            <button id='cancelNavigation' type="button" class="next-cancel-btn" onclick="onCancelNavigationClicked();"></button>
            <div <?php echo $isApp ? 'onclick="callNativeApp();"' : ""?>>
                <label class="custom-file-upload burger">
                    <?php echo $isApp ? "<!--" : "";?>
                    <input type="file" name="photo" id="photo2" onchange="onPhotoChanged(this);" value="" capture="camera" accept="image/*;capture=camera" onclick="blendeBurgerMenuAus();">
                    <?php echo $isApp ? "-->" : "";?>
                </label>
            </div>
        </div>
        <button id='startWaehlen' type="button" onclick="onNavigationStarten();">
            <!-- Info-Button "advance" -->
            <span class="span-inf-custom-btn" onclick="zeigeInfoboxAn(true);" style="display:none;">i</span>
            <span class="navi-start-btn-head">Navigation starten</span>
            <span class="navi-start-btn-info">1 Tg., 4 Std., 71 km</span>
        </button>

        <div class="terminal-elem terminal-qr-cnt">
            <div class="terminal-qr-txt-cnt">
                <span class="terminal-qr-txt-head">Scanne mich!</span>
                <span class="terminal-qr-txt-nrml">Scannen Sie den QR-Code mit Ihrer Handykamera, um zu Ihrem Zielort zu navigieren</span>
                <?php
                if($piKey=="HRS-vy4BL6LUR3zMbN31MX2mDNSpAeDE9e9FDGsryG22Q4WKiEeMGG" && $isTerminal){
                    echo '<span class="terminal-qr-txt-head-custom">Scannez moi!<br>Scanne mich!</span>';
                }
                ?>

            </div>
            <img id="qrImageDiv" class="terminal-qr-img" src=""/>
        </div>
        <div class="terminal-elem terminal-navi-cnt">
            <div class="terminal-navi-cnt-outer">
                <button class="terminal-navi-cnt-left navi-hide" onclick="showVorigenSchritt();">Zurück</button>
                <button class="terminal-navi-cnt-right" onclick="showNextSchritt();">Weiter</button>
                <?php
                if($piKey=="HRS-vy4BL6LUR3zMbN31MX2mDNSpAeDE9e9FDGsryG22Q4WKiEeMGG" && $isTerminal){
                    echo '<div class="terminal-beenden-wrapper"><button class="terminal-navi-cnt-right-beenden" onclick="forceIdleState();">Abbrechen</button></div>';
                }
                ?>
            </div>
        </div>

    </div>
</section>

<!--  No Javascript  -->

<style>
    .nojavascript{
        display: block !important;
        background-color: #f5f5f5;
        color: #6e6e6e;
        font-family: 'Open Sans', sans-serif !important;
        font-weight: 400;
        font-size: 48px;
        height: 100%;
        width: 100%;
        position:relative;
    }
    .nojavascript > .js-cnt{
        font-family: 'Open Sans', sans-serif !important;
        font-weight: 200;
        font-size: 36px;
        height: 50px;
        width: 100%;
        color: #6e6e6e;
        display: block;
        text-align: center;
        position:absolute;
        top:0; left:0; bottom:0; right:0;
        margin: auto;
    }
    .js-hide{display: none !important;}
    #Combined-Shape-1{
        -webkit-transition: all 0.2s ease;
        -moz-transition: all 0.2s ease;
        -ms-transition: all 0.2s ease;
        -o-transition: all 0.2s ease;
        transition: all 0.2s ease;
        -webkit-transform: all 0.2s ease;
        -moz-transform: all 0.2s ease;
        -ms-transform: all 0.2s ease;
        -o-transform: all 0.2s ease;
        transform: all 0.2s ease;
        opacity: 0.3;
        color: #ddd;
    }
    @media screen (orientation:landscape){}
</style>
<script>
    $('#header-carousel').on('slid.bs.carousel',function(){$('.title').css({ 'top': 50+'px', 'opacity': 1 });$('.text').css({ 'opacity': 1 });$('.more').css({ 'opacity': 1, 'bottom': 50+'px' });});
    $('#header-carousel').on('slide.bs.carousel',function(){$('.title').css({ 'top': 0+'px', 'opacity': 0 });$('.text').css({ 'opacity': 0 });$('.more').css({ 'opacity': 0, 'bottom': 0+'px' });});
    $(document).ready(function(){
        onDoumentReadyFunction();
        <?php
        if($isTerminal){
            //echo "// Virtual Keyboard";
            //echo "// https://franciscohodge.com/projects/simple-keyboard/documentation/";
            echo 'keyboardInput = document.getElementById("myInput");';
            echo 'keyboardPushNormal = document.getElementById("keyboard-push-normal");';
            echo 'keyboardPushWheelchair = document.getElementById("keyboard-push-wheelchair");';
            echo 'terminalSuche = document.getElementById("terminalsuche");';
            echo 'terminalSearchbar = document.getElementById("terminal-searchbar");';
            echo 'terminalSearchHideButton = document.getElementById("terminalSearchHideButton");';
            echo 'var Keyboard = window.SimpleKeyboard.default;';
            echo 'virtualKeyboard = new Keyboard({';
            echo 'onChange: input => onVirtualKeyboardChange(input),';
            echo 'onKeyPress: button => onVirtualKeyboardKeyPress(button),';
            echo 'layout: {';
            echo '	"default": [';
            echo '		"1 2 3 4 5 6 7 8 9 0 {bksp}",';
            echo '		"Q W E R T Z U I O P Ü \/",';
            echo '		"A S D F G H J K L Ö Ä {enter}",';
            echo '		"Y X C V B N M - .",';
            echo '		"{space}"';
            echo '	]';
            echo '}';
            echo '});';
        }
        ?>
    });
</script>
</body>
<?php getCustomJS($piKey); ?>

<script>
    /*document.getElementById("searchlist-header").addEventListener("touchstart", tapHandler);
    var tapedTwice = false;
    function tapHandler(event) {
        if(!tapedTwice) {
            tapedTwice = true;
            setTimeout( function() { tapedTwice = false; }, 300);

            LOG && console.log("Doubletap");
            // Verhindern, dass das Event ausgeführt wird (in der Theorie)
            event.preventDefault();
            resetZoom();

            return false;
        }
    }

    document.getElementById("searchlist-header").addEventListener('dblclick', function (e) {
        resetZoom();
    });

    function resetZoom(){
        document.body.style.zoom = 1.0;
        var scale = 'scale(1)';
        document.body.style.webkitTransform =  scale;    // Chrome, Opera, Safari
        document.body.style.msTransform =   scale;       // IE 9
        document.body.style.transform = scale;     // General
        //alert("Reset zoom?");
    }*/

    /*var zoomCount = 0;
    (function(){
        // Poll the pixel width of the window; invoke zoom listeners
        // if the width has been changed.
        var lastWidth = 0;
        function pollZoomFireEvent() {
            var widthNow = jQuery(window).width();
            if (lastWidth == widthNow) return;
            lastWidth = widthNow;
            if(zoomCount > 0){
                resetZoom();
            }
            zoomCount++;
        }
        setInterval(pollZoomFireEvent, 500);
    })();*/
</script>
</html> 