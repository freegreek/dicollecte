<?php

require('./config/config.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-users.class.php');
require('./code/mailbox.class.php');

$db = new Database();
if (!$db->connx) {
    exit;
}
$dbaUsers = new dbaccessUsers($db);

switch ($_GET['cmd']) {
    case 'noemailnotif':
        if (isset($_GET['id_user']) and isset($_GET['code'])) {
            $res = $dbaUsers->noEmailNotif($_GET['id_user'], $_GET['code']);
            if ($res) {
                setSysMsg('noenotif');
            }
            else {
                setSysMsg('_enotifnotdis');
            }
        }
        else {
            setSysMsg('_wrongurl');
        }
        break;
    case 'reinitpw':
        if (isset($_GET['id_user']) and isset($_GET['code'])) {
            require('./code/misc.php');
            $newpw = generatePassword(8);
            list($ok, $msgcode) = $dbaUsers->reinitPassword($_GET['id_user'], $_GET['code'], $newpw, $_GET['prj']);
            setSysMsg($msgcode);
        }
        else {
            setSysMsg('_wrongurl');
        }
        break;
    default:
        setSysMsg('_wrongurl');
}

header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);

?>
