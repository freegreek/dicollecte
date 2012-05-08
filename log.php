<?php

require('./config/config.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-users.class.php');
require('./code/dbaccess-log.class.php');
require('./code/template.class.php');
require('./code/pagination.class.php');

$ui = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);

if (isset($_GET['id_user']) and !preg_match('`^[0-9]+$`', $_GET['id_user'])) {
    setSysMsg('_wrongurl');
    header(URL_HEADER . 'log.php?prj=' . $_GET['prj']);
    exit;
}

$db = new Database();
$dbaUsers = new dbaccessUsers($db);

$template = new Template();
$template->SetPageVars($_GET['prj'], 'log', $dbaUsers);
$template->SetTrunkVar('TAB_TITLE', $ui['tabs']['log']);

$template->SetTrunkVars($ui['log']);

if ($db->connx) {
    $cat = (isset($_GET['cat'])) ? $_GET['cat'] : NULL;
    $nbElemsByPage = 100;
    $oPg = new Pagination('log.php', 'page', $nbElemsByPage);
    
    $id_user = (isset($_GET['id_user'])) ? $_GET['id_user'] : NULL;
    $dbaLog = new dbaccessLog($db);
    list($ok, $result, $nbOccur) = $dbaLog->select($_GET['prj'], $oPg->getOffset(), $nbElemsByPage, $cat, $id_user);
    if (!$ok) {
        setSysMsg($result);
        header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
        exit;
    }
    setlocale(LC_TIME, $ui['datetime']['locale']);
    if (isset($_GET['cat']) and $_GET['cat'] == 'A') {
        // announces
        $template->SetBranchVars('announces', $ui['announces']);
        $rank = $dbaUsers->getUserRankFor($_GET['prj']);
        if ($rank <= 2) {
            $template->SetBranchVars('script', array('FILENAME' => './js/widgets_control.js'));
            $template->SetBranchVars('newann', $ui['newAnn']);
        }
        if ($nbOccur > 0) {
            $template->SetTrunkVar('PAGES', $oPg->createLinks($nbOccur));
            foreach ($result as $data) {
                $template->SetBranchVars('ann', array('IDLOG' => $data['id_log'],
                                                      'ANNOUNCE' => $data['label'],
                                                      'IDUSER' => $data['id_user'],
                                                      'LOGIN' => $data['login'],
                                                      'DATETIME' => strftime($ui['datetime']['dtpattern'], $data['datetime'])
                                                     ));
                if ($rank <= 2) {
                    $template->SetBranchVars('ann.actions', array('IDLOG' => $data['id_log']));
                    $template->SetBranchVars('ann.edit', array('IDLOG' => $data['id_log']));
                }
                $template->SetGlobalVars($ui['annGlobal']);
            }
        }
        else {
            $template->SetBranchVars('log', array('LABEL' => $ui['logMsg']['noAnnounce']));
        }
    }
    else {
        // common log
        $template->SetBranchVars('table', $ui['logTable']);
        if ($nbOccur > 0) {
            require('./config/img_vars.php');
            $template->SetTrunkVar('PAGES', $oPg->createLinks($nbOccur));
            $catLinks = array('D' => '<a href="entry.php?prj=' . $_GET['prj'] . '&amp;id=%d">' . $ui['logCategory']['D'] . '</a>',
                              'E' => '<a href="entry.php?prj=' . $_GET['prj'] . '&amp;id=%d">' . $ui['logCategory']['E'] . '</a>',
                              'P' => '<a href="proposition.php?prj=' . $_GET['prj'] . '&amp;id=%d">' . $ui['logCategory']['P'] . '</a>',
                              'T' => '<a href="synonyms.php?prj=' . $_GET['prj'] . '&amp;id_word=%d">' . $ui['logCategory']['T'] . '</a>',
                              'N' => '<a href="entry.php?prj=' . $_GET['prj'] . '&amp;id=%d">' . $ui['logCategory']['N'] . '</a>',
                              'Y' => '<a href="synset.php?prj=' . $_GET['prj'] . '&amp;id=%d">' . $ui['logCategory']['Y'] . '</a>',
                              'A' => $ui['logCategory']['A'],
                              'S' => $ui['logCategory']['S']);
            foreach ($result as $data) {
                $category = ($data['id'] != 0) ? sprintf($catLinks[$data['cat']], $data['id']) : $ui['logCategory'][$data['cat']];
                $template->SetBranchVars('table.log', array('CATEGORY' => $category,
                                                            'ACTION' => $uiImgActionSmall[$data['action']],
                                                            'LABEL' => $data['label'],
                                                            'USER' => $data['login'],
                                                            'DATETIME' => strftime($ui['datetime']['dtpatternsmall'], $data['datetime'])
                                                           ));
            }
        }
        else {
            $template->SetBranchVars('table.log', array('LABEL' => $ui['logMsg']['nothingtxt']));
        }
    }
}

// display
$template->Grow('./html/div.head.tpl.html');
$template->Grow('./html/log.div.tpl.html');
$template->Grow('./html/div.foot.tpl.html');

?>
