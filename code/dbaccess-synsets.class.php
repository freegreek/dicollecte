<?php

class dbaccessSynsets {
    /*
        This object is an access to the db for the thesaurus management.
        Modified tables are: prj_syns, users
    */
    
    public $db;
    
    /* PUBLIC */
    function __construct ($db) {
        $this->db = $db;
    }

    public function search ($prj, $search, $offset, $nbEntriesByPage) {
        $this->cleanSynset($search);
        $elems = explode('|', $search);
        $search = '';
        foreach ($elems as $s) {
            $search .= " AND synset LIKE '%".$s."%'";
        }
        try {
            $oQ = $this->db->connx->query('SELECT * FROM dicl_'.$prj.'_syns  WHERE deleted = FALSE' . $search
                                        . ' ORDER BY synset OFFSET ' . $offset . ' LIMIT ' . $nbEntriesByPage);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'Synsets: search');
            return array(FALSE, '_dberror', 0);
        }
        $result = $oQ->fetchAll();
        $nbOccur = count($result);
        if ($nbOccur > 0) {
            try {
                $oQ2 = $this->db->connx->query('SELECT COUNT(id_synset) AS nbentries  FROM dicl_'.$prj.'_syns  WHERE deleted = FALSE' . $search);
                $result2 = $oQ2->fetchAll(PDO::FETCH_ASSOC);
                $nbOccur = $result2[0]['nbentries'];
            }
            catch (PDOException $e) {
                $this->db->dbErrorReport($prj, $e, 'synsets: count entries');
            }
        }
        return array(TRUE, $result, $nbOccur);
    }

    public function createSynset ($prj, $synset, $pos, $tags, $id_user) {
        $this->cleanSynset($synset);
        $pos = trim($pos);
        $tags = trim($tags);
        $nbsyn = substr_count($synset, '|') + 1;
        if ($synset == '' or $pos == '' or $nbsyn < 2) {
            return array(FALSE, '_data');
        }
        // log label
        $now = time();
        $logMsg = str_replace('|', ' | ', $synset) . ' • <span class="new"><b>[' . $nbsyn . ']</b></span>';
        if ($pos != '') { $logMsg .= ' • '.$pos; }
        if ($tags != '') { $logMsg .= ' • '.$tags; }
        // create
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_syns (pos, tags, synset, nbsyn, lastedit, id_user) VALUES ' . "('$pos', '$tags', '$synset', $nbsyn, $now, $id_user);");
            $id_synset = $this->db->connx->lastInsertId('dicl_'.$prj.'_syns_id_synset_seq');
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_members  SET nbactthes = nbactthes + 1  WHERE id_member = ' . $id_user);
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES ' . "($id_user, $id_synset, 'Y', '+', '$logMsg', $now)");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'synsets: new synset');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok → #'.$id_synset);
    }

    public function updateSynset ($prj, $id_synset, $synset, $pos, $tags, $id_user) {
        if (!is_numeric($id_synset)) {
            return array(FALSE, '_data');
        }
        list($ok, $result) = $this->getSynset($prj, $id_synset);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $data = $result[0];
        $this->addSlashesOnArray($data);
        $synset = preg_replace('`( *\| *)+`', '|', $synset);
        $this->cleanSynset($synset);
        $pos = trim($pos);
        $tags = trim($tags);
        $nbsyn = substr_count($synset, '|') + 1;
        if ($synset == '' or $pos == '' or $nbsyn < 2) {
            return array(FALSE, '_data');
        }
        // log label
        $now = time();
        $logMsg = str_replace('|', ' | ', $data['synset']) . ' • <span class="del"><b>[' . $data['nbsyn'] . ']</b></span> → <span class="new"><b>[' . $nbsyn . ']</b></span> • ';
        $logMsg .= $this->diffSynsets($data['synset'], $synset);
        if ($data['pos'] != $pos) $logMsg .= ' | <samp>' . $data['pos'] . '</samp> → <samp class="new">' . $pos . '</samp>';
        if ($data['tags'] != $tags) $logMsg .= ' | <samp>' . $data['tags'] . '</samp> → <samp class="new">' . $tags . '</samp>';
        // update
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_shist (id_synset, pos, tags, synset, nbsyn, lastedit, id_user) '
                                      . "VALUES ($id_synset, '{$data['pos']}', '{$data['tags']}', '{$data['synset']}', {$data['nbsyn']}, {$data['lastedit']}, {$data['id_user']})");
            $id_hist = $this->db->connx->lastInsertId('dicl_'.$prj.'_shist_id_hist_seq');
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_syns  SET nbsyn = ' . $nbsyn . ", synset = '$synset', pos = '$pos', tags = '$tags', nbhist = nbhist + 1,"
                                                               . ' lastedit = ' . $now . ', id_user = ' . $id_user . '  WHERE id_synset = ' . $id_synset);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_members  SET nbactthes = nbactthes + 1  WHERE id_member = ' . $id_user);
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES ' . "($id_user, $id_synset, 'Y', '>', '$logMsg', $now)");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'synsets: update synset');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok → #'.$id_hist);
    }

    public function deleteSynset ($prj, $id_synset, $id_user) {
        if (!is_numeric($id_synset)) {
            return array(FALSE, '_data');
        }
        list($ok, $result) = $this->getSynset($prj, $id_synset);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $data = $result[0];
        $this->addSlashesOnArray($data);
        // log label
        $now = time();
        $logMsg = str_replace('|', ' | ', $data['synset']) . ' • <span class="del"><b>[' . $data['nbsyn'] . ']</b></span>';
        if ($data['pos'] != '') { $logMsg .= ' • '.$data['pos']; }
        if ($data['tags'] != '') { $logMsg .= ' • '.$data['tags']; }
        // delete
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_syns  SET deleted = TRUE  WHERE id_synset = ' . $id_synset);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_members  SET nbactthes = nbactthes + 1  WHERE id_member = ' . $id_user);
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES ' . "($id_user, $id_synset, 'Y', '-', '$logMsg', $now)");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'synsets: erase entry');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    public function undeleteSynset ($prj, $id_synset, $id_user) {
        if (!is_numeric($id_synset)) {
            return array(FALSE, '_data');
        }
        list($ok, $result) = $this->getSynset($prj, $id_synset);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $data = $result[0];
        $this->addSlashesOnArray($data);
        // log label
        $now = time();
        $logMsg = str_replace('|', ' | ', $data['synset']) . ' • <span class="new"><b> ← [' . $data['nbsyn'] . ']</b></span>';
        if ($data['pos'] != '') { $logMsg .= ' • '.$data['pos']; }
        if ($data['tags'] != '') { $logMsg .= ' • '.$data['tags']; }
        // delete
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_syns  SET deleted = FALSE  WHERE id_synset = ' . $id_synset);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_members  SET nbactthes = nbactthes + 1  WHERE id_member = ' . $id_user);
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES ' . "($id_user, $id_synset, 'Y', '+', '$logMsg', $now)");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'synsets: undelete entry');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }

    public function getSynset ($prj, $id_synset) {
        if (!is_numeric($id_synset)) {
            return array(FALSE, '_data');
        }
        try {
            $oQ = $this->db->connx->query('SELECT *  FROM dicl_'.$prj.'_syns WHERE id_synset = ' . $id_synset);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'select synset');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        if (count($result) == 0) {
            return array(FALSE, '_noentry');
        }
        return array(TRUE, $result);
    }

    public function getHistSynsets ($prj, $id_synset) {
        if (!is_numeric($id_synset)) {
            return array(FALSE, '_data');
        }
        $qQuery = 'SELECT s.*, u.login FROM dicl_'.$prj.'_shist s'
                . ' LEFT OUTER JOIN dicl_users u  ON s.id_user = u.id_user WHERE id_synset = ' . $id_synset . ' ORDER BY lastedit DESC';
        try {
            $oQ = $this->db->connx->query($qQuery);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'Synset: read history');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        return array(TRUE, $result);
    }

    public function restoreHist ($prj, $id_hist, $id_user) {
        list($ok, $resultH) = $this->getHistSynset($prj, $id_hist);
        if (!$ok) {
            return array(FALSE, $resultH);
        }
        $dataH = $resultH[0];
        $this->addSlashesOnArray($dataH);
        list($ok, $resultS) = $this->getSynset($prj, $dataH['id_synset']);
        if (!$ok) {
            return array(FALSE, $resultS);
        }
        $dataS = $resultS[0];
        $this->addSlashesOnArray($dataS);
        // log label
        $now = time();
        $logMsg = str_replace('|', ' | ', $dataS['synset']) . ' • <span class="del"><b>[' . $dataS['nbsyn'] . ']</b></span> → <span class="new"><b>[' . $dataH['nbsyn'] . ']</b></span> • ';
        $logMsg .= $this->diffSynsets($dataS['synset'], $dataH['synset']);
        if ($dataH['pos'] != $dataS['pos']) $logMsg .= ' | <samp>' . $dataS['pos'] . '</samp> → <samp class="new">' . $dataH['pos'] . '</samp>';
        if ($dataH['tags'] != $dataS['tags']) $logMsg .= ' | <samp>' . $dataS['tags'] . '</samp> → <samp class="new">' . $dataH['tags'] . '</samp>';
        // update
        $this->db->connx->beginTransaction();
        try {
            // switch the current entry with the history entry
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_syns'
                                 . ' SET nbsyn = '.$dataH['nbsyn'].', lastedit = '.$dataH['lastedit'].', id_user = '.$dataH['id_user'].','
                                 . " synset = '{$dataH['synset']}', pos = '{$dataH['pos']}', tags = '{$dataH['tags']}'"
                                 . ' WHERE id_synset = ' . $dataS['id_synset']);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_shist'
                                 . ' SET nbsyn = '.$dataS['nbsyn'].', lastedit = '.$dataS['lastedit'].', id_user = '.$dataS['id_user'].','
                                 . " synset = '{$dataS['synset']}', pos = '{$dataS['pos']}', tags = '{$dataS['tags']}'"
                                 . ' WHERE id_hist = ' . $dataH['id_hist']);
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES ' . "($id_user, {$dataH['id_synset']}, 'Y', ':', '$logMsg', $now)");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'synsets: restore history synset');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    public function eraseHist ($prj, $id_hist, $id_user) {
        if (!is_numeric($id_hist)) {
            return array(FALSE, '_data');
        }
        list($ok, $result) = $this->getHistSynset($prj, $id_hist);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $data = $result[0];
        $this->addSlashesOnArray($data);
        $now = time();
        $logMsg = str_replace('|', ' | ', $data['synset']) . ' • <span class="del"><b>[' . $data['nbsyn'] . ']</b></span>';
        if ($data['pos'] != '') { $logMsg .= ' • '.$data['pos']; }
        if ($data['tags'] != '') { $logMsg .= ' • '.$data['tags']; }
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_syns  SET nbhist = nbhist - 1  WHERE id_synset = ' . $data['id_synset']);
            $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_shist WHERE id_hist = ' . $id_hist);
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES ' . "($id_user, {$data['id_synset']}, 'Y', '<', '$logMsg', $now)");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'synset: erase history synset');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }

    public function eraseSynset ($prj, $id_synset, $id_user) {
        if (!is_numeric($id_synset)) {
            return array(FALSE, '_data');
        }
        list($ok, $result) = $this->getSynset($prj, $id_synset);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $data = $result[0];
        $this->addSlashesOnArray($data);
        // log label
        $now = time();
        $logMsg = str_replace('|', ' | ', $data['synset']) . ' • <span class="del"><b>[' . $data['nbsyn'] . ']</b></span>';
        if ($data['pos'] != '') { $logMsg .= ' • '.$data['pos']; }
        if ($data['tags'] != '') { $logMsg .= ' • '.$data['tags']; }
        // erase
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_shist  WHERE id_synset = ' . $id_synset);
            $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_syns  WHERE id_synset = ' . $id_synset);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_members  SET nbactthes = nbactthes + 1  WHERE id_member = ' . $id_user);
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES ' . "($id_user, $id_synset, 'Y', '-', '$logMsg', $now)");
            //$this->db->connx->exec('UPDATE dicl_'.$prj.'_log  SET id = 0  WHERE id = ' . $id_synset . " AND cat = 'Y'");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'synsets: erase synset');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }

    
    /* PRIVATE */
    
    private function cleanSynset(&$synset) {
        $synset = str_replace("\n", '|', $synset);
        $synset = preg_replace('`( *\|+ *)+`', '|', $synset);
        $synset = trim($synset, '|  ');
    }

    private function getHistSynset ($prj, $id_hist) {
        try {
            $oQ = $this->db->connx->query('SELECT *  FROM dicl_'.$prj.'_shist h  WHERE id_hist = '. $id_hist);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'Synsets: get history synset');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        if (count($result) == 0) {
            return array(FALSE, '_noentry');
        }
        return array(TRUE, $result);
    }
    
    private function diffSynsets (&$oldSynset, &$newSynset) {
        $msg = '';
        $aNewSynset = explode('|', $newSynset);
        $aOldSynset = explode('|', $oldSynset);
        $aRemoved = array_diff($aOldSynset, $aNewSynset);
        $aAdded = array_diff($aNewSynset, $aOldSynset);
        foreach ($aRemoved as $s) {
            $msg .= ' <span class="del">−'.$s.'</span>';
        }
        foreach ($aAdded as $s) {
            $msg .= ' <span class="new">+'.$s.'</span>';
        }
        return $msg;
    }
    
    private function addSlashesOnArray (&$array) {
        foreach ($array as $key => $value) {
            if (is_string($value)) {
                $array[$key] = addslashes($value);
            }
        }
    }
}

?>
