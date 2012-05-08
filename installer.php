<?php

session_start();

require('./config/config.php');
require('./config/db_vars.php');
require('./code/dbtables.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-installer.class.php');
require('./code/template.class.php');

$db = new Database();
if (!$db->connx) {
    header(URL_HEADER . 'index.php');
    exit;
}

$dbaInstaller = new dbaccessInstaller($db);

$template = new Template();

// search for existing projects
$iniprojects = parse_ini_file('./config/projects.ini');

// what is the current situation?
if (in_array('dicl_projects', $dbaInstaller->lTables)) {
    // Dicollecte is already installed
    if (!isset($_COOKIE['login']) or !isset($_SESSION['id_user']) or $_SESSION['id_user'] != 1) {
        $template->SetBranchVars('connxform', array());
    }
    else {
        // we look for projects
        $ok = TRUE;
        $template->SetBranchVars('projectslist', array());
        if (sizeof($dbaInstaller->projects) > 0) {
            foreach ($dbaInstaller->projects as $oProject) {
                if ($oProject->version == DB_VERSION and $dbaInstaller->isProjectInstalled($oProject->prj, array_keys($dbPrjTables))) {
                    $icon = '<img src="./img/tag_valid.png" alt="*" />';
                    $version = '<samp>' . $oProject->version . '</samp>';
                }
                else {
                    $icon = '<img src="./img/tag_alert.png" alt="!" />';
                    $version = '<samp style="color: #FF4400">' . $oProject->version . '</samp>';
                    $ok = FALSE;
                }
                $template->SetBranchVars('projectslist.project', array('ICON' => $icon,
                                                                       'PRJ' => $oProject->prj,
                                                                       'LABEL' => $oProject->label,
                                                                       'VERSION' => $version));
            }
        }
        else {
            $template->SetBranchVars('projectslist.noprj', array());
        }
        if ($ok) {
            $template->SetbranchVars('create_project', array());
            $template->SetbranchVars('destroy_project', array());
        }
        else {
            $template->SetBranchVars('update', array('VERSION' => DB_VERSION));
        }
    }
}
else {
    // no installation found
    // full installation
    $template->SetBranchVars('install', array());
}

if (isset($_COOKIE['msg'])) {
    $template->SetTrunkVar('SYSMSG', '<div id="message_error"><p>' . $_COOKIE['msg'] . '</p></div>');
    setcookie('msg', FALSE);
}

//display 
$template->Grow('./html/div.head-nomenu.tpl.html');
$template->Grow('./html/installer.div.tpl.html');
$template->Grow('./html/div.foot.tpl.html');

?>
