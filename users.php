<?php

require('./config/config.php');
require('./code/init.php');
require('./code/template.class.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-users.class.php');

$ui = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);

if ($_COOKIE['login'] != 'Admin') {
    header(URL_HEADER);
    exit;
}

$db = new Database();
if (!$db->connx) {
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

$dbaUsers = new dbaccessUsers($db);

$template = new Template();
$template->SetPageVars($_GET['prj'], 'users', $dbaUsers);
$template->SetBranchVars('script', array('FILENAME' => 'js/ajaxcmd.js'));
$template->SetBranchVars('script', array('FILENAME' => 'js/admcmd.js'));

$template->SetTrunkVars($ui['users']);
$template->SetTrunkVar('LABEL', $ui['users']['LABEL'] . ' ' . $ui['usersVars']['allusers']);
$template->SetTrunkVar('TAB_TITLE', 'Users');

list($ok, $result) = $dbaUsers->listUsers();
if (!$ok) {
    setSysMsg($result);
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

if (count($result) > 0) {
    setlocale(LC_TIME, 'en_US.UTF-8');
    foreach ($result as $data) {
        $emailnotif = ($data['emailnotif']) ? '<img src="./img/tag_integrated.png" alt="*" />' : '';
        $template->SetBranchVars('user', array('ID' => $data['id_user'],
                                               'LOGIN' => $data['login'],
                                               'NAME' => $data['name'],
                                               'DATE' => strftime("%Y-%m-%d %H:%M", $data['datetime']),
                                               'EMAIL' => $data['email'],
                                               'EMAILNOTIF' => $emailnotif));
    }
}

// display
$template->Grow('./html/div.head.tpl.html');
$template->Grow('./html/users.div.tpl.html');
$template->Grow('./html/div.foot.tpl.html');

?>
