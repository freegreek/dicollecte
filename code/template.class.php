<?php

/*
    gTemplate or Growing Template 1.1.1 -- october 2008
    by Olivier Ronez -- dicollecte at gmail.com --
    License: GPL 3

    Variables must be written between two braces {{VARIABLE}}.
    All chars are allowed, but it is advised to use only uppercases, for a better readibility.

    <!-- BRANCH branchname -->  to begin a branch
    <!-- DIE branchname -->  to end it
    Only a-z and _ characters allowed. No uppercase.
    
    Variables in branches must be preceded by the names of branches.
    ex: {{branchname.VARIABLENAME}} {{branchname.childbranchname.VARIABLENAME}}
    
    If you intend to display stuff between two braces, create templates with the parameter TRUE.
    Only vars names will be removed. This has not been much tested.
    
    Global vars:
    These vars will be replaced whereever they are (trunk or branches), after local vars replacements.
    To avoid confusion, it is advised to use specific names for these vars, between underscores. Example: {{_VAR_}}.
*/

/* class Template ------------------------------------------------------------------------------------------- */
class Template {
    private $cautiousPruning = FALSE;
    private $page = array(); // an array of lines at first, then a string
    private $pagelength = 0;
    private $branches = array();
    private $branchesVars = array();
    private $globalVars = array();
    
    // public
    function __construct ($cautiousPruning = FALSE) {
        $this->cautiousPruning = $cautiousPruning;
    }
    
    public function SetTrunkVars ($vars) {
        // fills trunk vars
        foreach ($vars as $nameVar => $value) {
            $this->branchesVars[$nameVar] = $value;
        }
    }
    
    public function SetTrunkVar ($nameVar, $value) {
        // to set or change one var in the trunk
        $this->branchesVars[$nameVar] = $value;
    }
    
    public function SetBranchVars ($where, $newVars) {
        // set the array $vars at the $where branch
        if (!strstr($where, '.')) {
            // first level branch
            $this->branchesVars[$where . '.'][] = $newVars;
        }
        else {
            // children branches
            $where = explode('.', $where);
            $lastBranchName = array_pop($where) . '.';
            $bVars = &$this->branchesVars;
            foreach($where as $branchName) {
                $branchName .= '.';
                $pos = count($bVars[$branchName]) - 1;
                $bVars = &$bVars[$branchName][$pos];
            }
            $bVars[$lastBranchName][] = $newVars;
        }
    }
    
    public function UpdateBranchVars ($where, $newVars) {
        // update the last branch created
        $where = explode('.', $where);
        $bVars = &$this->branchesVars;
        foreach($where as $branchName) {
            $branchName .= '.';
            $pos = count($bVars[$branchName]) - 1;
            $bVars = &$bVars[$branchName][$pos];
        }
        foreach ($newVars as $nameVar => $value) {
            $bVars[$nameVar] = $value;
        }
    }
    
    public function SetGlobalVars ($vars) {
        // fills global vars
        foreach ($vars as $nameVar => $value) {
            $this->globalVars[$nameVar] = $value;
        }
    }
    
    public function SetGlobalVar ($nameVar, $value) {
        // to set or change one global var
        $this->globalVars[$nameVar] = $value;
    }
    
    public function Grow ($fileName) {
        // generates the page and display it
        if (is_file($fileName)) {
            $this->page = file($fileName);
            $this->pagelength = sizeof($this->page);
            $this->createBranches(0, '.'); // the page is cut in branches
            $this->page = $this->generateBranch('.', $this->branchesVars, ''); // branches are generated from the trunk (main page)
            $this->globalSprinkle(); // global vars insertion
            $this->prune(); // all unfilled vars are deleted
            echo $this->page; // display
        }
        else {
            echo '<h1>Error: file [' . $fileName . '] not found !</h1>';
        }
    }
    
