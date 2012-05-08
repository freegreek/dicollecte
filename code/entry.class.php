<?php

class Entry {

    public $lemma = '';
    public $flags = '';
    public $po = '';
    public $is = '';
    public $ds = '';
    public $ts = '';
    public $ip = '';
    public $dp = '';
    public $tp = '';
    public $sp = '';
    public $pa = '';
    public $st = '';
    public $al = '';
    public $ph = '';
    public $lex = '';
    public $sem = '';
    public $ety = '';
    public $dic = '*';
    public $ifq = '';

    public $flexions = NULL;
    public $is2lvlFlexions = FALSE;
    
    private $storedFlags = NULL;  // list of flags
    private $rules = NULL;        // affixes rules from the db 

    /* PUBLIC */
    
    function __construct (&$entry=NULL) {
        foreach ($entry as $key => $value) {
            if (property_exists('Entry', $key)) {
                $this->$key = preg_replace('`\s\s+`', ' ', trim($value));
            }
        }
    }
    
    // html code to display the entry
    public function createHtmlPresentation ($isEntry=FALSE) {
        global $prjDic;
        global $ui;
        global $activeFields;
        $htmlCode = '<p><label class="entryformlbl">' . $ui['entryObj']['lemma'] . '</label><b id="defaultlemma">' . $this->lemma . '</b> &nbsp;</p>' . PHP_EOL;
        $htmlCode .= '<p><label class="entryformlbl">' . $ui['entryObj']['flags'] . '</label><samp>' . $this->flags . '</samp> &nbsp;</p>' . PHP_EOL;
        $fields = array('po', 'is', 'ds', 'ts', 'ip', 'dp', 'tp', 'sp', 'pa', 'st', 'al', 'ph', 'lex', 'sem', 'ety');
        foreach ($fields as $field) {
            if ($activeFields[$field]) {
                $htmlCode .= '<p><label class="entryformlbl">' . $ui['entryObj'][$field] . '</label><b>' . $this->$field . '</b> &nbsp;</p>' . PHP_EOL;
            }
        }
        if ($activeFields['dic']) {
            $value = (isset($prjDic[$this->dic])) ? '<b>'.$prjDic[$this->dic].'</b>' : ' <samp>['.$this->dic.'] '.$ui['sysMsg']['_error'].'</samp>';
            $htmlCode .= '<p><label class="entryformlbl">' . $ui['entryObj']['dic'] . '</label>' . $value . ' &nbsp;</p>' . PHP_EOL;
        }
        if ($isEntry and $activeFields['ifq']) {
            $value = (isset($ui['ifq'][$this->ifq])) ? $ui['ifq'][$this->ifq] : '['.$this->ifq.'] '.$ui['ifq']['_'];
            $htmlCode .= '<p><label class="entryformlbl">' . $ui['entryObj']['ifq'] . '</label>  ' . $value . ' &nbsp;</p>';
        }
        return $htmlCode;
    }
    
