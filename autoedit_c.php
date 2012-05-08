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
    header(URL_HEADER . $_POST['pagename'] . '.php?prj=' . $_GET['prj']);
    exit;
}
if ($rank > 2) {
    setSysMsg('_noaccess');
    header(URL_HEADER . $_POST['pagename'] . '.php?prj=' . $_GET['prj']);
    exit;
}

$toreplace = array('<script', '</script>');

$mainbody = stripslashes(str_replace($toreplace, '', $_POST['mainbody']));
$rightcolumn = stripslashes(str_replace($toreplace, '', $_POST['rightcolumn']));

file_put_contents('./html/' . $_GET['prj'] . '/' . $_POST['pagename'] . '-left.div.html', $mainbody, LOCK_EX);
file_put_contents('./html/' . $_GET['prj'] . '/' . $_POST['pagename'] . '-right.div.html', $rightcolumn, LOCK_EX);

header(URL_HEADER . $_POST['pagename'] . '.php?prj=' . $_GET['prj']);

?>
