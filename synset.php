<?php

require('./config/config.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-synsets.class.php');
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

$template = new Template();
$template->SetPageVars($_GET['prj'], 'synset', $dbaUsers);

$template->SetBranchVars('script', array('FILENAME' => './js/widgets_control.js'));
$template->SetBranchVars('script', array('FILENAME' => './js/ajaxcmd.js'));
$template->SetBranchVars('script', array('FILENAME' => './lib/autosuggest/autosuggest.js'));
$template->SetBranchVars('script', array('FILENAME' => './html/'. $_GET['prj'] .'/tags.js'));

$dbaSynsets = new dbaccessSynsets($db);

list($ok, $result) = $dbaSynsets->getSynset($_GET['prj'], $_GET['id']);
if (!$ok) {
    setSysMsg($result);
    header(URL_HEADER . 'synsets.php?prj=' . $_GET['prj']);
    exit;
}

if (count($result) > 0) {
    $rank = $dbaUsers->getUserRankFor($_GET['prj']);
    $data = $result[0];
    setlocale(LC_TIME, $ui['datetime']['locale']);
    $template->SetGlobalVars($ui['synsetGlob']);
    $template->SetGlobalVar('_IDSYNSET_', $data['id_synset']);
    $template->SetTrunkVar('TAB_TITLE', $ui['tabs']['synsets']);
    if ($data['tags'] == '') { $data['tags'] = '—'; }
    $template->SetBranchVars('synset', $ui['synset']);
    $template->UpdateBranchVars('synset', array('POS' => $data['pos'],
                                                'TAGS' => $data['tags'],
                                                'DATE' => strftime($ui['datetime']['dtpattern'], $data['lastedit']),
                                                'SYNSET' => str_replace('|', ' | ', $data['synset']),
                                                'NBSYN' => $data['nbsyn'],
                                                'DELDISPLAY' => (!$data['deleted']) ? 'display: none;' : ''));
    if ($rank <= 7) {
        $template->SetBranchVars('synset.cmdbut', array('DELCMDDISPLAY' => ($data['deleted']) ? 'style="display: none;"' : '',
                                                        'UNDELCMDDISPLAY' => (!$data['deleted']) ? 'style="display: none;"' : ''));
    }
  
    // erase
    if ($rank <= 3) {
        $template->SetBranchVars('synset.delsyn', array());
    }
    
    // history
    if ($data['nbhist'] > 0) {
        list($ok, $result) = $dbaSynsets->getHistSynsets($_GET['prj'], $_GET['id']);
        if ($ok) {
            if (count($result) > 0) {
                foreach ($result as $datan) {
                    $template->SetBranchVars('old', array('IDHIST' => $datan['id_hist'],
                                                          'SYNSET' => str_replace('|', ' | ', $datan['synset']),
                                                          'POS' => $datan['pos'],
                                                          'TAGS' => $datan['tags'],
                                                          'NBSYN' => $datan['nbsyn'],
                                                          'DATE' => strftime($ui['datetime']['dtpattern'], $datan['lastedit'])));
                    if ($rank <= 3) {
                        $template->SetBranchVars('old.action', array('IDHIST' => $datan['id_hist']));
                    }
                }
            }
        }
        else {
            $template->SetBranchVars('message', array('MSGHEAD' => 'DATABASE ERROR',
                                                      'MESSAGE' => 'while reading archives'));
        }
    }
    

}
else {
    $template->SetBranchVars('message', array('MSGHEAD' => $ui['entryMsg']['nothing'],
                                              'MESSAGE' => $ui['entryMsg']['nothingmsg']));
}

// display
$template->Grow('./html/div.head.tpl.html');
$template->Grow('./html/synset.div.tpl.html');
$template->Grow('./html/div.foot.tpl.html');

?>
