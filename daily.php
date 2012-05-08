#!/usr/bin/php
<?php

$prj = 'fr';

require('./httpdocs/config/config.php');
require('./httpdocs/code/dbaccess.class.php');
require('./httpdocs/code/dbaccess-import-export.class.php');
require('./httpdocs/html/' . $prj . '/project_vars.php');

setlocale(LC_TIME, 'fr_FR.UTF8');

## create source dictionary from the db

$db = new Database();
$dbaIE = new dbaccessIE($db);

$selectedDict = TRUE; // all dictionaries are selected
$isNoDubiousEntries = TRUE;
$isHunspellFields = TRUE;
$isDicollecteFields = TRUE;

$dicFilename = 'fr-dicollecte-daily.dic';
$affFilename = 'fr-dicollecte-daily.aff';
list($ok, $result) = $dbaIE->exportDictionary($prj, $selectedDict, $isNoDubiousEntries, $isHunspellFields, $isDicollecteFields, $dicFilename, './httpdocs');
if (!$ok) {
    $db->errorReport($prj, 'daily export dictionary - ' . $result);
    exit;
}
list($ok, $result) = $dbaIE->exportAffixes($prj, $selectedDict, $isHunspellFields, $isDicollecteFields, $affFilename, './httpdocs');
if (!$ok) {
    $db->errorReport($prj, 'daily export dictionary - ' . $result);
    exit;
}
$db->close();


## build dictionaries from the source

// count subdictionaries entries
$hSource = @fopen('./httpdocs/export/'.$prj.'/'.$dicFilename, 'r');
if (!$hSource) {
    $db->errorReport($prj, 'daily export dictionary - cannot read: ' . $dicFilename);
    exit;
}
$nErrors = 0;
$aSubDict = $prjDic;
$line = fgets($hSource);
$line = fgets($hSource);
while (!feof($hSource)) {
    preg_match('` di:(.)`', $line, $matches);
    if (isset($matches[1])) {
        $aSubDict[$matches[1]] += 1;
    }
    else {
        $nErrors += 1;
    }
    $line = fgets($hSource);
}
fclose($hSource);

// create dictionaries files from source
$hSource = @fopen('./httpdocs/export/'.$prj.'/'.$dicFilename, 'r');
if (!$hSource) {
    $db->errorReport($prj, 'daily export dictionary - cannot read: ' . $dicFilename);
    exit;
}

$basename = $prj.'-dicollecte-daily-%s-v'.date('Y-m-d').'.dic';
$dicClasRef = sprintf($basename, 'classique-reforme1990');
$dicModerne = sprintf($basename, 'moderne');
$dicClassique = sprintf($basename, 'classique');
$dicReforme = sprintf($basename, 'reforme1990');
$hDicClasRef = @fopen('./httpdocs/export/'.$prj.'/'.$dicClasRef, 'w');
$hDicModerne = @fopen('./httpdocs/export/'.$prj.'/'.$dicModerne, 'w');
$hDicClassique = @fopen('./httpdocs/export/'.$prj.'/'.$dicClassique, 'w');
$hDicReforme = @fopen('./httpdocs/export/'.$prj.'/'.$dicReforme, 'w');
if (!$hDicClasRef or !$hDicModerne or !$hDicClassique or !$hDicReforme) {
    $db->errorReport($prj, 'daily export dictionary - cannot open destination files (dictionaries)');
    exit;
}

