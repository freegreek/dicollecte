<?php

require('./config/config.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-users.class.php');
require('./code/dbaccess-log.class.php');

$db = new Database();
if (!$db->connx) {
    setSysMsg('_nobase');
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

$dbaLog = new dbaccessLog($db);

$dbaUsers = new dbaccessUsers($db);
list($id_user, $rank, $error) = $dbaUsers->connectUser($_COOKIE['login'], $_COOKIE['pw'], $_GET['prj'], TRUE);
if ($id_user == -1) {
    setSysMsg($error);
    header(URL_HEADER . 'log.php?prj=' . $_GET['prj']);
    exit;
}
if ($rank > 2) {
    setSysMsg('_noaccess');
    header(URL_HEADER . 'log.php?prj=' . $_GET['prj']);
    exit;
}

switch ($_REQUEST['cmd']) {
    case 'newannounce':
        list($ok, $msgcode) = $dbaLog->createAnnounce($_GET['prj'], $_POST['announce'], $id_user);
        setSysMsg($msgcode);
        header(URL_HEADER . 'log.php?cat=A&prj=' . $_GET['prj']);
        break;
    case 'editannounce' :
        list($ok, $msgcode) = $dbaLog->editAnnounce($_GET['prj'], $_POST['id_log'], $_POST['newannounce']);
        setSysMsg($msgcode);
        header(URL_HEADER . 'log.php?cat=A&prj=' . $_GET['prj']);
        break;
    case 'deleteannounce' :
        if (!(isset($_GET['id_log']) and preg_match('`^[0-9]+$`', $_GET['id_log']))) {
            setSysMsg('_wrongurl');
            header(URL_HEADER . 'log.php?cat=A&prj=' . $_GET['prj']);
            exit;
        }
        list($ok, $msgcode) = $dbaLog->deleteAnnounce($_GET['prj'], $_GET['id_log']);
        setSysMsg($msgcode);
        header(URL_HEADER . 'log.php?cat=A&prj=' . $_GET['prj']);
        break;
    default:
        header(URL_HEADER . 'log.php?cat=A&prj=' . $_GET['prj']);
}

?>
