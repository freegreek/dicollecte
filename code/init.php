<?php

session_start();

/* the system can be disactivated in config.php */
if (!DICOLLECTE_ACTIVE and $_SESSION['id_user'] != 1) {
    header(URL_HEADER . 'index.php');
    exit;
}

date_default_timezone_set(DEFAULT_TIMEZONE);

/* if no project defined, restart */
$projects = parse_ini_file('./config/projects.ini');
if (!isset($_GET['prj']) or $_GET['prj'] == '' or !array_key_exists($_GET['prj'], $projects)) {
    setSysMsg('_noprj');
    header(URL_HEADER . 'index.php');
    exit;
}

/* Magic quotes? */
if (!get_magic_quotes_gpc()) {
    addSlashesOnArray($_POST);
    addSlashesOnArray($_GET);
    addSlashesOnArray($_REQUEST);
}

function addSlashesOnArray (&$array) {
    foreach ($array as $key => $value) {
        if (is_string($value)) {
            $array[$key] = addslashes($value);
        }
    }
}

function setSysMsg ($msg) {
    if ($msg == '') { $msg = 'no_msg'; }
    setcookie('msg', $msg);
}

?>
