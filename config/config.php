<?php

/* system active? */
define('DICOLLECTE_ACTIVE', TRUE);
define('DICOLLECTE_CLOSEMSG', 'Updating. This site is temporarily unavailable. Please, retry later.');

/* TIMEZONE */
define('DEFAULT_TIMEZONE', 'Europe/Paris');

/* web address (add a slash at the end) */
define('URL_ROOT',    'http://localhost:8080/dicollecte.org/');
define('URL_HEADER',  'Location: http://localhost:8080/dicollecte.org/');

/* database settings (PostgreSQL) */
define('DB_BASE',     'pgsql:host=localhost;dbname=postgres');
define('DB_HOST',     'localhost');
define('DB_NAME',     'postgres');
define('DB_USER',     'postgres');
define('DB_PASSWORD', 'a');

/* The e-mail address for the notification system */
define('SENDMAIL_FROM',    'sys@dicollecte.org');

?>
