<?php

class dbaccessIE {
    /*
        This object is an access to the db for the dictionary, the affixes and the thesaurus.
        Modified tables are: prj_dic, prj_thes, prj_flags, prj_synsets
    */
    
    private $db;

    /* PUBLIC */
    
    function __construct ($db) {
        $this->db = $db;
    }
    
    // this function is a non permanent tool, can be modified at any time, for any need
    public function sosSQL ($params) {
        $this->db->connx->beginTransaction();
        try {
            $res = $this->db->connx->exec("xxxxx");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport('fr', $e, 'sos SQL');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, $res);
    }
    
    
    /* Dictionary */
    
    public function importDictionary ($prj, $srcPathFile) {
        $hSrcFile = @fopen($srcPathFile, 'r');
        if (!$hSrcFile) {
            return array(FALSE, '_nofile');
        }
        $i = 0;
        $tmpPathFile = './export/' . $prj . '/_tmp_dic_' . time() . '.tabbed.txt';
        $hTmpFile = @fopen($tmpPathFile, 'w');
        if (!$hTmpFile) {
            return array(FALSE, '_nowrite');
        }
        $line = trim(fgets($hSrcFile));
        if (!preg_match('`^[0-9]+$`', $line)) {
            fclose($hSrcFile);
            fclose($hTmpFile);
            return array(FALSE, '_nodict');
        }
        while (!feof($hSrcFile)) {
            $line = trim(fgets($hSrcFile));
            $line = preg_replace('`( +|\t+)#.*$`', '', $line); // cut the comments
            if ($line != '') {
                $elems = preg_split('`( +|\t+)`', str_replace('"', '""', $line));
                $nbElems = count($elems);
                // lemma and flags
                if (strpos($elems[0], '/')) {
                    list($lemma, $flags) = explode('/', $elems[0]);
                }
                else {
                    $lemma = $elems[0];
                    $flags = '';
                }
                // other fields
                $data = array('po' => '', 'is' => '', 'ds' => '', 'ts' => '', 'ip' => '', 'dp' => '', 'tp' => '', 'sp' => '',
                              'pa' => '', 'st' => '', 'al' => '', 'ph' => '', 'lx' => '', 'se' => '', 'et' => '', 'di' => '*'
                             );
                for ($j=1; $j<$nbElems; $j++) {
                    if ($elems[$j]{2} == ':') {
                        // named fields
                        list($field, $value) = preg_split('`:`', $elems[$j], 2);
                        if (isset($data[$field])) {
                            if ($field != 'di') {
                                $data[$field] .= ($data[$field] == '') ? $value : ' ' . $value;
                            }
                            else {
                                $data['di'] = $value;
                            }
                        }
                    }
                }
                // checking
                //$this->db->errorReport($prj, 'Dictionary. Import - error in line: ' . $i . PHP_EOL . $line);
                // write line
                $dstline = $lemma . "\t" . $flags . "\t"
                         . $data['po'] . "\t" . $data['is'] . "\t" . $data['ds'] . "\t" . $data['ts'] . "\t"
                         . $data['ip'] . "\t" . $data['dp'] . "\t" . $data['tp'] . "\t" . $data['sp'] . "\t"
                         . $data['pa'] . "\t" . $data['st'] . "\t" . $data['al'] . "\t" . $data['ph'] . "\t"
                         . $data['lx'] . "\t" . $data['se'] . "\t" . $data['et'] . "\t" . $data['di'] . PHP_EOL;
                fwrite($hTmpFile, $dstline);
            }
            $i++;
        }
        fclose($hSrcFile);
        fclose($hTmpFile);

        // copy the temp file in the table
        $qTableName = 'dicl_'.$prj.'_dic (lemma, flags, po, "is", ds, ts, ip, dp, tp, sp, pa, st, al, ph, lex, sem, ety, dic)';
        list($ok, $msg) = $this->copyFileToTable($prj, $tmpPathFile, $qTableName, 'dictionary');
        if (!$ok) {
            return array(FALSE, $msg);
        }
        unlink($tmpPathFile);
        return array(TRUE, 'ok');
    }
    
