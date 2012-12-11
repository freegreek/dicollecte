<?php

class dbaccessPropositions {
    /*
        This object is an access to the db for the suggestions list.
        Modified tables are: prj_prop, prj_comments, prj_propsub, prj_dic, users
    */

    public $db;

    /* PUBLIC */
    function __construct ($db) {
        $this->db = $db;
    }

    public function selectTable ($prj, $tab, $orderby, $id_user, $offset, $nbEntriesByPage) {
        switch ($orderby) {
            case 'L': $dborderby = 'lemma'; break;
            case 'D': $dborderby = 'date DESC'; break;
            case 'V': $dborderby = 'value DESC, date'; break;
            default: $dborderby = 'prio DESC, date';
        }
        $qFilter = 'WHERE tab = ' . "'".$tab."'";
        if ($id_user) { $qFilter .= ' AND id_user = ' . $id_user; }
        $qSelect = 'SELECT * FROM dicl_'.$prj.'_prop ' . $qFilter . ' ORDER BY ' . $dborderby . ' OFFSET ' . $offset . ' LIMIT ' . $nbEntriesByPage;
        try {
            $oQ = $this->db->connx->query($qSelect);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'select table');
            return array(FALSE, '_dberror', 0);
        }        
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        $nbOccur = count($result);
        if ($nbOccur > 0) {
            $qSelect = 'SELECT COUNT(id_prop) AS nbentries FROM dicl_'.$prj.'_prop ' . $qFilter;
            try {
                $oQ2 = $this->db->connx->query($qSelect);
                $result2 = $oQ2->fetchAll(PDO::FETCH_ASSOC);
                $nbOccur = $result2[0]['nbentries'];
            }
            catch (PDOException $e) {
                $this->db->dbErrorReport($prj, $e, 'count propositions');
            }
        }
        return array(TRUE, $result, $nbOccur);
    }

    public function searchAll ($prj, $tagSearch, $entry, $id_user, $offset, $nbEntriesByPage) {
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
        $fields = array ('flags', 'po', 'is', 'ds', 'ts', 'ip', 'dp', 'tp', 'sp', 'pa', 'st', 'al', 'ph', 'lex', 'sem', 'ety');
        foreach ($fields as $field) {
            if (isset($entry[$field]) and $entry[$field] != '') {
                $qOptions[] = ($entry[$field] == '//') ? '"' . $field . '" = ' .  "''" : '"' . $field . '" LIKE ' .  "'%" . $entry[$field] . "%'";
            }
        }
        if (isset($entry['dic']) and $entry['dic'] != '') { $qOptions[] = 'dic = \'' . $entry['dic'] . "'"; }
        $qFilter = $this->db->createSelectionSubQuery($qOptions);
        // search
        $qSelect = 'SELECT * FROM dicl_'.$prj.'_prop' . $qFilter . ' ORDER BY lemma  OFFSET ' . $offset . ' LIMIT ' . $nbEntriesByPage;
        try {
            $oQ = $this->db->connx->query($qSelect);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'search in the propositions list');
            return array(FALSE, '_dberror', 0);
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        $nbOccur = count($result);
        if ($nbOccur > 0) {
            try {
                $oQ2 = $this->db->connx->query('SELECT COUNT(id_prop) AS nbentries FROM dicl_'.$prj.'_prop' . $qFilter);
                $result2 = $oQ2->fetchAll(PDO::FETCH_ASSOC);
                $nbOccur = $result2[0]['nbentries'];
            }
            catch (PDOException $e) {
                $this->db->dbErrorReport($prj, $e, 'count propositions');
            }
        }
        return array(TRUE, $result, $nbOccur);
    }

    public function createPropNewEntry ($prj, $dbaComments, $id_user, $entry, $comment) {
        list ($ok, $msgcode) = $this->doesEntryExistInDictionary ($prj, $entry->lemma, $entry->flags);
        if (!$ok) $msgcode = 'newprop';
        // fields and values
        $dbEntryFields = 'lemma, flags, po, "is", ds, ts, ip, dp, tp, sp, pa, st, al, ph, lex, sem, ety, dic,';
        $dbEntryValues = '';
        $fields = array('lemma', 'flags', 'po', 'is', 'ds', 'ts', 'ip', 'dp', 'tp', 'sp', 'pa', 'st', 'al', 'ph', 'lex', 'sem', 'ety', 'dic');
        foreach ($fields as $field) {
            $dbEntryValues .= "'" . $entry->$field . "', ";
        }
        // create prop
        $this->db->connx->beginTransaction();
        try {
            // new entry
            $oQ = $this->db->connx->query("SELECT nextval('dicl_{$prj}_prop_id_prop_seq') as key");
            $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
            $id_prop = $result[0]['key'];
            $qInsert = 'INSERT INTO dicl_'.$prj.'_prop (id_prop, '.$dbEntryFields.' action, id_user) VALUES ('.$id_prop.', '.$dbEntryValues."'+', ".$id_user.')';
            $this->db->connx->exec($qInsert);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_members SET nbprop = nbprop + 1, nbpropev = nbpropev + 1 WHERE id_member = ' . $id_user);
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'proposition: new entry');
            return array(FALSE, '_dberror', 0);
        }
        if ($comment != '') {
            list($ok, $msgcode) = $dbaComments->insertComment($prj, $id_user, $_COOKIE['login'], $id_prop, $comment);
            if (!$ok) {
                return array(TRUE, $msgcode, $id_prop);
            }
        }
        return array(TRUE, $msgcode, $id_prop);
    }

    public function selectProposition ($prj, $id_prop) {
        $qSelect = 'SELECT p.*, u.login FROM dicl_'.$prj.'_prop p  JOIN dicl_users u  ON p.id_user = u.id_user  WHERE id_prop = ' . $id_prop;
        try {
            $oQ = $this->db->connx->query($qSelect);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'select proposition');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, $oQ->fetchAll(PDO::FETCH_ASSOC));
    }

    public function selectNextProposition ($prj, $datetime, $isNext, $value = FALSE, $tab = 'E') {
        if ($isNext) { $op = '>'; $order = 'ASC'; } else { $op = '<'; $order = 'DESC'; }
        $selVal = ($value) ? ' AND value = ' . "'" . $value . "'" : '';
        $qSelect = 'SELECT p.*, u.login FROM dicl_'.$prj.'_prop p  JOIN dicl_users u  ON p.id_user = u.id_user'
                 . ' WHERE tab = ' . "'" . $tab . "'" . $selVal . ' AND date ' . $op . $datetime . ' ORDER BY date ' . $order . ' LIMIT 1';
        try {
            $oQ = $this->db->connx->query($qSelect);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'select next proposition');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, $oQ->fetchAll(PDO::FETCH_ASSOC));
    }

    public function updateProposition ($prj, $dbaComments, $id_user, $id_prop, $entry, $doUnvalidate) {
        if ($entry->lemma == '') {
            return array(FALSE, '_emptylemma');
        }
        list($ok, $result) = $this->getProposition ($prj, $id_prop);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $data = $result[0];
        // update
        global $activeFields;
        $qUpdate = 'UPDATE dicl_'.$prj.'_prop  SET ';
        $fields = array('lemma', 'flags', 'po', 'is', 'ds', 'ts', 'ip', 'dp', 'tp', 'sp', 'pa', 'st', 'al', 'ph', 'lex', 'sem', 'ety', 'dic');
        foreach ($fields as $field) {
            if ($activeFields[$field]) $qUpdate .= '"' . $field . '" = \'' . $entry->$field . "',"; 
        }
        $qUpdate = rtrim($qUpdate, ',');
        $qUpdate .= ' WHERE id_prop = ' . $id_prop;
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec($qUpdate);
            if ($data['value'] == 'V' and $doUnvalidate) {
                $this->db->connx->exec('UPDATE dicl_'.$prj.'_prop SET value = ' . "'?'" . ' WHERE id_prop = ' . $id_prop);
                $this->db->connx->exec('UPDATE dicl_'.$prj.'_members SET nbpropval = nbpropval - 1 WHERE id_member = ' . $data['id_user']);
            }
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'proposition update');
            return array(FALSE, '_dberror');
        }
        // autocomment
        addSlashesOnArray($data);
        global $prjDic;
        $autocomment = '';
        $fields = array('lemma', 'flags', 'po', 'is', 'ds', 'ts', 'ip', 'dp', 'tp', 'sp', 'pa', 'st', 'al', 'ph', 'lex', 'sem', 'ety');
        foreach ($fields as $field) {
            if ($activeFields[$field] and $data[$field] != $entry->$field) $autocomment .= '<samp>' . $data[$field] . '</samp> → <samp class="new">' . $entry->$field . '</samp><br />';
        }
        if ($activeFields['dic'] and $data['dic'] != $entry->dic) $autocomment .= '<samp>' . $prjDic[$data['dic']] . '</samp> → <samp class="new">' . $prjDic[$entry->dic] . '</samp><br />';
        if ($autocomment == '') $autocomment = 'Ø';
        list($ok, $msgcode) = $dbaComments->insertComment($prj, $id_user, $_COOKIE['login'], $id_prop, $autocomment, TRUE);
        if (!$ok) {
            return array(FALSE, $msgcode);
        }
        return array(TRUE, 'propupdated');
    }

    public function setPropositionPriority ($prj, $id_prop, $nbpriority) {
        if (!is_numeric($id_prop) and !is_numeric($nbpriority)) {
            return array(FALSE, '_data');
        }
        list($ok, $result) = $this->getProposition ($prj, $id_prop);
        if (!$ok) {
            return array(FALSE, $result);
        }
        if ($result[0]['tab'] !== 'E') {
            return array(FALSE, '_noaccess');
        }
        try {
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_prop SET prio = ' . $nbpriority . ' WHERE id_prop = ' . $id_prop);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'set proposition priority');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }

    public function setPropositionValue ($prj, $dbaComments, $id_user, $id_prop, $value) {
        list($ok, $result) = $this->getProposition ($prj, $id_prop);
        if (!$ok) {
            return array(FALSE, $result);
        }
        if ($result[0]['tab'] !== 'E') {
            return array(FALSE, '_noaccess');
        }
        $curValue = $result[0]['value'];
        if ($curValue == $value) {
            return array(TRUE, 'ok');
        }
        $bMore = ($curValue != 'V' and $value == 'V') ? TRUE : FALSE;
        $bLess = ($curValue == 'V' and $value != 'V') ? TRUE : FALSE;
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_prop SET value = ' . "'$value'" . ' WHERE id_prop = ' . $id_prop);
            if ($bMore) { $this->db->connx->exec('UPDATE dicl_'.$prj.'_members SET nbpropval = nbpropval + 1 WHERE id_member = ' . $result[0]['id_user']); }
            if ($bLess) { $this->db->connx->exec('UPDATE dicl_'.$prj.'_members SET nbpropval = nbpropval - 1 WHERE id_member = ' . $result[0]['id_user']); }
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'set proposition value');
            return array(FALSE, '_dberror');
        }
        // autocomment
        global $ui;
        global $uiImgValueSmall;
        switch ($value) {
            case 'V': $autocomment = $ui['sysComments']['VALID'] . $uiImgValueSmall['V']; break;
            case 'R': $autocomment = $ui['sysComments']['REJECT'] . $uiImgValueSmall['R']; break;
            case '!': $autocomment = $ui['sysComments']['ALERT'] . $uiImgValueSmall['!']; break;
            case 'S': $autocomment = $ui['sysComments']['SUSPEND'] . $uiImgValueSmall['S']; break;
            default: $autocomment = '<samp>error!</samp>';
        }
        list($ok, $msgcode) = $dbaComments->insertComment($prj, $id_user, $_COOKIE['login'], $id_prop, $autocomment, TRUE);
        if (!$ok) {
            return array(FALSE, $msgcode);
        }
        return array(TRUE, 'ok');
    }
    
    public function changeAction ($prj, $dbaComments, $id_user, $id_prop) {
        list($ok, $result) = $this->getProposition ($prj, $id_prop);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $data = $result[0];
        if (!($data['action'] == '>' or $data['action'] == '-')) {
            return array(FALSE, '_noaccess');
        }
        $action = ($data['action'] == '>') ? '-' : '>';
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_prop SET action = ' . "'$action'" . ', value = ' . "'?'" . ' WHERE id_prop = ' . $id_prop);
            if ($data['value'] == 'V') { $this->db->connx->exec('UPDATE dicl_'.$prj.'_members SET nbpropval = nbpropval - 1 WHERE id_member = ' . $data['id_user']); }
            if ($action == '-') {
                $qUpdate = 'UPDATE dicl_'.$prj.'_prop p SET (lemma, flags, po, "is", ds, ts, ip, dp, tp, sp, pa, st, al, ph, lex, sem, ety, dic)'
                         . ' = (d.lemma, d.flags, d.po, d.is, d.ds, d.ts, d.ip, d.dp, d.tp, d.sp, d.pa, d.st, d.al, d.ph, d.lex, d.sem, d.ety, d.dic)'
                         . ' FROM dicl_'.$prj.'_dic d  WHERE d.id_entry = p.id_entry AND p.id_prop = ' . $id_prop;
                $this->db->connx->exec($qUpdate);
            }
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'change proposition action');
            return array(FALSE, '_dberror');
        }
        // autocomment
        global $ui;
        global $uiImgActionSmall;
        switch ($action) {
            case '>': $autocomment = $ui['sysComments']['MODIFY'] . $uiImgActionSmall['>']; break;
            case '-': $autocomment = $ui['sysComments']['ERASE'] . $uiImgActionSmall['-']; break;
            default: $autocomment = '<samp>error!</samp>';
        }
        list($ok, $msgcode) = $dbaComments->insertComment($prj, $id_user, $_COOKIE['login'], $id_prop, $autocomment, TRUE);
        if (!$ok) {
            return array(FALSE, $msgcode);
        }
        return array(TRUE, 'ok');
    }
    
    public function moveProposition ($prj, $dbaComments, $id_user, $id_prop, $destination) {
        list($ok, $result) = $this->getProposition ($prj, $id_prop);
        if (!$ok) {
            return array(FALSE, $result);
        }
        global $ui;
        global $uiImgValueSmall;
        $data = $result[0];
        switch ($destination) {
            case 'reject':
                $tab = 'R'; $value = ($data['value'] == 'I') ? 'I' : 'F';
                $qOption = ', id_entry = NULL';
                $msgcode = 'propforbid';
                $autocomment = $ui['sysComments']['FORBIDDEN'] . $uiImgValueSmall['F'];
                $bEvMore = FALSE;
                break;
            case 'trash':
                $tab = 'T'; $value = ($data['value'] == 'I') ? 'I' : 'T';
                $qOption = ', id_entry = NULL';
                $msgcode = 'proptrash';
                $autocomment = $ui['sysComments']['TRASH'] . $uiImgValueSmall['T'];
                $bEvMore = FALSE;
                break;
            case 'eval':
                $tab = 'E'; $value = '!';
                $qOption = '';
                $msgcode = 'propreeval';
                $autocomment = $ui['sysComments']['REEVAL'] . $uiImgValueSmall['?'];
                $bEvMore = TRUE; 
                break;
            default:
                return array(FALSE, '_error');
        }
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_prop SET tab = ' . "'$tab', value = '$value'" . $qOption . ' WHERE id_prop = ' . $id_prop);
            if ($data['id_entry']) { $this->db->connx->exec('UPDATE dicl_'.$prj.'_dic  SET id_prop = NULL  WHERE id_entry = ' . $data['id_entry']); }
            if ($data['value'] == 'V') { $this->db->connx->exec('UPDATE dicl_'.$prj.'_members SET nbpropval = nbpropval - 1 WHERE id_member = ' . $data['id_user']); }
            if ($data['tab'] == 'E') { $this->db->connx->exec('UPDATE dicl_'.$prj.'_members SET nbpropev = nbpropev - 1 WHERE id_member = ' . $data['id_user']); }
            if ($bEvMore) { $this->db->connx->exec('UPDATE dicl_'.$prj.'_members SET nbpropev = nbpropev + 1 WHERE id_member = ' . $data['id_user']); }
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'move proposition');
            return array(FALSE, '_dberror');
        }
        list($ok, $info) = $dbaComments->insertComment($prj, $id_user, $_COOKIE['login'], $id_prop, $autocomment, TRUE);
        if (!$ok) {
            return array(FALSE, $info);
        }
        return array(TRUE, $msgcode);
    }

    public function eraseProposition ($prj, $id_prop) {
        list($ok, $result) = $this->getProposition ($prj, $id_prop);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $timestamp = $result[0]['date'];
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_comments WHERE id_prop = ' . $id_prop);
            $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_prop WHERE id_prop = ' . $id_prop);
            $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_propsub WHERE id_prop = ' . $id_prop);
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'erase proposition');
            return array(FALSE, '_dberror', 0);
        }
        return array(TRUE, 'properased', $timestamp);
    }

    public function applyProposition ($prj, $dbaComments, $id_user, $id_prop, $doValidate) {
        list($ok, $result) = $this->getProposition($prj, $id_prop);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $data = $result[0];
        if ($data['tab'] != 'E') {
            return array(FALSE, '_noevalprop');
        }
        addSlashesOnArray($data);
        global $ui;
        global $uiImgValueSmall;
        if ($data['value'] == 'R') {
            return array(FALSE, '_no');
        }
        $chk = ($doValidate) ? '3' : '1';
        $eval = ($doValidate) ? 'I' : 'i';
        $now = time();
        $qLessNbValid = ($data['value'] == 'V') ? ', nbpropval = nbpropval - 1 ' : '';
        switch ($data['action']) {
            case '+':
                // new entry
                $dbEntryFields = 'lemma, flags, po, "is", ds, ts, ip, dp, tp, sp, pa, st, al, ph, lex, sem, ety, dic,';
                $dbEntryValues = '';
                $fields = array('lemma', 'flags', 'po', 'is', 'ds', 'ts', 'ip', 'dp', 'tp', 'sp', 'pa', 'st', 'al', 'ph', 'lex', 'sem', 'ety', 'dic');
                foreach ($fields as $field) {
                    $dbEntryValues .= "'" . $data[$field] . "', ";
                }
                // log msg
                $logMsg = $this->getLogMsgIdEntry($data);
                // insertion
                $this->db->connx->beginTransaction();
                try {
                    $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_dic (' . $dbEntryFields . ' closed, chk)  VALUES (' . $dbEntryValues . " TRUE, '$chk')");
                    $this->db->connx->exec('UPDATE dicl_'.$prj.'_prop SET tab = ' . "'T', value = '" . $eval . "' WHERE id_prop = " . $id_prop);
                    $this->db->connx->exec('UPDATE dicl_'.$prj.'_members SET nbpropok = nbpropok + 1, nbpropev = nbpropev - 1' . $qLessNbValid . '  WHERE id_member = ' . $data['id_user']);
                    $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES ' . "($id_user, $id_prop, 'P', '+', '$logMsg', $now)");
                    $newId = $this->db->connx->lastInsertId('dicl_'.$prj.'_dic_id_entry_seq');
                    $this->db->connx->commit();
                }
                catch (PDOException $e) {
                    $this->db->connx->rollBack();
                    $this->db->dbErrorReport($prj, $e, 'apply proposition: +');
                    return array(FALSE, '_dberror');
                }
                $msgcode = 'newentry';
                break;
            case '>':
                // entry update
                if ($data['id_entry'] == NULL) {
                    return array(FALSE, '_noreference');
                }
                list($ok, $result) = $this->getEntry ($prj, $data['id_entry']);
                if (!$ok) {
                    return array(FALSE, $result);
                }
                global $prjDic;
                $datae = $result[0];
                addSlashesOnArray($datae);
                // log label
                $logMsg = $this->getLogMsgIdEntry($datae);
                $fields = array('lemma', 'flags', 'po', 'is', 'ds', 'ts', 'ip', 'dp', 'tp', 'sp', 'pa', 'st', 'al', 'ph', 'lex', 'sem', 'ety');
                foreach ($fields as $field) {
                    if ($datae[$field] != $data[$field]) $logMsg .= ' | <samp>' . $datae[$field] . '</samp> → <samp class="new">' . $data[$field] . '</samp>';
                }
                if ($datae['dic'] != $data['dic']) $logMsg .= ' | <samp>' . $prjDic[$datae['dic']] . '</samp> → <samp class="new">' . $prjDic[$data['dic']] . '</samp>';
                // update query
                $qUpdate = 'UPDATE dicl_'.$prj.'_dic  SET closed = TRUE, id_prop = NULL, chk = ' . $chk;
                $fields = array('lemma', 'flags', 'po', 'is', 'ds', 'ts', 'ip', 'dp', 'tp', 'sp', 'pa', 'st', 'al', 'ph', 'lex', 'sem', 'ety', 'dic');
                foreach ($fields as $field) {
                    $qUpdate .= ', "' . $field . '" = \'' . $data[$field] . "'"; 
                }
                $qUpdate .= ' WHERE id_entry = ' . $data['id_entry'];
                // update
                $this->db->connx->beginTransaction();
                try {
                    $this->db->connx->exec($qUpdate);
                    $this->db->connx->exec('UPDATE dicl_'.$prj.'_prop SET tab = ' . "'T', value = '" . $eval . "', id_entry = NULL WHERE id_prop = " . $id_prop);
                    $this->db->connx->exec('UPDATE dicl_'.$prj.'_members SET nbpropok = nbpropok + 1, nbpropev = nbpropev - 1' . $qLessNbValid . '  WHERE id_member = ' . $data['id_user']);
                    $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES ' . "($id_user, $id_prop, 'P', '>', '$logMsg', $now)");
                    $this->db->connx->commit();
                }
                catch (PDOException $e) {
                    $this->db->connx->rollBack();
                    $this->db->dbErrorReport($prj, $e, 'apply proposition: >');
                    return array(FALSE, '_dberror');
                }
                $newId = $data['id_entry'];
                $msgcode = 'entryupdated';
                break;
            case '-':
                // entry deletion
                if ($data['id_entry'] == NULL) {
                    return array(FALSE, '_noreference');
                }
                // log label
                list($ok, $result) = $this->getEntry ($prj, $data['id_entry']);
                if (!$ok) {
                    return array(FALSE, $result);
                }
                $datae = $result[0];
                addSlashesOnArray($datae);
                $logMsg = $this->getLogMsgIdEntry($datae);
                // delete
                $this->db->connx->beginTransaction();
                try {
                    $this->db->connx->exec('UPDATE dicl_'.$prj.'_prop  SET id_entry = NULL, tab = ' . "'T', value = 'I' WHERE id_prop = " . $id_prop);
                    $this->db->connx->exec('UPDATE dicl_'.$prj.'_prop  SET id_entry = NULL, tab = ' . "'T', value = 'T' WHERE tab = 'E' AND action = '>' AND id_entry = "  . $data['id_entry']);
                    $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_dic WHERE id_entry = ' . $data['id_entry']);
                    $this->db->connx->exec('UPDATE dicl_'.$prj.'_members SET nbpropok = nbpropok + 1, nbpropev = nbpropev - 1' . $qLessNbValid . '  WHERE id_member = ' . $data['id_user']);
                    $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES ' . "($id_user, $id_prop, 'P', '-', '$logMsg', $now)");
                    $this->db->connx->commit();
                }
                catch (PDOException $e) {
                    $this->db->connx->rollBack();
                    $this->db->dbErrorReport($prj, $e, 'apply proposition: -');
                    return array(FALSE, '_dberror');
                }
                $newId = FALSE;
                $msgcode = 'entryerased';
                break;
            default:
                return array(FALSE, 'action?');
        }
        $autocomment = ($doValidate) ? $ui['sysComments']['VALIDINTEGR'] . $uiImgValueSmall['V'] . $uiImgValueSmall['I']
                                     : $autocomment = $ui['sysComments']['DUBIOUSINTEGR']. $uiImgValueSmall['!']  . $uiImgValueSmall['i'];;
        if ($newId) { $autocomment .= ' → <a href="entry.php?prj='.$prj.'&amp;id='.$newId.'">'.$newId.'</a>'; }
        list($ok, $info) = $dbaComments->insertComment($prj, $id_user, $_COOKIE['login'], $id_prop, $autocomment, TRUE);
        if (!$ok) {
            return array(TRUE, $info);
        }
        return array(TRUE, $msgcode);
    }

    public function integrateAllValidPropositions ($prj, $id_admin, $logMsg, $id_user=NULL) {
        $now = time();
        $logMsg = addslashes($logMsg);
        // insertion
        $qInsert = 'INSERT INTO dicl_'.$prj.'_dic (lemma, flags, po, "is", ds, ts, ip, dp, tp, sp, pa, st, al, ph, lex, sem, ety, dic)'
                 . ' SELECT lemma, flags, po, "is", ds, ts, ip, dp, tp, sp, pa, st, al, ph, lex, sem, ety, dic'
                 . ' FROM dicl_'.$prj.'_prop  WHERE tab = ' . "'E' AND action = '+' AND value = 'V'";
        if ($id_user) { $qInsert .= ' AND id_user = ' . $id_user; }
        // deletion
        $qWhere = 'WHERE tab = ' . "'E' AND action = '-' AND value = 'V'";
        if ($id_user) { $qWhere .= ' AND id_user = ' . $id_user; }
        $qDelete = 'CREATE TEMP TABLE entries_to_del ON COMMIT DROP AS (SELECT id_entry FROM dicl_'.$prj.'_prop ' . $qWhere . '); '
                 . 'UPDATE dicl_'.$prj.'_prop  SET id_entry = NULL ' . $qWhere . '; '
                 . 'UPDATE dicl_'.$prj.'_prop  SET id_entry = NULL, tab = ' . "'T', value = 'T' WHERE tab = 'E' AND action = '>' AND id_entry  IN (SELECT * FROM entries_to_del); "
                 . 'DELETE FROM dicl_'.$prj.'_dic  WHERE id_entry  IN (SELECT * FROM entries_to_del)';
        // update
        $qUpdate = 'UPDATE dicl_'.$prj.'_dic d SET (lemma, flags, po, "is", ds, ts, ip, dp, tp, sp, pa, st, al, ph, lex, sem, ety, dic, id_prop)'
                 . ' = (p.lemma, p.flags, p.po, p.is, p.ds, p.ts, p.ip, p.dp, p.tp, p.sp, p.pa, p.st, p.al, p.ph, p.lex, p.sem, p.ety, p.dic, NULL)'
                 . ' FROM dicl_'.$prj.'_prop p  WHERE d.id_entry = p.id_entry AND tab = ' . "'E'  AND action = '>' AND value = 'V'";
        if ($id_user) { $qUpdate .= ' AND id_user = ' . $id_user; }
        // users rewards :)
        if ($id_user) { 
            $qUserUpdate = 'UPDATE dicl_'.$prj.'_members m'
                         . ' SET nbpropok = nbpropok + (SELECT count(id_user) FROM dicl_'.$prj.'_prop p WHERE p.tab = ' . "'E' AND p.value = 'V'" . ' AND p.id_user = '.$id_user.'),'
                         . '     nbpropval = 0,'
                         . '     nbpropev = nbpropev - (SELECT count(id_user) FROM dicl_'.$prj.'_prop p WHERE p.tab = ' . "'E' AND p.value = 'V'" . ' AND p.id_user = '.$id_user.')'  
                         . ' WHERE m.id_member = ' . $id_user;
        }
        else {
            $qUserUpdate = 'CREATE TEMP TABLE tmpusers ON COMMIT DROP AS (SELECT id_user, count(id_user) AS nbactions FROM dicl_'.$prj.'_prop WHERE tab = ' . "'E' AND value = 'V' GROUP BY id_user); "
                         . 'UPDATE dicl_'.$prj.'_members m SET nbpropok = nbpropok + u.nbactions, nbpropval = 0, nbpropev = nbpropev - u.nbactions  FROM tmpusers u  WHERE m.id_member = u.id_user';
        }
        // move proposals under consideration to the basket
        $qUpdateProp = 'UPDATE dicl_'.$prj.'_prop SET id_entry = NULL, tab = ' . "'T', value = 'I' WHERE tab = 'E' AND value = 'V'";
        if ($id_user) { $qUpdateProp .= ' AND id_user = ' . $id_user; }
        // log
        if ($id_user) { $logMsg .= ' [<a href="member.php?prj='.$prj.'&amp;tab=T&amp;id_user='.$id_user.'">'.$id_user.'</a>]'; }
        $qInsertLog = 'INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES (' . $id_admin . ', 0, ' . "'S', 's', '$logMsg', $now)";
        // action on db
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec($qInsert);
            $this->db->connx->exec($qDelete);
            $this->db->connx->exec($qUpdate);
            $this->db->connx->exec($qUserUpdate);
            $this->db->connx->exec($qUpdateProp);
            $this->db->connx->exec($qInsertLog);
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'integration of all validated propositions');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }

    public function emptyBasket ($prj, $selection, $id_user, $logMsg) {
        $qOption = (is_int($selection)) ? ' AND nbcomments <= ' . $selection : ' AND nbcomments <= 5';
        $now = time();
        $logMsg = addslashes($logMsg);
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_comments WHERE id_prop IN (SELECT id_prop FROM dicl_'.$prj.'_prop WHERE tab = ' . "'T'" . $qOption . ')');
            $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_propsub WHERE id_prop IN (SELECT id_prop FROM dicl_'.$prj.'_prop WHERE tab = ' . "'T'" . $qOption . ')');
            $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_prop WHERE tab = ' . "'T'" . $qOption);
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES (' . $id_user . ", 0, 'S', 's', '$logMsg', $now)");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'empty the basket');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }

    // (un)subscribe
    public function subscribe ($prj, $id_prop, $id_user) {
        if (!is_numeric($id_prop) and !is_numeric($id_user)) {
            return array(FALSE, '_data');
        }
        list($ok, $res) = $this->checkSubscription ($prj, $id_prop, $id_user);
        if (!$ok) {
            return array(FALSE, $res);
        }
        if (!$res) {
            // subscribe
            $this->db->connx->beginTransaction();
            try {
                // new entry
                $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_propsub (id_prop, id_user) VALUES ('.$id_prop.', '.$id_user.')');
                $this->db->connx->exec('UPDATE dicl_'.$prj.'_prop SET nbnotif = nbnotif + 1  WHERE id_prop = ' . $id_prop);
                $this->db->connx->commit();
            }
            catch (PDOException $e) {
                $this->db->connx->rollBack();
                $this->db->dbErrorReport($prj, $e, 'subscribing to suggestion');
                return array(FALSE, '_dberror');
            }
        }
        return array(TRUE, 'ok');
    }

    public function unsubscribe ($prj, $id_prop, $id_user) {
        if (!is_numeric($id_prop) and !is_numeric($id_user)) {
            return array(FALSE, '_data');
        }
        list($ok, $res) = $this->checkSubscription ($prj, $id_prop, $id_user);
        if (!$ok) {
            return array(FALSE, $res);
        }
        if ($res) {
            // unsubscribe
            $this->db->connx->beginTransaction();
            try {
                // new entry
                $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_propsub  WHERE id_prop = ' . $id_prop . ' AND id_user = '. $id_user);
                $this->db->connx->exec('UPDATE dicl_'.$prj.'_prop SET nbnotif = nbnotif - 1  WHERE id_prop = ' . $id_prop);
                $this->db->connx->commit();
            }
            catch (PDOException $e) {
                $this->db->connx->rollBack();
                $this->db->dbErrorReport($prj, $e, 'unsubscribing to suggestion');
                return array(FALSE, '_dberror');
            }
        }
        return array(TRUE, 'ok');
    }

    public function checkSubscription ($prj, $id_prop, $id_user) {
        if (!is_numeric($id_prop) and !is_numeric($id_user)) {
            return array(FALSE, '_data');
        }
        try {
            $oQ = $this->db->connx->query('SELECT * FROM dicl_'.$prj.'_propsub WHERE id_prop = ' . $id_prop . ' AND id_user = '. $id_user);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'suggestions: check subscription');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        if (count($result) == 0) {
            return array(TRUE, FALSE);
        }
        return array(TRUE, TRUE);
    }


    /* PRIVATE */
    
    private function getProposition ($prj, $id_prop) {
        try {
            $oQ = $this->db->connx->query('SELECT * FROM dicl_'.$prj.'_prop WHERE id_prop = ' . $id_prop);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'get proposition');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        if (count($result) > 0) {
            return array(TRUE, $result);
        }
        return array(FALSE, '_noprop');
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
    
    private function getLogMsgIdEntry (&$data) {
        global $prjDic;
        $logMsg = '<b>' . $data['lemma'] . '</b>';
        if ($data['flags'] != '') $logMsg .= '<dfn>/</dfn><samp>' . $data['flags'] . '</samp>';
        $fields = array('po', 'is', 'ds', 'ts', 'ip', 'dp', 'tp', 'sp', 'pa', 'st', 'al', 'ph');
        foreach ($fields as $field) {
            if ($data[$field] != '') { $logMsg .= ' ' . $data[$field]; }
        }
        if ($data['lex'] != '') { $logMsg .= ' • ' . $data['lex']; }
        if ($data['sem'] != '') { $logMsg .= ' • ' . $data['sem']; }
        if ($data['ety'] != '') { $logMsg .= ' • ' . $data['ety']; }
        if ($data['dic'] != '*') { $logMsg .= ' • ' . $prjDic[$data['dic']]; }
        return $logMsg;
    }
    
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
}

?>
