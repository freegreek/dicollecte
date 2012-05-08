<?php

require('./config/config.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-users.class.php');

$db = new Database();
if (!$db->connx) {
    setSysMsg('_nobase');
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

$dbaUsers = new dbaccessUsers($db);
list($id_user, $rank, $error) = $dbaUsers->connectUser($_COOKIE['login'], $_COOKIE['pw'], $_GET['prj']);
if ($id_user == -1) {
    setSysMsg($error);
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

switch($_POST['cmd']) {
    case 'update':
        if ($_POST['pw1'] != $_POST['pw2']) {
            setSysMsg('_notidpw');
            header(URL_HEADER . 'useraccount.php?prj=' . $_GET['prj']);
            exit;
        }
        
        $name = trim($_POST['name']);
        $name = ($name != '') ? $name : '[?]';
        
        $email = trim($_POST['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setSysMsg('_invalidemail');
            header(URL_HEADER . 'useraccount.php?prj=' . $_GET['prj']);
            exit;
        }
        
        $doNotify = (isset($_POST['emailnotif']) and $_POST['emailnotif'] == 'ON') ? TRUE : FALSE;
        $doNotifyAuto = (isset($_POST['enotifauto']) and $_POST['enotifauto'] == 'ON') ? TRUE : FALSE;
        $_SESSION['enotifauto'] = $doNotifyAuto;
        
        list($ok, $msgcode) = $dbaUsers->updateUser($id_user, $name, $email, $_POST['pw1'], $doNotify, $doNotifyAuto, $_GET['prj']);
        setSysMsg($msgcode);
        header(URL_HEADER . 'useraccount.php?prj=' . $_GET['prj']);
        break;
        
    case 'forum':
        // register to bbPress
        //require('./_forum/register.php');
        break;
        
    default:
        header(URL_HEADER . 'useraccount.php?prj=' . $_GET['prj']);
}

?>
