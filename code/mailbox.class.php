<?php

class Mailbox {
    private $mailboxname;
    private $answerheader;
    
    function __construct ($mailboxname, $answerheader) {
        $this->mailboxname = $mailboxname;
        $this->answerheader = $answerheader;
    }
    
    public function readMailbox ($email, $emailpw, $db) {       
        $mailbox = imap_open($this->mailboxname, $email, $emailpw);
        if (!$mailbox) {
            return FALSE;
        }
        $nbMsg = imap_num_msg($mailbox);
        $projects = parse_ini_file('./config/projects.ini');
        $dbaUsers = new dbaccessUsers($db);
        for ($i = 1; $i <= $nbMsg; $i++) {
            $mail = new CmdMail ($mailbox, $i, $this->answerheader);
            $mail->perform($mailbox, $projects, $db, $dbaUsers);
            imap_delete($mailbox, $i, 0);
        }
        imap_expunge($mailbox);
        imap_close($mailbox);
        return TRUE;
    }
}

class CmdMail {
    private $header; // object
    private $structure; //object
    private $sender; // string
    private $body; // string
    private $answerheader; // string
    
    // public
    function __construct ($mailbox, $mailnum, $email) {
        $this->header = @imap_header($mailbox, $mailnum);
        $this->sender = $this->header->from[0]->mailbox . '@' . $this->header->from[0]->host;
        $this->structure = @imap_fetchstructure($mailbox, $mailnum);
        $this->body = @imap_body($mailbox, $mailnum);
        $this->answerheader = "From: $email\nMIME-Version: 1.0\nContent-type: text/plain; charset=utf-8\nContent-transfer-encoding:8bit\n";
    }
    
