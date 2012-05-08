<?php

function displaytable ($dbaPropositions, $prj, $tab, $id_user, $template) {
    global $ui;
    global $uiImgActionSmall;
    global $uiImgValueSmall;
    global $activeFields;
    global $prjDic;
    global $rank;
    switch ($tab) {
        case 'E': $label = $ui['tableVars']['toeval']; $note = $ui['tableVars']['toevalnote']; $image = './img/icon_notes.png'; break;
        case 'R': $label = $ui['tableVars']['rejprop']; $note = $ui['tableVars']['rejpropnote']; $image = './img/icon_forbidden.png'; break;
        case 'T': $label = $ui['tableVars']['trash']; $note = $ui['tableVars']['trashnote']; $image = './img/icon_trash.png'; break;
        default : $label = '## error ##';
    }
    
    // sort
    $orderby = (isset($_GET['order'])) ? $_GET['order'] : 'D';
    $urlbase = ($id_user) ? 'member.php?prj=' . $prj . '&amp;tab=' . $tab . '&amp;id_user=' . $id_user : 'propositions.php?prj=' . $prj . '&amp;tab=' . $tab;
    if ($orderby != 'L') { $ui['tableFieldsLabel']['LEMMA'] = '<a href="'.$urlbase.'&amp;order=L">' . $ui['tableFieldsLabel']['LEMMA'] . '</a>'; }
    if ($orderby != 'P') { $ui['tableFieldsLabel']['PRIORITY'] = '<a href="'.$urlbase.'&amp;order=P">' . $ui['tableFieldsLabel']['PRIORITY'] . '</a>'; }
    if ($orderby != 'V') { $ui['tableFieldsLabel']['VALUE'] = '<a href="'.$urlbase.'&amp;order=V">' . $ui['tableFieldsLabel']['VALUE'] . '</a>'; }
    if ($orderby != 'D') { $ui['tableFieldsLabel']['DATE'] = '<a href="'.$urlbase.'&amp;order=D">' . $ui['tableFieldsLabel']['DATE'] . '</a>'; }

    // table
    $template->SetBranchVars('table', array('IMG' => $image,
                                            'NOTE' => $note,
                                            'LABEL' => $label));
    $template->UpdateBranchVars('table', $ui['iconsLabel']);
    
    if ($dbaPropositions->db->connx) {
        $nbEntriesByPage = 200;
        $script = ($id_user) ? 'member.php' : 'propositions.php';
        $oPg = new Pagination($script, 'page', $nbEntriesByPage);
    
        list($ok, $result, $nbOccur) = $dbaPropositions->selectTable($prj, $tab, $orderby, $id_user, $oPg->getOffset(), $nbEntriesByPage);
        if ($ok) {
            if ($nbOccur > 0) {
                // pagination
                if ($nbOccur > $nbEntriesByPage) {
                    $template->UpdateBranchVars('table', array('PAGES' => $oPg->createLinks($nbOccur)));
                }
                // display
                $template->SetBranchVars('table.list', $ui['tableFieldsLabel']);
                $prioritytags = array('', '•', '••', '•••');
                foreach ($result as $data) {
                    $lemmacell = '<a href="proposition.php?prj=' . $prj . '&amp;id=' . $data['id_prop'] . '">' . $data['lemma'] . '</a>';
                    if ($data['flags'] != '') { $lemmacell .= '<dfn>/</dfn><samp>' . $data['flags'] . '</samp>'; }
                    $dictlbl = (isset($prjDic[$data['dic']])) ? $prjDic[$data['dic']] : 'error!';
                    $dictcell = ($data['dic'] != '*') ? '<samp>' . $dictlbl . '</samp>' : $dictlbl;
                    $nbcomments = ($data['nbcomments'] != 0) ? $data['nbcomments'] : '';
                    $imgvalue = ($data['value'] != '?') ? $uiImgValueSmall[$data['value']] : '';
                    $morph = $data['po'];
                    $lexsem = ($data['sem'] == '') ? $data['lex'] : $data['lex'] . ' ' . $data['sem'];
                    if ($activeFields['is'] and $data['is'] != '') { $morph .= ' ' . $data['is']; }
                    if ($activeFields['ds'] and $data['ds'] != '') { $morph .= ' ' . $data['ds']; }
                    if ($activeFields['ts'] and $data['ts'] != '') { $morph .= ' ' . $data['ts']; }
                    if ($activeFields['ip'] and $data['ip'] != '') { $morph .= ' ' . $data['ip']; }
                    if ($activeFields['dp'] and $data['dp'] != '') { $morph .= ' ' . $data['dp']; }
                    if ($activeFields['tp'] and $data['tp'] != '') { $morph .= ' ' . $data['tp']; }
                    if ($activeFields['sp'] and $data['sp'] != '') { $morph .= ' ' . $data['sp']; }
                    $template->SetBranchVars('table.list.prop', array('ACTION' => $uiImgActionSmall[$data['action']], 
                                                                      'LEMMA' => $lemmacell,
                                                                      'GRAMM' => $morph,
                                                                      'LEX' => $lexsem,
                                                                      'DICT' => $dictcell,
                                                                      'NBCOMMENTS' => $nbcomments,
                                                                      'PRIORITY' => $prioritytags[$data['prio']],
                                                                      'VALUE' => $imgvalue));
                }
                
                // actions
                if ($rank <= 2) {
                    switch ($tab) {
                        case 'E':
                            $template->SetBranchVars('table.actionsbutton', $ui['propsAction']);
                            $template->SetBranchVars('table.actions', array());
                            $template->SetBranchVars('table.actions.actionvalid', $ui['propsActionValid']);
                            if ($id_user) {
                                $template->UpdateBranchVars('table.actions.actionvalid', array('CPLIDUSER' => '&amp;id_user='.$id_user));
                            }
                            break;
                        case 'T':
                            if (!$id_user) {
                                $template->SetBranchVars('script', array('FILENAME' => './js/prompt.js'));
                                $template->SetBranchVars('table.actionsbutton', $ui['propsAction']);
                                $template->SetBranchVars('table.actions', array());
                                $template->SetBranchVars('table.actions.actiondel', $ui['propsActionDel']);
                            }
                            break;
                    }
                }
            }
            else {
                $template->SetBranchVars('table.msg', array('MSGHEAD' => $ui['tableVars']['nothing'],
                                                            'MESSAGE' => $ui['tableVars']['void']));
            }
        }
        else {
            $template->SetBranchVars('table.msg', array('MSGHEAD' => $ui['sysMsg']['_dberror']));
        }
    }
    else {
        $template->SetBranchVars('table.msg', array('MSGHEAD' => $ui['sysMsg']['_dberror'],
                                                    'MESSAGE' => $ui['sysMsg']['_nobase']));
    }
}

?>
