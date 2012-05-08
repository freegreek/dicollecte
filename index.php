<?php

if (is_file('./_mypage/index.html')) {
    echo file_get_contents('./_mypage/index.html');
    exit;
}

require('./config/config.php');
require('./code/template.class.php');
require('./code/dbaccess.class.php');

$template = new Template();

if (!DICOLLECTE_ACTIVE) {
    $template->SetTrunkVar('SYSMSG', '<div id="message_error"><p>' . DICOLLECTE_CLOSEMSG . '</p></div>');
}
else {
    if (isset($_COOKIE['msg'])) {
        $ui = parse_ini_file('./html/_default/ui.ini', TRUE);
        $template->uiSystemMessage($_COOKIE['msg']);
        setcookie('msg', FALSE);
    }
    $db = new Database();
    if (!$db->connx) {
        $template->SetBranchVars('msg', array('MESSAGE' => DICOLLECTE_CLOSEMSG));
    }
    else {
        require('./code/dbaccess-projects.class.php');
        $dbaProject = new dbaccessProjects($db);
        list($ok, $result) = $dbaProject->listProjects();
        if (!$ok) {
            $template->SetBranchVars('msg', array('MESSAGE' => DICOLLECTE_CLOSEMSG));
        }
        else {
            foreach ($result as $data) {
                if (!$data['closed']) {
                    $template->SetBranchVars('project', array('PRJ' => $data['prj'],
                                                              'PROJECTLABEL' => $data['label']));
                }
            }
        }
    }
}

// display
$template->Grow('./html/div.head-nomenu.tpl.html');
$template->Grow('./html/index.div.tpl.html');
$template->Grow('./html/div.foot.tpl.html');

?>