    // html code to create the update form
    public function createHtmlForm ($isSearch=FALSE) {
        global $prjDic;
        global $activeFields;
        $htmlCode = (!$isSearch) ? $this->createWidgetText('lemma', DB_LEMMALEN) : '';
        if ($isSearch) { $this->dic = ''; }
        $htmlCode .= $this->createWidgetText('flags', DB_FLAGSLEN, TRUE);
        if ($activeFields['po']) { $htmlCode .= $this->createWidgetText('po', DB_HGRAMMLEN, TRUE); }
        if ($activeFields['is']) { $htmlCode .= $this->createWidgetText('is', DB_HGRAMMLEN, TRUE); }
        if ($activeFields['ds']) { $htmlCode .= $this->createWidgetText('ds', DB_HGRAMMLEN, TRUE); }
        if ($activeFields['ts']) { $htmlCode .= $this->createWidgetText('ts', DB_HGRAMMLEN, TRUE); }
        if ($activeFields['ip']) { $htmlCode .= $this->createWidgetText('ip', DB_HGRAMMLEN, TRUE); }
        if ($activeFields['dp']) { $htmlCode .= $this->createWidgetText('dp', DB_HGRAMMLEN, TRUE); }
        if ($activeFields['tp']) { $htmlCode .= $this->createWidgetText('tp', DB_HGRAMMLEN, TRUE); }
        if ($activeFields['sp']) { $htmlCode .= $this->createWidgetText('sp', DB_HGRAMMLEN, TRUE); }
        if ($activeFields['pa']) { $htmlCode .= $this->createWidgetText('pa', DB_HGRAMMLEN); }
        if ($activeFields['st']) { $htmlCode .= $this->createWidgetText('st', DB_LEMMALEN); }
        if ($activeFields['al']) { $htmlCode .= $this->createWidgetText('al', DB_LEMMALEN); }
        if ($activeFields['ph']) { $htmlCode .= $this->createWidgetText('ph', DB_LEMMALEN); }
        if ($activeFields['lex']) { $htmlCode .= $this->createWidgetText('lex', DB_LEXLEN, TRUE); }
        if ($activeFields['sem']) { $htmlCode .= $this->createWidgetText('sem', DB_LEXLEN, TRUE); }
        if ($activeFields['ety']) { $htmlCode .= $this->createWidgetText('ety', DB_LEXLEN, TRUE); }
        if ($activeFields['dic']) { $htmlCode .= $this->createWidgetListBox('dic', $prjDic, $isSearch); }
        return $htmlCode;
    }

    public function createFlexions ($dbaFlags, $prj, $project, $iPR, $iPRstop) {
        global $activeFields;
        $this->flexions = array();
        $this->rules = array();
        $this->storedFlags = array();
        $morph = $this->po.' '.$this->is.' '.$this->ds.' '.$this->ip.' '.$this->dp.' '.$this->sp.' ';
        $this->generateFlexions($dbaFlags, $prj, $project, $this->lemma, $this->flags, $morph, $iPR, $iPRstop);
        return $this->flexions;
    }
    
    public function createLinks () {
        global $prjCustomlinks;
        $res = '';
        foreach ($prjCustomlinks as $lbl => $link) {
            $res .= '<a href="' . $link . '" target="_blank">'. $lbl .'</a> &nbsp; ';
        }
        return $res;
    }
    
    /* PRIVATE */

    private function createWidgetText ($field, $maxlength, $doList=FALSE) {
        global $ui;
        $widget = '';
        if ($doList) { $widget = '<div class="widgetbutton"><a href="" onclick="clearField('."'".$field."'".'); return false;"><samp><b>-</b></samp></a></div>' . PHP_EOL; }
        $widget .= '<p><label class="entryformlbl" for="'.$field.'">' . $ui['entryObj'][$field]
                . '</label><input type="text" id="'.$field.'" name="'.$field.'" class="inputtxt" style="width: 350px;" maxlength="'.$maxlength
                . '" value="'.str_replace('"', '&quot;', $this->$field).'" onkeyup="generateLinks(false)" onkeydown="generateLinks(false)" />';
        $widget .= ($doList) ? '<script type="text/javascript">new autosuggest("'.$field.'", '.$field.'Values);</script></p><div class="clearer"></div>' : '</p>'; 
        return $widget . PHP_EOL;
    }
    
    private function createWidgetListBox ($field, &$options, $addEmptyOption=FALSE) {
        global $ui;
        $htmlOptions = '';
        if ($addEmptyOption) {
            $htmlOptions .= '<option value=""> </option>';
        }
        foreach ($options as $value => $label) {
            $selected = ($value === $this->$field) ? 'selected="selected"' : '';
            $htmlOptions .= '<option ' . $selected . ' value="' . $value . '">' . $label . '</option>';
        }
        return '<p><label class="entryformlbl" for="'.$field.'">' . $ui['entryObj'][$field]
               . '</label><select id="'.$field.'" name="'.$field.'" size="1" style="width: 350px;">' . $htmlOptions . '</select></p>' . PHP_EOL;
    }

