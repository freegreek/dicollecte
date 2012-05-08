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

if (isset($_POST['login'])) {
    $dbaUsers = new dbaccessUsers($db);
    switch ($_POST['cmd']) {
        case 'connx':
            // identification with the tiny form
            list($id_user, $rank, $error) = $dbaUsers->connectUser($_POST['login'], md5($_POST['pw']), $_GET['prj']);
            if ($id_user != -1) {
                $dbaUsers->setCookies($_POST['login'], $_POST['pw']);
                switch ($rank) {
                    case 5: setSysMsg('idctrl'); break;
                    case 3: setSysMsg('idedit'); break;
                    case 2:
                    case 1:
                    case 0: setSysMsg('idadmin'); break;
                    default: setSysMsg('idok'); break;
                }
            }
            else {
                setSysMsg($error);
            }
            break;
        case 'pwrecup':
            list($ok, $msgcode) = $dbaUsers->askPasswordReinit($_POST['login'], $_GET['prj']);
            setSysMsg($msgcode);
            break;
    }
}

// redirection
if (isset($_SERVER['HTTP_REFERER'])) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}
header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);

?>
