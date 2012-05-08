<?php

/*
// we receive the mail sent by .forward
$flux = fopen('php://STDIN', 'r');
$mailcontent = '';
while(!feof($flux)) {
    $mailcontent .= fgets($flux);
}
*/
 
require('./config/config.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-thesaurus.class.php');
require('./code/dbaccess-users.class.php');
require('./code/mailbox.class.php');

$db = new Database();
if (!$db->connx) {
    exit;
}

$mailbox = new Mailbox(MAILBOX_SETTINGS, MAILBOX_EMAIL);
$mailbox->readMailbox(MAILBOX_EMAIL, MAILBOX_PASSWORD, $db);

?>
