<?php

// do not edit this file! use the administration panel.

$project = array();
$project['flagtype'] = '1';
$project['needaffix'] = '-----';
$project['circumfix'] = '-----';
$project['dictDirectEdition'] = 3;
$project['flexionsDepth'] = '1';
$project['restrictedEdit'] = TRUE;
$project['thesAllUsersAllowed'] = TRUE;
$project['thesLockDuration'] = '3600';
$project['thesExtendedSearch'] = FALSE;
$project['thesUpdateByEmail'] = FALSE;

$project['exceptionslist'] = array();

$prjDic = array();
$prjDic['*'] = 'Common';
$prjDic['A'] = 'Dictionary A';
$prjDic['B'] = 'Dictionary B';

$prjDicAbr = array();
$prjDicAbr['*'] = '*';
$prjDicAbr['A'] = 'DictA';
$prjDicAbr['B'] = 'DictB';

$activeFields = array();
$activeFields['lemma'] = TRUE;
$activeFields['flags'] = TRUE;
$activeFields['lex'] = TRUE;
$activeFields['sem'] = FALSE;
$activeFields['ety'] = FALSE;
$activeFields['dic'] = TRUE;
$activeFields['ifq'] = FALSE;
$activeFields['po'] = TRUE;
$activeFields['is'] = TRUE;
$activeFields['ds'] = TRUE;
$activeFields['ts'] = TRUE;
$activeFields['ip'] = TRUE;
$activeFields['dp'] = TRUE;
$activeFields['tp'] = TRUE;
$activeFields['sp'] = FALSE;
$activeFields['pa'] = FALSE;
$activeFields['st'] = TRUE;
$activeFields['al'] = TRUE;
$activeFields['ph'] = TRUE;

$prjCustomlinks = array();
$prjCustomlinks['Here'] = 'http://www.dicollecte.org/dictionary.php?prj=en&amp;search=%s';
$prjCustomlinks['Webster'] = 'http://www.merriam-webster.com/dictionary/%s';
$prjCustomlinks['Cambridge'] = 'http://dictionary.cambridge.org/results.asp?searchword=%s&amp;x=0&amp;y=0';
$prjCustomlinks['Dictionary.com'] = 'http://dictionary.reference.com/browse/%s';
$prjCustomlinks['Google'] = 'http://www.google.com/search?q=%22%s%22';

?>
