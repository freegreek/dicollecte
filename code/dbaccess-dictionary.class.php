<?php

class dbaccessDictionary {
    /*
        This object is an access to the db for the dictionary.
        Modified tables are: prj_dic, prj_comments, prj_prop
    */
    
    public $db;
    
    /* PUBLIC */
    function __construct ($db) {
        $this->db = $db;
    }

    public function search ($prj, $tagSearch, $entry, $id_user, $order, $offset, $nbEntriesByPage) {
        // options
        $qOptions = array();
        if ($entry['lemma'] != '') {
            switch ($tagSearch) {
                case 'R': $qOptions[] = "lemma ~ '{$entry['lemma']}'"; break;
                //case 'P': $qOptions[] = "metaphone(lemma) = metaphone('{$entry['lemma']}')"; break;
                default: $qOptions[] = "lemma ILIKE '{$entry['lemma']}%'";
            }
        }
        if (is_numeric($id_user) and $id_user != 0) {
            $qOptions[] = 'id_user = ' . $id_user;
        }
        $fields = array('flags', 'po', 'is', 'ds', 'ts', 'ip', 'dp', 'tp', 'sp', 'pa', 'st', 'al', 'ph', 'lex', 'sem', 'ety');
        foreach ($fields as $field) {
            if (isset($entry[$field]) and $entry[$field] != '') {
                $qOptions[] = ($entry[$field] == '//') ? '"' . $field . '" = ' .  "''" : '"' . $field . '" LIKE ' .  "'%" . $entry[$field] . "%'";
            }
        }
        if (isset($entry['dic']) and $entry['dic'] != '') { $qOptions[] = 'dic = \'' . $entry['dic'] . "'"; }
        $qFilter = $this->db->createSelectionSubQuery($qOptions);
        switch ($order) {
            case 'C': $sqlOrderBy = 'chk, datetime DESC'; break;
            case 'D': $sqlOrderBy = 'datetime DESC, lemma'; break;
            case 'N': $sqlOrderBy = 'nbnotes DESC, lemma'; break;
            case 'O': $sqlOrderBy = 'closed, lemma'; break;
            case 'I': $sqlOrderBy = 'ifq DESC, lemma'; break;
            default: $sqlOrderBy = 'lemma';
        }
        // search
        $qSelect = 'SELECT * FROM dicl_'.$prj.'_dic' . $qFilter . ' ORDER BY ' . $sqlOrderBy . ' OFFSET ' . $offset . ' LIMIT ' . $nbEntriesByPage;
        try {
            $oQ = $this->db->connx->query($qSelect);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'search in the dictionary');
            return array(FALSE, '_dberror', 0);
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        $nbOccur = count($result);
        if ($nbOccur > 0) {
            $qSelect = 'SELECT COUNT(id_entry) AS nbentries FROM dicl_'.$prj.'_dic' . $qFilter;
            try {
                $oQ2 = $this->db->connx->query($qSelect);
                $result2 = $oQ2->fetchAll(PDO::FETCH_ASSOC);
                $nbOccur = $result2[0]['nbentries'];
            }
            catch (PDOException $e) {
                $this->db->dbErrorReport($prj, $e, 'count dictionary entries');
            }
        }
        return array(TRUE, $result, $nbOccur);
    }
    
