<?php

require('./config/config.php');
require('./config/img_vars.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-comments.class.php');
require('./code/dbaccess-propositions.class.php');
require('./code/dbaccess-users.class.php');
require('./html/' . $_GET['prj'] . '/project_vars.php');
$ui = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);

$db = new Database();
if (!$db->connx) {
    setSysMsg('_nobase');
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

$dbaUsers = new dbaccessUsers($db);
$dbaPropositions = new dbaccessPropositions($db);
$dbaComments = new dbaccessComments($db);

// user checking
list($id_user, $rank, $error) = $dbaUsers->connectUser($_COOKIE['login'], $_COOKIE['pw'], $_GET['prj'], TRUE);
if ($id_user == -1) {
    setSysMsg($error);
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

$id_prop = $_REQUEST['id_prop'];

switch ($_REQUEST['cmd']) {
    case 'insertcomment':
        list($ok, $info) = $dbaComments->insertComment($_GET['prj'], $id_user, $_COOKIE['login'], $_POST['id_prop'], $_POST['comment']);
        if (!$ok) {
            setSysMsg($info);
        }
        header(URL_HEADER . 'proposition.php?prj=' . $_GET['prj'] . '&id=' . $id_prop);
        exit;
    case 'editcomment':
        list($ok, $info) = $dbaComments->editComment($_GET['prj'], $_POST['id_com'], $_POST['newcomment']);
        if (!$ok) {
            setSysMsg($info);
        }
        header(URL_HEADER . 'proposition.php?prj=' . $_GET['prj'] . '&id=' . $id_prop);
        exit;
    case 'deletecomment':
        // AJAX
        list($ok, $info) = $dbaComments->deleteComment($_GET['prj'], $_GET['id_com']);
        echo $info;
        exit;
    case 'setpriority':
        // AJAX
        list($ok, $info) = $dbaPropositions->setPropositionPriority($_GET['prj'], $_GET['id_prop'], $_GET['nbp']);
        echo $info;
        exit;
    case 'subscribe':
        // AJAX
        list($ok, $info) = $dbaPropositions->subscribe($_GET['prj'], $_GET['id_prop'], $id_user);
        echo $info;
        exit;
    case 'unsubscribe':
        // AJAX
        list($ok, $info) = $dbaPropositions->unsubscribe($_GET['prj'], $_GET['id_prop'], $id_user);
        echo $info;
        exit;
    case 'update':
        // proposition update
        require('./code/entry.class.php');
        $doUnvalidate = ($rank > 3 or isset($_POST['unvalidate']));
        $entry = new Entry($_POST);
        list($ok, $msgcode) = $dbaPropositions->updateProposition($_GET['prj'], $dbaComments, $id_user, $id_prop, $entry, $doUnvalidate);
        setSysMsg($msgcode);
        header(URL_HEADER . 'proposition.php?prj=' . $_GET['prj'] . '&id=' . $id_prop);
        exit;
}

if ($rank > 5) {
    setSysMsg('_noaccess');
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

switch ($_REQUEST['cmd']) {
    case 'eval':
        // proposition evaluation
        list($ok, $msgcode) = $dbaPropositions->setPropositionValue($_GET['prj'], $dbaComments, $id_user, $id_prop, $_GET['value']);
        if (!$ok) {
            setSysMsg($msgcode);
        }
        break;
    case 'forbid':
        // definitely rejected proposition
        list($ok, $msgcode) = $dbaPropositions->moveProposition($_GET['prj'], $dbaComments, $id_user, $id_prop, 'reject');
        setSysMsg($msgcode);
        break;
    case 'reeval':
        // reevalutation
        list($ok, $msgcode) = $dbaPropositions->moveProposition($_GET['prj'], $dbaComments, $id_user, $id_prop, 'eval');
        setSysMsg($msgcode);
        break;
    case 'trash':
        // suggestion to the basket
        list($ok, $msgcode) = $dbaPropositions->moveProposition($_GET['prj'], $dbaComments, $id_user, $id_prop, 'trash');
        setSysMsg($msgcode);
        break;
    case 'erase':
        // delete the suggestion from the db
        list($ok, $msgcode, $timestamp) = $dbaPropositions->eraseProposition($_GET['prj'], $id_prop);
        setSysMsg($msgcode);
        if ($ok) {
            header(URL_HEADER . 'proposition.php?prj=' . $_GET['prj'] . '&cmd=next&tab=T&id=' . $timestamp);
            exit;
        }
        break;
    case 'changeaction':
        // change the action type of this suggestion
        list($ok, $msgcode) = $dbaPropositions->changeAction($_GET['prj'], $dbaComments, $id_user, $id_prop);
        setSysMsg($msgcode);
        break;
    case 'apply':
        // apply proposition
        $doValidate = (isset($_GET['cmd2'])) ? TRUE : FALSE;
        if ($rank <= 3 or $rank <= $project['dictDirectEdition']) {
            list($ok, $msgcode) = $dbaPropositions->applyProposition($_GET['prj'], $dbaComments, $id_user, $id_prop, $doValidate);
            setSysMsg($msgcode);
        }
        else {
            setSysMsg('_noaccess');
        }
        break;
    default:
        setSysMsg($_REQUEST['cmd']);
        break;
}

header(URL_HEADER . 'proposition.php?prj=' . $_GET['prj'] . '&id=' . $id_prop);

?>
