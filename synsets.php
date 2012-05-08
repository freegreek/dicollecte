<?php

require('./config/config.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-users.class.php');
require('./code/dbaccess-synsets.class.php');
require('./code/template.class.php');
require('./code/pagination.class.php');

require('./html/' . $_GET['prj'] . '/project_vars.php');
$ui = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);

$db = new Database();
$dbaUsers = new dbaccessUsers($db);

$template = new Template();
$template->SetPageVars($_GET['prj'], 'synsets', $dbaUsers);
$template->SetTrunkVar('TAB_TITLE', $ui['tabs']['synsets']);

$template->SetTrunkVars($ui['synsets']);
$template->SetBranchVars('script', array('FILENAME' => './js/widgets_control.js'));
$template->SetBranchVars('script', array('FILENAME' => './js/ajaxcmd.js'));
$template->SetBranchVars('script', array('FILENAME' => './lib/autosuggest/autosuggest.js'));
$template->SetBranchVars('script', array('FILENAME' => './html/'. $_GET['prj'] .'/tags.js'));

if ($db->connx and isset($_REQUEST['search'])) {
    $nbEntriesByPage = 100;
    $oPg = new Pagination('synsets.php', 'page', $nbEntriesByPage);
    
    $search = trim(stripslashes($_REQUEST['search']));
    $sqlSearch = str_replace(array('"', "'"), array('\"', "\'"), $search);
    
    $dbaSynsets = new dbaccessSynsets($db);
    list($ok, $result, $nbOccur) = $dbaSynsets->search($_GET['prj'], $sqlSearch, $oPg->getOffset(), $nbEntriesByPage);
    if (!$ok) {
        setSysMsg($result);
        header(URL_HEADER . 'synsets.php?prj=' . $_GET['prj']);
        exit;
    }

    $rank = $dbaUsers->getUserRankFor($_GET['prj']);
    $isModifAllowed = (isset($_COOKIE['login']) and $rank <= 5 or ($rank <= 7 and $project['thesAllUsersAllowed'])) ? TRUE : FALSE;
    if ($nbOccur > 0) {
        setlocale(LC_TIME, $ui['datetime']['locale']);
        
        // pagination
        $template->SetTrunkVar('PAGES', $oPg->createLinks($nbOccur));
        foreach ($result as $data) {
            if ($data['tags'] == '') { $data['tags'] = '—'; }
            $template->SetBranchVars('synset', array('ID' => $data['id_synset'],
                                                     'POS' => $data['pos'],
                                                     'TAGS' => $data['tags'],
                                                     'SYNSET' => str_replace('|', ' | ', $data['synset']),
                                                     'NBSYN' => $data['nbsyn']));
            if (isset($_COOKIE['login'])) {
                $template->SetBranchVars('synset.cmdbut', array('ID' => $data['id_synset'],
                                                                'DATE' => strftime($ui['datetime']['dpattern'], $data['lastedit'])));
            }
        }
        $template->SetGlobalVars($ui['synsetGlob']);
    }
    else {
        $template->SetBranchVars('message', array('MSGHEAD' => $ui['synsMsg']['nothing'],
                                                  'MESSAGE' => '<samp>' . $search . '…</samp>' . $ui['synsMsg']['noresult']));
    }
    if (isset($_COOKIE['login'])) {
        $template->SetBranchVars('createbutton', $ui['createbuttonss']);
    }
}
else {
    // presentation
    $template->SetBranchVars('message', array('MSGHEAD' => $ui['synsMsg']['instructions'],
                                              'MESSAGE' => $ui['synsMsg']['instructionstxt']));
}

// display
$template->Grow('./html/div.head.tpl.html');
$template->Grow('./html/synsets.div.tpl.html');
$template->Grow('./html/div.foot.tpl.html');

?>
