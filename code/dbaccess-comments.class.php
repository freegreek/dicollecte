<?php

class dbaccessComments {
    /*
        This object is an access to the db for comments.
        Modified tables are: prj_comments
    */

    public  $db;
    private $mailHeader;
    
    /* PUBLIC */
    
    function __construct ($db) {
        $this->db = $db;
    }

    public function select ($prj, $offset, $nbEntriesByPage, $hideSysCom=FALSE, $hideIdUserCom=NULL, $id_user=NULL, $prop_user=NULL) {
        $qOptions = array();
        if ($hideSysCom) $qOptions[] = 'autocom = FALSE';
        if ($id_user) $qOptions[] = 'c.id_user = ' . $id_user;
        if ($prop_user) $qOptions[] = 'c.prop_user = ' . $prop_user;
        if ($hideIdUserCom and $hideIdUserCom != $id_user) $qOptions[] = 'c.id_user != ' . $hideIdUserCom;
        $qOptions = $this->db->createSelectionSubQuery($qOptions);
        $qSelect = 'SELECT id_com, comment, c.id_user, login, datetime, c.id_prop, action, lemma, flags'
                 . ' FROM dicl_'.$prj.'_comments c  JOIN dicl_'.$prj.'_prop p ON p.id_prop = c.id_prop ' . $qOptions
                 . ' ORDER BY datetime DESC  OFFSET ' . $offset . '  LIMIT ' . $nbEntriesByPage;
        try {
            $oQ = $this->db->connx->query($qSelect);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'select comments');
            return array(FALSE, '_dberror', 0);
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        $nbOccur = count($result);
        if ($nbOccur > 0) {
            $qSelect = 'SELECT COUNT(id_com) AS nbentries FROM dicl_'.$prj.'_comments c ' . $qOptions;
            try {
                $oQ2 = $this->db->connx->query($qSelect);
                $result2 = $oQ2->fetchAll(PDO::FETCH_ASSOC);
                $nbOccur = $result2[0]['nbentries'];
            }
            catch (PDOException $e) {
                $this->db->dbErrorReport($prj, $e, 'count comments');
            }
        }
        return array(TRUE, $result, $nbOccur);
    }

    public function selectByProp ($prj, $id_prop) {
        $qSelect = 'SELECT * FROM dicl_'.$prj.'_comments WHERE id_prop = ' . $id_prop . ' ORDER BY datetime';
        try {
            $oQ = $this->db->connx->query($qSelect);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'select comments of prop');
            return array(FALSE, '_dberror', 0);
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        return array(TRUE, $result, count($result));
    }

