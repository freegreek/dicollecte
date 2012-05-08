<?php

class dbaccessFlags {
    /*
        This object is an access to the db for flags.
        Modified tables are: prj_flags, prj_log
    */
    
    public $db;
    
    /* PUBLIC */
    function __construct ($db) {
        $this->db = $db;
    }

    // list all flags
    public function listAllFlags ($prj) {
        try {
            $oQ = $this->db->connx->query('SELECT flag FROM dicl_'.$prj.'_flags GROUP BY flag ORDER BY flag');
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'list all flags');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, $oQ->fetchAll(PDO::FETCH_ASSOC));
    }

    // select one flag
    public function selectFlag($prj, $flag) {
        $qSelect = 'SELECT * FROM dicl_'.$prj.'_flags WHERE flag = ' . "'$flag'" . ' ORDER BY id_aff';
        try {
            $oQ = $this->db->connx->query($qSelect);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'select flag ' . $flag);
            return array(FALSE, '_dberror');
        }
        return array(TRUE, $oQ->fetchAll(PDO::FETCH_ASSOC));
    }

    // new affixes (create a new flag or add new rules)
    public function newAffixes ($prj, $txtFlag, $id_user) {
        list($flag, $sqlValues, $nbLines) = $this->txtFlagToSQLValues($txtFlag);
        if ($flag === FALSE) {
            return array(FALSE, '_flagerror');
        }
        // log msg
        $now = time();
        $logMsg = '<a href="affixes.php?prj='.$prj.'&amp;flag='.$flag.'">'.$flag.'</a> • <span class="new"><b>['.$nbLines.']</b></span>';
        // db
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_flags (afftype, mix, flag, cut, add, flags, cond, po, "is", ds, ts, ip, dp, tp, sp, pa, dic, comment) VALUES ' . $sqlValues);
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES (' . $id_user . ", 0, 'F', '+', '$logMsg', $now)");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'new flag');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, $flag);
    }

    // edit a flag
    public function editFlag ($prj, $txtFlag, $id_user) {
        list($flag, $sqlValues, $nbRules) = $this->txtFlagToSQLValues($txtFlag);
        if ($flag === FALSE) {
            return array(FALSE, '_flagerror');
        }
        list($ok, $nbOldRules) = $this->countFlagRules($prj, addslashes($flag));
        if (!$ok) {
            return array(FALSE, $nbOldRules);
        }
        // log msg
        $now = time();
        $logMsg = '<a href="affixes.php?prj='.$prj.'&amp;flag='.$flag.'">'.$flag.'</a> • <span class="del"><b>['.$nbOldRules.']</b></span> → <span class="new"><b>['.$nbRules.']</b></span>';
        $flag = addslashes($flag);
        // db
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_flags WHERE flag = ' . "'".$flag."'");
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_flags (afftype, mix, flag, cut, add, flags, cond, po, "is", ds, ts, ip, dp, tp, sp, pa, dic, comment) VALUES ' . $sqlValues);
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES (' . $id_user . ", 0, 'F', '>', '$logMsg', $now)");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'edit flag');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, $flag);
    }

    // erase flag from the table prj_flags
    public function eraseFlag ($prj, $flag, $id_user) {
        list($ok, $nbOldRules) = $this->countFlagRules($prj, $flag);
        if (!$ok) {
            return array(FALSE, $nbOldRules);
        }
        // log msg
        $now = time();
        $logMsg = stripslashes($flag) . ' • <span class="del"><b>['.$nbOldRules.']</b></span>';
        // db
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_flags WHERE flag = ' . "'".$flag."'");
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES (' . $id_user . ", 0, 'F', '-', '$logMsg', $now)");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'erase flag');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'flagerased');
    }
    
    public function fieldToHunspell($fieldName, $fieldValue) {
        $sep = ' ' . $fieldName . ':';
        if (strpos($fieldValue, ' ') !== FALSE) {
            $fieldValue = str_replace(' ' , $sep, $fieldValue);
        }
        return $sep . $fieldValue;
    }
        
    /* PRIVATE */
    
    private function countFlagRules ($prj, $flag) {
        try {
            $oQ = $this->db->connx->query('SELECT count(id_aff) AS nbrules  FROM dicl_'.$prj.'_flags WHERE flag = ' . "'$flag'");
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'count flag rules' . $flag);
            return array(FALSE, '_dberror');
        }
        $res = $oQ->fetchAll(PDO::FETCH_ASSOC);
        return array(TRUE, $res[0]['nbrules']);
    }
    
    // transform a flag in SQLvalues
    private function txtFlagToSQLValues ($content) {
        $content = trim($content);
        $lines = explode("\n", $content);
        $nbLines = count($lines);
        $sqlValues = '';
        // first line
        $elems = preg_split('`( +|\t+)`', $lines[0]);
        if ($elems[0] != 'PFX' and $elems[0] != 'SFX') { return array(FALSE, ''); }
        if ($elems[2] != 'Y' and $elems[2] != 'N') { return array(FALSE, ''); }
        //if (!preg_match("`^[0-9]+$`", $elems[3])) { return array(FALSE, ''); }
        $linehead = ($elems[0] == 'PFX') ? "('P', " : "('S', ";
        $linehead .= ($elems[2] == 'Y') ? 'TRUE, ' : 'FALSE, ';
        $linehead .= "'".$elems[1]."', ";
        $flag = $elems[1];
        $nbNewLines = 0;
        // rules
        for ($i = 1; $i < $nbLines;  $i++) {
            $line = trim($lines[$i]);
            if ($line != '') {
                if (strpos($line, 'SFX') === 0 or strpos($line, 'PFX') === 0) {
                    // comment
                    $comment = '';
                    if (strpos($line, '#') !== FALSE) {
                        list($line, $comment) = explode('#', $line, 2);
                        $line = trim($line);
                        if (mb_strlen($comment) > DB_AFFCOMMENTLEN) { $comment = mb_strcut($comment, 0, DB_AFFCOMMENTLEN); } 
                    }
                    // rule
                    $elems = preg_split('`( +|\t+)`', $line);
                    $nbElems = count($elems);
                    if ($nbElems > 4) {
                        $cut = ($elems[2] != '0') ? $elems[2] : '';
                        $subelems = explode('/', $elems[3]);
                        $add = ($subelems[0] != '0') ? $subelems[0] : '';
                        $flags = (count($subelems) > 1) ? $subelems[1] : '';
                        $cond = $elems[4];
                        $data = array('po' => '', 'is' => '', 'ds' => '', 'ts' => '', 'ip' => '', 'dp' => '', 'tp' => '', 'sp' => '', 'pa' => '', 'di' => '*');
                        // other fields
                        for ($j = 5;  $j < $nbElems;  $j++) {
                            if ($elems[$j]{2} == ':') {
                                // named fields
                                list($field, $value) = preg_split('`:`', $elems[$j], 2);
                                if ($field != 'di') {
                                    $data[$field] .= ($data[$field] == '') ? $value : ' ' . $value;
                                }
                                else {
                                    $data['di'] = $value;
                                }
                            }
                        }
                        // create SQL values
                        $sqlValues .= $linehead . "'" . $cut . "', '" . $add . "', '" . $flags . "', '" . $cond . "', '"
                                    . $data['po'] . "', '" . $data['is'] . "', '" . $data['ds'] . "', '" . $data['ts'] . "', '"
                                    . $data['ip'] . "', '" . $data['dp'] . "', '" . $data['tp'] . "', '" . $data['sp'] . "', '"
                                    . $data['pa'] . "', '" . $data['di'] . "', '" . $comment . "'),";
                        $nbNewLines += 1;
                    }
                }
            }
        }
        if (!$nbNewLines) { return array(FALSE, '', 0); }
        return array($flag, trim($sqlValues, ','), $nbNewLines);
    }
}

?>
