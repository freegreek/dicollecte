<?php

require('./config/config.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-comments.class.php');
require('./code/dbaccess-users.class.php');
require('./code/template.class.php');
require('./code/pagination.class.php');

$ui = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);

$db = new Database();
$dbaUsers = new dbaccessUsers($db);
$dbaComments = new dbaccessComments($db);

$template = new Template();
$template->SetPageVars($_GET['prj'], 'comments', $dbaUsers);
$template->SetTrunkVar('TAB_TITLE', $ui['tabs']['comments']);

if ($db->connx) {
    // comment selection
    $urlbase = 'comments.php?prj=' . $_GET['prj'];
    if (isset($_GET['id_user'])) { $urlbase .= '&amp;id_user=' . $_GET['id_user']; }
    if (isset($_GET['prop_user'])) { $urlbase .= '&amp;prop_user=' . $_GET['prop_user'] . '&amp;user=' . $_GET['user']; }
    
    // system comments
    $url = (isset($_GET['hidemycom'])) ? $urlbase . '&amp;hidemycom=Y' : $urlbase;
    if (isset($_GET['hidesyscom'])) {
        $selectsys = '<b style="color: #FDD">' . $ui['commentsVars']['hidden'] . '</b> | <a href="' . $url . '">' . $ui['commentsVars']['show'] . '</a>';
        $urlbase .= '&amp;hidesyscom=Y';
        $hideSysCom = TRUE;
    }
    else {
        $selectsys = '<b style="color: #DFD">' . $ui['commentsVars']['shown'] . '</b> | <a href="' . $url . '&amp;hidesyscom=Y">' . $ui['commentsVars']['hide'] . '</a>';
        $hideSysCom = FALSE;
    }
    $template->SetTrunkVar('SELECTSYS', $selectsys);
    
    // viewer comments
    if (isset($_COOKIE['login']) and !isset($_GET['id_user'])) {
        $template->SetBranchVars('mycom', $ui['mycomments']);
        $url = (isset($_GET['hidesyscom'])) ? $urlbase . '&amp;hidesyscom=Y' : $urlbase;
        if (isset($_GET['hidemycom'])) {
            $selectme = '<b style="color: #FDD">' . $ui['commentsVars']['hidden'] . '</b> | <a href="' . $url . '">' . $ui['commentsVars']['show'] . '</a>';
            $urlbase .= '&amp;hidemycom=Y';
            $hideIdUserCom = $_SESSION['id_user'];
        }
        else {
            $selectme = '<b style="color: #DFD">' . $ui['commentsVars']['shown'] . '</b> | <a href="' . $url . '&amp;hidemycom=Y">' . $ui['commentsVars']['hide'] . '</a>';
            $hideIdUserCom = NULL;
        }
        $template->UpdateBranchVars('mycom', array('SELECTME' => $selectme));
    }
    else {
        $hideIdUserCom = NULL;
    }
    
    
    // read comments
    $nbElemsByPage = 50;
    $oPg = new Pagination('comments.php', 'page', $nbElemsByPage);
    $idUserSelected = (isset($_GET['id_user'])) ? $_GET['id_user'] : NULL;
    $propUser = (isset($_GET['prop_user'])) ? $_GET['prop_user'] : NULL;
    list($ok, $result, $nbOccur) = $dbaComments->select($_GET['prj'], $oPg->getOffset(), $nbElemsByPage, $hideSysCom, $hideIdUserCom, $idUserSelected, $propUser);
    if (!$ok) {
        setSysMsg($result);
        header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
        exit;
    }
    
    $label = (!isset($_GET['id_user'])) ? $ui['commentsVars']['label'] : sprintf($ui['commentsVars']['label_id'], $result[0]['login']);
    if (isset($_GET['prop_user'])) { $label = sprintf($ui['commentsVars']['label_propuser'], $_GET['user']); }
    $template->SetTrunkVars($ui['comments']);
    $template->SetTrunkVar('LABEL', $label);
    
    if ($nbOccur > 0) {
        $template->SetTrunkVar('PAGES', $oPg->createLinks($nbOccur));
        
        // comments
        require('./config/img_vars.php');
        setlocale(LC_TIME, $ui['datetime']['locale']);
        foreach ($result as $data) {
            $date = strftime($ui['datetime']['dtpattern'], $data['datetime']);
            if ($data['flags'] != '') $flags = '<dfn>/</dfn>' . $data['flags']; else $flags = '';
            $template->SetBranchVars('comment', array('IDPROP' => $data['id_prop'],
                                                      'IMGACTION' => $uiImgActionSmall[$data['action']],
                                                      'LEMMA' => $data['lemma'],
                                                      'FLAGS' => $flags,
                                                      'IDUSER' => $data['id_user'],
                                                      'LOGIN' => $data['login'],
                                                      'COMMENT' => $data['comment'],
                                                      'DATE' => $date));
        }
    }
    else {
        if (isset($_GET['id_user'])) {
            setSysMsg('_nocomments');
            header(URL_HEADER . 'member.php?prj=' . $_GET['prj'] . '&id_user=' . $_GET['id_user']);
            exit;
        }
        $template->SetBranchVars('message', array('MSGHEAD' => $ui['commentsMsg']['nothing'],
                                                  'MESSAGE' => $ui['commentsMsg']['void']));
    }
}
else {
    $template->SetBranchVars('message', array('MSGHEAD' => $ui['sysMsg']['_dberror'],
                                              'MESSAGE' => $ui['sysMsg']['_nobase']));
}

// display
$template->Grow('./html/div.head.tpl.html');
$template->Grow('./html/comments.div.tpl.html');
$template->Grow('./html/div.foot.tpl.html');

?>
