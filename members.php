<?php

require('./config/config.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-users.class.php');
require('./code/pagination.class.php');
require('./code/template.class.php');

$ui = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);

$db = new Database();
$dbaUsers = new dbaccessUsers($db);

$template = new Template();
$template->SetPageVars($_GET['prj'], 'members', $dbaUsers);
$template->SetTrunkVar('TAB_TITLE', $ui['tabs']['members']);

$template->SetTrunkVars($ui['users']);

if ($db->connx) {
    $nbEntriesByPage = 200;
    $oPg = new Pagination('members.php', 'page', $nbEntriesByPage);
    
    list($ok, $result, $nbOccur) = $dbaUsers->listMembers($_GET['prj'], $oPg->getOffset(), $nbEntriesByPage);
    if (!$ok) {
        setSysMsg($result);
        header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
        exit;
    }
    if ($nbOccur > 0) {
        if ($nbOccur > $nbEntriesByPage) {
            $template->SetTrunkVar('PAGES', $oPg->createLinks($nbOccur));
        }
        foreach ($result as $data) {
            $nbvalid = ($data['nbpropval'] == 0) ? '0' : '<a href="member.php?prj=' . $_GET['prj'] . '&amp;tab=E&amp;id_user=' . $data['id_user'] . '&amp;order=V">' . $data['nbpropval'] .'</a>';
            $nbpropok = ($data['nbpropok'] == 0) ? '0' : '<a href="member.php?prj=' . $_GET['prj'] . '&amp;tab=T&amp;id_user=' . $data['id_user'] . '">'. $data['nbpropok'] .'</a>';
            $nbcomments = ($data['nbcomments'] == 0) ? '0' : '<a href="comments.php?prj=' . $_GET['prj'] . '&amp;id_user=' . $data['id_user'] . '">' . $data['nbcomments'] .'</a>';
            $nbadddict = ($data['nbadddict'] == 0) ? '0' : '<a href="dictionary.php?prj=' . $_GET['prj'] . '&amp;lemma=&amp;id_user=' . $data['id_user'] . '">' . $data['nbadddict'] .'</a>';
            $nbactdict = ($data['nbactdict'] == 0) ? '0' : '<a href="log.php?prj=' . $_GET['prj'] . '&amp;cat=D&amp;id_user=' . $data['id_user'] . '">' . $data['nbactdict'] .'</a>';
            $nbnotes = ($data['nbnotes'] == 0) ? '0' : '<a href="log.php?prj=' . $_GET['prj'] . '&amp;cat=N&amp;id_user=' . $data['id_user'] . '">' . $data['nbnotes'] .'</a>';
            $nbactthes = ($data['nbactthes'] == 0) ? '0' : '<a href="log.php?prj=' . $_GET['prj'] . '&amp;cat=T&amp;id_user=' . $data['id_user'] . '">' . $data['nbactthes'] .'</a>';
            $nbmsg = ($data['nbmsg'] == 0) ? '0' : '<a href="messages.php?prj=' . $_GET['prj'] . '&amp;id_user=' . $data['id_user'] . '">' . $data['nbmsg'] .'</a>';
            $emailnotif = ($data['emailnotif']) ? '<img src="./img/tag_integrated.png" alt="*" />' : '';
            $template->SetBranchVars('user', array('ID' => $data['id_user'],
                                                   'LOGIN' => $data['login'],
                                                   'RANK' => $ui['rank'][$data['rk']],
                                                   'NBTOEVAL' => $data['nbpropev']  . ' / ' . $data['nbprop'],
                                                   'NBVALID' => $nbvalid,
                                                   'NBINTEGR' => $nbpropok,
                                                   'NBCOMMENTS' => $nbcomments,
                                                   'NBADDDICT' => $nbadddict,
                                                   'NBACTDICT' => $nbactdict,
                                                   'NBNOTES' => $nbnotes,
                                                   'NBACTTHES' => $nbactthes,
                                                   'NBMSG' => $nbmsg,
                                                   'EMAILNOTIF' => $emailnotif));
        }
    }
}

// display
$template->Grow('./html/div.head.tpl.html');
$template->Grow('./html/members.div.tpl.html');
$template->Grow('./html/div.foot.tpl.html');

?>
