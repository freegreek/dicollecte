<?php

require('./config/config.php');
require('./config/db_vars.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-users.class.php');
require('./code/template.class.php');
require('./code/entry.class.php');
require('./html/' . $_GET['prj'] . '/project_vars.php');

$ui = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);

$db = new Database();
if (!$db->connx) {
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

$dbaUsers = new dbaccessUsers($db);

$template = new Template();
$template->SetPageVars($_GET['prj'], 'new', $dbaUsers);
$template->SetTrunkVar('TAB_TITLE', $ui['tabs']['new']);
$template->SetBranchVars('script', array('FILENAME' => './js/widgets_control.js'));
$template->SetBranchVars('script', array('FILENAME' => './lib/autosuggest/autosuggest.js'));
$template->SetBranchVars('script', array('FILENAME' => './html/'. $_GET['prj'] .'/tags.js'));

$fields = array(); 
$entry = new Entry($fields);

$langlinks = file_get_contents('./html/' . $_GET['prj'] . '/langlinks.div.html');
$template->SetTrunkVars($ui['newEntry']);
$template->SetTrunkVar('ENTRYFORM', $entry->createHtmlForm());
$template->SetTrunkVar('SRCLINKS', $entry->createLinks());
$template->SetTrunkVar('LANGLINKS', $langlinks);


if (isset($_COOKIE['login'])) {
    $template->SetBranchVars('submit', $ui['newEntrySubmit']);
    $rank = $dbaUsers->getUserRankFor($_GET['prj']);
    if ($rank <= 3 or $rank <= $project['dictDirectEdition']) {
        $template->SetBranchVars('submit.di', $ui['newEntrySubmitDI']);
    }
}
else {
    $template->SetBranchVars('noid', $ui['newEntryNoID']);
}

// display
$template->Grow('./html/div.head.tpl.html');
$template->Grow('./html/newentry.div.tpl.html');
$template->Grow('./html/div.foot.tpl.html');

?>
