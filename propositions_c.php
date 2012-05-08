<?php

require('./config/config.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-propositions.class.php');
require('./code/dbaccess-users.class.php');
$ui = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);

$db = new Database();
if (!$db->connx) {
    setSysMsg('_nobase');
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

$dbaUsers = new dbaccessUsers($db);
$dbaPropositions = new dbaccessPropositions($db);

// user checking
list($id_user, $rank, $error) = $dbaUsers->connectUser($_COOKIE['login'], $_COOKIE['pw'], $_GET['prj']);
if ($id_user == -1) {
    setSysMsg($error);
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}
if ($rank > 2) {
    setSysMsg('_noaccess');
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

switch ($_GET['cmd']) {
    case 'erasecomments':
        list($ok, $msgcode) = $dbaPropositions->emptyBasket($_GET['prj'], (int) $_GET['nbcom'], $id_user, $ui['logMsg']['emptyBasket']);
        setSysMsg($msgcode);
        header(URL_HEADER . 'propositions.php?prj=' . $_GET['prj'] . '&tab=T');
        break;
    case 'validall' :
        $selectedUser = (isset($_GET['id_user'])) ? $_GET['id_user'] : NULL;
        list($ok, $msgcode) = $dbaPropositions->integrateAllValidPropositions($_GET['prj'], $id_user, $ui['logMsg']['validEntriesIntegrated'], $selectedUser);
        setSysMsg($msgcode);
        if (!$selectedUser) {
            header(URL_HEADER . 'propositions.php?prj=' . $_GET['prj'] . '&tab=T');
            exit;
        }
        header(URL_HEADER . 'member.php?prj=' . $_GET['prj'] . '&tab=T' . '&id_user=' . $_GET['id_user']);
        break;
    default :
        setSysMsg($_GET['cmd']);
        header(URL_HEADER . 'propositions.php?prj=' . $_GET['prj'] . '&tab=E');
        break;
}

?>
