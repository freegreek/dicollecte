<?php

class dbaccessThesaurus {
    /*
        This object is an access to the db for the thesaurus management.
        Modified tables are: prj_thes, users
    */
    
    public $db;
    
    /* PUBLIC */
    function __construct ($db) {
        $this->db = $db;
    }

    public function search ($prj, $search, $isRegEx, $offset, $nbEntriesByPage) {
        $search = ($isRegEx) ? "~ '$search'" : "LIKE '$search%'";
        try {
            $oQ = $this->db->connx->query('SELECT id_word, word, nbclass, syn FROM dicl_'.$prj.'_thes WHERE word ' . $search . ' ORDER BY word OFFSET ' . $offset . ' LIMIT ' . $nbEntriesByPage);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'Thesaurus: search');
            return array(FALSE, '_dberror', 0);
        }
        $result = $oQ->fetchAll();
        $nbOccur = count($result);
        if ($nbOccur > 0) {
            try {
                $oQ2 = $this->db->connx->query('SELECT COUNT(id_word) AS nbentries FROM dicl_'.$prj.'_thes WHERE word ' . $search);
                $result2 = $oQ2->fetchAll(PDO::FETCH_ASSOC);
                $nbOccur = $result2[0]['nbentries'];
            }
            catch (PDOException $e) {
                $this->db->dbErrorReport($prj, $e, 'count thesaurus entries');
            }
        }
        return array(TRUE, $result, $nbOccur);
    }

    public function searchExt ($prj, $search, $isRegEx, $offset, $nbEntriesByPage) {
        $search = ($isRegEx) ? "~ '$search'" : "LIKE '%$search%'";
        try {
            $oQ = $this->db->connx->query('SELECT id_word, word, nbclass, syn FROM dicl_'.$prj.'_thes WHERE syn ' . $search . ' ORDER BY word OFFSET ' . $offset . ' LIMIT ' . $nbEntriesByPage);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'Thesaurus: search');
            return array(FALSE, '_dberror', 0);
        }
        $result = $oQ->fetchAll();
        $nbOccur = count($result);
        if ($nbOccur > 0) {
            try {
                $oQ2 = $this->db->connx->query('SELECT COUNT(id_word) AS nbentries FROM dicl_'.$prj.'_thes WHERE syn ' . $search);
                $result2 = $oQ2->fetchAll(PDO::FETCH_ASSOC);
                $nbOccur = $result2[0]['nbentries'];
            }
            catch (PDOException $e) {
                $this->db->dbErrorReport($prj, $e, 'count thesaurus entries');
            }
        }
        return array(TRUE, $result, $nbOccur);
    }

    public function readAndLockEntry ($prj, $id_word, $lockDuration, $id_user) {
        $qQuery = 'SELECT id_word, word, nbclass, syn, lastedit, t.id_user, u.login, lock, keyid'
                . ' FROM dicl_'.$prj.'_thes t  LEFT OUTER JOIN dicl_users u  ON t.id_user = u.id_user WHERE id_word = ' . $id_word;
        try {
            $oQ = $this->db->connx->query($qQuery);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'Thesaurus: read and lock entry');
            return array(FALSE, '_dberror', FALSE);
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        if (count($result) === 0) {
            return array(FALSE, '_noentry', FALSE);
        }
        $data = $result[0];
        $now = time();
        if ($now > $data['lock'] or $id_user == $data['keyid']) {
            // we lock this entry
            list($ok, $info) = $this->lockEntry($prj, $id_word, $now + $lockDuration, $id_user);
            if ($ok) {
                return array(TRUE, $result, TRUE);
            }
            else {
                return array(FALSE, $info, FALSE);
            }
        }
        else {
            // the entry is locked
            return array(TRUE, $result, FALSE);
        }
    }
    
    public function readHistoryEntry ($prj, $id_word, $id_user) {
        $qQuery = 'SELECT id_word, word, nbclass, syn, lastedit, t.id_user, u.login, lock, keyid'
                . ' FROM dicl_'.$prj.'_thes t  LEFT OUTER JOIN dicl_users u  ON t.id_user = u.id_user WHERE id_word = ' . $id_word;
        try {
            $oQ = $this->db->connx->query($qQuery);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'Thesaurus: read entry');
            return array(FALSE, '_dberror', FALSE);
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        if (count($result) === 0) {
            return array(FALSE, '_noentry', FALSE);
        }
        $data = $result[0];
        $word = addslashes($data['word']);
        $now = time();
        $isEditable = ($now > $data['lock'] or $id_user == $data['keyid']) ? TRUE : FALSE;
        try {
            $oQ = $this->db->connx->query("SELECT id_hist, word, nbclass, syn, lastedit, t.id_user, u.login
                                             FROM dicl_{$prj}_thist t  LEFT OUTER JOIN dicl_users u ON t.id_user = u.id_user
                                            WHERE word = '$word' ORDER BY lastedit DESC");
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'Thesaurus: read history entry');
            return array(FALSE, '_dberror', FALSE);
        }
        $resulthist = $oQ->fetchAll(PDO::FETCH_ASSOC);
        return array(TRUE, array_merge($result, $resulthist), $isEditable);
    }
    
    public function newEntry ($prj, $word, $syn, $id_user) {
        list($ok, $result) = $this->getEntry($prj, $word);
        if ($ok) {
            return array(FALSE, 'alreadyexists');
        }
        $nbclass = substr_count($syn, '##') + 1;
        $now = time();
        $this->db->connx->beginTransaction();
        try {
            $oQ = $this->db->connx->query("SELECT nextval('dicl_{$prj}_thes_id_word_seq') as key");
            $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
            $id_word = $result[0]['key'];
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_thes (id_word, word, nbclass, syn, lastedit) VALUES ' . "($id_word, '$word', $nbclass, '$syn', $now);");
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_members SET nbactthes = nbactthes + 1 WHERE id_member = ' . $id_user);
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES ' . "($id_user, $id_word, 'T', '+', '$word', $now)");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'thesaurus: new entry');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, $id_word);
    }
    
    public function updateEntry ($prj, $id_word, $syn, $id_user) {
        list($ok, $result) = $this->getEntryById($prj, $id_word);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $data = $result[0];
        $this->addSlashesOnArray($data);
        $nbclass = substr_count($syn, '##') + 1;
        $now = time();
        $label = $data['word'] . ' | [' . $data['nbclass'] . '] → [' . $nbclass . ']';
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec("INSERT INTO dicl_{$prj}_thist (word, nbclass, syn, lastedit, id_user)
                                         VALUES ('{$data['word']}', {$data['nbclass']}, '{$data['syn']}', {$data['lastedit']}, {$data['id_user']})");
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_thes SET nbclass = ' . $nbclass . ", syn = '$syn'" . ', lastedit = ' . $now . ', id_user = ' . $id_user .', lock = 0 WHERE id_word = ' . $data['id_word']);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_members SET nbactthes = nbactthes + 1 WHERE id_member = ' . $id_user);
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES ' . "($id_user, $id_word, 'T', '>', '$label', $now)");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'thesaurus: update entry');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    public function eraseEntry ($prj, $id_word, $id_user) {
        list($ok, $result) = $this->getEntryById($prj, $id_word);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $data = $result[0];
        $this->addSlashesOnArray($data);
        $now = time();
        $label = $data['word'] . ' | [' . $data['nbclass'] . ']';
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec("INSERT INTO dicl_{$prj}_thist (word, nbclass, syn, lastedit, id_user)
                                         VALUES ('{$data['word']}', {$data['nbclass']}, '{$data['syn']}', {$data['lastedit']}, {$data['id_user']})");
            $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_thes WHERE id_word = ' . $data['id_word']);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_members SET nbactthes = nbactthes + 1 WHERE id_member = ' . $id_user);
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES ' . "($id_user, {$data['id_word']}, 'T', '-', '$label', $now)");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'thesaurus: erase entry');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'entryerased');
    }
    
    public function deleteHistEntry ($prj, $id_hist, $id_user) {
        list($ok, $result) = $this->getHistEntry($prj, $id_hist);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $data = $result[0];
        $now = time();
        $label = $data['word'] . ' | [' . $data['nbclass'] . ']';
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_thist WHERE id_hist = ' . $id_hist);
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES ' . "($id_user, {$data['id_word']}, 'T', '<', '$label', $now)");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'thesaurus: erase history entry');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'entryerased');
    }
    
    public function restoreHistEntry ($prj, $id_hist, $id_user) {
        list($ok, $resultH) = $this->getHistEntry($prj, $id_hist);
        if (!$ok) {
            return array(FALSE, $resultH);
        }
        $dataH = $resultH[0];
        $this->addSlashesOnArray($dataH);
        list($ok, $resultE) = $this->getEntry($prj, $dataH['word']);
        if (!$ok) {
            if ($resultE != '_noentry') {
                return array(FALSE, $resultE);
            }
            $dataE = FALSE;
        }
        else {
            $dataE = $resultE[0];
            $this->addSlashesOnArray($dataE);
        }
        $now = time();
        $label = $dataH['word'] . ' | [' . $dataH['nbclass'] . '] &larr; [' . $dataE['nbclass'] . ']';
        $this->db->connx->beginTransaction();
        try {
            if ($dataE === FALSE) {
                // there is no more current entry
                $this->db->connx->exec("INSERT INTO dicl_{$prj}_thes (word, nbclass, syn, lastedit, id_user)
                                             VALUES ('{$dataE['word']}', {$dataE['nbclass']}, '{$dataE['syn']}', {$dataE['lastedit']}, {$dataE['id_user']})");
                $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_thist WHERE id_hist = ' . $id_hist);
            }
            else {
                // switch the current entry with the history entry
                $this->db->connx->exec("INSERT INTO dicl_{$prj}_thist (word, nbclass, syn, lastedit, id_user)
                                             VALUES ('{$dataE['word']}', {$dataE['nbclass']}, '{$dataE['syn']}', {$dataE['lastedit']}, {$dataE['id_user']})");
                $this->db->connx->exec("UPDATE dicl_{$prj}_thes
                                           SET nbclass = {$dataH['nbclass']}, syn = '{$dataH['syn']}', lastedit = {$dataH['lastedit']}, id_user = {$dataH['id_user']}
                                         WHERE word = '{$dataE['word']}'");
                $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_thist WHERE id_hist = ' . $id_hist);
            }
            $this->db->connx->exec("INSERT INTO dicl_{$prj}_log (id_user, id, cat, action, label, datetime) VALUES ($id_user, {$dataE['id_word']}, 'T', ':', '$label', $now)");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'thesaurus: restore history entry');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    public function showEntry ($prj, $word) {
        // returns the text of an entry
        list($ok, $result) = $this->getEntry($prj, $word);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $data = $result[0];
        $entry = $data['word'] . '|' . $data['nbclass'] . "\r\n" . str_replace('##', "\r\n", $data['syn']);
        return array(TRUE, $entry);
    }
    
    public function generateSyn ($syn, $gramm, $meaning, $synonyms) {
        // grammatical field
        $gramm = str_replace(array('##', '"', '|'), ' ', $gramm);
        $gramm = trim($gramm);
        if ($gramm == '') return $syn;
        if ($gramm{0} != '(') $gramm = '(' . $gramm;
        if ($gramm{strlen($gramm)-1} != ')') $gramm .= ')';
        // meaning field
        $meaning = str_replace(array('##', '"', '|'), ' ', $meaning);
        $meaning = trim($meaning);
        if ($meaning == '') return $syn;
        // synonyms field
        $synonyms = str_replace(array('##', '"', "\n", "\r", "\r\n"), ' ', $synonyms);
        $synonyms = preg_replace('`( *\| *)+`', '|', $synonyms);
        $synonyms = trim($synonyms, ' |');
        // sum
        if ($syn != '') $syn .= '##';
        $syn .= $gramm . '|' . $meaning;
        if ($synonyms != '') $syn .= '|' . $synonyms;
        return $syn;
    }
    
    
    /* PRIVATE */
    
    private function getEntry ($prj, $word) {
        try {
            $oQ = $this->db->connx->query("SELECT * FROM dicl_{$prj}_thes WHERE word = '$word'");
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'Thesaurus: get entry');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll();
        if (count($result) == 0) {
            return array(FALSE, '_noentry');
        }
        return array(TRUE, $result);
    }
    
    private function getEntryById ($prj, $id_word) {
        try {
            $oQ = $this->db->connx->query('SELECT * FROM dicl_'.$prj.'_thes WHERE id_word = ' . $id_word);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'Thesaurus: get entry');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll();
        if (count($result) == 0) {
            return array(FALSE, '_noentry');
        }
        return array(TRUE, $result);
    }
    
    private function getHistEntry ($prj, $id_hist) {
        $qSelect = 'SELECT h.id_hist, h.word, h.nbclass, h.syn, h.lastedit, h.id_user, t.id_word'
                 . ' FROM dicl_'.$prj.'_thist h  LEFT OUTER JOIN dicl_'.$prj.'_thes t ON h.word = t.word  WHERE id_hist = '. $id_hist;
        try {
            $oQ = $this->db->connx->query($qSelect);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'Thesaurus: get history entry');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll();
        if (count($result) == 0) {
            return array(FALSE, '_noentry');
        }
        return array(TRUE, $result);
    }
    
    private function lockEntry ($prj, $id, $unixtimestamp, $id_user) {
        try {
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_thes SET lock = ' . $unixtimestamp . ', keyid = ' . $id_user . ' WHERE id_word = ' . $id);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'lock thesaurus entry');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
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
