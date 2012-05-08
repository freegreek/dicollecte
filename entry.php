<?php

require('./config/config.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-dictionary.class.php');
require('./code/dbaccess-flags.class.php');
require('./code/dbaccess-users.class.php');
require('./code/template.class.php');
require('./html/' . $_GET['prj'] . '/project_vars.php');

$ui = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);

$db = new Database();
if (!$db->connx) {
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

$dbaUsers = new dbaccessUsers($db);
$dbaFlags = new dbaccessFlags($db);
$dbaDictionary = new dbaccessDictionary($db);

$template = new Template();
$template->SetPageVars($_GET['prj'], 'entry', $dbaUsers);
$template->SetBranchVars('script', array('FILENAME' => './js/widgets_control.js'));
$template->SetBranchVars('script', array('FILENAME' => './js/ajaxcmd.js'));
$template->SetBranchVars('script', array('FILENAME' => './lib/autosuggest/autosuggest.js'));
$template->SetBranchVars('script', array('FILENAME' => './html/'. $_GET['prj'] .'/tags.js'));

$langlinks = file_get_contents('./html/'  . $_GET['prj'] .  '/langlinks.div.html');
$template->SetTrunkVars(array('ENTRY_SIDEBAR' => $langlinks));

list($ok, $result) = $dbaDictionary->selectEntry($_GET['prj'], $_GET['id']);
if (!$ok) {
    setSysMsg($result);
    header(URL_HEADER . 'dictionary.php?prj=' . $_GET['prj']);
    exit;
}

if (count($result) > 0) {
    require('./code/entry.class.php');
    $data = $result[0];
    setlocale(LC_TIME, $ui['datetime']['locale']);
    $date = strftime($ui['datetime']['dpattern'], $data['datetime']);
    $template->SetGlobalVar('_IDENTRY_', $data['id_entry']);
    $template->SetTrunkVar('TAB_TITLE', $data['lemma']);
    $entry = new Entry($data);
    $fd = (isset($_GET['fd'])) ? 2 : $project['flexionsDepth'];
    $entry->createFlexions($dbaFlags, $_GET['prj'], $project, 0, $fd);
    $allflexions = (isset($_COOKIE['login']) and $entry->is2lvlFlexions) ? '<a href="entry.php?prj=' . $_GET['prj'] . '&amp;id=' . $_GET['id'] . '&amp;fd=2">[' . $ui['entryVars']['allflexions'] . ']</a>' : '';
    $template->SetBranchVars('entry', $ui['entry']);
    $template->UpdateBranchVars('entry', array('LABEL' => $data['lemma'],
                                               'FROMDATE' => $date, 
                                               'ENTRY' => $entry->createHtmlPresentation(TRUE),
                                               'NBFLEXIONS' => count($entry->flexions) . ' ' . $ui['entry']['flexions'],
                                               'ALLFLEXIONS' => $allflexions
                                              ));
    foreach ($entry->flexions as $flexion) {
        if (isset($prjDicAbr[$flexion[2]])) {
            $flexdic = ($flexion[2] != '*') ? ' <samp>[' . $prjDicAbr[$flexion[2]] . ']</samp>' : '';
        }
        else {
            $flexdic = ' <samp>[' . $ui['sysMsg']['_error'] . ']</samp>';
        }
        $template->SetBranchVars('entry.flexion', array('LEMMA' => $flexion[0],
                                                        'MORPH' => $flexion[1] . $flexdic));
    }
    $rank = $dbaUsers->getUserRankFor($_GET['prj']);
    // notes
    if ($data['nbnotes'] > 0) {
        require('./code/dbaccess-notes.class.php');
        $dbaNotes = new dbaccessNotes($db);
        list($ok, $result) = $dbaNotes->selectNotes($_GET['prj'], $data['id_entry']);
        if ($ok) {
            if (count($result) > 0) {
                $template->SetGlobalVar('_CONFIRMDELETECOM_', $ui['commentGlob']['_CONFIRMDELETECOM_']);
                setlocale(LC_TIME, $ui['datetime']['locale']);
                foreach ($result as $datan) {
                    $date = strftime($ui['datetime']['dtpattern'], $datan['datetime']);
                    $template->SetBranchVars('note', array('IDUSER' => $datan['id_user'],
                                                           'LOGIN' => $datan['login'],
                                                           'IDNOTE' => $datan['id_note'],
                                                           'NOTE' => $datan['note'],
                                                           'DATE' => $date));
                    if ($rank <= 2 or $datan['id_user'] == $_SESSION['id_user']) {
                        $template->SetBranchVars('note.noteactions', array('IDNOTE' => $datan['id_note']));
                        $template->SetBranchVars('note.noteedit', $ui['editNote']);
                        $template->UpdateBranchVars('note.noteedit', array('IDNOTE' => $datan['id_note'],
                                                                           'MAXLENGTH' => $ui['newNote']['MAXLENGTH']));
                    }
                }
            }
        }
        else {
            $template->SetBranchVars('message', array('MSGHEAD' => 'DATABASE ERROR',
                                                      'MESSAGE' => 'while reading notes'));
        }
    }
    // custom links
    $links = $entry->createLinks();
    $template->UpdateBranchVars('entry', array('SRCLINKS' => $links, 'CUSTOMLINKS' => str_replace('%s', htmlentities($data['lemma'], ENT_COMPAT, 'UTF-8'), $links)));
    // link to proposition
    if ($data['id_prop']) {
        $template->SetBranchVars('proplink', $ui['proplink']);
        $template->UpdateBranchVars('proplink', array('IDPROP' => $data['id_prop']));
    }
    // edition
    if (isset($_COOKIE['login'])) {
        if (!$data['id_prop']) {
            require('./config/db_vars.php');
            $template->SetBranchVars('entry.makeprop', $ui['makeProp']);
            $template->SetBranchVars('entry.edit', $ui['entryEdit']);
            $template->UpdateBranchVars('entry.edit', array('ENTRYFORM' => $entry->createHtmlForm()));
            if ($rank <= 3 or $rank <= $project['dictDirectEdition'] or ($project['restrictedEdit'] and !$data['closed'])) {
                $template->SetBranchVars('entry.edit.de', $ui['entryEditDE']);
                $template->UpdateBranchVars('entry.edit.de', array('SUBMIT' => $ui['entryEdit']['SUBMIT']));
            }
            if ($rank <= 2 or $rank <= $project['dictDirectEdition']) {
                $template->SetBranchVars('action', $ui['entryAction']);
                // create note and erase entry
                $template->SetBranchVars('action.restrictedbuttons', $ui['otherAction']);
            }
        }
        if ($rank <= 5 or $rank <= $project['dictDirectEdition']) {
            $template->SetBranchVars('entry.checkarea', $ui['checkArea']);
            $template->SetTrunkVar('CHKVALUE', $data['chk']);
            $template->SetBranchVars('entry.newnote', $ui['newNote']);
            $template->UpdateBranchVars('entry.newnote', array('LEMMA' => $data['lemma']));
        }
        else {
            $template->SetBranchVars('entry.checktag', array());
            $template->UpdateBranchVars('entry.checktag', array('CHK' => $data['chk']));
        }
    }
}
else {
    $template->SetBranchVars('message', array('MSGHEAD' => $ui['entryMsg']['nothing'],
                                              'MESSAGE' => $ui['entryMsg']['nothingmsg']));
}


// display
$template->Grow('./html/div.head.tpl.html');
$template->Grow('./html/entry.div.tpl.html');
$template->Grow('./html/div.foot.tpl.html');

?>
