
DICOLLECTE
==========

## REQUIREMENTS

 * PostgreSQL 8.4 - UTF-8 encoding
 * PHP 5.2 + library PDO for PostgreSQL
 * phpPgAdmin


INSTALLATION
============

## FIRST SETTINGS

Edit "config.php" in "/config".
Edit the lines according to your system.

Modify the settings for the mailbox is necessary only if you want to allow
the thesaurus edition by e-mail.


## DATABASE CREATION

With your browser, go to:
http://[your_address]/installer.php

Enter a password for the superadmin account, called "Admin".


## PROJECT CREATION

Once the database is ready, you can create/erase projects.

For each project, your must choose a project identifier, like "fr" or "en_US" or "de" or "es".
5 letters maximum, no special characters!

It is not necessary to create a project for each locale of a language, as it is also possible
to define subdictionaries for each project.

Once the project is created, log in as administrator and edit variables in the administration panel.


## UPLOADING DICTIONARIES

Log in as administrator.
Go to the administration panel, section "Import|Export".

To convert files to UTF-8, see iconv or any other tool.

iconv:
 * [Linux, MacOS]: http://www.gnu.org/software/libiconv/documentation/libiconv/iconv.1.html 
 * [Windows]: http://gnuwin32.sourceforge.net/packages/libiconv.htm

Syntax:
`iconv -f original_encoding -t new_encoding filename > newfilename`


## PERSONAL HOMEPAGE

You can create your own home page by creating a file named index.html in the folder _mypage.


UPDATING FROM A OLD VERSION OF DICOLLECTE (0.9 or higher)
=========================================================

WARNING!
Updating from versions older than 1.0 is tricky, you might loose data in your database.

 - Save "config.php" in /config

 - Copy the content of the archive in the Dicollecte folder
 
 - Edit "config.php" in /config
 
 - With your browser, go to: http://[your_address]/installer.php
 
 - Log in, then click on Update.

