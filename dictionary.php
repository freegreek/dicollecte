<?php

require('./config/config.php');
require('./config/db_vars.php');
require('./code/init.php');
require('./code/template.class.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-users.class.php');
require('./code/entry.class.php');
require('./code/pagination.class.php');
require('./html/' . $_GET['prj'] . '/project_vars.php');

$ui = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);

$db = new Database();
$dbaUsers = new dbaccessUsers($db);

$template = new Template();
$template->SetPageVars($_GET['prj'], 'dictionary', $dbaUsers);
$template->SetTrunkVar('TAB_TITLE', $ui['tabs']['dictionary']);

$aSearch = array('dic' => '');
$entry = new Entry($aSearch);

$template->SetTrunkVars($ui['dictionary']);
$template->SetTrunkVar('DICTFORM', $entry->createHtmlForm(TRUE));
$template->SetBranchVars('script', array('FILENAME' => './js/widgets_control.js'));
$template->SetBranchVars('script', array('FILENAME' => './lib/autosuggest/autosuggest.js'));
$template->SetBranchVars('script', array('FILENAME' => './html/'. $_GET['prj'] .'/tags.js'));

if ($db->connx and isset($_REQUEST['lemma'])) {
    require('./code/dbaccess-dictionary.class.php');
    require('./code/dbaccess-propositions.class.php');
    
    // search in dictionary
    require('./config/img_vars.php');
    $tagSearch = (isset($_REQUEST['regex']) and $_REQUEST['regex'] == 'ON') ? 'R' : 'N';
    //if (isset($_REQUEST['mphone']) and $_REQUEST['mphone'] == 'ON') $tagSearch = 'P';
    
    // sort
    $orderby = (isset($_REQUEST['order'])) ? $_REQUEST['order'] : 'L';
    $urlbase = 'dictionary.php?';
    $i = 0;
    $params = array_merge($_GET, $_POST);
    foreach ($params as $key => $value) {
        if ($key != 'order') {
            if ($i > 0) $urlbase .= '&amp;';
            $urlbase .= $key . '=' . $value;
            $i++;
        }
    }
    if ($orderby != 'L') { $ui['dictList']['LEMMA'] = '<a href="'.$urlbase.'&amp;order=L">' . $ui['dictList']['LEMMA'] . '</a>'; }
    if ($orderby != 'D') { $ui['dictList']['DATE'] = '<a href="'.$urlbase.'&amp;order=D">' . $ui['dictList']['DATE'] . '</a>'; }
    if ($orderby != 'C') { $ui['dictList']['CHECK'] = '<a href="'.$urlbase.'&amp;order=C">' . $ui['dictList']['CHECK'] . '</a>'; }
    if ($orderby != 'N') { $ui['dictList']['NOTES'] = '<a href="'.$urlbase.'&amp;order=N">' . $ui['dictList']['NOTES'] . '</a>'; }
    if ($orderby != 'O') { $ui['dictList']['LOCK'] = '<a href="'.$urlbase.'&amp;order=O">' . $ui['dictList']['LOCK'] . '</a>'; }
    
    $nbEntriesByPage = 100;
    $oPg1 = new Pagination('dictionary.php', 'page', $nbEntriesByPage);
    
    $selectuser = (isset($_REQUEST['id_user'])) ? (int) $_REQUEST['id_user'] : 0;

    $dbaDictionary = new dbaccessDictionary($db);
    list($ok, $result, $nbOccur) = $dbaDictionary->search($_GET['prj'], $tagSearch, $_REQUEST, $selectuser, $orderby, $oPg1->getOffset(), $nbEntriesByPage);
    if (!$ok) {
        setSysMsg($result);
        header(URL_HEADER . 'dictionary.php?prj=' . $_GET['prj']);
        exit;
    }
    
    $template->SetBranchVars('submit', array('SUBMIT' => $ui['dictNewEntry']['SUBMIT']));
    if ($nbOccur > 0) {
        $template->SetTrunkVar('PAGES', $oPg1->createLinks($nbOccur));
        $template->SetBranchVars('list', $ui['dictList']);
        foreach ($result as $data) {
            $lemmacell = '<a href="entry.php?prj=' . $_GET['prj'] . '&amp;id=' . $data['id_entry'] . '">' . $data['lemma'] . '</a>';
            if ($data['flags'] != '') { $lemmacell .= '<dfn>/</dfn><samp>' . $data['flags'] . '</samp>'; }
            $dictlbl = (isset($prjDic[$data['dic']])) ? $prjDic[$data['dic']] : 'error!';
            $dictcell = ($data['dic'] != '*') ? '<samp>' . $dictlbl . '</samp>' : $dictlbl;
            $lockcell = ($data['closed']) ? $uiImgValueSmall['C'] : '';
            $chktag = '<img src="img/chk_tag_'. $data['chk'] . '.png" alt="" />';
            $nbnotes = ($data['nbnotes'] > 0) ? $data['nbnotes'] : ''; 
            $morph = $data['po'];
            $lexsem = ($data['sem'] == '') ? $data['lex'] : $data['lex'] . ' ' . $data['sem'];
            if ($data['ety'] != '') $lexsem .= ' ' . $data['ety'];
            if ($activeFields['is'] and $data['is'] != '') { $morph .= ' ' . $data['is']; }
            if ($activeFields['ds'] and $data['ds'] != '') { $morph .= ' ' . $data['ds']; }
            if ($activeFields['ts'] and $data['ts'] != '') { $morph .= ' ' . $data['ts']; }
            if ($activeFields['ip'] and $data['ip'] != '') { $morph .= ' ' . $data['ip']; }
            if ($activeFields['dp'] and $data['dp'] != '') { $morph .= ' ' . $data['dp']; }
            if ($activeFields['tp'] and $data['tp'] != '') { $morph .= ' ' . $data['tp']; }
            if ($activeFields['sp'] and $data['sp'] != '') { $morph .= ' ' . $data['sp']; }
            $template->SetBranchVars('list.entry', array('LEMMA' => $lemmacell,
                                                         'GRAMM' => $morph,
                                                         'LEX' => $lexsem,
                                                         'DICT' => $dictcell,
                                                         'NBNOTES' => $nbnotes,
                                                         'CHKTAG' => $chktag,
                                                         'IFQ' => $data['ifq'],
                                                         'LOCK' => $lockcell));
        }
    }
    else {
        $template->SetBranchVars('message', array('MSGHEAD' => $ui['dictMsg']['nothing'],
                                                  'MESSAGE' => '<samp>' . stripslashes($_REQUEST['lemma']) . '…</samp>' . $ui['dictMsg']['noresult']));
        if (!$_COOKIE['login']) { $db->record('NOT FOUND: ' . stripslashes($_REQUEST['lemma']), 'search-log.txt'); }
    }

    
    // search in the suggestions list
    $fields = array('lemma', 'flags', 'po', 'is', 'ds', 'ts', 'ip', 'dp', 'tp', 'sp', 'pa', 'st', 'al', 'ph', 'lex', 'sem', 'ety', 'dic');
    $doPropSearch = FALSE;
    foreach ($fields as $field) {
        if (isset($_REQUEST[$field]) and $_REQUEST[$field] != '') { $doPropSearch = TRUE; }
    }
    if ($doPropSearch) {
        $oPg2 = new Pagination('dictionary.php', 'page2', $nbEntriesByPage);
        $dbaPropositions = new dbaccessPropositions($db);
        list($ok, $result, $nbOccur2) = $dbaPropositions->searchAll($_GET['prj'], $tagSearch, $_REQUEST, $selectuser, $oPg2->getOffset(), $nbEntriesByPage);
        if (!$ok) {
            setSysMsg($result);
            header(URL_HEADER . 'dictionary.php?prj=' . $_GET['prj']);
            exit;
        }
        if ($nbOccur2 > 0) {
            if ($nbOccur > 20) $template->SetTrunkVar('PROPSFOUND', '<samp>|</samp> <a href="#props">' . $ui['dictMsg']['propsfound'] . '</a>');
            $template->SetBranchVars('props', $ui['dictProps']);
            if ($nbOccur2 > $nbEntriesByPage) $template->UpdateBranchVars('props', array('PAGES' => $oPg2->createLinks($nbOccur2, '#props')));
            foreach ($result as $data) {
                $lemmacell = '<a href="proposition.php?prj=' . $_GET['prj'] . '&amp;id=' . $data['id_prop'] . '">' . $data['lemma'] . '</a>';
                if ($data['flags'] != '') { $lemmacell .= '<dfn>/</dfn><samp>' . $data['flags'] . '</samp>'; }
                $dictlbl = (isset($prjDic[$data['dic']])) ? $prjDic[$data['dic']] : 'error!';
                $dictcell = ($data['dic'] != '*') ? '<samp>' . $dictlbl . '</samp>' : $dictlbl;
                $nbcomments = ($data['nbcomments'] != 0) ? $data['nbcomments'] : '';
                $imgvalue = ($data['value'] != '?') ? $uiImgValueSmall[$data['value']] : '';
                $morph = $data['po'];
                $lexsem = ($data['sem'] == '') ? $data['lex'] : $data['lex'] . ' ' . $data['sem'];
                if ($data['ety'] != '') $lexsem .= ' ' . $data['ety'];
                if ($activeFields['is'] and $data['is'] != '') { $morph .= ' ' . $data['is']; }
                if ($activeFields['ds'] and $data['ds'] != '') { $morph .= ' ' . $data['ds']; }
                if ($activeFields['ts'] and $data['ts'] != '') { $morph .= ' ' . $data['ts']; }
                if ($activeFields['ip'] and $data['ip'] != '') { $morph .= ' ' . $data['ip']; }
                if ($activeFields['dp'] and $data['dp'] != '') { $morph .= ' ' . $data['dp']; }
                if ($activeFields['tp'] and $data['tp'] != '') { $morph .= ' ' . $data['tp']; }
                if ($activeFields['sp'] and $data['sp'] != '') { $morph .= ' ' . $data['sp']; }
                $template->SetBranchVars('props.sugg', array('ACTION' => $uiImgActionSmall[$data['action']], 
                                                             'LEMMA' => $lemmacell,
                                                             'GRAMM' => $morph,
                                                             'LEX' => $lexsem,
                                                             'DICT' => $dictcell,
                                                             'NBCOMMENTS' => $nbcomments,
                                                             'VALUE' => $imgvalue));
            }
        }
    }
}
else {
    $template->SetBranchVars('doc', $ui['dicDoc']);
}

// display
$template->Grow('./html/div.head.tpl.html');
$template->Grow('./html/dictionary.div.tpl.html');
$template->Grow('./html/div.foot.tpl.html');

?>