    public function selectEntry ($prj, $id_entry) {
        if (!is_numeric($id_entry)) {
            return array(FALSE, '_data');
        }
        try {
            $oQ = $this->db->connx->query('SELECT * FROM dicl_'.$prj.'_dic WHERE id_entry = ' . $id_entry);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'select dictionary entry');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, $oQ->fetchAll(PDO::FETCH_ASSOC));
    }
    
    public function createNewEntry ($prj, $dbaNotes, $id_user, $entry, $comment) {
        list ($ok, $msgcode) = $this->doesEntryExistInDictionary ($prj, $entry->lemma, $entry->flags);
        if (!$ok) $msgcode = 'newentry';
        // fields and values
        $dbEntryFields = 'lemma, flags, po, "is", ds, ts, ip, dp, tp, sp, pa, st, al, ph, lex, sem, ety, dic,';
        $dbEntryValues = '';
        $fields = array('lemma', 'flags', 'po', 'is', 'ds', 'ts', 'ip', 'dp', 'tp', 'sp', 'pa', 'st', 'al', 'ph', 'lex', 'sem', 'ety', 'dic');
        foreach ($fields as $field) {
            $dbEntryValues .= "'" . $entry->$field . "', ";
        }
        // log label
        global $prjDic;
        $data = (array) $entry;
        $logMsg = $this->getLogMsgIdEntry($data);
        $this->remixLogMsg($comment);
        if ($comment != '') $logMsg .= ' | ' . $comment; 
        $now = time();
        // insertion
        $this->db->connx->beginTransaction();
        try {
            $oQ = $this->db->connx->query("SELECT nextval('dicl_{$prj}_dic_id_entry_seq') as key");
            $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
            $id_entry = $result[0]['key'];
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_dic (id_entry, '.$dbEntryFields.' closed, chk, id_user) VALUES (' . $id_entry . ', ' . $dbEntryValues . "TRUE, '2', " . $id_user . ')');
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_members SET nbadddict = nbadddict + 1 WHERE id_member = ' . $id_user);
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES ' . "($id_user, $id_entry, 'D', '+', '$logMsg', $now)");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'dictionary: new entry');
            return array(FALSE, '_dberror', 0);
        }
        return array(TRUE, $msgcode, $id_entry);
    }
    
    public function updateEntry ($prj, $id_entry, $id_user, $entry) {
        if (!is_numeric($id_entry)) {
            return array(FALSE, '_data');
        }
        list($ok, $result) = $this->getEntry ($prj, $id_entry);
        if (!$ok) {
            return array(FALSE, $result);
        }
        // update query
        global $activeFields;
        $qUpdate = 'UPDATE dicl_'.$prj.'_dic  SET lemma = ' . "'" . $entry->lemma . "'";
        $fields = array('flags', 'po', 'is', 'ds', 'ts', 'ip', 'dp', 'tp', 'sp', 'pa', 'st', 'al', 'ph', 'lex', 'sem', 'ety', 'dic');
        foreach ($fields as $field) {
            if ($activeFields[$field]) $qUpdate .= ', "' . $field . '" = \'' . $entry->$field . "'"; 
        }
        $qUpdate .= ' WHERE id_entry = ' . $id_entry;
        // log label
        global $prjDic;
        $data = $result[0];
        addSlashesOnArray($data);
        $logMsg = $this->getLogMsgIdEntry($data);
        $fields = array('lemma', 'flags', 'po', 'is', 'ds', 'ts', 'ip', 'dp', 'tp', 'sp', 'pa', 'st', 'al', 'ph', 'lex', 'sem', 'ety');
        foreach ($fields as $field) {
            if ($activeFields[$field] and $data[$field] != $entry->$field) $logMsg .= ' | <samp>' . $data[$field] . '</samp> → <samp class="new">' . $entry->$field . '</samp>';
        }
        if ($activeFields['dic'] and $data['dic'] != $entry->dic) $logMsg .= ' | <samp>' . $prjDic[$data['dic']] . '</samp> → <samp class="new">' . $prjDic[$entry->dic] . '</samp>';
        $now = time();
        // update
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec($qUpdate);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_members SET nbactdict = nbactdict + 1 WHERE id_member = ' . $id_user);
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES ' . "($id_user, $id_entry, 'D', '>', '$logMsg', $now)");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'update dictionary entry');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'entryupdated');
    }
    
    public function eraseEntry ($prj, $id_entry, $id_user) {
        if (!is_numeric($id_entry)) {
            return array(FALSE, '_data');
        }
        list($ok, $result) = $this->getEntry ($prj, $id_entry);
        if (!$ok) {
            return array(FALSE, $result);
        }
        // log label
        global $prjDic;
        $data = $result[0];
        addSlashesOnArray($data);
        $logMsg = $this->getLogMsgIdEntry($data);
        $now = time();
        // update
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_dic  WHERE id_entry = ' . $id_entry);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_members SET nbactdict = nbactdict + 1 WHERE id_member = ' . $id_user);
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES ' . "($id_user, $id_entry, 'D', '-', '$logMsg', $now)");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'erase dictionary entry');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'entryerased');
    }
    
    public function createPropModifyEntry ($prj, $dbaComments, $id_user, $id_entry, $entry, $comment) {
        if (!is_numeric($id_entry)) {
            return array(FALSE, '_data', 0);
        }
        list($ok, $result) = $this->getEntry ($prj, $id_entry);
        if (!$ok) {
            return array(FALSE, $result, 0);
        }
        $data = $result[0];
        addSlashesOnArray($data);
        // fields, values and autocomment
        global $prjDic;
        $dbEntryFields = 'lemma, flags, po, "is", ds, ts, ip, dp, tp, sp, pa, st, al, ph, lex, sem, ety, dic, ';
        $dbEntryValues = '';
        $autocomment = '';
        // string fields
        $fields = array('lemma', 'flags', 'po', 'is', 'ds', 'ts', 'ip', 'dp', 'tp', 'sp', 'pa', 'st', 'al', 'ph', 'lex', 'sem', 'ety');
        foreach ($fields as $field) {
            $dbEntryValues .= "'" . $entry->$field . "', ";
            if ($data[$field] != $entry->$field) $autocomment .= '<samp>' . $data[$field] . '</samp> → <samp class="new">' . $entry->$field . '</samp><br />';
        }
        // other dicollecte fields
        $dbEntryValues .= "'" . $entry->dic . "', ";
        if ($data['dic'] != $entry->dic) $autocomment .= '<samp>' . $prjDic[$data['dic']] . '</samp> → <samp class="new">' . $prjDic[$entry->dic] . '</samp><br />';
        if ($autocomment == '') $autocomment = 'Ø';
        // suggest modifications
        $this->db->connx->beginTransaction();
        try {
            $oQ = $this->db->connx->query("SELECT nextval('dicl_{$prj}_prop_id_prop_seq') as key");
            $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
            $id_prop = $result[0]['key'];
            $qInsert1 = 'INSERT INTO dicl_'.$prj.'_prop (id_prop, id_entry, id_user, action, ' . $dbEntryFields . ' nbcomments)'
                      . ' VALUES ('. $id_prop . ', ' . $id_entry . ', ' . $id_user . ', ' . "'>', " . $dbEntryValues . ' 1)';
            $qInsert2 = 'INSERT INTO dicl_'.$prj.'_comments (id_prop, id_user, login, comment, autocom) VALUES ' . "($id_prop, $id_user, '{$_COOKIE['login']}', '$autocomment', TRUE)";
            $this->db->connx->exec($qInsert1);
            $this->db->connx->exec($qInsert2);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_dic SET id_prop = ' . $id_prop . ' WHERE id_entry = ' . $id_entry);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_members SET nbprop = nbprop + 1, nbpropev = nbpropev + 1  WHERE id_member = ' . $id_user);
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'proposition: entry modification');
            return array(FALSE, '_dberror', 0);
        }
        $msgcode = 'newprop';
        if ($comment != '') {
            list($ok, $info) = $dbaComments->insertComment($prj, $id_user, $_COOKIE['login'], $id_prop, $comment);
            if (!$ok) { $msgcode = $info; }
        }
        return array(TRUE, $msgcode, $id_prop);
    }
    
    public function createPropDeleteEntry ($prj, $id_user, $id_entry, $isEntryRejected=FALSE, $dbaComments=NULL) {
        if (!is_numeric($id_entry)) {
            return array(FALSE, '_data', 0);
        }
        list($ok, $result) = $this->getEntry ($prj, $id_entry);
        if (!$ok) {
            return array(FALSE, $result, 0);
        }
        $data = $result[0];
        if ($data['id_prop']) {
            return array(TRUE, 'ok', $data['id_prop']);
        }
        // fields and values
        addSlashesOnArray($data);
        $dbEntryFields = 'lemma, flags, po, "is", ds, ts, ip, dp, tp, sp, pa, st, al, ph, lex, sem, ety, dic,';
        $dbEntryValues = '';
        $fields = array('lemma', 'flags', 'po', 'is', 'ds', 'ts', 'ip', 'dp', 'tp', 'sp', 'pa', 'st', 'al', 'ph', 'lex', 'sem', 'ety', 'dic');
        foreach ($fields as $field) {
            $dbEntryValues .= "'" . $data[$field] . "', ";
        }
        // create prop
        $this->db->connx->beginTransaction();
        try {
            $oQ = $this->db->connx->query("SELECT nextval('dicl_{$prj}_prop_id_prop_seq') as key");
            $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
            $id_prop = $result[0]['key'];
            $qInsert1 = 'INSERT INTO dicl_'.$prj.'_prop (id_prop, id_entry, id_user, ' . $dbEntryFields . ' action)'
                      . ' VALUES (' . $id_prop . ', '. $id_entry . ', ' . $id_user . ', ' . $dbEntryValues . " '-')";
            $this->db->connx->exec($qInsert1);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_dic SET id_prop = ' . $id_prop . ' WHERE id_entry = ' . $id_entry);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_members SET nbprop = nbprop + 1, nbpropev = nbpropev + 1  WHERE id_member = ' . $id_user);
            $this->db->connx->commit();
            if ($isEntryRejected) {
                global $ui;
                $autocomment = $ui['sysComments']['REJECTEDENTRY'] . ' <img src="img/chk_tag_0.png" alt="R" />';
                list($ok, $info) = $dbaComments->insertComment($prj, $id_user, $_COOKIE['login'], $id_prop, $autocomment, TRUE);
                if (!$ok) { 
                    return array(FALSE, $info, $id_prop);
                }
            }
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'proposition: delete entry');
            return array(FALSE, '_dberror', 0);
        }
        return array(TRUE, 'newprop', $id_prop);
    }
    
    public function setCheckTag ($prj, $id_entry, $nTag, $id_user) {
        if (!is_numeric($id_entry) or $nTag < 0 or $nTag > 3) {
            return array(FALSE, '_data');
        }
        list($ok, $result) = $this->getEntry ($prj, $id_entry);
        if (!$ok) {
            return array(FALSE, $result);
        }
        // log label
        global $prjDic;
        $data = $result[0];
        addSlashesOnArray($data);
        $logMsg = $this->getLogMsgIdEntry($data);
        $logMsg .= ' | <img src="img/chk_tag_' . $data['chk'] . '.png" alt="' . $data['chk'] . '" /> → <img src="img/chk_tag_' . $nTag . '.png" alt="' . $nTag . '" />';
        $now = time();
        // SQL
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_dic SET chk = ' . "'".$nTag."'" . '  WHERE id_entry = ' . $id_entry);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_members SET nbdiceval = nbdiceval + 1  WHERE id_member = ' . $id_user);
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES ' . "($id_user, $id_entry, 'E', '>', '$logMsg', $now)");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'set check tag');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
        
    }
    
    public function closeGrammTagEntries ($prj) {
        try {
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_dic SET closed = TRUE WHERE closed = FALSE AND po != ' . "''");
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'close tagged entries');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }

    
    /* PRIVATE */
    
    private function getEntry ($prj, $id_entry) {
        try {
            $oQ = $this->db->connx->query('SELECT * FROM dicl_'.$prj.'_dic WHERE id_entry = ' . $id_entry);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'get entry');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        if (count($result) > 0) {
            return array(TRUE, $result);
        }
        return array(FALSE, '_noentry');
    }
    
    private function doesEntryExistInDictionary ($prj, $lemma, $flags) {
        try {
            $oQ = $this->db->connx->query('SELECT id_entry FROM dicl_'.$prj.'_dic WHERE lemma = ' . "'$lemma' AND flags = '$flags'");
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'does this entry exit?');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        if (count($result) > 0) {
            return array(TRUE, $result[0]['id_entry']);
        }
        return array(FALSE, '_noentry');
    }
    
    private function remixLogMsg (&$msg) {
        $msg = strip_tags($msg, '<b><i><u>');
        $msg = preg_replace('`[a-zA-Z]+:\/\/([a-z][a-z0-9_\..-]*[a-z]{2,6})[^()\s]*`i', ' <a href="$0">$1…</a> ', $msg);
        $msg = str_replace('&', '&amp;', $msg);
    }
    
    private function getLogMsgIdEntry (&$data) {
        // beware that $data must be secure (with addSlashesOnArray)
        global $prjDic;
        $logMsg = '<b>' . $data['lemma'] . '</b>';
        if ($data['flags'] != '') { $logMsg .= '<dfn>/</dfn><samp>' . $data['flags'] . '</samp>'; } 
        $fields = array('po', 'is', 'ds', 'ts', 'ip', 'dp', 'tp', 'sp', 'pa', 'st', 'al', 'ph');
        foreach ($fields as $field) {
            if ($data[$field] != '') $logMsg .= ' ' . $data[$field];
        }
        if ($data['lex'] != '') $logMsg .= ' • ' . $data['lex'];
        if ($data['sem'] != '') $logMsg .= ' • ' . $data['sem'];
        if ($data['ety'] != '') $logMsg .= ' • ' . $data['ety'];
        if ($data['dic'] != '*') $logMsg .= ' • ' . $prjDic[$data['dic']];
        return $logMsg;
    }
}

?>
