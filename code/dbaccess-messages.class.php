<?php

class dbaccessMessages {
    /*
        This object is an access to the db for forum.
        Modified tables are: prj_thread, prj_msg
    */

    public  $db;
    
    /* PUBLIC */
    
    function __construct ($db) {
        $this->db = $db;
    }

    public function viewThread ($prj, $id_thread, $offset=NULL, $nbEntriesByPage=NULL) {
        if (!is_numeric($id_thread)) {
            return array(FALSE, '_data');
        }
        $qSelectT = 'SELECT t.*, f.label AS forumlabel FROM dicl_'.$prj.'_thread t  LEFT OUTER JOIN dicl_'.$prj.'_forum f ON t.id_forum = f.id_forum'
                  . ' WHERE t.id_thread = ' . $id_thread;
        $qSelectM = 'SELECT m.*, u.login FROM dicl_'.$prj.'_msg m LEFT OUTER JOIN dicl_users u ON m.id_user = u.id_user'
                  . ' WHERE id_thread = ' . $id_thread . ' ORDER BY msgnum';
        // . ' OFFSET ' . $offset . ' LIMIT ' . $nbEntriesByPage
        try {
            $oQT = $this->db->connx->query($qSelectT);
            $oQM = $this->db->connx->query($qSelectM);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'view thread');
            return array(FALSE, '_dberror', 0);
        }
        $resultT = $oQT->fetchAll(PDO::FETCH_ASSOC);
        $resultM = $oQM->fetchAll(PDO::FETCH_ASSOC);
        return array(TRUE, $resultT, $resultM);
    }

    public function listMessages ($prj, $offset, $nbEntriesByPage, $idUserSelected=NULL) {
        if ($idUserSelected and !is_numeric($idUserSelected)) {
            return array(FALSE, '_data');
        }
        $qOptions = ($idUserSelected) ? 'WHERE m.id_user = ' . $idUserSelected : '';
        $qSelect = 'SELECT m.*, t.label, u.login FROM dicl_'.$prj.'_msg m  JOIN dicl_'.$prj.'_thread t ON t.id_thread = m.id_thread'
                 . ' JOIN dicl_users u ON u.id_user = m.id_user '
                 . $qOptions . ' ORDER BY m.creationdt DESC  OFFSET ' . $offset . '  LIMIT ' . $nbEntriesByPage;
        try {
            $oQ = $this->db->connx->query($qSelect);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'view messages');
            return array(FALSE, '_dberror', 0);
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        $nbOccur = count($result);
        if ($nbOccur > 0) {
            $qSelect = 'SELECT COUNT(id_msg) AS nbentries FROM dicl_'.$prj.'_msg m ' . $qOptions;
            try {
                $oQ2 = $this->db->connx->query($qSelect);
                $result2 = $oQ2->fetchAll(PDO::FETCH_ASSOC);
                $nbOccur = $result2[0]['nbentries'];
            }
            catch (PDOException $e) {
                $this->db->dbErrorReport($prj, $e, 'count messages');
            }
        }
        return array(TRUE, $result, $nbOccur);
    }

    public function newMessage ($prj, $id_user, $id_thread, $msg) {
        if (!is_numeric($id_thread) and !is_numeric($id_user)) {
            return array(FALSE, '_data');
        }
        if ($msg == '') {
            return array(TRUE, '_emptyfields');
        }
        list($ok, $result) = $this->getThread($prj, $id_thread);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $data = $result[0];
        $emailMsg = strip_tags($msg);
        $this->remixMsg($msg);
        if (mb_strlen($msg) > 45000) {
            return array(FALSE, '_msgtoolong');
        }
        // insertion
        $now = time();
        $msgnum = $data['msgcount'] + 1;
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_msg (id_thread, id_user, msg, msgnum) VALUES ' . "($id_thread, $id_user, '$msg', $msgnum)");
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_thread SET nbmsg = nbmsg + 1, msgcount = msgcount + 1, id_user_up = ' . $id_user
                                 . ', updatedt = ' . $now . ', msgnum = ' . $msgnum . ' WHERE id_thread = ' . $id_thread);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_forum SET id_thread = ' . $id_thread . ', id_user_up = ' . $id_user . ', updatedt = ' . $now
                                 . ', msgnum = ' . $msgnum . ' WHERE id_forum = ' . $data['id_forum']);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_members SET nbmsg = nbmsg + 1  WHERE id_member = ' . $id_user);
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'new message');
            return array(FALSE, '_dberror');
        }
        // e-mail notification
        if ($data['nbnotif'] > 0) {
            $uiMail = parse_ini_file('./html/' . $prj . '/mail.ini', TRUE);
            $subject = 'dicollecte ['.$prj.'] • =?UTF-8?B?' . base64_encode($data['label']) . '?=';
            $msg = URL_ROOT . 'thread.php?prj=' . $prj . '&t=' . $id_thread . '#msg' . $msgnum . PHP_EOL . PHP_EOL
                 . sprintf($uiMail['newmsg']['label'], $_COOKIE['login']) . PHP_EOL . PHP_EOL
                 . stripslashes($emailMsg);
            if ($data['nbnotif'] > 0) {
                $qSelect = 'SELECT u.email  FROM dicl_users u  JOIN dicl_'.$prj.'_threadsub t ON u.id_user = t.id_user'
                         . ' WHERE id_thread = ' . $id_thread;
                try {
                    $oQ = $this->db->connx->query($qSelect);
                }
                catch (PDOException $e) {
                    $this->db->dbErrorReport($prj, $e, 'message: select e-mail notifications');
                    return array(FALSE, '_dberror', 0);
                }
                $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
                foreach ($result as $data) {
                    $this->sendMail($data['email'], $subject, $msg);
                }
            }
        }
        return array(TRUE, $msgnum);
    }
    
    public function editMessage ($prj, $id_msg, $newmsg) {
        if (!is_numeric($id_msg)) {
            return array(FALSE, '_data');
        }
        if ($newmsg == '') {
            return array(TRUE, '_emptyfields');
        }
        list($ok, $result) = $this->getMessage($prj, $id_msg);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $data = $result[0];
        if (!($data['id_user'] == $_SESSION['id_user'] or $_SESSION['rank_'.$prj] <= 2)) {
            return array(FALSE, '_noaccess');
        }
        $this->remixMsg($newmsg);
        try {
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_msg  SET msg = ' . "'".$newmsg."'" . ' WHERE id_msg = ' . $id_msg);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'edit message');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, $data['msgnum']);
    }
    
    public function deleteMessage ($prj, $id_msg) {
        if (!is_numeric($id_msg)) {
            return array(FALSE, '_data');
        }
        list($ok, $result) = $this->getMessage($prj, $id_msg);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $data = $result[0];
        if (!($data['id_user'] == $_SESSION['id_user'] or $_SESSION['rank_'.$prj] <= 2)) {
            return array(FALSE, '_noaccess');
        }
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_msg  WHERE id_msg = ' . $id_msg);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_thread  SET nbmsg = nbmsg - 1  WHERE id_thread = ' . $data['id_thread']);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_members  SET nbmsg = nbmsg - 1  WHERE id_member = ' . $data['id_user']);
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'delete message');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    public function changeUserMessage ($prj, $id_msg, $id_user) {
        if (!is_numeric($id_msg) or !is_numeric($id_user)) {
            return array(FALSE, '_data');
        }
        list($ok, $result) = $this->getMessage($prj, $id_msg);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $data = $result[0];
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_msg  SET id_user = ' . $id_user . '  WHERE id_msg = ' . $id_msg);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_members  SET nbmsg = nbmsg - 1  WHERE id_member = ' . $data['id_user']);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_members  SET nbmsg = nbmsg + 1  WHERE id_member = ' . $id_user);
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'change user message');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    // (un)subscribe
    public function subscribe ($prj, $id_thread, $id_user) {
        if (!is_numeric($id_thread) and !is_numeric($id_user)) {
            return array(FALSE, '_data');
        }
        list($ok, $res) = $this->checkSubscription ($prj, $id_thread, $id_user);
        if (!$ok) {
            return array(FALSE, $res);
        }
        if (!$res) {
            // subscribe
            $this->db->connx->beginTransaction();
            try {
                // new entry
                $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_threadsub (id_thread, id_user) VALUES ('.$id_thread.', '.$id_user.')');
                $this->db->connx->exec('UPDATE dicl_'.$prj.'_thread  SET nbnotif = nbnotif + 1  WHERE id_thread = ' . $id_thread);
                $this->db->connx->commit();
            }
            catch (PDOException $e) {
                $this->db->connx->rollBack();
                $this->db->dbErrorReport($prj, $e, 'subscribing to thread');
                return array(FALSE, '_dberror');
            }
        }
        return array(TRUE, 'ok');
    }

    public function unsubscribe ($prj, $id_thread, $id_user) {
        if (!is_numeric($id_thread) and !is_numeric($id_user)) {
            return array(FALSE, '_data');
        }
        list($ok, $res) = $this->checkSubscription ($prj, $id_thread, $id_user);
        if (!$ok) {
            return array(FALSE, $res);
        }
        if ($res) {
            // unsubscribe
            $this->db->connx->beginTransaction();
            try {
                // new entry
                $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_threadsub  WHERE id_thread = ' . $id_thread . ' AND id_user = '. $id_user);
                $this->db->connx->exec('UPDATE dicl_'.$prj.'_thread  SET nbnotif = nbnotif - 1  WHERE id_thread = ' . $id_thread);
                $this->db->connx->commit();
            }
            catch (PDOException $e) {
                $this->db->connx->rollBack();
                $this->db->dbErrorReport($prj, $e, 'unsubscribing to thread');
                return array(FALSE, '_dberror');
            }
        }
        return array(TRUE, 'ok');
    }
    
    public function checkSubscription ($prj, $id_thread, $id_user) {
        if (!is_numeric($id_thread) and !is_numeric($id_user)) {
            return array(FALSE, '_data');
        }
        try {
            $oQ = $this->db->connx->query('SELECT * FROM dicl_'.$prj.'_threadsub WHERE id_thread = ' . $id_thread . ' AND id_user = '. $id_user);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'thread: check subscription');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        if (count($result) == 0) {
            return array(TRUE, FALSE);
        }
        return array(TRUE, TRUE);
    }
    
    
    /* PRIVATE */
    
    private function getThread ($prj, $id_thread) {
        $qSelect = 'SELECT t.*, login  FROM dicl_'.$prj.'_thread t  JOIN dicl_users u ON u.id_user = t.id_user  WHERE id_thread = ' . $id_thread;
        try {
            $oQ = $this->db->connx->query($qSelect);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'get thread');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        if (count($result) == 0) {
            return array(FALSE, '_nothread');
        }
        return array(TRUE, $result);
    }
    
    private function getMessage ($prj, $id_msg) {
        try {
            $oQ = $this->db->connx->query('SELECT * FROM dicl_'.$prj.'_msg WHERE id_msg = ' . $id_msg);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'get message');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        if (count($result) == 0) {
            return array(FALSE, '_nomsg');
        }
        return array(TRUE, $result);
    }
    
    private function remixMsg (&$msg) {
        $msg = strip_tags($msg, '<b><i><u><s><q><h3><img>');
        $msg = str_replace(array('<q>', '</q>'), array('<blockquote><p>', '</p></blockquote>'), $msg);
        $msg = preg_replace('`[a-zA-Z]+:\/\/([a-z][a-z0-9_\..-]*[a-z]{2,6})[^()\s]*`i', '<a href="$0">$1…</a>', $msg);
        $msg = str_replace('&', '&amp;', $msg);
        $msg = nl2br($msg);
    }
    
    private function sendMail ($to, $subject, $msg) {
        $headers = 'From: Dicollecte <' . SENDMAIL_FROM . '>' . PHP_EOL . 'MIME-Version: 1.0' . PHP_EOL
                 . 'Content-type: text/plain; charset=utf-8' . PHP_EOL . 'Content-transfer-encoding: 8bit' . PHP_EOL . 'X-Mailer: PHP' . PHP_EOL;
        mail($to, $subject, $msg, $headers);
        // for UTF8 subjects: ' =?UTF-8?B?' . base64_encode($subject) . '?='
    }
}

?>
