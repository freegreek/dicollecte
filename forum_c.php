<?php

require('./config/config.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-users.class.php');
require('./code/dbaccess-forum.class.php');
require('./html/' . $_GET['prj'] . '/project_vars.php');

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
    header(URL_HEADER . 'forum.php?prj=' . $_GET['prj']);
    exit;
}

$dbaForum = new dbaccessForum($db);

switch ($_REQUEST['cmd']) {
    case 'newthread':
        list($ok, $id_thread) = $dbaForum->createThread($_GET['prj'], $_POST['id_forum'], $id_user, $_POST['subject']);
        if (!$ok) {
            setSysMsg($id_thread);
            header(URL_HEADER . 'forum.php?prj=' . $_GET['prj'] . '&f=' . $_POST['id_forum']);
            exit;    
        }
        require('./code/dbaccess-messages.class.php');
        $dbaMessages = new dbaccessMessages($db);
        list($ok, $msgcode) = $dbaMessages->newMessage($_GET['prj'], $id_user, $id_thread, $_POST['msg']);
        if (!$ok) setSysMsg($msgcode);
        header(URL_HEADER . 'thread.php?prj=' . $_GET['prj'] . '&t=' . $id_thread);
        exit;
}

if ($rank > 2) {
    setSysMsg('_noadmin');
    header(URL_HEADER . 'forum.php?prj=' . $_GET['prj']);
    exit;
}

switch ($_REQUEST['cmd']) {
    case 'newforum':
        // create a new forum
        list($ok, $msgcode) = $dbaForum->createForum($_GET['prj'], $_POST['name'], $_POST['descr']);
        if (!$ok) setSysMsg($msgcode);
        header(URL_HEADER . 'forum.php?prj=' . $_GET['prj']);
        break;
    case 'delforum':
        // delete a forum
        list($ok, $msgcode) = $dbaForum->deleteForum($_GET['prj'], $_GET['id_forum']);
        setSysMsg($msgcode);
        header(URL_HEADER . 'forum.php?prj=' . $_GET['prj']);
        break;
    case 'renforum':
        // rename a forum
        list($ok, $msgcode) = $dbaForum->renameForum($_GET['prj'], $_POST['id_forum'], $_POST['name'], $_POST['descr']);
        if (!$ok) setSysMsg($msgcode);
        header(URL_HEADER . 'forum.php?prj=' . $_GET['prj'] . '&f=' . $_POST['id_forum']);
        break;
    default:
        setSysMsg($_POST['cmd']);
        header(URL_HEADER . 'forum.php?prj=' . $_GET['prj']);
        break;
}

?>
