<?php

require('./config/config.php');
require('./code/init.php');
require('./code/template.class.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-users.class.php');
require('./code/dbaccess-messages.class.php');
require('./code/pagination.class.php');

$ui = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);

$db = new Database();
$dbaUsers = new dbaccessUsers($db);
$dbaMessages = new dbaccessMessages($db);

$template = new Template();
$template->SetPageVars($_GET['prj'], 'forum', $dbaUsers);
$template->SetTrunkVar('TAB_TITLE', $ui['tabs']['messages']);

if ($db->connx) {    
    $template->SetBranchVars('threads', $ui['threads']);
    $nbElemsByPage = 10;
    $oPg = new Pagination('messages.php', 'page', $nbElemsByPage);
    $idUserSelected = (isset($_GET['id_user'])) ? $_GET['id_user'] : NULL;
    list($ok, $result, $nbOccur) = $dbaMessages->listMessages($_GET['prj'], $oPg->getOffset(), $nbElemsByPage, $idUserSelected);
    if (!$ok) {
        setSysMsg($result);
        header(URL_HEADER . 'forum.php?prj=' . $_GET['prj']);
        exit;
    }
    if ($nbOccur > 0) {
        $headerlbl = ($idUserSelected) ? sprintf($ui['messages']['usermsg'], $result[0]['login']) : $ui['messages']['allmsg'];
        $template->SetTrunkVar('LABEL', $headerlbl);
        $template->SetTrunkVar('PAGES', $oPg->createLinks($nbOccur));
        setlocale(LC_TIME, $ui['datetime']['locale']);
        foreach ($result as $data) {
            $date = strftime($ui['datetime']['dtpattern'], $data['creationdt']);
            $update = ($data['creationdt'] != $data['updatedt']) ? strftime($ui['datetime']['dtpattern'], $data['updatedt']) . ' ←' : ''; 
            $template->SetBranchVars('msg', array('IDUSER' => $data['id_user'],
                                                  'LOGIN' => $data['login'],
                                                  'DATE' => $date,
                                                  'UPDATE' => $update,
                                                  'IDMSG' => $data['id_msg'],
                                                  'MSG' => $data['msg'],
                                                  'MSGNUM' => $data['msgnum'],
                                                  'IDTHREAD' => $data['id_thread'],
                                                  'THREAD' => $data['label']));
        }
    }
}

// display
$template->Grow('./html/div.head.tpl.html');
$template->Grow('./html/messages.div.tpl.html');
$template->Grow('./html/div.foot.tpl.html');

?>