    // special
    public function SetOptionsList ($globalVar, $options, $select=NULL) {
        $htmlOptions = '';
        foreach ($options as $value => $label) {
            $selected = ($value == $select) ? 'selected="selected"' : '';
            $htmlOptions .= '<option ' . $selected . ' value="' . $value . '">' . $label . '</option>';
        }
        $this->globalVars[$globalVar] = $htmlOptions;
    }
    
    // debug
    public function display_branches () {
        echo '<pre>'; print_r($this->branches); echo '</pre>';
    }
    public function display_branchesVars () {
        echo '<pre>'; print_r($this->branchesVars); echo '</pre>';
    }
    
    
    /* PRIVATE */
    
    private function createBranches ($lineptr, $branchName) {
        // recursive method !
        // reads the page and separates trunk and branches
        // branches are stored in the array $this->branches['branchname.']
        // trunk is stored in $this->branches['.']
        // <!-- BRANCH branchname --> are replaced by {{branchname.}}
        // <!-- DIE branchname --> are removed
        if ($lineptr == 0)  {
            // trunk (main body)
            $this->branches[$branchName] = array();
            while ($lineptr < $this->pagelength) {
                if (!preg_match('`<!-- BRANCH ([a-z_]+) -->`', $this->page[$lineptr], $match)) {
                    $this->branches[$branchName][] = $this->page[$lineptr];
                }
                else {
                    $this->branches[$branchName][] = preg_replace('`<!-- BRANCH ([a-z_]+) -->`', '{{$1.}}', $this->page[$lineptr]);
                    $lineptr++;
                    $lineptr = $this->createBranches($lineptr, $match[1]);
                }
                $lineptr++;
            }
            $this->branches[$branchName] = implode('', $this->branches[$branchName]);
        }
        else {
            // branches
            $branchNameDot = $branchName . '.';
            $this->branches[$branchNameDot] = array();
            while (strpos($this->page[$lineptr], '<!-- DIE ' . $branchName . ' -->') === FALSE and $lineptr < $this->pagelength) {
                if (!preg_match('`<!-- BRANCH ([a-z_.]+) -->`', $this->page[$lineptr], $match)) {
                    $this->branches[$branchNameDot][] = $this->page[$lineptr];
                }
                else {
                    $this->branches[$branchNameDot][] = preg_replace('`<!-- BRANCH ([a-z_.]+) -->`', '{{$1.}}', $this->page[$lineptr]);
                    $lineptr++;
                    $lineptr = $this->createBranches($lineptr, $match[1]);
                }
                $lineptr++;
            }
            $this->branches[$branchNameDot] = implode('', $this->branches[$branchNameDot]);
        }
        return $lineptr;
    }
    
    private function generateBranch ($branchName, $branchVars, $trunk) {
        // recursive function !
        // we read recursively the array where are stored vars and assign them to the branch branchName
        $text = $this->branches[$branchName];
        if ($branchName != '.') $trunk .= $branchName;
        foreach ($branchVars as $nameVar => $value) {
            if ($nameVar{strlen($nameVar)-1} == '.') {
                // this is a branch. we check first if this branch exists in this subpage.
                if (isset($this->branches[$nameVar])) {
                    // there is possibly several branches.
                    $branchesText = '';
                    foreach ($value as $childBranchVars) {
                        $branchesText .= $this->generateBranch($nameVar, $childBranchVars, $trunk);
                    }
                    $text = str_replace('{{'.$nameVar.'}}', $branchesText, $text);
                }
            }
            else {
                // this is a var
                $text = str_replace('{{'.$trunk.$nameVar.'}}', $value, $text);
            }
        }
        return $text;
    }
    
    private function globalSprinkle () {
        // global vars insertion
        foreach ($this->globalVars as $nameVar => $value) {
            $this->page = str_replace('{{'.$nameVar.'}}', $value, $this->page);
        }
    }
    