    public function perform ($mailbox, $projects, $db, $dbaUsers) {
        // check mail
        if (!preg_match('`^(D|d)icollecte`', $this->header->subject)) {
            $this->answer('unexpected mail', 'Unexpected mail. The purpose of this mailbox is to collect commands to edit the thesaurus. Mails which do not fit the command pattern are deleted.');
            return FALSE;
        }
        // encoding check
        $this->body = str_replace(array("\r\n", "\r"), "\n", $this->body); // useful? check imap_open about CRLF.
        switch ($this->structure->encoding) {
            case 4: $this->body = imap_qprint($this->body); break;
        }
        $encoding = mb_detect_encoding($this->body);
        if (!($encoding == 'ASCII' or $encoding == 'UTF-8')) {
            $this->answer('wrong encoding', 'Wrong encoding:' . $encoding . '. ASCII or UTF-8 only.');
            return FALSE;
        }
        // pattern check
        $pattern = '`USER=([a-zA-Z0-9_-]+)\nPASSWORD=(.+)\nPROJECT=(.+)\nCOMMAND=([A-Z]+)\nENTRY\n((.+\n)+)/ENTRY`';
        if (preg_match($pattern, $this->body, $matches)) {
            $login = $matches[1];
            $pw = $matches[2];
            $prj = $matches[3];
            $cmd = $matches[4];
            $entry = addslashes($matches[5]);
            
            // project checking
            if (!array_key_exists($prj, $projects)) {
                $this->answer('unknown project', 'The project ' . $prj . ' does not exist.');
                return FALSE;
            }
            require('./html/' . $prj . '/project_vars.php');
            if (!$project['thesUpdateByEmail']) {
                $this->answer('edition by e-mail not allowed on project ' . $prj, 'The project ' . $prj  . ' does not allow edition by e-mail.');
                return FALSE;
            }
            
            // user checking
            list($id_user, $rank, $error) = $dbaUsers->connectUser($login, $pw, $prj, TRUE);
            if ($id_user != -1) {
                if (($rank <= 5) or ($rank <= 7 and $project['thesAllUsersAllowed'])) {
                    // action on db
                    $dbaThesaurus = new dbaccessThesaurus($db);
                    switch ($cmd) {
                        case 'ADD':
                            list($word, $syn) = $this->makeWordSyn($entry);
                            if ($word === FALSE)  {
                                $mailpatterns = file_get_contents('./html/mailpatterns.txt');
                                $this->answer('wrong entry', 'Your entry does not match the pattern:' . $mailpatterns);
                                return FALSE;
                            }
                            list($ok, $info) = $dbaThesaurus->newEntry($prj, $word, $syn, $id_user, $login);
                            if ($ok) {
                                $this->answer('- new entry: ' . $word, 'New entry: ' . $word);
                            }
                            else {
                                $this->answer('- error: ' . $word, 'Error on entry: ' . $word . ' - ' . $info);
                            }
                            break;
                            return TRUE;
                        case 'UPDATE':
                            list($word, $syn) = $this->makeWordSyn($entry);
                            if ($word === FALSE)  {
                                $mailpatterns = file_get_contents('./html/mailpatterns.txt');
                                $this->answer('wrong entry', 'Your entry does not match the pattern:' . $mailpatterns);
                                return FALSE;
                            }
                            list($ok, $info) = $dbaThesaurus->updateEntry($prj, $word, $syn, $project['thesLockDuration'], $id_user, $login);
                            if ($ok) {
                                $this->answer('- entry updated: ' . $word, 'The entry ' . $word . ' has been updated.');
                            }
                            else {
                                $this->answer('- error: ' . $word, 'Error on entry: ' . $word . ' - ' . $info);
                            }
                            break;
                            return TRUE;
                        case 'DELETE':
                            $word = trim($entry);
                            list($ok, $info) = $dbaThesaurus->eraseEntry($prj, $word, $id_user, $login);
                            if ($ok) {
                                $this->answer('- entry deleted: ' . $word, 'The entry ' . $word . ' has been deleted.');
                            }
                            else {
                                $this->answer('- error: ' . $word, 'Error on entry: ' . $word . ' - ' . $info);
                            }
                            break;
                            return TRUE;
                        case 'SEE':
                            $elems = explode("\n", trim($entry));
                            foreach($elems as $word) {
                                list($ok, $info) = $dbaThesaurus->showEntry($prj, $word);
                                if ($ok) {
                                    $this->answer('- entry: ' . $word, $info);
                                }
                                else {
                                    $this->answer('- error: ' . $word, 'Error on entry: ' . $word . ' - ' . $info);
                                }
                            }
                            break;
                            return TRUE;
                        default: // unknown command
                            $mailpatterns = file_get_contents('./html/mailpatterns.txt');
                            $this->answer('- unknown command: ' . $cmd, 'Unknown command: ' . $cmd . $mailpatterns);
                            return FALSE;
                    }
                    return $ok;
                }
                else {
                    // access not allowed to this user
                    $this->answer('edition not allowed', 'You are not allowed to edit the thesaurus.');
                    return FALSE;
                }
            }
            else {
                // no identification
                $this->answer('wrong login or password', 'Login or password not recognized');
                return FALSE;
            }
        }
        else {
            // wrong pattern
            $mailpatterns = file_get_contents('./html/mailpatterns.txt');
            $this->answer('wrong entry', 'Your entry does not match the pattern:' . $mailpatterns);
            return FALSE;
        }
    }

    public function answer ($subject, $msg) { 
        mail($this->sender, 'dicollecte: =?UTF-8?B?' . base64_encode($subject) . '?=', $msg . "\n\n------------ Your mail was: ----------\n" . $this->body, $this->answerheader);
        // $this->echoDebug($this->sender, 'dicollecte: ' . $title, $msg . "\n\n------------ Your mail was: ----------\n" . $this->body, $this->answerheader);
    }
    
    
    // private
    private function makeWordSyn ($entry) {
        $entry = str_replace('#', ' ', $entry);
        $pattern = '`^([^ |]+)(|[0-9]+)*\n((.+\n)+)`';
        if (!preg_match($pattern, $entry, $matches)) {
            return array(FALSE, FALSE);
        }
        $word = $matches[1];
        $syn = str_replace("\n", '#', trim($matches[3]));
        return array($word, $syn);
    }
    
    private function echoDebug ($to, $title, $msg, $header) {
        echo '<h2>MSG:</h2><pre>';
        echo $to . PHP_EOL;
        echo $header . PHP_EOL;
        echo $title . PHP_EOL;
        echo $msg . PHP_EOL;
        echo '</pre>';
    }  
}

?>
