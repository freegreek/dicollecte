<?php

require('./config/config.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-thesaurus.class.php');
require('./code/dbaccess-users.class.php');
require('./code/template.class.php');
require('./code/pagination.class.php');
require('./html/' . $_GET['prj'] . '/project_vars.php');

$ui = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);

$db = new Database();
$dbaUsers = new dbaccessUsers($db);

$template = new Template();
$template->SetPageVars($_GET['prj'], 'thesaurus', $dbaUsers);
$template->SetTrunkVar('TAB_TITLE', $ui['tabs']['thesaurus']);

$template->SetTrunkVars($ui['thesaurus']);
$ESDchecked = ($project['thesExtendedSearch']) ? 'checked="checked"' : '';
$template->SetTrunkVars(array('ESDCHECKED' => $ESDchecked));

if ($db->connx and isset($_REQUEST['search'])) {
    $nbEntriesByPage = 25;
    $oPg1 = new Pagination('thesaurus.php', 'page', $nbEntriesByPage);
    $oPg2 = new Pagination('thesaurus.php', 'page2', $nbEntriesByPage);
    
    $isRegEx = (isset($_REQUEST['regex']) and $_REQUEST['regex'] == 'ON') ? TRUE : FALSE;
    
    $search = trim(stripslashes($_REQUEST['search']));
    $sqlSearch = str_replace(array('"', "'"), array('\"', "\'"), $search);
    
    $dbaThesaurus = new dbaccessThesaurus($db);
    list($ok, $result, $nbOccur) = $dbaThesaurus->search($_GET['prj'], $sqlSearch, $isRegEx, $oPg1->getOffset(), $nbEntriesByPage);
    if (!$ok) {
        setSysMsg($result);
        header(URL_HEADER . 'thesaurus.php?prj=' . $_GET['prj']);
        exit;
    }
    
    $isWordFound = FALSE;
    $rank = $dbaUsers->getUserRankFor($_GET['prj']);
    $isModifAllowed = (isset($_COOKIE['login']) and $rank <= 5 or ($rank <= 7 and $project['synsAllUsersAllowed'])) ? TRUE : FALSE;
    if ($nbOccur > 0) {
        // pagination
        $template->SetTrunkVar('PAGES', $oPg1->createLinks($nbOccur));
        // get data
        foreach ($result as $data) {
            if ($data['word'] == $search) $isWordFound = TRUE; 
            $wordcell = ($isModifAllowed) ? '<a href="synonyms.php?prj=' . $_GET['prj'] . '&amp;id_word=' . $data['id_word'] . '">' . $data['word'] . '</a>' : $data['word'];
            $template->SetBranchVars('entry', array ('WORD' => $wordcell));
            $synsArray = explode('##', $data['syn'], $data['nbclass']);
            for ($i=0; $i < $data['nbclass']; $i++) {
                $elems = explode('|', $synsArray[$i], 3);
                $syn = '';
                if (isset($elems[2])) {
                    $syn = str_replace(' ', '&nbsp;', $elems[2]);
                    $syn = str_replace('|', ' | ', $syn);
                }
                $template->SetBranchVars('entry.line', array('GRAMM' => $elems[0],
                                                             'MEANING' => $elems[1],
                                                             'SYNONYMS' => $syn));
            }
        }
    }
    else {
        $template->SetBranchVars('message', array('MSGHEAD' => $ui['thesaurusMsg']['nothing'],
                                                  'MESSAGE' => '<samp>' . $search . '…</samp>' . $ui['thesaurusMsg']['noresult']));
    }
    if (!$isWordFound and $isModifAllowed and !$isRegEx and $search != '') {
        $template->SetBranchVars('create', $ui['thesNewEntry']);
        $template->UpdateBranchVars('create', array('WORD' => $search));
    }
    
    // extended search in synonyms
    if (isset($_REQUEST['extendsearch']) and $_REQUEST['extendsearch'] == 'ON' and $search != '') {
        $extSearch = $search;
        $extSqlSearch = $sqlSearch;
        if ($isRegEx) {
            // we change the regex pattern if beginning or ending of a word
            $len = mb_strlen($search, 'UTF-8');
            $firstChar = mb_substr($search, 0, 1, 'UTF-8');
            $lastChar = mb_substr($search, $len-1, 1, 'UTF-8');
            if ($firstChar == '^') {
                $extSearch = '(?:^|[#\|])' . mb_substr($extSearch, 1, $len-1, 'UTF-8');
                $extSqlSearch = '(?:^|[#\|])' . mb_substr($extSqlSearch, 1, $len-1, 'UTF-8');
                $len = mb_strlen($extSsearch, 'UTF-8');
            }
            if ($lastChar == '$') {
                $extSearch = mb_substr($extSearch, 0, $len-1, 'UTF-8') . '(?:$|[#\|])';
                $extSqlSearch = mb_substr($extSqlSearch, 0, $len-1, 'UTF-8') . '(?:$|[#\|])';
            }
        }
        // search
        list($ok, $result, $nbOccur) = $dbaThesaurus->searchExt($_GET['prj'], $extSqlSearch, $isRegEx, $oPg2->getOffset(), $nbEntriesByPage);
        if (!$ok) {
            $template->SetBranchVars('message', array('MSGHEAD' => 'DATABASE ERROR',
                                                      'MESSAGE' => 'while extending search'));
        }
        else {
            if ($nbOccur > 0) {
                function setSynsSubentryVars (&$template, &$syns) {
                    $elems = explode('|', $syns, 3);
                    $syn = '';
                    if (isset($elems[2])) {
                        $syn = str_replace(' ', '&nbsp;', $elems[2]);
                        $syn = str_replace('|', ' | ', $syn);
                    }
                    $template->SetBranchVars('synonymof.subentry.cat', array('GRAMM' => $elems[0],
                                                                             'MEANING' => $elems[1],
                                                                             'SYNONYMS' => $syn));
                }
                // pagination
                $ui['thesExtSearch']['PAGES'] = $oPg2->createLinks($nbOccur, '#extendedres');
                
                // prepare subentries for displaying
                $template->SetBranchVars('synonymof', $ui['thesExtSearch']);
                foreach ($result as $data) {
                    $wordcell = ($isModifAllowed) ? '<a href="synonyms.php?prj=' . $_GET['prj'] . '&amp;id_word=' . $data['id_word'] . '">' . $data['word'] . '</a>' : $data['word'];
                    $template->SetBranchVars('synonymof.subentry', array('WORD' => $wordcell));
                    $synsArray = explode('##', $data['syn'], $data['nbclass']);
                    if ($isRegEx) {
                        // search for a regular expression
                        $pattern = '`' . $extSearch . '`';
                        for ($i=0; $i < $data['nbclass'] ; $i++) {
                            if (@preg_match($pattern, $synsArray[$i], $matches)) {
                                $syns = @preg_replace($pattern, '<b><u>$0</u></b>', $synsArray[$i]);
                                setSynsSubentryVars($template, $syns);
                            }
                        }
                    }
                    else {
                        // search for a simple string
                        for ($i=0; $i < $data['nbclass'] ; $i++) {
                            if (strpos($synsArray[$i], $extSearch) !== FALSE) {
                                $syns = str_replace($extSearch, '<b><u>' . $search . '</u></b>', $synsArray[$i]);
                                setSynsSubentryVars($template, $syns);
                            }
                        }
                    }
                }
            }
        }
    }
}
else {
    // presentation
    $template->SetBranchVars('message', array('MSGHEAD' => $ui['thesaurusMsg']['instructions'],
                                              'MESSAGE' => $ui['thesaurusMsg']['instructionstxt']));
    $template->SetBranchVars('entry', $ui['thesEntry']);
    for ($i=1; $i < 7; $i++) {
        $template->SetBranchVars('entry.line', array('GRAMM' => $ui['thesEntryFields']['GRAMM'] . ' ' . $i,
                                                     'MEANING' => $ui['thesEntryFields']['MEANING'] . ' ' . $i,
                                                     'SYNONYMS' => $ui['thesEntryFields']['SYNONYMS']));
    }
}

// display
$template->Grow('./html/div.head.tpl.html');
$template->Grow('./html/thesaurus.div.tpl.html');
$template->Grow('./html/div.foot.tpl.html');

?>