    private function generateFlexions ($dbaFlags, $prj, $project, $lemma, $sFlags, $morph, $iPR=0, $iPRstop=2) {
        // recursive function !
        // generate a list of tuples (flexion, morphology, dic)

        if ($iPR == $iPRstop) {
            return array();
        }
        $aFlags = $this->makeflags($sFlags, $project['flagtype']);
        $flex_prefix = array();
        $flex_suffix = array();
        if ($iPR==0 and !in_array($project['needaffix'], $aFlags)) {
            $this->flexions[] = array($lemma, $morph, '*');
        }

        foreach ($aFlags as $flag) {
            if (!in_array($flag, $project['exceptionslist']) and $flag != $project['needaffix']) {
                $doFlexions = TRUE;
                if (!in_array($flag, $this->storedFlags)) {
                    // we load the flag rules once in memory
                    if ($flag != $project['circumfix']) {
                        $reqflag = str_replace("'", "\'", $flag);
                        list($ok, $result) = $dbaFlags->selectFlag($prj, $reqflag);
                        if ($ok) {
                            if (count($result) > 0) {
                                $this->storedFlags[] = $flag;
                                $this->rules = array_merge($this->rules, $result);
                            }
                            else {
                                $this->flexions[] = array('## error ##', '## ' . $flag . ' : unknown flag ##', '*');
                                $doFlexions = FALSE;
                            }
                        }
                        else {
                            $this->flexions[] = array('# error at flag: ' . $flag, 'database error', '*');
                            $doFlexions = FALSE;
                        }
                    }
                    else {
                        $doFlexions = FALSE;
                    }
                }
                
                $bTest2Flex = ($iPR == 0 and $iPRstop == 1) ? TRUE : FALSE;
                
                if ($doFlexions) {
                    // flexions
                    foreach ($this->rules as $data) {
                        if ($data['flag'] == $flag) {
                            $addMorph = $data['po'].' '.$data['is'].' '.$data['ds'].' '.$data['ip'].' '.$data['dp'].' '.$data['sp'].' ';
                            $curMorph = $morph;
                            $endTag = '';
                            if ($bTest2Flex and $data['flags'] != '') {
                                $endTag = ' *';
                                $this->is2lvlFlexions = TRUE;
                            }
                            // endTag is a tag for first level flexions which indicates if there is second level flags (not shown)
                            if ($data['afftype'] == 'P') {
                                // prefixes
                                $pattern = '`^' . $data['cond'] . '`u';
                                if (preg_match($pattern, $lemma)) {
                                    if ($data['cut'] == '') {
                                        $flexion = array($data['add'].$lemma, $addMorph.$curMorph.$endTag, $data['dic']);
                                        if ($data['mix']) {
                                            $flex_prefix[] = $flexion;
                                            foreach ($flex_suffix as $flex) {
                                                $flexion = array($data['add'].$flex[0], $flex[1].$addMorph.$endTag, $data['dic']);
                                                $this->flexions[] = $flexion;
                                            }
                                        }
                                        else {
                                            $this->flexions[] = $flexion;
                                        }
                                    }
                                    else {
                                        $cutpattern = '`^' . $data['cut'] . '`u';
                                        $flexion = array(preg_replace($cutpattern, $data['add'], $lemma, 1), $addMorph.$curMorph.$endTag, $data['dic']);
                                        if ($data['mix']) {
                                            $flex_prefix[] = $flexion;
                                            foreach ($flex_suffix as $flex) {
                                                $flexion = array(preg_replace($cutpattern, $data['add'], $flex[0], 1), $flex[1].$addMorph.$endTag, $data['dic']);
                                                $this->flexions[] = $flexion;
                                            }
                                        }
                                        else {
                                            $this->flexions[] = $flexion;
                                        }
                                    }
                                    if ($data['flags'] != '' and $data['flags'] != $project['circumfix']) {
                                        $this->generateFlexions($dbaFlags, $prj, $project, $flexion[0], $data['flags'], $flexion[1], $iPR+1, $iPRstop);
                                    }
                                }
                            }
                            else {
                                // suffixes
                                $pattern = '`' . $data['cond'] . '$`u';
                                if (preg_match($pattern, $lemma)) {
                                    $ruleflags = $this->makeflags($data['flags'], $project['flagtype']);
                                    if (!in_array($project['circumfix'], $ruleflags) or $data['flags'] == $project['circumfix']) {
                                        // normal case, no circumfix
                                        if ($data['cut'] == '') {
                                            $flexion = array($lemma.$data['add'], $curMorph.$addMorph.$endTag, $data['dic']);
                                            if ($data['mix']) {
                                                $flex_suffix[] = $flexion;
                                                foreach($flex_prefix as $flex) {
                                                    $flexion = array($flex[0].$data['add'], $flex[1].$addMorph.$endTag, $data['dic']);
                                                    $this->flexions[] = $flexion;
                                                }
                                            }
                                            else {
                                                $this->flexions[] = $flexion;
                                            }
                                        }
                                        else {
                                            $cutpattern = '`' . $data['cut'] . '$`u';
                                            $flexion = array(preg_replace($cutpattern, $data['add'], $lemma, 1), $curMorph.$addMorph.$endTag, $data['dic']);
                                            if ($data['mix']) {
                                                $flex_suffix[] = $flexion;
                                                foreach($flex_prefix as $flex) {
                                                    $flexion = array(preg_replace($cutpattern, $data['add'], $flex[0], 1), $flex[1].$addMorph.$endTag, $data['dic']);
                                                    $this->flexions[] = $flexion;
                                                }
                                            }
                                            else {
                                                $this->flexions[] = $flexion;
                                            }
                                        }
                                        if ($data['flags'] != '' and $data['flags'] != $project['circumfix']) {
                                            $this->generateFlexions($dbaFlags, $prj, $project, $flexion[0], $data['flags'], $flexion[1], $iPR+1, $iPRstop);
                                        }
                                    }
                                    else {
                                        // circumfix
                                        if ($data['cut'] == '') {
                                            $flexion = array($lemma.$data['add'], $curMorph.$addMorph.$endTag, $data['dic']);
                                        }
                                        else {
                                            $cutpattern = '`' . $data['cut'] . '$`u';
                                            $flexion = array(preg_replace($cutpattern, $data['add'], $lemma, 1), $curMorph.$addMorph.$endTag, $data['dic']);
                                        }
                                        $this->generateFlexions($dbaFlags, $prj, $project, $flexion[0], $data['flags'], $flexion[1], $iPR+1, $iPRstop);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        $this->flexions = array_merge($this->flexions, $flex_prefix, $flex_suffix);
    }

    private function makeflags ($sFlags, $flagtype) {
        // generate a flags list from a string 'chFlags'
        if ($sFlags == '') return array();
        switch ($flagtype) {
            case '1':
                // one character flags
                $flags = array();
                $length = mb_strlen($sFlags, 'UTF-8');
                for ($i = 0;  $i < $length;  $i++) {
                    $flags[] = mb_substr($sFlags, $i, 1, 'UTF-8');
                }
                return $flags;
                break;
            case '2':
                // two-characters flags
                $length = mb_strlen($sFlags, 'UTF-8');
                if (($length % 2) != 0) {
                    // odd number of letters, we add a space
                    $sFlags = $sFlags . ' '; 
                }
                $flags = array();
                for ($i = 0;  $i < $length;  $i = $i + 2) {
                    $flags[] = mb_substr($sFlags, $i, 2, 'UTF-8');
                }
                return $flags;
                break;
            case 'N':
            case 'n':
                // numeric flags
                return explode(',', $sFlags);
                break;
            default:
                return array();
        }
    }
}

?>
