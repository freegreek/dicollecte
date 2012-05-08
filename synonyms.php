<?php

require('./config/config.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-thesaurus.class.php');
require('./code/dbaccess-users.class.php');
require('./code/template.class.php');
require('./html/' . $_GET['prj'] . '/project_vars.php');

$ui = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);

if (!(isset($_GET['id_word']) and preg_match('`^[0-9]+$`', $_GET['id_word']))) {
    setSysMsg('_wrongurl');
    header(URL_HEADER . 'thesaurus.php?prj=' . $_GET['prj']);
    exit;
}

$db = new Database();
if (!$db->connx) {
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

$dbaUsers = new dbaccessUsers($db);
list($id_user, $rank, $error) = $dbaUsers->connectUser($_COOKIE['login'], $_COOKIE['pw'], $_GET['prj']);
if ($id_user == -1) {
    setSysMsg($error);
    header(URL_HEADER . 'thesaurus.php?prj=' . $_GET['prj']);
    exit;
}
if (!($rank <= 5 or ($rank <= 7 and $project['thesAllUsersAllowed']))) {
    setSysMsg('_noaccess');
    header(URL_HEADER . 'thesaurus.php?prj=' . $_GET['prj']);
    exit;
}

$dbaThesaurus = new dbaccessThesaurus($db);

$isEditMode = (isset($_GET['edit']) and $_GET['edit'] == 'ON') ? TRUE : FALSE;
if ($isEditMode) {
    list($ok, $result, $isEditable) = $dbaThesaurus->readAndLockEntry($_GET['prj'], $_GET['id_word'], $project['thesLockDuration'], $id_user);
}
else {
    list($ok, $result, $isEditable) = $dbaThesaurus->readHistoryEntry($_GET['prj'], $_GET['id_word'], $id_user);
}
if (!$ok) {
    setSysMsg($result);
    header(URL_HEADER . 'thesaurus.php?prj=' . $_GET['prj']);
    exit;
}
if ($isEditMode and !$isEditable) {
    header(URL_HEADER . 'synonyms.php?prj=' . $_GET['prj'] . '&id_word=' . $_GET['id_word']);
    exit;
}

$template = new Template();
$template->SetPageVars($_GET['prj'], 'entry', $dbaUsers);

// the entry
$data = $result[0];
$template->SetTrunkVar('TAB_TITLE',  $data['word'] . ' (' . $ui['tabs']['thesaurus'] . ')');
$template->SetTrunkVars($ui['synonyms']);
setlocale(LC_TIME, $ui['datetime']['locale']);
$date = strftime($ui['datetime']['dtpattern'], $data['lastedit']);
$lastedit = ($data['id_user'] != 0) ? sprintf($ui['synonymsHistory']['lastedit'], $date, $data['login']) : '';
$template->SetTrunkVars(array('WORD' => $data['word'],
                              'LASTEDIT' => $lastedit));
$template->SetGlobalVar('_IDWORD_', $data['id_word']);

$synsArray = explode('##', $data['syn'], $data['nbclass']);

if (!$isEditMode) {
    // history of the entry
    if ($isEditable) {
        $template->SetBranchVars('button', $ui['synonymsAction']);
        $template->SetGlobalVars($ui['synonymsHistoryAction']);
    }
    else {
        $lockMessage = sprintf($ui['synonymsMsg']['lockedentry'], $data['login'], (int) (($data['lock'] - time()) / 60));
        $template->SetBranchVars('message', array('LOCKMESSAGE' => $lockMessage));
    }
}
else {
    // create the form
    $lockMessage = sprintf($ui['synonymsMsg']['yourlock'], strftime($ui['datetime']['dtpattern'], time()), (int) ($project['thesLockDuration'] / 60));
    $template->SetBranchVars('message', array('LOCKMESSAGE' => $lockMessage));
    $template->SetBranchVars('script', array('FILENAME' => './js/synonyms_manager.js'));
    $template->SetBranchVars('form', $ui['synonymsEdit']);
    $template->UpdateBranchVars('form', array('WORD' => $data['word'],
                                              'NBCLASS' => $data['nbclass']));
}
for ($i=0; $i < $data['nbclass'] ; $i++) {
    $elems = explode('|', $synsArray[$i], 3);
    $syn = '';
    if (isset($elems[2])) {
        $syn = str_replace(' ', '&nbsp;', $elems[2]);
        $syn = str_replace('|', ' | ', $syn);
    }
    $template->SetBranchVars('cat', array('GRAMM' => $elems[0],
                                          'MEANING' => $elems[1],
                                          'SYNONYMS' => $syn));
    if ($isEditMode) {
        // fill the form’s lines
        $nblines = (int) (strlen($syn) / 50) + 3;
        $template->SetBranchVars('form.editline', array('DISPLAY' => 'table-row',
                                                        'NUM' => $i+1,
                                                        'ROWS' => $nblines,
                                                        'GRAMM' => $elems[0],
                                                        'MEANING' => $elems[1],
                                                        'SYNONYMS' => $syn));
    }
}
if ($isEditMode) {
    // form’s empty lines
    for ($i = $data['nbclass'] + 1;  $i <=100;  $i++) {
        $template->SetBranchVars('form.editline', array('DISPLAY' => 'none',
                                                        'NUM' => $i,
                                                        'ROWS' => 3));
    }
}

// history of the entry
if (!$isEditMode) {
    $nbHistEntries = count($result);
    for ($d = 1;  $d < $nbHistEntries;  $d++) {
        $data = $result[$d];
        $template->SetBranchVars('old', $ui['synonyms']);
        $date = strftime($ui['datetime']['dtpattern'], $data['lastedit']);
        $lastedit = ($data['id_user'] != 0) ? sprintf($ui['synonymsHistory']['lastedit'], $date, $data['login']) : $date;
        $template->UpdateBranchVars('old', array('WORD' => $data['word'],
                                                 'LASTEDIT' => $lastedit,
                                                 'IDHIST' => $data['id_hist']));
        if ($isEditable) {
            $template->SetBranchVars('old.action', array('IDHIST' => $data['id_hist']));
        }
        $synsArray = explode('##', $data['syn'], $data['nbclass']);
        for ($i=0; $i < $data['nbclass'] ; $i++) {
            $elems = explode('|', $synsArray[$i], 3);
            $syn = '';
            if (isset($elems[2])) {
                $syn = str_replace(' ', '&nbsp;', $elems[2]);
                $syn = str_replace('|', ' | ', $syn);
            }
            $template->SetBranchVars('old.line', array('GRAMM' => $elems[0],
                                                       'MEANING' => $elems[1],
                                                       'SYNONYMS' => $syn));
        }
    }
}

// display
$template->Grow('./html/div.head.tpl.html');
$template->Grow('./html/synonyms.div.tpl.html');
$template->Grow('./html/div.foot.tpl.html');

?>
