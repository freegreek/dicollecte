<?php

require('./config/config.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-thesaurus.class.php');
require('./code/dbaccess-users.class.php');
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
    header(URL_HEADER . 'thesaurus.php?prj=' . $_GET['prj']);
    exit;
}
if (!($rank <= 5 or ($rank <= 7 and $project['thesAllUsersAllowed']))) {
    setSysMsg('_noaccess');
    header(URL_HEADER . 'thesaurus.php?prj=' . $_GET['prj']);
    exit;
}

$dbaThesaurus = new dbaccessThesaurus($db);

switch ($_REQUEST['cmd']) {
    case 'new':
        list($ok, $res) = $dbaThesaurus->newEntry($_GET['prj'], $_GET['word'], '(?)|?', $id_user, $_COOKIE['login']);
        if ($ok) {
            header(URL_HEADER . 'synonyms.php?prj=' . $_GET['prj'] . '&id_word=' . $res);
        }
        else {
            setSysMsg($res);
            header(URL_HEADER . 'thesaurus.php?prj=' . $_GET['prj']);
        }
        break;
    case 'update':
        // synonyms formatting
        $syn = '';
        for ($i=1; $i <=100; $i++) {
            $syn = $dbaThesaurus->generateSyn($syn, $_POST['gramm'.$i], $_POST['meaning'.$i], $_POST['synonyms'.$i]);
        }
        if ($syn == '') {
            setSysMsg('_emptyfields');
            header(URL_HEADER . 'thesaurus.php?prj=' . $_GET['prj']);
            exit;
        }
        // database update
        list($ok, $res) = $dbaThesaurus->updateEntry($_GET['prj'], $_POST['id_word'], $syn, $id_user);
        if ($ok) {
            setSysMsg('entryupdated');
            header(URL_HEADER . 'synonyms.php?prj=' . $_GET['prj'] . '&id_word=' . $_POST['id_word']);
        }
        else {
            setSysMsg($res);
            header(URL_HEADER . 'thesaurus.php?prj=' . $_GET['prj'] . '&search=' . $_POST['word']);
        }
        break;
    case 'erase':
        if ($rank > 3) {
            setSysMsg('_noaccess');
            header(URL_HEADER . 'synonyms.php?prj=' . $_GET['prj'] . '&id_word=' . $_GET['id_word']);
            exit;
        }
        list($ok, $res) = $dbaThesaurus->eraseEntry($_GET['prj'], $_GET['id_word'], $id_user);
        if ($ok) {
            setSysMsg($res);
            header(URL_HEADER . 'thesaurus.php?prj=' . $_GET['prj']);
        }
        else {
            setSysMsg($res);
            header(URL_HEADER . 'synonyms.php?prj=' . $_GET['prj'] . '&id_word=' . $_GET['id_word']);
        }
        break;
    case 'deletehist':
        if ($rank > 3) {
            setSysMsg('_noaccess');
        }
        else {
            list($ok, $res) = $dbaThesaurus->deleteHistEntry($_GET['prj'], $_GET['id_hist'], $id_user);
            setSysMsg($res);
        }
        header(URL_HEADER . 'synonyms.php?prj=' . $_GET['prj'] . '&id_word=' . $_GET['from']);
        break;
    case 'restorehist':
        list($ok, $res) = $dbaThesaurus->restoreHistEntry($_GET['prj'], $_GET['id_hist'], $id_user);
        setSysMsg($res);
        header(URL_HEADER . 'synonyms.php?prj=' . $_GET['prj'] . '&id_word=' . $_GET['from']);
        break;
    default:
        setSysMsg($cmd);
        header(URL_HEADER . 'thesaurus.php?prj=' . $_GET['prj']);
        break;
}

?>
