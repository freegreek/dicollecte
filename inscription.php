<?php

require('./config/config.php');
require('./code/init.php');

if (isset($_COOKIE['login'])) {
    header(URL_HEADER . 'logout_c.php?prj=' . $_GET['prj']);
    exit;
}

require('./code/dbaccess.class.php');
require('./code/dbaccess-users.class.php');
require('./code/template.class.php');

$ui = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);

$db = new Database();
if (!$db->connx) {
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

$dbaUsers = new dbaccessUsers($db);

$template = new Template();
$template->SetPageVars($_GET['prj'], 'inscription', $dbaUsers);
$template->SetTrunkVar('TAB_TITLE', $ui['tabs']['inscription']);

$template->SetGlobalVar('_PRJ_', $_GET['prj']);
$template->SetTrunkVars($ui['inscription']);
$laHtml = file_get_contents('./html/' . $_GET['prj'] . '/license_agreement.div.html');
$mainright = file_get_contents('./html/' . $_GET['prj'] . '/home-right.div.html');
$template->SetTrunkVars(array('LICENSE_AGREEMENT' => $laHtml,
                              'MAINRIGHT' => $mainright));

// display
$template->Grow('./html/div.head.tpl.html');
$template->Grow('./html/inscription.div.tpl.html');
$template->Grow('./html/div.foot.tpl.html');

$_SESSION['nospambot_time'] = time();

?>
