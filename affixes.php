<?php

require('./config/config.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-flags.class.php');
require('./code/dbaccess-users.class.php');
require('./code/template.class.php');
require('./html/' . $_GET['prj'] . '/project_vars.php');

$ui = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);

$db = new Database();
$dbaUsers = new dbaccessUsers($db);
$dbaFlags = new dbaccessFlags($db);

$template = new Template();
$template->SetPageVars($_GET['prj'], 'affixes', $dbaUsers);
$template->SetTrunkVar('TAB_TITLE', $ui['tabs']['affixes']);
$template->SetTrunkVars($ui['affixes']);

if ($db->connx) {
    list($ok, $result) = $dbaFlags->listAllFlags($_GET['prj']);
    if ($ok) {
        if (count($result) > 0) {
            $allflags = '';
            foreach ($result as $data) {
                $allflags .= '<a href="affixes.php?prj=' . $_GET['prj'] . '&amp;flag=' . rawurlencode($data['flag']) . '">' . $data['flag'] . '</a> ';
            }
            $template->SetTrunkVar('ALLFLAGS', $allflags);
        }
    }
    else {
        $template->SetBranchVars('message', array('MESSAGE' => 'DATABASE ERROR'));
    }
    
    $rank = $dbaUsers->getUserRankFor($_GET['prj']);
    if ($rank <= 3) {
        $template->SetBranchVars('leftbuttons', $ui['flagLeftButtons']);
        $template->SetBranchVars('newflag', $ui['flagNew']);
    }
    
    // flag and its affixes
    if (isset($_GET['flag'])) {
        function str_pad_u8($input, $pad_length) {
            $length = $pad_length - iconv_strlen($input, "UTF-8");
            if ($length > 0) {
                return $input . str_repeat(' ', $length);
            }
            else {
                return $input;
            }
        }
    
        $flag = rawurldecode($_GET['flag']);
        list($ok, $result) = $dbaFlags->selectFlag($_GET['prj'], $flag);
        if (!$ok) {
            setSysMsg('_dberror');
            header(URL_HEADER . 'affixes.php?prj=' . $_GET['prj']);
            exit;
        }
        if (count($result) > 0) {
            $flag = stripslashes($flag);
            if ($rank <= 3) {
                $template->SetBranchVars('rightbuttons', $ui['flagRightButtons']);
                $template->UpdateBranchVars('rightbuttons', array('FLAGNAME' => $flag,
                                                                  'FLAGURL' => rawurlencode($flag)));
                $template->SetBranchVars('editflag', $ui['flagEdit']);
                $flagrules = '';
            }
            $i = 0;
            foreach ($result as $data) {
                if ($i == 0) {
                    $afftype = ($data['afftype'] == 'P') ? $ui['flagTypeVars']['prefix'] : $ui['flagTypeVars']['suffix'];
                    $mix = ($data['mix'] == 't') ? $ui['flagTypeVars']['mixyes'] : $ui['flagTypeVars']['mixno'];
                    $template->SetbranchVars('flag', $ui['flag']);
                    $template->UpdateBranchVars('flag', array('NAME' => $data['flag'],
                                                              'TYPE' => $afftype,
                                                              'MIX' => $mix));
                    if ($rank <= 3) {
                        $linehead = ($data['afftype'] == 'P') ? 'PFX ' . $flag . ' ' : 'SFX ' . $flag . ' ';
                        $firstline = ($data['mix'] == 't') ? $linehead . 'Y ' : $linehead . 'N ' ;
                    }
                }
                if ($data['flags'] != '') $flags = '/' . $data['flags']; else $flags = '';
                $dictlbl = (isset($prjDicAbr[$data['dic']])) ? $prjDicAbr[$data['dic']] : 'error!';
                $dictcell = ($data['dic'] != '*') ? '<samp>' . $dictlbl . '</samp>' : $dictlbl;
                $morph = '';
                if ($data['po'] != '') { $morph .= $dbaFlags->fieldToHunspell('po', $data['po']); }
                if ($data['is'] != '') { $morph .= $dbaFlags->fieldToHunspell('is', $data['is']); }
                if ($data['ds'] != '') { $morph .= $dbaFlags->fieldToHunspell('ds', $data['ds']); }
                if ($data['ts'] != '') { $morph .= $dbaFlags->fieldToHunspell('ts', $data['ts']); }
                if ($data['ip'] != '') { $morph .= $dbaFlags->fieldToHunspell('ip', $data['ip']); }
                if ($data['dp'] != '') { $morph .= $dbaFlags->fieldToHunspell('dp', $data['dp']); }
                if ($data['tp'] != '') { $morph .= $dbaFlags->fieldToHunspell('tp', $data['tp']); }
                if ($data['sp'] != '') { $morph .= $dbaFlags->fieldToHunspell('sp', $data['sp']); }
                $template->SetBranchVars('flag.rule', array('CUT' => $data['cut'],
                                                            'ADD' => $data['add'],
                                                            'FLAGS' => $flags,
                                                            'COND' => $data['cond'],
                                                            'GRAMM' => $morph,
                                                            'DIC' => $dictcell));
                if ($rank <= 3) {
                    $cut = ($data['cut'] != '') ? $data['cut'] : '0';
                    $add = ($data['add'] != '') ? $data['add'] : '0';
                    $flagrules .= $linehead . '  '. str_pad_u8($cut, 16) . ' ' . str_pad_u8($add . $flags, 24) . ' ' . str_pad_u8($data['cond'], 16) . ' '
                                . $morph . ' di:' . $data['dic'];
                    $flagrules .= ($data['comment'] != '') ? ' #' . $data['comment'] . PHP_EOL : PHP_EOL;
                }
                $i = $i + 1;
            }
            $template->SetTrunkVars(array('NBRULES' => $i, 'RULES' => $ui['flag']['RULES']));
            if ($rank <= 3) {
                $firstline .= $i . "\n";
                $flagrules = $firstline . $flagrules;  
                $template->UpdateBranchVars('editflag', array('FLAGRULES' => $flagrules));
            }
        }
    }
    
    // entry and flexions
    if (isset($_POST['entry']) and strpos($_POST['entry'], '/') !== FALSE) {
        $entry = stripslashes($_POST['entry']);
        list($lemma, $flags) = preg_split('`/`', $entry, 2);
        if ($lemma != '') {
            require('./html/' . $_GET['prj'] . '/project_vars.php');
            require('./code/entry.class.php');
            $data = array('lemma' => $lemma, 'flags' => $flags);
            $entry = new Entry($data);
            $entry->createFlexions($dbaFlags, $_GET['prj'], $project, 0, 2);
            $template->SetBranchVars('flexionslist', $ui['myEntry']);
            $template->UpdateBranchVars('flexionslist', array('LEMMA' => $lemma,
                                                              'FLAGS' => $flags,
                                                              'NBFLEXIONS' => count($entry->flexions)));
            foreach ($entry->flexions as $flexion) {
                $template->SetBranchVars('flexionslist.flexion', array('LEMMA' => $flexion[0],
                                                                       'MORPH' => $flexion[1]));
            }
        }
    }
}

// display
$template->Grow('./html/div.head.tpl.html');
$template->Grow('./html/affixes.div.tpl.html');
$template->Grow('./html/div.foot.tpl.html');

?>
