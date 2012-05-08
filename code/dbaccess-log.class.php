<?php

class dbaccessLog {
    /*
        This object is an access to the db for notes.
        Modified tables are: prj_log
    */

    public $db;
    
    
    /* PUBLIC */
    
    function __construct ($db) {
        $this->db = $db;
    }
    
    public function select ($prj, $offset, $nbEntriesByPage, $cat, $id_user=NULL) {
        $qOptions = array();
        if ($cat == 'D' or $cat == 'E' or $cat == 'P' or $cat == 'T' or $cat == 'N' or $cat == 'A' or $cat == 'S' or $cat == 'F' or $cat == 'Y') $qOptions[] = "cat = '$cat'";
        if ($id_user) $qOptions[] = 'l.id_user = ' . $id_user;
        $qOptions = $this->db->createSelectionSubQuery($qOptions);
        $qSelect = 'SELECT id_log, l.id_user, id, cat, action, label, l.datetime, login'
                 . ' FROM dicl_'.$prj.'_log l  JOIN dicl_users u  ON l.id_user = u.id_user ' . $qOptions
                 . ' ORDER BY l.datetime DESC  OFFSET ' . $offset . '  LIMIT ' . $nbEntriesByPage;
        try {
            $oQ = $this->db->connx->query($qSelect);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'select log');
            return array(FALSE, '_dberror', 0);
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        $nbOccur = count($result);
        if ($nbOccur > 0) {
            $qSelect = 'SELECT COUNT(id_log) AS nblog FROM dicl_'.$prj.'_log l ' . $qOptions;
            try {
                $oQ2 = $this->db->connx->query($qSelect);
                $result2 = $oQ2->fetchAll(PDO::FETCH_ASSOC);
                $nbOccur = $result2[0]['nblog'];
            }
            catch (PDOException $e) {
                $this->db->dbErrorReport($prj, $e, 'count log');
            }
        }
        return array(TRUE, $result, $nbOccur);
    }
    
    public function createAnnounce ($prj, $logMsg, $id_user) {
        $this->remixAnnounce($logMsg);
        $now = time();
        try {
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES ' . "($id_user, 0, 'A', 'a', '$logMsg', $now)");
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'create announce');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    public function deleteAnnounce ($prj, $id_log) {
        list($ok, $result) = $this->getAnnounce($prj, $id_log);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $data = $result[0];
        if ($data['cat'] != 'A' or $_SESSION['rank_'.$prj] > 2) {
            return array(FALSE, '_noaccess');
        }
        try {
            $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_log WHERE id_log = ' . $id_log);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'delete announce');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    public function editAnnounce ($prj, $id_log, $newannounce) {
        list($ok, $result) = $this->getAnnounce($prj, $id_log);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $data = $result[0];
        if ($data['cat'] != 'A' or $_SESSION['rank_'.$prj] > 2) {
            return array(FALSE, '_noaccess');
        }
        $this->remixAnnounce($newannounce);
        try {
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_log SET label = ' . "'".$newannounce."'" . ' WHERE id_log = ' . $id_log);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'edit announce');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    /* PRIVATE */

    private function getAnnounce ($prj, $id_log) {
        try {
            $oQ = $this->db->connx->query('SELECT * FROM dicl_'.$prj.'_log WHERE id_log = ' . $id_log);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'get log');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        if (count($result) == 0) {
            return array(FALSE, '_noann');
        }
        return array(TRUE, $result);
    }

    private function remixAnnounce (&$ann) {
        $ann = strip_tags($ann, '<b><i><u><s>');
        $ann = preg_replace('`[a-zA-Z]+:\/\/([a-z][a-z0-9_\..-]*[a-z]{2,6})[^\s]*`i', ' <a href="$0">$1…</a> ', $ann);
        $ann = str_replace('&', '&amp;', $ann);
        $ann = nl2br($ann);
    }
}

?>