// number of lines
$line = fgets($hSource);
fwrite($hDicClasRef, $line);
fwrite($hDicModerne, (string) ($aSubDict['*'] + $aSubDict['M'])); fwrite($hDicModerne, PHP_EOL);
fwrite($hDicClassique, (string) ($aSubDict['*'] + $aSubDict['M'] + $aSubDict['V'])); fwrite($hDicClassique, PHP_EOL);
fwrite($hDicReforme, (string) ($aSubDict['*'] + $aSubDict['R'])); fwrite($hDicReforme, PHP_EOL);
// parse
$line = fgets($hSource);
while (!feof($hSource)) {
    preg_match('` di:(.)`', $line, $matches);
    $sline = preg_replace('` (lx|et|se|di|fq|id):.*$`', '', $line);
    switch ($matches[1]) {
        case '*':   fwrite($hDicClasRef, $sline);
                    fwrite($hDicModerne, $sline);
                    fwrite($hDicClassique, $sline);
                    fwrite($hDicReforme, $sline);
                    break;
        case 'M':   fwrite($hDicClasRef, $sline);
                    fwrite($hDicModerne, $sline);
                    fwrite($hDicClassique, $sline);
                    break;
        case 'R':   fwrite($hDicClasRef, $sline);
                    fwrite($hDicReforme, $sline);
                    break;
        case 'V':   fwrite($hDicClasRef, $sline);
                    fwrite($hDicClassique, $sline);
                    break;
        case 'A':   fwrite($hDicClasRef, $sline);
                    break;
        default:    $db->errorReport($prj, 'daily export dictionary - error: '.$line);
    }
    $line = fgets($hSource);
}
fclose($hSource);
fclose($hDicClasRef);
fclose($hDicModerne);
fclose($hDicClassique);
fclose($hDicReforme);


## create affixes files from source
    
$hSource = @fopen('./httpdocs/export/'.$prj.'/'.$affFilename, 'r');
if (!$hSource) {
    $db->errorReport($prj, 'daily export dictionary - cannot read: ' . $affFilename);
    exit;
}

$basename = $prj.'-dicollecte-daily-%s-v'.date('Y-m-d').'.aff';
$affClasRef = sprintf($basename, 'classique-reforme1990');
$affModerne = sprintf($basename, 'moderne');
$affClassique = sprintf($basename, 'classique');
$affReforme = sprintf($basename, 'reforme1990');
$hAffClasRef = @fopen('./httpdocs/export/'.$prj.'/'.$affClasRef, 'w');
$hAffModerne = @fopen('./httpdocs/export/'.$prj.'/'.$affModerne, 'w');
$hAffClassique = @fopen('./httpdocs/export/'.$prj.'/'.$affClassique, 'w');
$hAffReforme = @fopen('./httpdocs/export/'.$prj.'/'.$affReforme, 'w');
if (!$hAffClasRef or !$hAffModerne or !$hAffClassique or !$hAffReforme) {
    $db->errorReport($prj, 'daily export dictionary - cannot open destination files (affixes)');
    exit;
}

// affixes header
$affheaderfile = './httpdocs/html/'.$prj.'/affixes-header.aff';
$affHeader = '# AFFIXES DU DICTIONNAIRE FRANÇAIS «%s» v' . date('Y-m-d') . PHP_EOL
           . '# par Olivier R. -- licences LGPL, GPL, MPL' . PHP_EOL
           . '# Généré le ' . date('d F Y à H:i') . PHP_EOL
           . '# Pour améliorer le dictionnaire, allez sur http://www.dicollecte.org/' . PHP_EOL . PHP_EOL;
$affHeader .= (is_file($affheaderfile)) ? file_get_contents($affheaderfile) . PHP_EOL : 'SET UTF-8' . PHP_EOL . PHP_EOL;
fwrite($hAffClasRef, sprintf($affHeader, 'Classique & Réforme 1990'));
fwrite($hAffModerne, sprintf($affHeader, 'Moderne'));
fwrite($hAffClassique, sprintf($affHeader, 'Classique'));
fwrite($hAffReforme, sprintf($affHeader, 'Réforme 1990'));

