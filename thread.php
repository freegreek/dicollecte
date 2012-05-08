<?php

require('./config/config.php');
require('./code/init.php');
require('./code/template.class.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-users.class.php');
require('./code/dbaccess-messages.class.php');
//require('./code/pagination.class.php');

$ui = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);

$db = new Database();
$dbaUsers = new dbaccessUsers($db);
$dbaMessages = new dbaccessMessages($db);

$template = new Template();
$template->SetPageVars($_GET['prj'], 'forum', $dbaUsers);

if ($db->connx) {
    if (isset($_GET['t'])) {
        $rank = $dbaUsers->getUserRankFor($_GET['prj']);
        // forum selected
        $template->SetBranchVars('script', array('FILENAME' => './js/widgets_control.js'));
        $template->SetBranchVars('script', array('FILENAME' => './js/ajaxcmd.js'));
        $template->SetBranchVars('threads', $ui['threads']);
        
        list($ok, $resultT, $resultM) = $dbaMessages->viewThread($_GET['prj'], $_GET['t']);
        if (!$ok) {
            setSysMsg($resultT);
            header(URL_HEADER . 'forum.php?prj=' . $_GET['prj']);
            exit;
        }
        if (count($resultT) > 0) {
            $dataT = $resultT[0];
            $template->SetGlobalVar('_IDTHREAD_', $dataT['id_thread']);
            $template->SetTrunkVars($ui['thread']);
            $template->SetTrunkVar('TAB_TITLE', $dataT['label']);
            $template->SetTrunkVar('PATH', '<a href="forum.php?prj='.$_GET['prj'].'">' . $ui['forums']['FORUMS'] . '</a>'
                                           . ' → <a href="forum.php?prj='.$_GET['prj'].'&amp;f='.$dataT['id_forum'].'">' . $dataT['forumlabel'] . '</a>'
                                           . ' → <a href="thread.php?prj='.$_GET['prj'].'&amp;t='.$dataT['id_thread'].'">' . $dataT['label'] . '</a>');
            $tagimg = ($dataT['tag'] != '?') ? ' style="background-image: url(./img/forum/tag_'.$dataT['tag'].'_big.png); width: 64px; height: 64px;"' : '';
            // notif
            $subcmd = '';
            $notifopacity = '0.33';
            if (isset($_SESSION['id_user'])) {
                list($ok, $res) = $dbaMessages->checkSubscription($_GET['prj'], $_GET['t'], $_SESSION['id_user']);
                $notifopacity = ($res === TRUE) ? '1': '0.33';
                $subcmd = 'switchThreadSubscription(\'{{_PRJ_}}\', {{_IDTHREAD_}});';
            }
            $template->SetTrunkVars(array('LABEL' => $dataT['label'],
                                          'FLOW' => 'flow_'.$dataT['flow'].'_big.png',
                                          'TAG' => $tagimg,
                                          'CLOSEDIMG' => ($dataT['locked']) ? '<img class="imgoverlaybig" src="img/forum/locked_overlay_big.png" alt="" />' : '',
                                          'SOLVEDIMG' => ($dataT['solved']) ? '<img class="imgoverlaybig" src="img/forum/solved_overlay_big.png" alt="" />' : '',
                                          'SUBCMD' => $subcmd,
                                          'NBNOTIF' => $dataT['nbnotif'],
                                          'NOTIFOPAC' => $notifopacity));
            
            if ($rank <= 2 or (isset($_SESSION['id_user']) and $dataT['id_user'] == $_SESSION['id_user'] and !$dataT['locked'])) {
                $template->SetBranchVars('threadbutton', array());
                $template->SetBranchVars('threadform', $ui['threadform']);
                if ($rank <= 2) {
                    $template->SetBranchVars('threadform.flow', $ui['flow']);
                }
            }
            // messages
            setlocale(LC_TIME, $ui['datetime']['locale']);
            foreach ($resultM as $data) {
                $date = strftime($ui['datetime']['dtpattern'], $data['creationdt']);
                $update = ($data['creationdt'] != $data['updatedt']) ? '→ ' . strftime($ui['datetime']['dtpattern'], $data['updatedt']) : '';
                $at = ($rank > 0) ? '' : ' <a href="#" onclick="return changeUserMsg(\'' . $_GET['prj']. "', " . $data['id_msg'] . ', \'id user\')">@</a>';
                $template->SetBranchVars('msg', array('IDUSER' => $data['id_user'],
                                                      'LOGIN' => $data['login'],
                                                      'AT' => $at,
                                                      'DATE' => $date,
                                                      'UPDATE' => $update,
                                                      'IDMSG' => $data['id_msg'],
                                                      'MSGNUM' => $data['msgnum'],
                                                      'MSG' => $data['msg']));
                if ($rank <= 2 or (isset($_SESSION['id_user']) and $data['id_user'] == $_SESSION['id_user'] and !$dataT['locked'])) {
                    $template->SetBranchVars('msg.msgact', array('IDMSG' => $data['id_msg'],
                                                                 'MSGNUM' => $data['msgnum']));
                }
                if (isset($_COOKIE['login'])) {
                    $template->SetBranchVars('msg.msgcit', array('IDMSG' => $data['id_msg']));
                }
            }
            $template->SetGlobalVar('_CONFIRMDELETEMSG_', $ui['msgGlob']['_CONFIRMDELETEMSG_']);
            if ($rank <= 2 or (isset($_COOKIE['login']) and !$dataT['locked'])) {
                $template->SetBranchVars('newmsg', $ui['newmsg']);
            }
            if ($rank <= 2) {
                $template->SetBranchVars('script', array('FILENAME' => './js/prompt.js'));
                $template->SetBranchVars('admthread', $ui['admthread']);
                $template->UpdateBranchVars('admthread', array('LOCKSELECT' => ($dataT['locked']) ? 'off' : 'on',
                                                               'SOLVEDSELECT' => ($dataT['solved']) ? 'undo' : 'solved'));
            }
        }
    }
}

// display
$template->Grow('./html/div.head.tpl.html');
$template->Grow('./html/thread.div.tpl.html');
$template->Grow('./html/div.foot.tpl.html');

?>
