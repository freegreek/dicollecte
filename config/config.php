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


/* 
    MAILBOX SETTINGS, FOR EDITION BY E-MAIL
    - IMAP mailbox: {address:port}INBOX
    - POP mailbox:  {address:port/pop3}INBOX
    
    examples: {localhost:143}INBOX
              {pop.gmail.com:995/pop3/ssl/novalidate-cert}INBOX
              {imap.gmail.com:993/imap/ssl}INBOX
    
    All options:
    http://fr.php.net/manual/en/function.imap-open.php
    
    DO NOT USE YOUR USUAL EMAIL, BUT CREATE A DEDICATED EMAIL TO THIS TASK.
*/
define('MAILBOX_SETTINGS', '{pop.dicollecte.org:110/pop3}INBOX');
define('MAILBOX_EMAIL',    'bot@dicollecte.org');
define('MAILBOX_PASSWORD', 'xxxxxxxx');

/* The e-mail address for the notification system */
define('SENDMAIL_FROM',    'sys@dicollecte.org');

?>