    public function insertComment ($prj, $id_user, $login, $id_prop, $comment, $autocom=FALSE) {
        if ($comment == '') {
            return array(TRUE, 'empty');
        }
        list($ok, $result) = $this->getProposition($prj, $id_prop);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $data = $result[0];
        $prop_user = $data['id_user'];
        $emailComment = strip_tags($comment);
        if (!$autocom) { $this->remixComment($comment); }
        if (mb_strlen($comment) > 4500) {
            return array(FALSE, '_commenttoolong');
        }
        // autosubscribe
        $enotifauto = FALSE;
        if ($_SESSION['enotifauto'] and $id_user != $prop_user) {
            global $dbaPropositions; 
            $dbaPropositions->subscribe($prj, $id_prop, $id_user);
            $enotifauto = TRUE;
        }
        // insertion
        $autocom = ($autocom) ? 'TRUE' : 'FALSE';
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_comments (id_prop, id_user, login, comment, autocom, prop_user) VALUES ' . "($id_prop, $id_user, '$login', '$comment', $autocom, $prop_user)");
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_prop SET nbcomments = nbcomments + 1 WHERE id_prop = ' . $id_prop);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_members SET nbcomments = nbcomments + 1 WHERE id_member = ' . $id_user);
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'new comment');
            return array(FALSE, '_dberror');
        }
        // e-mail notification
        if ($data['emailnotif'] or $data['nbnotif'] > 0) {
            $uiMail = parse_ini_file('./html/' . $prj . '/mail.ini', TRUE);
            $subject = 'dicollecte ['.$prj.'] #'.$data['id_prop'] . ' ' . $data['action'] . ' =?UTF-8?B?' . base64_encode($data['lemma']) . '?=';
            $msg = URL_ROOT . 'proposition.php?prj=' . $prj . '&id=' . $id_prop . PHP_EOL . PHP_EOL
                 . sprintf($uiMail['newcomment']['label'], $login) . PHP_EOL . PHP_EOL
                 . stripslashes($emailComment);
            if ($data['emailnotif']) {
                $msg2 = $msg . PHP_EOL . PHP_EOL . '-- ' . PHP_EOL
                      . $uiMail['newcomment']['yourlog'] . PHP_EOL
                      . '<' . URL_ROOT . 'comments.php?prj=' . $prj . '&prop_user=' . $prop_user . '&user=' . $data['login'] . '>' . PHP_EOL . PHP_EOL
                      . $uiMail['newcomment']['unsubs'] . PHP_EOL
                      . '<' . URL_ROOT . 'user_c.php?prj=' . $prj . '&cmd=noemailnotif&id_user=' . $prop_user . '&code=' . md5($data['pw']) . '>' . PHP_EOL . PHP_EOL;
                $this->sendMail($data['email'], $subject, $msg2);
            }
            if ($data['nbnotif'] > 0 or $enotifauto) {
                $qSelect = 'SELECT u.email  FROM dicl_users u  JOIN dicl_'.$prj.'_propsub p ON u.id_user = p.id_user'
                         . ' WHERE id_prop = ' . $data['id_prop'];
                try {
                    $oQ = $this->db->connx->query($qSelect);
                }
                catch (PDOException $e) {
                    $this->db->dbErrorReport($prj, $e, 'comment: select e-mail notifications');
                    return array(FALSE, '_dberror', 0);
                }
                $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
                foreach ($result as $data) {
                    $this->sendMail($data['email'], $subject, $msg);
                }
            }
        }
        return array(TRUE, 'ok');
    }
    
    public function deleteComment ($prj, $id_com) {
        if (!is_numeric($id_com)) {
            return array(FALSE, '_data');
        }
        list($ok, $result) = $this->getComment($prj, $id_com);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $data = $result[0];
        if (!($data['id_user'] == $_SESSION['id_user'] or $_SESSION['rank_'.$prj] <= 2)) {
            return array(FALSE, '_noaccess');
        }
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_comments WHERE id_com = ' . $id_com);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_prop SET nbcomments = nbcomments - 1 WHERE id_prop = ' . $data['id_prop']);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_members SET nbcomments = nbcomments - 1 WHERE id_member = ' . $data['id_user']);
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'delete comment');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    public function editComment ($prj, $id_com, $newcomment) {
        if (!is_numeric($id_com) or $newcomment == '') {
            return array(FALSE, '_data');
        }
        list($ok, $result) = $this->getComment($prj, $id_com);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $data = $result[0];
        if (!($data['id_user'] == $_SESSION['id_user'] or $_SESSION['rank_'.$prj] <= 2)) {
            return array(FALSE, '_noaccess');
        }
        $this->remixComment($newcomment);
        try {
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_comments SET comment = ' . "'".$newcomment."'" . ' WHERE id_com = ' . $id_com);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'edit comment');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    
    /* PRIVATE */
    
    private function getProposition ($prj, $id_prop) {
        $qSelect = 'SELECT p.*, login, pw, emailnotif, email  FROM dicl_'.$prj.'_prop p  JOIN dicl_users u ON u.id_user = p.id_user  WHERE id_prop = ' . $id_prop;
        try {
            $oQ = $this->db->connx->query($qSelect);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'get proposition');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        if (count($result) == 0) {
            return array(FALSE, '_noprop');
        }
        return array(TRUE, $result);
    }
    
    private function getComment ($prj, $id_com) {
        try {
            $oQ = $this->db->connx->query('SELECT * FROM dicl_'.$prj.'_comments WHERE id_com = ' . $id_com);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'get comment');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        if (count($result) == 0) {
            return array(FALSE, '_nocomm');
        }
        return array(TRUE, $result);
    }
    
    private function remixComment (&$comment) {
        $comment = strip_tags($comment, '<b><i><u><s><q>');
        $comment = str_replace(array('<q>', '</q>'), array('<blockquote><p>', '</p></blockquote>'), $comment);
        $comment = preg_replace('`[a-zA-Z]+:\/\/([a-z][a-z0-9_\..-]*[a-z]{2,6})[^()\s]*`i', '<a href="$0">$1…</a>', $comment);
        $comment = str_replace('&', '&amp;', $comment);
        $comment = nl2br($comment);
    }
    
    private function sendMail ($to, $subject, $msg) {
        $headers = 'From: Dicollecte <' . SENDMAIL_FROM . '>' . PHP_EOL . 'MIME-Version: 1.0' . PHP_EOL
                    . 'Content-type: text/plain; charset=utf-8' . PHP_EOL . 'Content-transfer-encoding: 8bit' . PHP_EOL . 'X-Mailer: PHP' . PHP_EOL;
        mail($to, $subject, $msg, $headers);
        // for UTF8 subjects: ' =?UTF-8?B?' . base64_encode($subject) . '?='
    }
}

?>
