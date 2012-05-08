<?php

require('./config/config.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-flags.class.php');
require('./code/dbaccess-users.class.php');

$db = new Database();
if (!$db->connx) {
    setSysMsg('_nobase');
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

$dbaUsers = new dbaccessUsers($db);
list($id_user, $rank, $error) = $dbaUsers->connectUser($_COOKIE['login'], $_COOKIE['pw'], $_GET['prj'], TRUE);
if ($id_user == -1) {
    setSysMsg($error);
    header(URL_HEADER . 'affixes.php?prj=' . $_GET['prj']);
    exit;
}
if ($rank > 3) {
    setSysMsg('_noaccess');
    header(URL_HEADER . 'affixes.php?prj=' . $_GET['prj']);
    exit;
}

$dbaFlags = new dbaccessFlags($db);

switch ($_GET['cmd']) {
    case 'newaffixes':
        require('./config/db_vars.php');
        list($ok, $flag) = $dbaFlags->newAffixes($_GET['prj'], $_POST['flagaffixes'], $id_user);
        if ($ok) {
            setSysMsg('newflag');
            header(URL_HEADER . 'affixes.php?prj=' . $_GET['prj'] . '&flag=' . rawurlencode($flag));
        }
        else {
            setSysMsg($flag);
            header(URL_HEADER . 'affixes.php?prj=' . $_GET['prj']);
        }
        break;
    case 'editflag':
        require('./config/db_vars.php');
        list($ok, $flag) = $dbaFlags->editFlag($_GET['prj'], $_POST['flagaffixes'], $id_user);
        if ($ok) {
            setSysMsg('flagsaved');
            header(URL_HEADER . 'affixes.php?prj=' . $_GET['prj'] . '&flag=' . rawurlencode($flag));
        }
        else {
            setSysMsg($flag);
            header(URL_HEADER . 'affixes.php?prj=' . $_GET['prj']);
        }
        break;
    case 'eraseflag':
        $flag = rawurldecode($_GET['flag']);
        list($ok, $msgcode) = $dbaFlags->eraseFlag($_GET['prj'], $flag, $id_user);
        if ($ok) {
            setSysMsg($msgcode);
            header(URL_HEADER . 'affixes.php?prj=' . $_GET['prj']);
        }
        else {
            setSysMsg($msgcode);
            header(URL_HEADER . 'affixes.php?prj=' . $_GET['prj'] . '&flag=' . rawurlencode(stripslashes($flag)));
        }
        break;
    default:
        header(URL_HEADER . 'affixes.php?prj=' . $_GET['prj']);
        exit; 
}

?>