// parse
$line = fgets($hSource);
while (!feof($hSource)) {
    if (preg_match('`^[PS]FX`', $line)) {
        if (preg_match('`^[PS]FX .. [YN] [0-9]+`', $line)) {
            // new flag
            $flagHeader = preg_replace('` [0-9].*$`', ' %d', $line);
        }
        else {
            // rule
            preg_match('` di:(.)`', $line, $matches);
            $sline = preg_replace('` (di:|#).*$`', '', $line);
            switch ($matches[1]) {
                case '*':   $flagClasRef .= $sline; $nRulesClasRef += 1;
                            $flagModerne .= $sline; $nRulesModerne += 1;
                            $flagClassique .= $sline; $nRulesClassique += 1;
                            $flagReforme .= $sline; $nRulesReforme += 1;
                            break;
                case 'M':   $flagClasRef .= $sline; $nRulesClasRef += 1;
                            $flagModerne .= $sline; $nRulesModerne += 1;
                            $flagClassique .= $sline; $nRulesClassique += 1;
                            break;
                case 'R':   $flagClasRef .= $sline; $nRulesClasRef += 1;
                            $flagReforme .= $sline; $nRulesReforme += 1;
                            break;
                case 'V':   $flagClasRef .= $sline; $nRulesClasRef += 1;
                            $flagClassique .= $sline; $nRulesClassique += 1;
                            break;
                case 'A':   $flagClasRef .= $sline; $nRulesClasRef += 1;
                            break;
                default:    $db->errorReport($prj, 'daily export dictionary - error: '.$line);
            }
        }
    }
    else {
        if ($nRulesClasRef > 0) {
            fwrite($hAffClasRef, sprintf($flagHeader, $nRulesClasRef));
            fwrite($hAffClasRef, $flagClasRef);
            fwrite($hAffModerne, sprintf($flagHeader, $nRulesModerne));
            fwrite($hAffModerne, $flagModerne);
            fwrite($hAffClassique, sprintf($flagHeader, $nRulesClassique));
            fwrite($hAffClassique, $flagClassique);
            fwrite($hAffReforme, sprintf($flagHeader, $nRulesReforme));
            fwrite($hAffReforme, $flagReforme);
            $flagHeader = '';
            $flagClasRef = ''; $nRulesClasRef = 0;
            $flagModerne = ''; $nRulesModerne = 0;
            $flagClassique = ''; $nRulesClassique = 0;
            $flagReforme = ''; $nRulesReforme = 0;
        }
    }
    $line = fgets($hSource);
}

fclose($hSource);
fclose($hAffClasRef);
fclose($hAffModerne);
fclose($hAffClassique);
fclose($hAffReforme);


## copy and zip files
$zipfile = './httpdocs/download/'.$prj.'/hunspell-'.$prj.'-dicollecte-daily.zip';
unlink($zipfile);
$zip = new ZipArchive;
$res = $zip->open($zipfile, ZipArchive::CREATE);
if ($res === TRUE) {
    // full dictionary
    //$zip->addFile('./httpdocs/export/'.$prj.'/'.$dicFilename, $dicFilename);
    //$zip->addFile('./httpdocs/export/'.$prj.'/'.$affFilename, $affFilename);
    // others
    $zip->addFile('./httpdocs/export/'.$prj.'/'.$dicClasRef, $dicClasRef);
    $zip->addFile('./httpdocs/export/'.$prj.'/'.$affClasRef, $affClasRef);
    $zip->addFile('./httpdocs/export/'.$prj.'/'.$dicModerne, $dicModerne);
    $zip->addFile('./httpdocs/export/'.$prj.'/'.$affModerne, $affModerne);
    $zip->addFile('./httpdocs/export/'.$prj.'/'.$dicClassique, $dicClassique);
    $zip->addFile('./httpdocs/export/'.$prj.'/'.$affClassique, $affClassique);
    $zip->addFile('./httpdocs/export/'.$prj.'/'.$dicReforme, $dicReforme);
    $zip->addFile('./httpdocs/export/'.$prj.'/'.$affReforme, $affReforme);
    $zip->addFile('./httpdocs/html/'.$prj.'/README_fr_daily.txt', 'README_fr_daily.txt');
    $zip->close();
}
else {
    $db->errorReport($prj, 'daily export dictionary - zipfile cannot be created');
}
unlink('./httpdocs/export/'.$prj.'/'.$dicClasRef);
unlink('./httpdocs/export/'.$prj.'/'.$affClasRef);
unlink('./httpdocs/export/'.$prj.'/'.$dicModerne);
unlink('./httpdocs/export/'.$prj.'/'.$affModerne);
unlink('./httpdocs/export/'.$prj.'/'.$dicClassique);
unlink('./httpdocs/export/'.$prj.'/'.$affClassique);
unlink('./httpdocs/export/'.$prj.'/'.$dicReforme);
unlink('./httpdocs/export/'.$prj.'/'.$affReforme);

$db->errorReport($prj, 'daily export dictionary - zipfile created');

?>