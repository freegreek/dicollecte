<?php

require('./config/config.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-users.class.php');

$db = new Database();
$dbaUsers = new dbaccessUsers($db);

// user checking
list($id_user, $rank, $error) = $dbaUsers->connectUser($_COOKIE['login'], $_COOKIE['pw'], $_GET['prj']);
if ($id_user == -1) {
    setSysMsg($error);
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}
if ($rank > 2) {
    setSysMsg('_noaccess');
    header(URL_HEADER . 'home.php?prj=' . $_GET['prj']);
    exit;
}

if (strpos($_REQUEST['cmd'], 'import') === 0) {
    if (!isset($_FILES['myfile'])) {
        setSysMsg('_nofile');
        header(URL_HEADER . 'administration.php?prj=' . $_GET['prj']);
        exit;
    }
    if ($_FILES['myfile']['error']) {
        setSysMsg('_upload_error_' . $_FILES['ifqfile']['error']);
        header(URL_HEADER . 'administration.php?prj=' . $_GET['prj']);
        exit;
    }
    if ($_FILES['myfile']['size'] > 30000000) {
        setSysMsg('_filetoobig');
        header(URL_HEADER . 'administration.php?prj=' . $_GET['prj']);
        exit;
    }
}

switch ($_REQUEST['cmd']) {
    case 'updatestats':
        // AJAX
        require('./code/dbaccess-projects.class.php');
        $dbaProject = new dbaccessProjects($db);
        list($ok, $result) = $dbaProject->updateStats($_GET['prj']);
        echo $result;
        exit;
    case 'closegrammtagentries':
        // AJAX
        require('./code/dbaccess-dictionary.class.php');
        $dbaDictionary = new dbaccessDictionary($db);
        list($ok, $result) = $dbaDictionary->closeGrammTagEntries($_GET['prj']);
        echo $result;
        exit;
    case 'deletereport':
        // AJAX
        if (file_exists('./log/'.$_GET['prj'].'/log.txt')) {
            unlink('./log/'.$_GET['prj'].'/log.txt');
        }
        echo 'ok';
        exit;
    case 'importifq':
        require('./code/dbaccess-import-export.class.php');
        $dbaIE = new dbaccessIE($db);
        list($ok, $result) = $dbaIE->updateIfq($_GET['prj'], $_FILES['myfile']['tmp_name']);
        setSysMsg($result);
        header(URL_HEADER . 'administration.php?prj=' . $_GET['prj']);
        exit;
    case 'importdict':
        require('./code/dbaccess-import-export.class.php');
        $dbaIE = new dbaccessIE($db);
        list($ok, $result) = $dbaIE->importDictionary($_GET['prj'], $_FILES['myfile']['tmp_name']);
        setSysMsg($result);
        header(URL_HEADER . 'administration.php?prj=' . $_GET['prj']);
        exit;
    case 'importaffix':
        require('./code/dbaccess-import-export.class.php');
        require('./config/db_vars.php');
        $dbaIE = new dbaccessIE($db);
        list($ok, $result) = $dbaIE->importAffixes($_GET['prj'], $_FILES['myfile']['tmp_name']);
        setSysMsg($result);
        header(URL_HEADER . 'administration.php?prj=' . $_GET['prj']);
        exit;
    case 'importthes':
        require('./code/dbaccess-import-export.class.php');
        $dbaIE = new dbaccessIE($db);
        list($ok, $result) = $dbaIE->importThesaurus($_GET['prj'], $_FILES['myfile']['tmp_name']);
        setSysMsg($result);
        header(URL_HEADER . 'administration.php?prj=' . $_GET['prj']);
        exit;
    case 'importsynsets':
        require('./code/dbaccess-import-export.class.php');
        $dbaIE = new dbaccessIE($db);
        list($ok, $result) = $dbaIE->importSynsets($_GET['prj'], $_FILES['myfile']['tmp_name']);
        setSysMsg($result);
        header(URL_HEADER . 'administration.php?prj=' . $_GET['prj']);
        exit;
    case 'exportdict':
        if (!preg_match('`^[0-9a-zA-Z_-]*$`', $_POST['name_addon'])) {
            setSysMsg('_wrongchars');
            header(URL_HEADER . 'administration.php?prj=' . $_GET['prj']);
            exit;
        }
        require('./code/dbaccess-import-export.class.php');
        require('./html/' . $_GET['prj'] . '/project_vars.php');
        if ($_POST['alldict'] == 'ON') {
            $selectedDict = TRUE; // all dictionaries are selected
        }
        else {
            $selectedDict = array();
            $nbDict = count($prjDic);
            for ($i = 0;  $i < $nbDict;  $i++) {
                if (isset($_POST['dict'.$i])) $selectedDict[] = $_POST['dict'.$i];
            }
        }
        $isNoDubiousEntries = (isset($_POST['nodubiousentries'])) ? TRUE : FALSE;
        $isHunspellFields = (isset($_POST['hunspellfields'])) ? TRUE : FALSE;
        $isDicollecteFields = (isset($_POST['dicollectefields'])) ? TRUE : FALSE;
        $fileName = $_GET['prj'] . '_' . $_POST['name_addon'] . '_' . date('Y-m-d') . '_' . $_COOKIE['login'];
        $dbaIE = new dbaccessIE($db);
        list($ok, $result) = $dbaIE->exportDictionary($_GET['prj'], $selectedDict, $isNoDubiousEntries, $isHunspellFields, $isDicollecteFields,
                                                      $fileName . '.dic');
        if (!$ok) {
            setSysMsg($result);
            header(URL_HEADER . 'administration.php?prj=' . $_GET['prj']);
            exit;
        }
        list($ok, $result) = $dbaIE->exportAffixes($_GET['prj'], $selectedDict, $isHunspellFields, $isDicollecteFields,
                                                   $fileName . '.aff');
        setSysMsg($result);
        header(URL_HEADER . 'administration.php?prj=' . $_GET['prj']);
        exit;
    case 'exportthes':
        if (!preg_match('`^[0-9a-zA-Z_-]*$`', $_POST['name_addon'])) {
            setSysMsg('_wrongchars');
            header(URL_HEADER . 'administration.php?prj=' . $_GET['prj']);
            exit;
        }
        require('./code/dbaccess-import-export.class.php');
        $dbaIE = new dbaccessIE($db);
        list($ok, $result) = $dbaIE->exportThesaurus($_GET['prj'], 'thes_' . $_GET['prj'] . '_' . $_POST['name_addon'] . '_' . date('Y-m-d') . '_' . $_COOKIE['login']);
        setSysMsg($result);
        header(URL_HEADER . 'administration.php?prj=' . $_GET['prj']);
        exit;
    case 'exportsynsets':
        if (!preg_match('`^[0-9a-zA-Z_-]*$`', $_POST['name_addon'])) {
            setSysMsg('_wrongchars');
            header(URL_HEADER . 'administration.php?prj=' . $_GET['prj']);
            exit;
        }
        require('./code/dbaccess-import-export.class.php');
        $dbaIE = new dbaccessIE($db);
        list($ok, $result) = $dbaIE->exportSynsets($_GET['prj'], 'synsets_' . $_GET['prj'] . '_' . $_POST['name_addon'] . '_' . date('Y-m-d') . '_' . $_COOKIE['login']);
        setSysMsg($result);
        header(URL_HEADER . 'administration.php?prj=' . $_GET['prj']);
        exit;
    case 'erasefile':
        // AJAX
        $filename = './export/' . $_GET['prj'] . '/' . $_GET['file'];
        if (file_exists($filename)) {
            unlink($filename);
        }
        echo 'ok';
        exit;
    case 'saveui':
        // user interface in "ui.ini"
        require('./code/misc.php');
        file_put_contents('./html/' . $_GET['prj'] . '/ui.ini', stripslashes($_POST['ui']), LOCK_EX);
        $newUi = parse_ini_file('./html/_default/ui.ini', TRUE);
        $prjUi = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);
        updateIniArray($newUi, $prjUi);
        updateIniFile($_GET['prj'], $newUi);
        // linguistic resources panel - langlinks.div.html
        file_put_contents('./html/' . $_GET['prj'] . '/langlinks.div.html', stripslashes($_POST['langlinks']), LOCK_EX);
        setSysMsg('ok');
        header(URL_HEADER . 'administration.php?prj=' . $_GET['prj']);
        exit;
    case 'updatetags':
        $content = trim(stripslashes(strip_tags($_POST['tags'])));
        file_put_contents('./html/' . $_GET['prj'] . '/tags.list.txt', $content, LOCK_EX);
        // create system files
        $jsVars = '// JavaScript Document' . PHP_EOL;
        $lines = explode(PHP_EOL, str_replace("'", "\\'", $content));
        $nbLines = count($lines);
        $isEmpty = TRUE;
        for ($i = 0;  $i < $nbLines;  $i++) {
            $line = trim($lines[$i]);
            if (preg_match('`^__([a-z]+)__$`', $line, $matches)) {
                // new section
                if (!$isEmpty) {
                    $jsVars = rtrim($jsVars, ',');
                    $jsVars .= '];' . PHP_EOL;
                }
                $jsVars .= 'var ' . $matches[1] . 'Values = [';
                $isEmpty = FALSE;
            }
            else {
                // new var
                if (strpos($line, ' = ') !== FALSE) {
                    $elems = explode(' = ', $line);
                    $jsVars .= "['" . $elems[1] . "','" . $elems[0] . "'],";
                }
                elseif ($line != '' and $line{0} != ';') {
                    $jsVars .= "['" . $line . "',''],";
                } 
            }
        }
        $jsVars .= '];' . PHP_EOL;
        file_put_contents('./html/' . $_GET['prj'] . '/tags.js', $jsVars, LOCK_EX);
        setSysMsg('ok');
        header(URL_HEADER . 'administration.php?prj=' . $_GET['prj']);
        exit;
    case 'updatesettings':        
        require('./code/misc.php');
        // build array of vars
        $project = array();
        // flags
        $project['flagtype'] = $_POST['flagtype'];
        $project['needaffix'] = ($_POST['needaffix'] != '') ? $_POST['needaffix'] : '-----';
        $project['circumfix'] = ($_POST['circumfix'] != '') ? $_POST['circumfix'] : '-----';
        $exceptionslist = explode(' ', $_POST['exceptionslist']);
        // dictionary
        $project['dictDirectEdition'] = (int) $_POST['dictDirectEdition'];
        $project['flexionsDepth'] = $_POST['flexionsdepth'];
        $project['restrictedEdit'] = (isset($_POST['restrictededit']) and $_POST['restrictededit'] == 'ON') ? TRUE : FALSE;
        // thesaurus
        $project['thesAllUsersAllowed'] = (isset($_POST['thesAllUsersAllowed']) and $_POST['thesAllUsersAllowed'] == 'ON') ? TRUE : FALSE;
        $project['thesLockDuration'] = (preg_match('`^[0-9]+$`', $_POST['lockDuration'])) ? $_POST['lockDuration'] : 3600;
        $project['thesExtendedSearch'] = (isset($_POST['extendedSearch']) and $_POST['extendedSearch'] == 'ON') ? TRUE : FALSE;
        $project['thesUpdateByEmail'] = (isset($_POST['updateByEmail']) and $_POST['updateByEmail'] == 'ON') ? TRUE : FALSE;
        // sub dictionaries
        $prjDic = array();
        $prjDicAbr = array();
        $lines = explode("\n", stripslashes(trim($_POST['subdicts'])));
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('`^([a-zA-Z0-9*_+@-]) = (.+) = ([^ ]+)`', $line, $matches)) {
                $prjDic[$matches[1]] = $matches[2];
                $prjDicAbr[$matches[1]] = $matches[3];
            }
        }
        // custom links
        $customlinks = explode("\n", stripslashes(trim($_POST['customlinks'])));
        foreach ($customlinks as $entry) {
            if (preg_match('`^[^ ]+ = http://.+`', $entry)) {
                list($name, $link) = explode(' = ', $entry);
                $prjCustomlinks[$name] = str_replace('&', '&amp;', trim(str_replace('"', '%22', $link)));;
            }
        }
        // active fields
        $activeFields = array();
        $activeFields['lemma'] = TRUE;
        $activeFields['flags'] = TRUE;
        $activeFields['lex'] = TRUE;
        $activeFields['dic'] = TRUE;
        $fields = array('po', 'is', 'ds', 'ts', 'ip', 'dp', 'tp', 'sp', 'pa', 'st', 'al', 'ph', 'sem', 'ety', 'ifq');
        foreach ($fields as $field) {
            $activeFields[$field] = (isset($_POST[$field])) ? TRUE : FALSE;
        }
        // create file
        $content = "<?php\n\n// do not edit this file! use the administration panel.\n\n";
        $content .= genPhpArray('project', $project);
        $content .= genPhpNumArray("project['exceptionslist']", $exceptionslist);
        $content .= genPhpArray('prjDic', $prjDic);
        $content .= genPhpArray('prjDicAbr', $prjDicAbr);
        $content .= genPhpArray('activeFields', $activeFields);
        $content .= genPhpArray('prjCustomlinks', $prjCustomlinks);
        $content .= '?' . '>';
        file_put_contents('./html/' . $_GET['prj'] . '/project_vars.php', $content, LOCK_EX);
        
        setSysMsg('ok');
        header(URL_HEADER . 'administration.php?prj=' . $_GET['prj']);
        exit;
    case 'sossql':
        require('./code/dbaccess-import-export.class.php');
        $dbaIE = new dbaccessIE($db);
        list($ok, $result) = $dbaIE->sosSQL('.inv', '', 'inv');
        if (!$ok) {
            echo $result;
        }
        else {
            echo 'done<br />';
            echo 'dic : ', $result[0], '<br />';
            echo 'prop: ', $result[1], '<br />';
        }
        exit;
}

if ($rank > 0) {
    setSysMsg('_noaccess');
    header(URL_HEADER);
    exit;
}

switch ($_REQUEST['cmd']) {
    case 'deluser':
        // AJAX
        list($ok, $result) = $dbaUsers->deleteUser($_GET['id_user']);
        echo $result;
        exit;
    default:
        setSysMsg($_REQUEST['cmd']);
        header(URL_HEADER . 'administration.php?prj=' . $_GET['prj']);
}
?>
