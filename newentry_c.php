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

$dbaUsers = new dbaccessUsers($db);

// user checking
list($id_user, $rank, $error) = $dbaUsers->connectUser($_COOKIE['login'], $_COOKIE['pw'], $_GET['prj'], TRUE);
if ($id_user == -1) {
    setSysMsg($error);
    header(URL_HEADER . 'newentry.php?prj=' . $_GET['prj']);
    exit;
}

$lemma = trim($_POST['lemma']);
if ($lemma == '') {
    setSysMsg('_emptylemma');
    header(URL_HEADER . 'newentry.php?prj=' . $_GET['prj']);
    exit;
}

require('./code/entry.class.php');
$entry = new Entry($_POST);

if (isset($_POST['insertindict']) and $_POST['insertindict'] == 'ON') {
    if (!($rank <= 3 or $rank <= $project['dictDirectEdition'])) {
        setSysMsg('_noaccess');
        header(URL_HEADER . 'newentry.php?prj=' . $_GET['prj']);
        exit;
    }
    require('./code/dbaccess-dictionary.class.php');
    require('./code/dbaccess-notes.class.php');
    $dbaDictionary = new dbaccessDictionary($db);
    $dbaNotes = new dbaccessNotes($db);
    list($ok, $msgcode, $id_entry) = $dbaDictionary->createNewEntry($_GET['prj'], $dbaNotes, $id_user, $entry, $_POST['comment']);
    setSysMsg($msgcode);
    if ($ok) {
        header(URL_HEADER . 'entry.php?prj=' . $_GET['prj'] . '&id=' . $id_entry);
        exit;
    }
}
else {
    require('./code/dbaccess-propositions.class.php');
    require('./code/dbaccess-comments.class.php');
    $dbaPropositions = new dbaccessPropositions($db);
    $dbaComments = new dbaccessComments($db);
    list($ok, $msgcode, $id_prop) = $dbaPropositions->createPropNewEntry($_GET['prj'], $dbaComments, $id_user, $entry, $_POST['comment']);
    setSysMsg($msgcode);
    if ($ok) {
        header(URL_HEADER . 'proposition.php?prj=' . $_GET['prj'] . '&id=' . $id_prop);
        exit;
    }
}

header(URL_HEADER . 'newentry.php?prj=' . $_GET['prj']);

?>