    private function prune () {
        // remove unfilled vars
        if ($this->cautiousPruning) {
            // we search for all vars names and remove only them. (This has not been much tested.)
            $allVars = array();
            foreach ($this->branches as $branch) {
                preg_match_all('`\{\{[a-zA-Z0-9_.]+\}\}`', $branch, $vars);
                $allVars = array_merge($allVars, $vars[0]);
            }
            $this->page = str_replace($allVars, '', $this->page);
        }
        else {
            // we don't care of what is in between two braces.
            $this->page = preg_replace('`\{\{[a-zA-Z0-9_.]+\}\}`', '', $this->page);
        }
    }
    
    
    /* ==================== Dicollecte specific  ==================== */
    
    private $debugStack = '';
    
    public function debugStack ($myvar) {
        $this->debugStack .= (is_array($myvar)) ? print_r($myvar, TRUE) . PHP_EOL . PHP_EOL : $myvar . PHP_EOL . PHP_EOL;
    }
    
    public function SetPageVars ($prj, $pagetitle, $dbaUsers) {
        // fill the header and the footer
        global $ui;
        
        if ($ui['menu']['MENU_COMMUNITYLINK'] == '') {
            $ui['menu']['MENU_COMMUNITY'] = '';
        }
        $this->SetTrunkVars($ui['mainPage']);
        $this->SetTrunkVars($ui['menu']);
        if ($dbaUsers->db->connx) {
            if (isset($_COOKIE['login'])) {
                $rank = $dbaUsers->getUserRankFor($prj);
                $admlinks = '';
                if ($rank <= 2) {
                    $editablepages = array('home', 'news', 'faq', 'documentation', 'documentationthes', 'download');
                    $autoeditlink = (in_array($pagetitle, $editablepages)) ? ' | <a href="'.$pagetitle.'.php?prj='.$prj.'&amp;cmd=autoedit">'.$ui['adminMenu']['AUTOEDIT'].'</a>' : '';
                    $this->SetBranchVars('adminpanel', array('ADMIN' => $ui['adminMenu']['ADMIN'],
                                                             'AUTOEDITLINK' => $autoeditlink));
                }
                $this->SetBranchVars('userpanel', $ui['myMenu']);
                $this->UpdateBranchVars('userpanel', array('IDUSER' => $_SESSION['id_user'],
                                                           'LOGIN' => $_COOKIE['login']));
            }
            else {
                $this->SetBranchVars('connxform', $ui['connxForm']);
            }
            // system message
            if (isset($_COOKIE['msg'])) {
                $this->uiSystemMessage($_COOKIE['msg']);
                setcookie('msg', FALSE);
            }
        }
        else {
            $this->uiSystemMessage('_nobase');
        }
        // global var: _PRJ_
        $this->SetGlobalVar('_PRJ_', $prj);
        // debug
        if ($this->debugStack != '') {
            $this->SetTrunkVar('DEBUGPANEL', '<pre><b>DEBUG PANEL:</b>'.PHP_EOL.PHP_EOL.'<b><samp>' . $this->debugStack . '</samp></b></pre>');
        }
    }

    public function uiSystemMessage($msgcode) {
        // return a system message
        global $ui;
        if (isset($ui['sysMsg'][$msgcode])) {
            $msg = ($msgcode{0} == '_') ? '<div id="message_error"><p>' . $ui['sysMsg'][$msgcode] . '</p></div>'
                                        : '<div id="messagebox"><div id="message"><p>' . $ui['sysMsg'][$msgcode] . '</p></div></div>';
        }
        elseif (preg_match('`^[0-9]+$`', $msgcode)) {
            $msg = '<div id="message_error"><p>' . $ui['sysMsg']['_entryexists'] . ' <a href="entry.php?prj={{_PRJ_}}&amp;id=' . $msgcode . '">' . $msgcode . '</a></p></div>';
        }
        else {
            $msg = '<div id="message_error"><p>Unknown message code [' . $msgcode . ']</p></div>';
        }
        $this->SetTrunkVar('SYSMSG', $msg);
    }   
}

?>
