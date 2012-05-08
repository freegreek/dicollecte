<?php

require('./config/config.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-users.class.php');
require('./code/template.class.php');

$ui = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);

$db = new Database();
$dbaUsers = new dbaccessUsers($db);

$template = new Template();
$template->SetPageVars($_GET['prj'], 'download', $dbaUsers);
$template->SetTrunkVar('TAB_TITLE', $ui['tabs']['download']);

// we load the page
$mainleft = file_get_contents('./html/' . $_GET['prj'] . '/download-left.div.html');
$mainright = file_get_contents('./html/' . $_GET['prj'] . '/download-right.div.html');
if (isset($_GET['cmd']) and $_GET['cmd'] == 'autoedit') {
    // page edition
    $template->SetBranchVars('pageedit', $ui['autoEdit']);
    $template->UpdateBranchVars('pageedit', array('PRJ' => $_GET['prj'],
                                                  'PAGETITLE' => '[download]',
                                                  'MAINRIGHT' => $mainright,
                                                  'MAINLEFT' => $mainleft,
                                                  'PAGENAME' => 'download'));
}
else {
    // page display only
    $template->SetBranchVars('page', array('MAINRIGHT' => $mainright,
                                           'MAINLEFT' => $mainleft));
}

// display
$template->Grow('./html/div.head.tpl.html');
$template->Grow('./html/autoedit.div.tpl.html');
$template->Grow('./html/div.foot.tpl.html');

?>
