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
if ($rank > 2 or ($_POST['user_rank'] == 1 and $rank > 1)) {
    setSysMsg('_noaccess');
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

list($ok, $msgcode) = $dbaUsers->setRank($_POST['id_user'], $_GET['prj'], $_POST['user_rank']);
setSysMsg($msgcode);
header(URL_HEADER . 'member.php?prj=' . $_GET['prj'] . '&id_user=' . $_POST['id_user']);

?>
