<?php

require('./config/config.php');
require('./code/init.php');
require('./code/template.class.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-users.class.php');
require('./html/' . $_GET['prj'] . '/project_vars.php');

$ui = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);

$db = new Database();
$dbaUsers = new dbaccessUsers($db);

// user control
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

// main
$template = new Template();
$template->SetPageVars($_GET['prj'], 'administration', $dbaUsers);
$template->SetTrunkVar('TAB_TITLE', $ui['tabs']['administration']);
$template->SetBranchVars('script', array('FILENAME' => 'js/widgets_control.js'));
$template->SetBranchVars('script', array('FILENAME' => 'js/ajaxcmd.js'));
$template->SetBranchVars('script', array('FILENAME' => 'js/admcmd.js'));
$template->SetBranchVars('script', array('FILENAME' => 'js/onoff.js'));
$template->SetTrunkVars($ui['administration']);

setlocale(LC_TIME, $ui['datetime']['locale']);

// settings
switch ($project['flagtype']) {
    case '1' : $whatchecked = 'CHECKEDONE'; break;
    case '2' : $whatchecked = 'CHECKEDTWO'; break;
    case 'N' : $whatchecked = 'CHECKEDNUM'; break;
}
$exceptions = '';
foreach ($project['exceptionslist'] as $value) {
    $exceptions .= $value . ' ';
}
$exceptions = trim($exceptions);

$thesChecked = ($project['thesAllUsersAllowed']) ? 'checked="checked"' : '';
$ESDchecked = ($project['thesExtendedSearch']) ? 'checked="checked"' : '';
$UBMchecked = ($project['thesUpdateByEmail']) ? 'checked="checked"' : '';
$resEdChecked = ($project['restrictedEdit']) ? 'checked="checked"' : '';

$customlinks = '';
foreach ($prjCustomlinks as $name => $link) {
    $customlinks .= $name . ' = ' . $link . "\n";
}

$subdicts = '';
foreach ($prjDic as $code => $label) {
    $subdicts .= $code . ' = ' . $label . ' = ' . $prjDicAbr[$code] . PHP_EOL;
}

$template->SetTrunkVars(array($whatchecked => 'checked="checked"',
                              'NEEDAFFIX' => $project['needaffix'],
                              'CIRCUMFIX' => $project['circumfix'],
                              'EXCEPTIONSLIST' => $exceptions,
                              'FDSELECTED_' . $project['flexionsDepth'] => 'selected="selected"',
                              'LOCKDURATION' => $project['thesLockDuration'],
                              'ESDCHECKED' => $ESDchecked,
                              'UBMCHECKED' => $UBMchecked,
                              'SELECTED_' . $project['dictDirectEdition'] => 'selected="selected"',
                              'RESEDCHECKED' => $resEdChecked,
                              'THESCHECKED' => $thesChecked,
                              'CUSTOMLINKS' => $customlinks,
                              'SUBDICTS' => $subdicts));

foreach ($activeFields as $key => $value) {
    $template->SetTrunkVar($key.'LBL', $ui['entryObj'][$key]);
    if ($value) $template->SetTrunkVar($key.'CHECKED', 'checked="checked"');
}



// tags
if (!file_exists('./html/' . $_GET['prj'] . '/tags.list.txt')) {
    $contentTags = file_get_contents('./html/_default/tags.list.txt');
}
else {
    $contentTags = file_get_contents('./html/' . $_GET['prj'] . '/tags.list.txt');
}
$template->SetTrunkVar('TAGS', $contentTags);

// ui edition
if (!file_exists('./html/' . $_GET['prj'] . '/ui.ini')) {
    $myui = file_get_contents('./html/_default/ui.ini');
}
else {
    $myui = file_get_contents('./html/' . $_GET['prj'] . '/ui.ini');
}
$template->SetTrunkVar('MYUI', htmlspecialchars($myui));

$langlinks = file_get_contents('./html/' . $_GET['prj'] . '/langlinks.div.html'); 
$template->SetTrunkVar('LANGLINKS', $langlinks);

// Bugs report
if (file_exists('./log/'.$_GET['prj'].'/log.txt')) {
    $bugsreport = file_get_contents('./log/'.$_GET['prj'].'/log.txt');
    $template->SetTrunkVar('ERRORSREPORT', $bugsreport);
}
else {
    $template->SetTrunkVar('ERRORSREPORT', $ui['administrationMsg']['noerror']);
}

// Import - Export
$i = 0;
foreach ($prjDic as $key => $name) {
    $template->SetBranchVars('dictlist', array('NBDICT' => $i,
                                               'DICTCODE' => $key,
                                               'DICTNAME' => $name));
    $i++;
}

$filenames = @scandir('./export/' . $_GET['prj']);
if ((count($filenames) - 2) > 0) {
    foreach ($filenames as $filename) {
        $template->SetTrunkVar('NOFILELBL', '');
        if ($filename != '.' and $filename != '..') {
            $fpn = './export/'.$_GET['prj'].'/'.$filename;
            $template->SetBranchVars('file', array('FILE' => $filename,
                                                   'DATE' => strftime($ui['datetime']['dtpatternsmall'], filemtime($fpn)),
                                                   'SIZE' => (int) (filesize($fpn) / 1024) ));
        }
    }
    $template->SetGlobalVars($ui['administrationGlob']);
}

// display
$template->Grow('./html/div.head.tpl.html');
$template->Grow('./html/administration.div.tpl.html');
$template->Grow('./html/div.foot.tpl.html');

?>