    public function exportDictionary ($prj, $selectedDict, $isNoDubiousEntries, $isHunspellFields, $isDicollecteFields, $name_addon, $path='.') {
        // subdictionaries selection
        $sqlDictSelect = $this->createSqlDictSelectQuery($selectedDict);
        $sqlRestrict = '';
        if ($isNoDubiousEntries) {
            $sqlRestrict = ($sqlDictSelect != '') ? " AND chk != '0'" : " WHERE chk != '0'";
        }
        // count
        try {
            $oQ = $this->db->connx->query('SELECT count(id_entry) FROM dicl_'.$prj.'_dic ' . $sqlDictSelect . $sqlRestrict);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'export dictionary: count');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll();
        $nbentries = $result[0][0];
        // select entries
        try {
            $oQ = $this->db->connx->query('SELECT * FROM dicl_'.$prj.'_dic ' . $sqlDictSelect . $sqlRestrict . ' ORDER BY lemma');
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'export dictionary');
            return array(FALSE, '_dberror');
        }
        global $activeFields;
        $file = fopen($path.'/export/'.$prj.'/'.$name_addon, 'w');
        if (!$file) {
            return array(FALSE, '_nowrite');
        }
        fwrite($file, $nbentries . PHP_EOL);
        $hunspellFields = array('po', 'is', 'ds', 'ts', 'ip', 'dp', 'tp', 'sp', 'pa', 'st', 'al', 'ph');
        while ($data = $oQ->fetch(PDO::FETCH_ASSOC)) {
            $line = $data['lemma'];
            if ($data['flags'] != '') { $line .= '/' . $data['flags']; }
            // hunspell fields
            if ($isHunspellFields) {
                foreach ($hunspellFields as $field) {
                    if ($activeFields[$field] and $data[$field] != '') { $line .= $this->fieldToHunspell($field, $data[$field]); }
                }
            }
            // dicollecte fields
            if ($isDicollecteFields) {
                if ($data['lex'] != '') { $line .= $this->fieldToHunspell('lx', $data['lex']); }
                if ($data['sem'] != '') { $line .= $this->fieldToHunspell('se', $data['sem']); }
                if ($data['ety'] != '') { $line .= $this->fieldToHunspell('et', $data['ety']); }
                if ($data['dic'] != '') { $line .= ' di:' . $data['dic']; }
                if ($data['ifq'] != ' ') { $line .= ' fq:' . $data['ifq']; }
                $line .= ' id:' . $data['id_entry'];
            }
            $line .= PHP_EOL;
            fwrite($file, $line);
        }
        fclose($file);
        return array(TRUE, 'ok');
    }
    
    public function updateIfq ($prj, $srcPathFile) {
        // upadte the field ifq in the dictionary
        $qTableName = 'dicl_tmp_'.$prj.'_ifq_'.time();
        try {
            $oQ = $this->db->connx->exec('CREATE TABLE '.$qTableName.' (id  integer PRIMARY KEY,  fq  character NOT NULL CHECK (fq <> \'\'));');
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'create temp table for ifq');
            return array(FALSE, '_dberror');
        }
        
        // copy the file in a temp table
        list($ok, $msg) = $this->copyFileToTable($prj, $srcPathFile, $qTableName, 'frequencies index');
        if (!$ok) {
            return array(FALSE, $msg);
        }
        
        // update the dictionary from the temp table
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_dic d  SET ifq = t.fq  FROM '.$qTableName.' t  WHERE d.id_entry = t.id');
            $this->db->connx->exec('DROP TABLE '.$qTableName);
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'update frequencies index');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, $msg);
    }
    
    
    /* Affixes */
    
    public function importAffixes ($prj, $srcPathFile) {
        $hSrcFile = @fopen($srcPathFile, 'r');
        if (!$hSrcFile) {
            return array(FALSE, '_nofile');
        }
        $i = 0;
        $tmpPathFile = './export/' . $prj . '/_tmp_aff_' . time() . '.tabbed.txt';
        $hTmpFile = fopen($tmpPathFile, 'w');
        if (!$hTmpFile) {
            return array(FALSE, '_nowrite');
        }
        while (!feof($hSrcFile)) {
            $line = trim(fgets($hSrcFile));
            if ($line == '') {
                continue;
            }
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
                if ($nbElems == 4) {
                    // new flag
                    $mix = ($elems[2] == 'Y') ? 'TRUE' : 'FALSE';
                }
                else {
                    // new rule
                    // affixe type, flag, cut, add, flags, cond
                    $afftype = ($elems[0] == 'PFX') ? 'P' : 'S';
                    $flag = str_replace('"', '""', $elems[1]);
                    $cut = ($elems[2] != '0') ? $elems[2] : '';
                    $subelems = explode('/', $elems[3]);
                    $add = ($subelems[0] != '0') ? $subelems[0] : '';
                    $flags = (count($subelems) > 1) ? $subelems[1] : '';
                    $cond = $elems[4];
                    // other fields
                    $data = array('po' => '', 'is' => '', 'ds' => '', 'ts' => '', 'ip' => '', 'dp' => '', 'tp' => '', 'sp' => '', 'pa' => '', 'di' => '*');
                    for ($j=5; $j<$nbElems; $j++) {
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
                    // checking
                    //$this->db->errorReport($prj, 'Affixes. Import - error in line: ' . $i . PHP_EOL . $line);
                    // write line
                    $newline = $afftype . "\t" . $mix . "\t" . $flag . "\t" . $cut . "\t" . $add . "\t" . $flags . "\t" . $cond . "\t"
                             . $data['po'] . "\t" . $data['is'] . "\t" . $data['ds'] . "\t" . $data['ts'] . "\t"
                             . $data['ip'] . "\t" . $data['dp'] . "\t" . $data['tp'] . "\t" . $data['sp'] . "\t"
                             . $data['pa'] . "\t" . $data['di'] . "\t" . $comment . PHP_EOL;
                    fwrite($hTmpFile, $newline);
                }
            }
            $i++;
        }
        fclose($hSrcFile);
        fclose($hTmpFile);
        
        // copy the temp file in the table
        $qTableName = 'dicl_'.$prj.'_flags (afftype, mix , flag, cut, add, flags, cond, po, "is", ds, ts, ip, dp, tp, sp, pa, dic, comment)';
        list($ok, $msg) = $this->copyFileToTable($prj, $tmpPathFile, $qTableName, 'flags');
        if (!$ok) {
            return array(FALSE, $msg);
        }
        unlink($tmpPathFile);
        return array(TRUE, 'ok');
    }
    
    public function exportAffixes ($prj, $selectedDict, $isHunspellFields, $isDicollecteFields, $name_addon, $path='.') {
        // subdictionaries selection
        $sqlDictSelect = $this->createSqlDictSelectQuery($selectedDict);
        // we count the number of rules for each flag
        try {
            $oQ = $this->db->connx->query('SELECT flag, count(flag) as nbrules FROM dicl_'.$prj.'_flags ' . $sqlDictSelect . ' GROUP BY flag ORDER BY flag');
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'export affixes: list all flags');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        $allNbFlags = array();
        foreach ($result as $data) {
            $allNbFlags[$data['flag']] = $data['nbrules'];
        }
        // creating file
        try {
            $oQ = $this->db->connx->query('SELECT * FROM dicl_'.$prj.'_flags ' . $sqlDictSelect . ' ORDER BY flag, id_aff');
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'export affixes');
            return array(FALSE, '_dberror');
        }
        global $activeFields;
        $hunspellFields = array('po', 'is', 'ds', 'ts', 'ip', 'dp', 'tp', 'sp', 'pa');        
        $file = fopen($path.'/export/'.$prj.'/'.$name_addon, 'w');
        if (!$file) {
            return array(FALSE, '_nowrite');
        }
        $affheader = $path.'/html/'.$prj.'/affixes-header.aff';
        if (is_file($affheader)) {
            fwrite($file, file_get_contents($affheader).PHP_EOL);
        }
        else{
            fwrite($file, 'SET UTF-8' . PHP_EOL . PHP_EOL . PHP_EOL);
        }
        $currentFlag = '';
        while ($data = $oQ->fetch(PDO::FETCH_ASSOC)) {
            if ($data['flag'] != $currentFlag) {
                $currentFlag = $data['flag'];
                $typeFlag = ($data['afftype'] == 'P') ? 'PFX ' : 'SFX ';
                $mix = ($data['mix']) ? ' Y ' : ' N ';
                fwrite($file, PHP_EOL . $typeFlag . $data['flag'] . $mix . $allNbFlags[$data['flag']] . PHP_EOL);
            }
            $flags = ($data['flags'] != '') ? '/' . $data['flags'] : '';
            $cut = ($data['cut'] != '') ? $data['cut'] : '0';
            $add = ($data['add'] != '') ? $data['add'] : '0';
            $line = $typeFlag . $currentFlag . ' ' . $cut . ' ' . $add . $flags . ' ' . $data['cond'];
            // hunspell fields
            if ($isHunspellFields){
                foreach ($hunspellFields as $field) {
                    if ($data[$field] != '') { $line .= $this->fieldToHunspell($field, $data[$field]); }
                }
            }
            // dicollecte fields
            if ($isDicollecteFields) {
                $line .= ' di:' . $data['dic'];
            }
            if ($isHunspellFields and $data['comment'] != '') { $line .= ' #' . $data['comment']; }
            fwrite($file, $line . PHP_EOL);
        }
        fwrite($file, PHP_EOL);
        fclose($file);
        return array(TRUE, 'ok');
    }
    
    
    /* Synsets */

    public function importSynsets ($prj, $srcPathFile) {
        $hSrcFile = @fopen($srcPathFile, 'r');
        if (!$hSrcFile) {
            return array(FALSE, '_nofile');
        }
        $i = 0;
        $tmpPathFile = './export/' . $prj . '/_tmp_synsets_' . time() . '.tabbed.txt';
        $hTmpFile = @fopen($tmpPathFile, 'w');
        if (!$hTmpFile) {
            return array(FALSE, '_nowrite');
        }
        while (!feof($hSrcFile)) {
            $line = trim(fgets($hSrcFile));
            if ($line != '') {
                $line = trim($line, '| ');
                $line = preg_replace('`( *\| *)+`', '|', $line);
                list($descr, $synset) = explode('|', $line, 2);
                $nbsyn = substr_count($synset, '|') + 1;
                $descr = trim($descr, '() ');
                if (strpos($descr, ',') !== FALSE) {
                    list($pos, $tags) = explode(',', $descr, 2);
                    $pos = trim($pos);
                    $tags = trim($tags);
                }
                else {
                    $pos = $descr;
                    $tags = '';
                }
                fwrite($hTmpFile, $pos . "\t" . $tags . "\t" . $synset . "\t" . $nbsyn . PHP_EOL);
            }
            $i++;
        }
        fclose($hSrcFile);
        fclose($hTmpFile);
        
        // copy the temp file in the table
        $qTableName = 'dicl_'.$prj.'_syns (pos, tags, synset, nbsyn)';
        list($ok, $msg) = $this->copyFileToTable($prj, $tmpPathFile, $qTableName, 'synsets');
        if (!$ok) {
            return array(FALSE, $msg);
        }
        //unlink($tmpPathFile);
        return array(TRUE, 'ok');
    }
    
    public function exportSynsets ($prj, $name_addon) {
        try {
            $oQ = $this->db->connx->query('SELECT * FROM dicl_'.$prj.'_syns WHERE deleted = FALSE ORDER BY synset');
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'export synsets');
            return array(FALSE, '_dberror');
        }
        $hSyns = fopen('./export/'.$prj.'/'.$name_addon.'.dat', 'w');
        if (!$hSyns) {
            return array(FALSE, '_nowrite');
        }
        while ($data = $oQ->fetch(PDO::FETCH_ASSOC)) {
            $tags = ($data['tags'] != '') ? ',' . $data['tags']  : ''; 
            $line = '('.$data['pos'] . $tags . ')|' . $data['synset'] . PHP_EOL;
            fwrite($hSyns, $line);
        }
        fclose($hSyns);
        return array(TRUE, 'ok');
    }
    
    /* PRIVATE */
    
    private function copyFileToTable ($prj, $srcPathFile, $dbTableName, $msg) {
        if (!$this->db->altconnx) {
            $this->db->openAltConnx();
            if (!$this->db->altconnx) return array(FALSE, '_nobase');
        }
        $hSrcFile = @fopen($srcPathFile, 'r');
        if (!$hSrcFile) {
            return array(FALSE, '_nofile');
        }
        pg_query($this->db->altconnx, 'COPY '.$dbTableName.' FROM stdin');
        $line = fgets($hSrcFile);
        $nErrors = 0;
        while (!feof($hSrcFile)) {
            // note : with pg_put_line, each element in a line is separated by tabulation, there must be \n at the end of the line.
            $ok = pg_put_line($this->db->altconnx, $line);
            if (!$ok) $nErrors += 1;
            $line = fgets($hSrcFile);
        }
        pg_put_line($this->db->altconnx, "\\." . PHP_EOL);
        pg_end_copy($this->db->altconnx);
        fclose($hSrcFile);
        $this->db->errorReport($prj, 'IMPORTATION (' . $msg . '): ' . $nErrors . ' errors');
        return array(TRUE, 'ok');
    }
    
    private function createSqlDictSelectQuery ($selectedDict) {
        $sqlDictSelect = '';
        if ($selectedDict !== TRUE) {
            $nbDict = count($selectedDict);
            if ($nbDict != 0) {
                $sqlDictSelect = 'WHERE dic = ' . "'".$selectedDict[0]."'";
                for ($i = 1;  $i < $nbDict;  $i++) {
                    $sqlDictSelect .= ' OR dic = ' . "'".$selectedDict[$i]."'";
                }
            }
        }
        return $sqlDictSelect;
    }
    
    private function fieldToHunspell ($fieldName, $value) {
        if (strpos($value, ' ') !== FALSE) {
            $values = explode(' ', $value);
            $field = '';
            foreach ($values as $elem) {
                $field .= ' ' . $fieldName . ':' . $elem;
            }
        }
        else {
            $field = ' ' . $fieldName . ':' . $value;
        }
        return $field;
    }
}

?>
