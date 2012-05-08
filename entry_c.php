<?php

require('./config/config.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-users.class.php');
require('./html/' . $_GET['prj'] . '/project_vars.php');

$db = new Database();
if (!$db->connx) {
    setSysMsg('_nobase');
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

$id_entry = $_REQUEST['id_entry'];

$dbaUsers = new dbaccessUsers($db);
list($id_user, $rank, $error) = $dbaUsers->connectUser($_COOKIE['login'], $_COOKIE['pw'], $_GET['prj'], TRUE);
if ($id_user == -1) {
    setSysMsg($error);
    header(URL_HEADER . 'entry.php?prj=' . $_GET['prj'] . '&id=' . $id_entry);
    exit;
}

switch ($_REQUEST['cmd']) {
    case 'update':
        require('./code/dbaccess-dictionary.class.php');
        require('./code/entry.class.php');
        $dbaDictionary = new dbaccessDictionary($db);
        $entry = new Entry($_POST);
        if (!isset($_POST['editindict'])) {
            // suggest modifications
            require('./code/dbaccess-comments.class.php');
            $dbaComments = new dbaccessComments($db);
            list($ok, $msgcode, $id_prop) = $dbaDictionary->createPropModifyEntry($_GET['prj'], $dbaComments, $id_user, $id_entry, $entry, $_POST['comment']);
            if ($ok) {
                setSysMsg($msgcode);
                header(URL_HEADER . 'proposition.php?prj=' . $_GET['prj'] . '&id=' . $id_prop);
            }
            else {
                setSysMsg($msgcode);
                header(URL_HEADER . 'entry.php?prj=' . $_GET['prj'] . '&id=' . $id_entry);
            }
        }
        else {
            // entry update
            if ($rank <= 3 or $rank <= $project['dictDirectEdition'] or ($project['restrictedEdit'] and !$data['closed'])) {
                list($ok, $msgcode) = $dbaDictionary->updateEntry($_GET['prj'], $id_entry, $id_user, $entry);
                setSysMsg($msgcode);
                header(URL_HEADER . 'entry.php?prj=' . $_GET['prj'] . '&id=' . $id_entry);
            }
            else {
                setSysMsg('_noaccess');
                header(URL_HEADER . 'entry.php?prj=' . $_GET['prj'] . '&id=' . $id_entry);
            }
            break;
        }
        break;
    case 'propdel':
        // suggest deletion of an entry
        require('./code/dbaccess-dictionary.class.php');
        $dbaDictionary = new dbaccessDictionary($db);
        list($ok, $msgcode, $id_prop) = $dbaDictionary->createPropDeleteEntry($_GET['prj'], $id_user, $id_entry);
        if ($ok) {
            setSysMsg($msgcode);
            header(URL_HEADER . 'proposition.php?prj=' . $_GET['prj'] . '&id=' . $id_prop);
        }
        else {
            setSysMsg($msgcode);
            header(URL_HEADER . 'entry.php?prj=' . $_GET['prj'] . '&id=' . $id_entry);
        }
        break;
    case 'eraseentry':
        if ($rank <= 3 or $rank <= $project['dictDirectEdition']) {
            require('./code/dbaccess-dictionary.class.php');
            $dbaDictionary = new dbaccessDictionary($db);
            list($ok, $msgcode) = $dbaDictionary->eraseEntry($_GET['prj'], $id_entry, $id_user);
            setSysMsg($msgcode);
            header(URL_HEADER . 'dictionary.php?prj=' . $_GET['prj']);
        }
        else {
            setSysMsg('_noaccess');
            header(URL_HEADER . 'entry.php?prj=' . $_GET['prj'] . '&id=' . $id_entry);
        }
        break;
    case 'setchecktag':
        // AJAX
        if ($rank <= 3 or $rank <= $project['dictDirectEdition']) {
            require('./code/dbaccess-dictionary.class.php');
            $dbaDictionary = new dbaccessDictionary($db);
            list($ok, $msgcode) = $dbaDictionary->setCheckTag($_GET['prj'], $id_entry, $_GET['nbc'], $id_user);
            if ($ok and $_GET['nbc'] === '0') {
                // open suggestion of deletion
                require('./code/dbaccess-comments.class.php');
                $dbaComments = new dbaccessComments($db);
                $ui = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);
                list($ok, $msgcode, $id_prop) = $dbaDictionary->createPropDeleteEntry($_GET['prj'], $id_user, $id_entry, TRUE, $dbaComments);
                if ($ok) {
                    setSysMsg($msgcode);
                    header(URL_HEADER . 'proposition.php?prj=' . $_GET['prj'] . '&id=' . $id_prop);
                    exit;
                }
            }
            echo $msgcode;
        }
        else {
            echo '_noaccess';
        }
        exit;
    case 'createnote':
        // create a note
        require('./code/dbaccess-notes.class.php');
        $dbaNotes = new dbaccessNotes($db);
        list($ok, $msgcode) = $dbaNotes->createNote($_GET['prj'], $_POST['lemma'], $_POST['note'], $id_user, $id_entry, $_COOKIE['login']);
        setSysMsg($msgcode);
        header(URL_HEADER . 'entry.php?prj=' . $_GET['prj'] . '&id=' . $id_entry);
        break;
    case 'editnote':
        // create a note
        require('./code/dbaccess-notes.class.php');
        $dbaNotes = new dbaccessNotes($db);
        list($ok, $msgcode) = $dbaNotes->editNote($_GET['prj'], $_POST['id_note'], $_POST['newnote'], $_GET['from']);
        if (!$ok) setSysMsg($msgcode);
        header(URL_HEADER . 'entry.php?prj=' . $_GET['prj'] . '&id=' . $_GET['from']);
        break;
    case 'deletenote':
        // create a note
        require('./code/dbaccess-notes.class.php');
        $dbaNotes = new dbaccessNotes($db);
        list($ok, $msgcode) = $dbaNotes->deleteNote($_GET['prj'], $_GET['id_note'], $_GET['from']);
        if (!$ok) setSysMsg($msgcode);
        header(URL_HEADER . 'entry.php?prj=' . $_GET['prj'] . '&id=' . $_GET['from']);
        break;
    default:
        setSysMsg($_REQUEST['cmd']);
        header(URL_HEADER . 'entry.php?prj=' . $_GET['prj'] . '&id=' . $id_entry);
        break;
}

?>
