<?php

/*
    DO NOT EDIT
*/

define('DB_LOGINLEN', 20);          // login length
define('DB_NAMELEN', 40);           // full name length
define('DB_EMAILLEN', 60);          // e-mail length

define('DB_LEMMALEN', 200);         // lemma length
define('DB_FLAGSLEN', 150);         // flags length
define('DB_LEXLEN', 50);            // lexical field length
define('DB_HGRAMMLEN', 50);         // Hunspell morphological fields length

define('DB_AFFCUTLEN', 30);         // cut field length of affix rule
define('DB_AFFADDLEN', 30);         // add field length of affix rule
define('DB_AFFFLAGSLEN', 150);      // flags field length of affix rule
define('DB_AFFCONDLEN', 100);       // conditional field length of affix rule
define('DB_AFFCOMMENTLEN', 200);    // conditional field length of affix rule

define('DB_COMMENTLEN', 3000);      // comment length, 1/2 will be added in the database for formatting change
define('DB_NOTELEN', 5000);         // note length, 1/2 will be added in the database for formatting change

define('DB_THESENTRYLEN', 200);     // thesaurus entry length
define('DB_POSSYNLEN', 50);         // thesaurus synset pos
define('DB_SYNSLEN', 10000);        // synset field length

define('DB_LOGLABELLEN', 3000);     // log label length

define('DB_FORUMLBLLEN', 100);      // forum label length
define('DB_FORUMDESCRLEN', 400);    // forum descr length 
define('DB_FORUMMSGLEN', 30000);    // msg length

?>
