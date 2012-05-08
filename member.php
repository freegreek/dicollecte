<?php

require('./config/config.php');
require('./code/init.php');
require('./code/template.class.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-propositions.class.php');
require('./code/dbaccess-users.class.php');
require('./code/pagination.class.php');
require('./html/' . $_GET['prj'] . '/project_vars.php');

$ui = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);

if (!(isset($_GET['id_user']) and preg_match('`^[0-9]+$`', $_GET['id_user']))) {
    setSysMsg('_wrongurl');
    header(URL_HEADER . 'members.php?prj=' . $_GET['prj']);
    exit;
}

$db = new Database();
if (!$db->connx) {
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

$dbaUsers = new dbaccessUsers($db);
$dbaPropositions = new dbaccessPropositions($db);

$template = new Template();
$template->SetPageVars($_GET['prj'], 'user', $dbaUsers);



list($ok, $result) = $dbaUsers->selectMember($_GET['id_user'], $_GET['prj']);
if (!$ok) {
    setSysMsg($result);
    header(URL_HEADER . 'members.php?prj=' . $_GET['prj']);
    exit;
}

$data = $result[0];
$template->SetTrunkVar('TAB_TITLE', $data['login']);
$template->SetBranchVars('user', $ui['members']);
$template->UpdateBranchVars('user', $ui['users']);
$template->SetTrunkVars($ui['iconsLabel']);


$rank = $dbaUsers->getUserRankFor($_GET['prj']);
$nbcomments = ($data['nbcomments'] == 0) ? '0' : '<a href="comments.php?prj=' . $_GET['prj'] . '&amp;id_user=' . $data['id_user'] . '">' . $data['nbcomments'] .'</a>';
$nbadddict = ($data['nbadddict'] == 0) ? '0' : '<a href="dictionary.php?prj=' . $_GET['prj'] . '&amp;lemma=&amp;id_user=' . $data['id_user'] . '">' . $data['nbadddict'] .'</a>';
$nbactdict = ($data['nbactdict'] == 0) ? '0' : '<a href="log.php?prj=' . $_GET['prj'] . '&amp;cat=D&amp;id_user=' . $data['id_user'] . '">' . $data['nbactdict'] .'</a>';
$nbnotes = ($data['nbnotes'] == 0) ? '0' : '<a href="log.php?prj=' . $_GET['prj'] . '&amp;cat=N&amp;id_user=' . $data['id_user'] . '">' . $data['nbnotes'] .'</a>';
$nbactthes = ($data['nbactthes'] == 0) ? '0' : '<a href="log.php?prj=' . $_GET['prj'] . '&amp;cat=T&amp;id_user=' . $data['id_user'] . '">' . $data['nbactthes'] .'</a>';
$nbmsg = ($data['nbmsg'] == 0) ? '0' : '<a href="messages.php?prj=' . $_GET['prj'] . '&amp;id_user=' . $data['id_user'] . '">' . $data['nbmsg'] .'</a>';
$email = ($data['email'] != '' and $rank <= 5) ? '<a href="mailto: ' . $data['email'] . '"><img src="img/mail.png" alt="[msg]" /></a>' : '';
setlocale(LC_TIME, $ui['datetime']['locale']);
$date = strftime($ui['datetime']['dpattern'], $data['datetime']);
$template->UpdateBranchVars('user', array('ID' => $data['id_user'],
                                          'LOGIN' => $data['login'],
                                          'NAME' => $data['name'],
                                          'EMAIL' => $email,
                                          'SINCEDATE' => $date,
                                          'EMAILNOTIF' => ($data['emailnotif']) ? '' : 'style="display: none"', 
                                          'RANK' => $ui['memberRank'][$data['rk']],
                                          'NBTOEVAL' => $data['nbpropev'],
                                          'NBPROP' => $data['nbprop'],
                                          'NBVALID' => $data['nbpropval'],
                                          'NBINTEGR' => $data['nbpropok'],
                                          'NBCOMMENTS' => $nbcomments,
                                          'NBADDDICT' => $nbadddict,
                                          'NBACTDICT' => $nbactdict,
                                          'NBNOTES' => $nbnotes,
                                          'NBACTTHES' => $nbactthes,
                                          'NBMSG' => $nbmsg,
                                          'TOEVAL' => $ui['tableVars']['toeval'],
                                          'REJPROP' => $ui['tableVars']['rejprop'],
                                          'TRASH' => $ui['tableVars']['trash']));
if ($rank <= 2) {
    $template->SetBranchVars('user.userrank', $ui['memberRank']);
    $template->UpdateBranchVars('user.userrank', array('PRJ' => $_GET['prj'],
                                                       'ID' => $data['id_user'],
                                                       $data['rk'] . 'SEL' => 'selected="selected"'));
}

$tab = (isset($_GET['tab']) and ($_GET['tab'] == 'E' or $_GET['tab'] == 'R' or $_GET['tab'] == 'T')) ? $_GET['tab'] : 'E';

require('./config/img_vars.php');
require('./code/displaytable.php');
displaytable($dbaPropositions, $_GET['prj'], $tab, $_GET['id_user'], $template);

// display
$template->Grow('./html/div.head.tpl.html');
$template->Grow('./html/propositions.div.tpl.html');
$template->Grow('./html/div.foot.tpl.html');

?>
