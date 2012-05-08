<?php

require('./config/config.php');
require('./code/init.php');
require('./code/template.class.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-users.class.php');
require('./code/dbaccess-forum.class.php');
require('./code/pagination.class.php');

$ui = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);

$db = new Database();
$dbaUsers = new dbaccessUsers($db);
$dbaForum = new dbaccessForum($db);

$template = new Template();
$template->SetPageVars($_GET['prj'], 'forum', $dbaUsers);
$template->SetTrunkVar('TAB_TITLE', $ui['tabs']['forum']);

if ($db->connx) {
    $rank = $dbaUsers->getUserRankFor($_GET['prj']);
    setlocale(LC_TIME, $ui['datetime']['locale']);
    if (isset($_GET['f'])) {
        // forum selected
        $template->SetBranchVars('script', array('FILENAME' => './js/widgets_control.js'));
        $template->SetBranchVars('threads', $ui['threads']);
        $nbElemsByPage = 40;
        $oPg = new Pagination('forum.php', 'page', $nbElemsByPage);
        list($ok, $resultT, $resultF, $nbOccurT) = $dbaForum->listThreads($_GET['prj'], $nbElemsByPage, $oPg->getOffset(), $_GET['f']);
        if (!$ok) {
            setSysMsg($resultT);
            header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
            exit;
        }
        if (count($resultF) > 0) {
            $dataF = $resultF[0]; 
            $path = '<a href="forum.php?prj='.$_GET['prj'].'">' . $ui['forums']['FORUMS'] . '</a>'
                  . ' → <a href="forum.php?prj='.$_GET['prj'].'&amp;f='.$dataF['id_forum'].'">' . $dataF['label'] . '</a>';
            $template->UpdateBranchVars('threads', array('LABEL' => $resultF[0]['label'],
                                                         'DESCR' => $resultF[0]['descr'],
                                                         'PATH' => $path,
                                                         'PAGES' => $oPg->createLinks($nbOccurT)));
            if ($rank <= 2) {
                $template->SetBranchVars('rendelbutton', array());
                $template->SetBranchVars('rendelforum', $ui['admforum']);
                $template->UpdateBranchVars('rendelforum', array('IDFORUM' => $_GET['f']));
            }
            foreach ($resultT as $data) {
                $label = '<a href="thread.php?prj='.$_GET['prj'].'&amp;t='.$data['id_thread'].'"><b>' . $data['label'] . '</b></a><br />'
                       . '<div class="tinytxt">' . strftime($ui['datetime']['dtpattern'], $data['creationdt']) . '</div>';
                $lastedit = '<div class="tinytxt">' . $data['login'] . ', '
                          . '<a href="thread.php?prj='.$_GET['prj'].'&amp;t='.$data['id_thread'].'#msg'.$data['msgnum'].'">'
                          . strftime($ui['datetime']['dtpattern'], $data['updatedt']) . '</a></div>';
                $template->SetBranchVars('threads.thread', array('FLOW' => $data['flow'],
                                                                 'LOCKED' => ($data['locked']) ? '<img class="imgoverlay" src="img/forum/locked_overlay.png" alt="" />' : '',
                                                                 'TAG' => ($data['tag'] != '?') ? ' style="background-image: url(./img/forum/tag_'.$data['tag'].'.png);"' : '',
                                                                 'SOLVED' => ($data['solved']) ? '<img class="imgoverlay" src="img/forum/solved_overlay.png" alt="" />' : '',
                                                                 'LABEL' => $label,
                                                                 'NBMSG' => $data['nbmsg'],
                                                                 'LASTMSG' => $lastedit));
            }
            
            if (isset($_COOKIE['login'])) {
                $template->SetBranchVars('newthread', $ui['newmsg']);
                $template->UpdateBranchVars('newthread', array('IDFORUM' => $_GET['f']));
            }
        }
    }
    else {
        // all forums
        $template->SetBranchVars('forums', $ui['forums']);
        list($ok, $result) = $dbaForum->listForums($_GET['prj']);
        if (!$ok) {
            setSysMsg($result);
            header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
            exit;
        }
        if ($rank <= 2) {
            $template->SetBranchVars('admbutton', array());
            $template->SetBranchVars('createforum', $ui['admforum']);
        }
        
        $aForums = array();
        foreach ($result as $data) {
            $label = '<a href="forum.php?prj='.$_GET['prj'].'&amp;f='.$data['id_forum'].'"><b>' . $data['label'] . '</b></a><br />' . $data['descr'];
            $lastedit = ($data['nbthreads'] == 0) ? '' : '<div class="tinytxt">' . $data['login'] . ', '
                                                         . '<a href="thread.php?prj='.$_GET['prj'].'&amp;t='.$data['id_thread'].'#msg'.$data['msgnum'].'">'
                                                         . strftime($ui['datetime']['dtpattern'], $data['updatedt']) . '</a></div>';
            $template->SetBranchVars('forums.forum', array('ICON' => '',
                                                           'LABEL' => $label,
                                                           'NBTHD' => $data['nbthreads'],
                                                           'LASTMSG' => $lastedit));
            $aForums[$data['id_forum']] = $data['label'];
        }
        
        // all threads
        $nbElemsByPage = 15;
        $oPg = new Pagination('forum.php', 'page', $nbElemsByPage);
        list($ok, $resultT, $resultF, $nbOccurT) = $dbaForum->listThreads($_GET['prj'], $nbElemsByPage, $oPg->getOffset());
        if (!$ok) {
            setSysMsg($result);
            header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
            exit;
        }
        $template->SetBranchVars('threads', $ui['threads']);
        $template->UpdateBranchVars('threads', array('PAGES' => $oPg->createLinks($nbOccurT, '#tlist')));
        foreach ($resultT as $data) {
            $label = '<a href="thread.php?prj='.$_GET['prj'].'&amp;t='.$data['id_thread'].'"><b>' . $data['label'] . '</b></a><br />'
                   . '<div class="tinytxt">→ <a href="forum.php?prj='.$_GET['prj'].'&amp;f='.$data['id_forum'].'">' . $aForums[$data['id_forum']]  
                   . '</a> • ' . strftime($ui['datetime']['dtpattern'], $data['creationdt']) . '</div>';
            $lastedit = '<div class="tinytxt">' . $data['login'] . ', '
                      . '<a href="thread.php?prj='.$_GET['prj'].'&amp;t='.$data['id_thread'].'#msg'.$data['msgnum'].'">'
                      . strftime($ui['datetime']['dtpattern'], $data['updatedt']) . '</a></div>';
            $template->SetBranchVars('threads.thread', array('FLOW' => $data['flow'],
                                                             'LOCKED' => ($data['locked']) ? '<img class="imgoverlay" src="img/forum/locked_overlay.png" alt="" />' : '',
                                                             'TAG' => ($data['tag'] != '?') ? ' style="background-image: url(./img/forum/tag_'.$data['tag'].'.png);"' : '',
                                                             'SOLVED' => ($data['solved']) ? '<img class="imgoverlay" src="img/forum/solved_overlay.png" alt="" />' : '',
                                                             'LABEL' => $label,
                                                             'NBMSG' => $data['nbmsg'],
                                                             'LASTMSG' => $lastedit));
        }
    }
}

// display
$template->Grow('./html/div.head.tpl.html');
$template->Grow('./html/forum.div.tpl.html');
$template->Grow('./html/div.foot.tpl.html');

?>
