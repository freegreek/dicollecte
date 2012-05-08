<?php

session_start();

require('./config/config.php');
require('./config/db_vars.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-installer.class.php');
require('./code/dbaccess-users.class.php');
require('./code/dbtables.php');

function setSysMsg ($msg) {
    setcookie('msg', $msg);
}

$db = new Database();
if (!$db->connx) {
    setSysMsg('_nobase');
    header(URL_HEADER . 'index.php?prj=' . $_GET['prj']);
    exit;
}

$dbaInstaller = new dbaccessInstaller($db);
$dbaUsers = new dbaccessUsers($db);

switch ($_REQUEST['cmd']) {
    // identification as administrator
    case 'connect':
        list($ok, $msgcode) = $dbaInstaller->connectSuperAdmin($_POST['login'], md5(stripslashes($_POST['pw'])), TRUE);
        if (!$ok) {
            setSysMsg($msgcode);
            header(URL_HEADER . 'installer.php');
            exit;
        }
        break;
    // installation from scratch
    case 'firstinstall':
        if (!isset($_REQUEST['password']) or $_REQUEST['password'] == '') {
            setSysMsg('error: empty password');
            header(URL_HEADER . 'installer.php');
            exit;
        }
        // create common tables
        list($ok, $msgcode) = $dbaInstaller->createTables($dbTables);
        if (!$ok) {
            setSysMsg('error: database error');
            header(URL_HEADER . 'installer.php');
            exit;
        }
        // create the superuser account (Admin)
        list($ok, $msgcode) = $dbaInstaller->createAdminAccount($_REQUEST['password']);
        if (!$ok) {
            setSysMsg('error: database error');
            header(URL_HEADER . 'installer.php');
            exit;
        }
        break;
    // update tables from the dbtables schemes
    case 'update': 
        // update tables
        list($ok, $msgcode) = $dbaInstaller->connectSuperAdmin($_COOKIE['login'], $_COOKIE['pw']);
        if (!$ok) {
            setSysMsg($msgcode);
            header(URL_HEADER . 'installer.php');
            exit;
        }
        $error = FALSE;
        // create common tables which do not exist
        list($ok, $msgcode) = $dbaInstaller->createTables($dbTables);
        if (!$ok) {
            setSysMsg('error: database error');
            header(URL_HEADER . 'installer.php');
            exit;
        }
        // update common tables
        list($ok, $msgcode) = $dbaInstaller->updateCommonTables($dbTables);
        if (!$ok) {
            setSysMsg('error: database error');
            header(URL_HEADER . 'installer.php');
            exit;
        }
        // update projects tables
        require('./code/misc.php');
        foreach ($dbaInstaller->projects as $oProject) {
            if (!($oProject->version == DB_VERSION and $dbaInstaller->isProjectInstalled($oProject->prj, array_keys($dbPrjTables)))) {
                // create dirs which do not exist
                createprjdirs($oProject->prj);
                // create prj tables which do not exist
                list($ok, $msgcode) = $dbaInstaller->createTables($dbPrjTables, $oProject->prj, $label);
                if (!$ok) {
                    setSysMsg('database error: tables creation');
                    header(URL_HEADER . 'installer.php');
                    exit;
                }
                // update project tables
                list($ok, $msgcode) = $dbaInstaller->updatePrjTables($oProject->prj, $dbPrjTables, $oProject->version);
                if (!$ok) {
                    setSysMsg('database error while updating project ' . $oProject->prj);
                    header(URL_HEADER . 'installer.php');
                    exit;
                }
            }
            // update ui.ini
            $newUi = parse_ini_file('./html/_default/ui.ini', TRUE);
            $prjUi = parse_ini_file('./html/' . $oProject->prj . '/ui.ini', TRUE);
            updateIniArray($newUi, $prjUi);
            updateIniFile($oProject->prj, $newUi);
        }
        break;
    // create a project
    case 'createprj':
        list($ok, $msgcode) = $dbaInstaller->connectSuperAdmin($_COOKIE['login'], $_COOKIE['pw']);
        if (!$ok) {
            setSysMsg($msgcode);
            header(URL_HEADER . 'installer.php?');
            exit;
        }
        $prj = $_REQUEST['identifier'];
        $label = $_REQUEST['label'];
        if (!preg_match('`^[a-zA-Z_]+$`', $prj)) {
            setSysMsg('error: wrong identifier -- authorized characters: a-z A-Z _');
            header(URL_HEADER . 'installer.php');
            exit;
        }
        if (in_array($prj, $dbaInstaller->lProjects)) {
            setSysMsg('error: this identifier [' . $prj . '] already exists');
            header(URL_HEADER . 'installer.php');
            exit;
        }
        if ($label == '') {
            setSysMsg('error: empty label');
            header(URL_HEADER . 'installer.php');
            exit;
        }
        require('./code/misc.php');
        // create folders for project
        createprjdirs($prj);
        // copy files from /html/_default
        dircopy('./html/_default/', './html/'.$prj);
        // create tables for project
        list($ok, $msgcode) = $dbaInstaller->createTables($dbPrjTables, $prj, $label);
        if (!$ok) {
            setSysMsg('database error: tables creation');
            header(URL_HEADER . 'installer.php');
            exit;
        }
        // add an entry in projects.ini
        addPrjIni($prj, stripslashes($label));
        break;
    // destroy a project
    case 'destroyprj':
        list($ok, $msgcode) = $dbaInstaller->connectSuperAdmin($_COOKIE['login'], $_COOKIE['pw']);
        if (!$ok) {
            setSysMsg($msgcode);
            header(URL_HEADER . 'installer.php');
            exit;
        }
        $prj = $_REQUEST['identifier'];
        if (!preg_match('`^[a-zA-Z_]+$`', $prj)) {
            setSysMsg('error: wrong identifier -- authorized characters: a-z A-Z _');
            header(URL_HEADER . 'installer.php');
            exit;
        }
        // destroy tables of project
        list($ok, $msgcode) = $dbaInstaller->destroyTablesOfPrj($dbPrjTables, $prj);
        if (!$ok) {
            setSysMsg('error: database error');
            header(URL_HEADER . 'installer.php');
            exit;
        }
        // destroy folders of project
        require('./code/misc.php');
        deleteprjdirs($prj);
        // rewrite projects.ini
        delPrjIni($prj);
        break;
    default:
        setSysMsg('error: unknown command');
        header(URL_HEADER . 'installer.php');
}

header(URL_HEADER . 'installer.php');

?>
