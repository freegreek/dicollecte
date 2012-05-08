<?php

require('./config/config.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-users.class.php');
require('./code/misc.php');

if (isset($_COOKIE['login'])) {
    header(URL_HEADER . 'logout_c.php?prj=' . $_GET['prj']);
    exit;
}

// spambot ?
$now = time();
if (!isset($_SESSION['nospambot_time']) or ($now - $_SESSION['nospambot_time']) < 6) {
    // it is assumed that if the fields are filled too quickly (less than 6 sec.), this is a spambot
    $_SESSION['nospambot_errors'] = (isset($_SESSION['nospambot_errors'])) ? ++$_SESSION['nospambot_errors'] : 0;
    if ($_SESSION['nospambot_errors'] >= 3) {
        // no message, no information
        header(URL_HEADER);
    }
    else {
        setSysMsg('_newusererror');
        header(URL_HEADER . 'inscription.php?prj=' . $_GET['prj']);
    }
    exit;
}

// not a spambot
$login = trim($_POST['login']);
$email = trim($_POST['email']);
$name = trim($_POST['name']);
$name = ($name != '') ? $name : '[?]';

// login checking
if ($login == '') {
    setSysMsg('_emptylogin');
    header(URL_HEADER . 'inscription.php?prj=' . $_GET['prj']);
    exit;
}
if (!preg_match('`^[a-zA-Z][a-zA-Z0-9_]+$`', $login)) {
    setSysMsg('_wronglogin');
    header(URL_HEADER . 'inscription.php?prj=' . $_GET['prj']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    setSysMsg('_invalidemail');
    header(URL_HEADER . 'inscription.php?prj=' . $_GET['prj']);
    exit;
}
if ($_POST['iAgree'] != 'ON') {
    setSysMsg('_licenseagreement');
    header(URL_HEADER . 'inscription.php?prj=' . $_GET['prj']);
    exit;
}

// action
$db = new Database();
if (!$db->connx) {
    setSysMsg('_nobase');
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

$pw = generatePassword(8);

$dbaUsers = new dbaccessUsers($db);
list($ok, $msgcode) = $dbaUsers->createUser($login, $name, $pw, $email, $_GET['prj']);
if ($ok) {
    setSysMsg('inscrok');
    header(URL_HEADER . 'dictionary.php?prj=' . $_GET['prj']);
}
else {
    setSysMsg($msgcode);
    header(URL_HEADER . 'inscription.php?prj=' . $_GET['prj']);
}

?>
