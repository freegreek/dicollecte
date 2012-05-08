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
list($id_user, $rank, $error) = $dbaUsers->connectUser($_COOKIE['login'], $_COOKIE['pw'], $_GET['prj'], FALSE);
if ($id_user == -1) {
    setSysMsg($error);
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

require('./code/dbaccess-synsets.class.php');
$dbaSyn = new dbaccessSynsets($db);

switch ($_REQUEST['cmd']) {
    case 'editsyn':
        // AJAX
        list($ok, $result) = $dbaSyn->updateSynset($_GET['prj'], $_POST['id_synset'], $_POST['synset'], $_POST['pos'], $_POST['tags'], $id_user);
        echo $result;
        exit;
    case 'newsyn':
        // AJAX
        list($ok, $result) = $dbaSyn->createSynset($_GET['prj'], $_POST['newsynset'], $_POST['newpos'], $_POST['newtags'], $id_user);
        echo $result;
        exit;
    case 'delsyn':
        // AJAX
        list($ok, $result) = $dbaSyn->deleteSynset($_GET['prj'], $_GET['id_synset'], $id_user);
        echo $result;
        exit;
    case 'undelete':
        // AJAX
        list($ok, $result) = $dbaSyn->undeleteSynset($_GET['prj'], $_GET['id_synset'], $id_user);
        echo $result;
        exit;
    case 'restorehist':
        // AJAX
        list($ok, $result) = $dbaSyn->restoreHist($_GET['prj'], $_GET['id_hist'], $id_user);
        echo $result;
        exit;
}

if ($rank > 3) {
    setSysMsg('_noaccess');
    header(URL_HEADER . 'synsets.php?prj=' . $_GET['prj']);
    exit;
}

switch ($_REQUEST['cmd']) {
    case 'erasehist':
        // AJAX
        list($ok, $result) = $dbaSyn->eraseHist($_GET['prj'], $_GET['id_hist'], $id_user);
        echo $result;
        exit;
    case 'erasesyn':
        list($ok, $result) = $dbaSyn->eraseSynset($_GET['prj'], $_GET['id_synset'], $id_user);
        setSysMsg($result);
        header(URL_HEADER . 'synsets.php?prj=' . $_GET['prj']);
        exit;
}

echo '_unknown_command';

?>
