<?php

require('./config/config.php');
require('./code/init.php');
require('./code/template.class.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-users.class.php');

$ui = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);

$db = new Database();
if (!$db->connx) {
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

$dbaUsers = new dbaccessUsers($db);
list($id_user, $rank, $error) = $dbaUsers->connectUser($_COOKIE['login'], $_COOKIE['pw'], $_GET['prj']);
if ($id_user == -1) {
    setSysMsg($error);
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

$template = new Template();
$template->SetPageVars($_GET['prj'], 'useraccount', $dbaUsers);


list($ok, $result) = $dbaUsers->selectUser($id_user);
if (!$ok) {
    setSysMsg($result);
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

$data = $result[0];
$template->SetTrunkVar('TAB_TITLE', $ui['tabs']['useraccount'].' ('.$data['login'].')');
$template->SetTrunkVars($ui['userAccount']);
$template->SetTrunkVars(array('PRJ' => $_GET['prj'],
                              'LOGIN' => $data['login'],
                              'MYNAME' => $data['name'],
                              'MYEMAIL' => $data['email'],
                              'CRYPTEDPW' => $data['pw'],
                              'EMAILNOTIFCHECKED' => ($data['emailnotif']) ? 'checked="checked"' : '',
                              'ENOTIFAUTOCHECKED' => ($data['enotifauto']) ? 'checked="checked"' : ''));

// display
$template->Grow('./html/div.head.tpl.html');
$template->Grow('./html/useraccount.div.tpl.html');
$template->Grow('./html/div.foot.tpl.html');

?>
