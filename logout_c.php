<?php

require('./config/config.php');
require('./code/init.php');
setcookie('login', '', 0);
setcookie('pw', '', 0);
session_destroy();
setSysMsg('disconnected');
if (isset($_SERVER['HTTP_REFERER'])) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
}
else {
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
}

?>
