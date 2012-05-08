<?php

require('./config/config.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-flags.class.php');
require('./code/dbaccess-propositions.class.php');
require('./code/dbaccess-users.class.php');
require('./code/template.class.php');
require('./html/' . $_GET['prj'] . '/project_vars.php');

$ui = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);

if (!(isset($_GET['id']) and preg_match('`^[0-9]+$`', $_GET['id']))) {
    setSysMsg('_wrongurl');
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

$db = new Database();
if (!$db->connx) {
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

$dbaUsers = new dbaccessUsers($db);
$dbaFlags = new dbaccessFlags($db);
$dbaPropositions = new dbaccessPropositions($db);

$template = new Template();
$template->SetPageVars($_GET['prj'], 'proposition', $dbaUsers);
$template->SetBranchVars('script', array('FILENAME' => './js/widgets_control.js'));
$template->SetBranchVars('script', array('FILENAME' => './js/ajaxcmd.js'));
$template->SetBranchVars('script', array('FILENAME' => './lib/autosuggest/autosuggest.js'));
$template->SetBranchVars('script', array('FILENAME' => './html/'. $_GET['prj'] .'/tags.js'));

$langlinks = file_get_contents('./html/' . $_GET['prj'] . '/langlinks.div.html');
$template->SetTrunkVar('LANGLINKS', $langlinks);

$cmd = (isset($_GET['cmd'])) ? $_GET['cmd'] : NULL;
$tab = (isset($_GET['tab']) and strlen($_GET['tab']) === 1) ? $_GET['tab'] : 'E';
$value = (isset($_GET['value']) and strlen($_GET['value']) === 1) ? $_GET['value'] : FALSE;
switch ($cmd) {
    case 'prev': list($ok, $result) = $dbaPropositions->selectNextProposition($_GET['prj'], $_GET['id'], FALSE, $value, $tab); break;
    case 'next': list($ok, $result) = $dbaPropositions->selectNextProposition($_GET['prj'], $_GET['id'], TRUE, $value, $tab); break;
    default:     list($ok, $result) = $dbaPropositions->selectProposition($_GET['prj'], $_GET['id']);
}
if (!$ok) {
    setSysMsg($result);
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}
if (count($result) > 0) {
    require('./config/img_vars.php');
    require('./code/entry.class.php');
    $rank = $dbaUsers->getUserRankFor($_GET['prj']);
    $data = $result[0];
    $template->SetGlobalVar('_IDPROP_', $data['id_prop']);
    $template->SetTrunkVar('TAB_TITLE', $data['action'] . ' ' . $data['lemma']);
    $entry = new Entry($data);
    switch ($data['action']) {
        case '+':
            $action = $ui['propLabelVars']['add'];
            break;
        case '-':
            $action = ($data['id_entry'] == NULL) ? $ui['propLabelVars']['del'] : '<a href="entry.php?prj=' . $_GET['prj'] . '&amp;id=' . $data['id_entry'] . '">' . $ui['propLabelVars']['del'] . '</a>';
            break;
        case '>':
            $action = ($data['id_entry'] == NULL) ? $ui['propLabelVars']['modif'] : '<a href="entry.php?prj=' . $_GET['prj'] . '&amp;id=' . $data['id_entry'] . '">' . $ui['propLabelVars']['modif'] . '</a>';
            break;
    }
    $login = '<a href="member.php?prj=' . $_GET['prj'] . '&amp;id_user=' . $data['id_user'] . '">' . $data['login'] . '</a>';
    setlocale(LC_TIME, $ui['datetime']['locale']);
    $date = strftime($ui['datetime']['dpattern'], $data['date']);
    $fd = (isset($_GET['fd'])) ? 2 : $project['flexionsDepth'];
    $entry->createFlexions ($dbaFlags, $_GET['prj'], $project, 0, $fd);
    $allflexions = (isset($_COOKIE['login']) and $entry->is2lvlFlexions) ? '<a href="proposition.php?prj=' . $_GET['prj'] . '&amp;id=' . $data['id_prop'] . '&amp;fd=2">[' . $ui['entryVars']['allflexions'] . ']</a>' : '';
    $template->SetBranchVars('prop', $ui['proposition']);
    $subcmd = '';
    $notifopacity = '0.33';
    if (isset($_SESSION['id_user'])) {
        list($ok, $res) = $dbaPropositions->checkSubscription($_GET['prj'], $data['id_prop'], $_SESSION['id_user']);
        $notifopacity = ($res === TRUE) ? '1': '0.33';
        $subcmd = 'switchPropSubscription(\'{{_PRJ_}}\', {{_IDPROP_}});';
    }
    $template->UpdateBranchVars('prop', array('ACTIONIMG' => $uiImgAction[$data['action']],
                                              'VALUEIMG' => $uiImgValue[$data['value']],
                                              'ACTION' => $action,
                                              'LABEL' => $data['lemma'],
                                              'LOGIN' => $login,
                                              'DATE' => $date,
                                              'ENTRY' => $entry->createHtmlPresentation(),
                                              'NOTIFOPAC' => $notifopacity,
                                              'SUBCMD' => $subcmd,
                                              'NBNOTIF' => $data['nbnotif'],
                                              'NBFLEXIONS' => count($entry->flexions) . ' ' . $ui['entry']['flexions'],
                                              'ALLFLEXIONS' => $allflexions
                                             ));
    $template->SetBranchVars('nav', $ui['propNav']);
    $template->UpdateBranchVars('nav', array('DATETIME' => $data['date'],
                                             'TAB' => '&amp;tab='.$data['tab']
                                            ));
    
    // custom links
    $links = $entry->createLinks();
    $template->UpdateBranchVars('prop', array('SRCLINKS' => $links, 'CUSTOMLINKS' => str_replace('%s', htmlentities($data['lemma'], ENT_COMPAT, 'UTF-8'), $links)));
    
    // flexions
    foreach ($entry->flexions as $flexion) {
        if (isset($prjDic[$flexion[2]])) {
            $flexdic = ($flexion[2] != '*') ? ' <samp>[' . $prjDicAbr[$flexion[2]] . ']</samp>' : '';
        }
        else {
            $flexdic = ' <samp>[' . $ui['sysMsg']['_error'] . ']</samp>';
        }
        $template->SetBranchVars('prop.flexion', array('LEMMA' => $flexion[0] . ' &nbsp; <a href="http://www.dicollecte.org/dictionary.php?prj=fr&amp;lemma='.$flexion[0].'">→</a>',
                                                       'MORPH' => $flexion[1] . $flexdic));
    }

    // comments
    if ($data['nbcomments'] > 0) {
        require('./code/dbaccess-comments.class.php');
        $dbaComments = new dbaccessComments($db);
        list($ok, $result, $nbOccur) = $dbaComments->selectByProp($_GET['prj'], $data['id_prop']);
        if ($ok) {
            if ($nbOccur > 0) {
                $template->SetGlobalVar('_CONFIRMDELETECOM_', $ui['commentGlob']['_CONFIRMDELETECOM_']);
                foreach ($result as $datac) {
                    $date = strftime($ui['datetime']['dtpattern'], $datac['datetime']);
                    $template->SetBranchVars('comment', array('IDUSER' => $datac['id_user'],
                                                              'IDCOM' => $datac['id_com'],
                                                              'LOGIN' => $datac['login'],
                                                              'COMMENT' => $datac['comment'],
                                                              'DATE' => $date));
                    if ($rank <= 2 or (isset($_SESSION['id_user']) and $datac['id_user'] == $_SESSION['id_user'])) {
                        $template->SetBranchVars('comment.commactions', array('IDCOM' => $datac['id_com']));
                        if (!$datac['autocom']) {
                            $template->SetBranchVars('comment.commedit', $ui['editComment']);
                            $template->UpdateBranchVars('comment.commedit', array('IDCOM' => $datac['id_com'],
                                                                                  'MAXLENGTH' => $ui['newComment']['MAXLENGTH']));
                        }
                    }
                }
            }
        }
        else {
            $template->SetBranchVars('message', array('MSGHEAD' => 'DATABASE ERROR',
                                                      'MESSAGE' => 'Sorry, something went wrong.'));
        }
    }
    // your comment
    if (isset($_COOKIE['login']) and ($data['tab'] == 'E' or $data['tab'] == 'R')) {
        $template->SetBranchVars('yourcomment', $ui['newComment']);
    }

    // edition
    if ($data['tab'] == 'E' and ($rank <= 5 or ($rank <= 7 and isset($_SESSION['id_user']) and $_SESSION['id_user'] == $data['id_user']))) {
        $template->SetBranchVars('prop.priority', array());
        $template->SetTrunkVar('STARVALUE', $data['prio']);
        if ($data['action'] == '+' or $data['action'] == '>') {
            require('./config/db_vars.php');
            $template->SetBranchVars('prop.editbutton', $ui['propEditButton']);
            $template->SetBranchVars('prop.edit', $ui['propEdit']);
            $isUDisabled = ($rank > 3) ? 'disabled="disabled"' : '';
            $template->UpdateBranchVars('prop.edit', array('ENTRYFORM' => $entry->createHtmlForm(), 'UDISABLED' => $isUDisabled));
        }
    }
    // proposition under consideration
    if ($data['tab'] == 'E' and $rank <= 5) {
        $template->SetBranchVars('action', $ui['propAction']);
        $template->SetBranchVars('action.evaluate', $ui['propActionEval']);
        $template->SetBranchVars('action.forbidbutton', array('FORBID' => $ui['propActionVars']['forbid'],
                                                              'CONFIRMFORBID' => $ui['propActionVars']['confirmforbid']));
        $template->SetBranchVars('action.trashbutton', array('TRASH' => $ui['propActionVars']['trash'],
                                                             'CONFIRMTRASH' => $ui['propActionVars']['confirmtrash']));
        if ($data['action'] == '-') {
            $template->SetBranchVars('action.changeaction', array('CHANGEACTION' => $ui['propActionVars']['changetomodif'],
                                                                  'CONFIRMCHANGE' => $ui['propActionVars']['confirmchangetomodif']));
        }
        elseif ($data['action'] == '>') {
            $template->SetBranchVars('action.changeaction', array('CHANGEACTION' => $ui['propActionVars']['changetodel'],
                                                                  'CONFIRMCHANGE' => $ui['propActionVars']['confirmchangetodel']));
        }
        if ($rank <= 3 or $rank <= $project['dictDirectEdition']) {
            $template->SetBranchVars('action.applybutton', array('APPLYDUBIOUS' => $ui['propActionVars']['applydubious'],
                                                                 'CONFIRMAPPLYDUBIOUS' => $ui['propActionVars']['confirmapplydubious'],
                                                                 'VALIDAPPLY' => $ui['propActionVars']['validapply'],
                                                                 'CONFIRMVALIDAPPLY' => $ui['propActionVars']['confirmvalidapply']
                                                                 ));
        }
    }
    // rejected proposition
    if ($data['tab'] == 'R' and $rank <= 5) {
        $template->SetBranchVars('action', $ui['propAction']);
        if ($data['action'] != '>' and $data['action'] != '-' and $data['value'] != 'I') {
            $template->SetBranchVars('action.reevalbutton', array('REEVAL' => $ui['propActionVars']['reeval'],
                                                                  'CONFIRMREEVAL' => $ui['propActionVars']['confirmreeval']));
        }
        $template->SetBranchVars('action.trashbutton', array('TRASH' => $ui['propActionVars']['trash'],
                                                             'CONFIRMTRASH' => $ui['propActionVars']['confirmtrash']));
    }
    // proposition in the basket
    if ($data['tab'] == 'T' and $rank <= 5) {
        $template->SetBranchVars('action', $ui['propAction']);
        if ($data['value'] != 'I' and $data['action'] != '>' and $data['action'] != '-') {
            $template->SetBranchVars('action.reevalbutton', array('REEVAL' => $ui['propActionVars']['reeval'],
                                                                  'CONFIRMREEVAL' => $ui['propActionVars']['confirmreeval']));
        }
        $template->SetBranchVars('action.forbidbutton', array('FORBID' => $ui['propActionVars']['forbid'],
                                                              'CONFIRMFORBID' => $ui['propActionVars']['confirmforbid']));
        if ($rank <= 2) {
            $template->SetBranchVars('action.erasebutton', array('ERASE' => $ui['propActionVars']['erase'],
                                                                 'CONFIRMERASE' => $ui['propActionVars']['confirmerase']));
        }
    }
}
else {
    $template->SetBranchVars('message', array('MSGHEAD' => $ui['propMsg']['nothing'],
                                              'MESSAGE' => $ui['propMsg']['nothingmsg']));
}

// display
$template->Grow('./html/div.head.tpl.html');
$template->Grow('./html/proposition.div.tpl.html');
$template->Grow('./html/div.foot.tpl.html');

?>
