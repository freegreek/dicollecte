<?php

require('./config/config.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-users.class.php');
require('./code/dbaccess-projects.class.php');
require('./code/template.class.php');

$ui = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);

$db = new Database();
$dbaUsers = new dbaccessUsers($db);

$template = new Template();
$template->SetPageVars($_GET['prj'], 'home', $dbaUsers);
$template->SetTrunkVar('TAB_TITLE', $ui['tabs']['home']);

// we load the page
$mainleft = file_get_contents('./html/' . $_GET['prj'] . '/home-left.div.html');
$mainright = file_get_contents('./html/' . $_GET['prj'] . '/home-right.div.html');
if (isset($_GET['cmd']) and $_GET['cmd'] == 'autoedit') {
    // page edition
    $template->SetBranchVars('pageedit', $ui['autoEdit']);
    $template->UpdateBranchVars('pageedit', array('PRJ' => $_GET['prj'],
                                                  'PAGETITLE' => '[home]',
                                                  'MAINRIGHT' => $mainright,
                                                  'MAINLEFT' => $mainleft,
                                                  'PAGENAME' => 'home'));
}
else {
    // page display
    $template->SetBranchVars('page', array('MAINRIGHT' => $mainright,
                                           'MAINLEFT' => $mainleft));
    // home 
    $template->SetBranchVars('page.home', $ui['home']);
    
    // stats
    if ($db->connx) {
        $dbaProject = new dbaccessProjects($db);
        list($ok, $result) = $dbaProject->selectProject($_GET['prj']);
        if ($ok) {
            setlocale(LC_TIME, $ui['datetime']['locale']);
            $data = $result[0];
            $pcentgramtag = ($data['nbdictent'] != 0) ? (int) ($data['nbentgramtag']/$data['nbdictent']*100) : 0;
            $pcentsemtag = ($data['nbdictent'] != 0) ? (int) ($data['nbentsemtag']/$data['nbdictent']*100) : 0;
            $template->SetBranchVars('page.stats', $ui['stats']);
            $template->UpdateBranchVars('page.stats', array('LASTUPDATE' => strftime($ui['datetime']['dtpattern'], $data['lastupdate']),
                                                            'NBDICTENT' => $data['nbdictent'],
                                                            'NBENTGRAMTAG' => $data['nbentgramtag'],
                                                            'PCENTGRAMTAG' => $pcentgramtag,
                                                            'NBENTSEMTAG' => $data['nbentsemtag'],
                                                            'PCENTSEMTAG' => $pcentsemtag,
                                                            'NBNOTES' => $data['nbnotes'],
                                                            'NBPROP' => $data['nbprop'],
                                                            'NBSYNSETS' => $data['nbsynsets'],
                                                            'NBSYNS' => $data['nbsyns']));
        }
    }
}

// display
$template->Grow('./html/div.head.tpl.html');
$template->Grow('./html/autoedit.div.tpl.html');
$template->Grow('./html/div.foot.tpl.html');

?>
