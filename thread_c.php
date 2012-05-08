<?php

require('./config/config.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-users.class.php');
require('./code/dbaccess-messages.class.php');
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

$dbaMessages = new dbaccessMessages($db);

switch ($_REQUEST['cmd']) {
    case 'subscribe':
        // AJAX
        list($ok, $info) = $dbaMessages->subscribe($_GET['prj'], $_GET['id_thread'], $id_user);
        echo $info;
        exit;
    case 'unsubscribe':
        // AJAX
        list($ok, $info) = $dbaMessages->unsubscribe($_GET['prj'], $_GET['id_thread'], $id_user);
        echo $info;
        exit;
    case 'newmsg':
        list($ok, $msgnum) = $dbaMessages->newMessage($_GET['prj'], $id_user, $_POST['id_thread'], $_POST['msg']);
        if (!$ok) { setSysMsg($msgnum); }
        header(URL_HEADER . 'thread.php?prj=' . $_GET['prj'] . '&t=' . $_POST['id_thread'] . '#msg' . $msgnum);
        exit;
    case 'editmsg':
        list($ok, $msgnum) = $dbaMessages->editMessage($_GET['prj'], $_POST['id_msg'], $_POST['msg']);
        if (!$ok) {
            setSysMsg($msgnum);
            header(URL_HEADER . 'thread.php?prj=' . $_GET['prj'] . '&t=' . $_POST['id_thread']);
            exit;
        }
        header(URL_HEADER . 'thread.php?prj=' . $_GET['prj'] . '&t=' . $_POST['id_thread'] . '#msg' . $msgnum);
        exit;
    case 'delmsg':
        // AJAX
        list($ok, $msgcode) = $dbaMessages->deleteMessage($_GET['prj'], $_GET['id_msg']);
        if ($ok) {
            require('./code/dbaccess-forum.class.php');
            $dbaForum = new dbaccessForum($db);
            list($ok, $id_forum) = $dbaForum->reInitThread($_GET['prj'], $_GET['t']);
            if ($ok) {
                list($ok, $msgcode) = $dbaForum->reInitForum($_GET['prj'], $id_forum);
                echo $msgcode;
            }
            else {
                echo $id_forum;
            }
        }
        else {
            echo $msgcode;
        }
        exit;
    case 'renthread':
        require('./code/dbaccess-forum.class.php');
        $dbaForum = new dbaccessForum($db);
        list($ok, $id_forum) = $dbaForum->renameThread($_GET['prj'], $_POST['id_thread'], $_POST['label']);
        if (!$ok) { setSysMsg($msgcode); }
        header(URL_HEADER . 'thread.php?prj=' . $_GET['prj'] . '&t=' . $_POST['id_thread']);
        exit;
    case 'settag':
        require('./code/dbaccess-forum.class.php');
        $dbaForum = new dbaccessForum($db);
        list($ok, $msgcode) = $dbaForum->setTag($_GET['prj'], $_GET['t'], $_GET['tag']);
        setSysMsg($msgcode);
        header(URL_HEADER . 'thread.php?prj=' . $_GET['prj'] . '&t=' . $_GET['t']);
        exit;
}

if ($rank > 2) {
    setSysMsg('_noadmin');
    header(URL_HEADER . 'forum.php?prj=' . $_GET['prj']);
    exit;
}

require('./code/dbaccess-forum.class.php');
$dbaForum = new dbaccessForum($db);
        
switch ($_REQUEST['cmd']) {
    // administration
    case 'setflowtag':
        list($ok, $msgcode) = $dbaForum->setFlowTag($_GET['prj'], $_GET['t'], $_GET['tag']);
        setSysMsg($msgcode);
        header(URL_HEADER . 'thread.php?prj=' . $_GET['prj'] . '&t=' . $_GET['t']);
        break;
    case 'switchlock':
        list($ok, $msgcode) = $dbaForum->switchThreadLock($_GET['prj'], $_GET['t']);
        setSysMsg($msgcode);
        header(URL_HEADER . 'thread.php?prj=' . $_GET['prj'] . '&t=' . $_GET['t']);
        break;
    case 'switchsolved':
        list($ok, $msgcode) = $dbaForum->switchThreadSolved($_GET['prj'], $_GET['t']);
        setSysMsg($msgcode);
        header(URL_HEADER . 'thread.php?prj=' . $_GET['prj'] . '&t=' . $_GET['t']);
        break;
    case 'movethread':
        list($ok, $msgcode) = $dbaForum->moveThread($_GET['prj'], $_GET['t'], $_GET['f']);
        setSysMsg($msgcode);
        header(URL_HEADER . 'thread.php?prj=' . $_GET['prj'] . '&t=' . $_GET['t']);
        break;
    case 'copythread':
        list($ok, $msgcode) = $dbaForum->copyThread($_GET['prj'], $_GET['t']);
        setSysMsg($msgcode);
        header(URL_HEADER . 'thread.php?prj=' . $_GET['prj'] . '&t=' . $_GET['t']);
        break;
    case 'cutthread':
        list($ok, $msgcode) = $dbaForum->cutThread($_GET['prj'], $_GET['t'], $_GET['m']);
        setSysMsg($msgcode);
        header(URL_HEADER . 'thread.php?prj=' . $_GET['prj'] . '&t=' . $_GET['t']);
        break;
    case 'jointhread':
        list($ok, $msgcode) = $dbaForum->joinThread($_GET['prj'], $_GET['t'], $_GET['t2']);
        setSysMsg($msgcode);
        if ($ok) {
            header(URL_HEADER . 'thread.php?prj=' . $_GET['prj'] . '&t=' . $_GET['t2']);
        }
        else {
            header(URL_HEADER . 'thread.php?prj=' . $_GET['prj'] . '&t=' . $_GET['t']);
        }
        break;
    case 'delthread':
        list($ok, $id_forum) = $dbaForum->eraseThread($_GET['prj'], $_GET['t']);
        if (!$ok) {
            setSysMsg($id_forum);
            header(URL_HEADER . 'thread.php?prj=' . $_GET['prj'] . '&t=' . $_GET['t']);
            exit;
        }
        header(URL_HEADER . 'forum.php?prj=' . $_GET['prj'] . '&f=' . $id_forum);
        break;
    case 'changeuser':
        // AJAX
        list($ok, $login) = $dbaMessages->changeUserMessage($_GET['prj'], $_GET['id_msg'], $_GET['id_user']);
        echo $login;
        exit;
    default:
        setSysMsg($_REQUEST['cmd']);
        header(URL_HEADER . 'forum.php?prj=' . $_GET['prj']);
        break;
}

?>
