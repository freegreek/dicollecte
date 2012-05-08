<?php

require('./config/config.php');
require('./config/img_vars.php');
require('./code/init.php');
require('./code/dbaccess.class.php');
require('./code/dbaccess-users.class.php');
require('./code/dbaccess-propositions.class.php');
require('./code/template.class.php');
require('./code/displaytable.php');
require('./code/pagination.class.php');
require('./html/' . $_GET['prj'] . '/project_vars.php');

$ui = parse_ini_file('./html/' . $_GET['prj'] . '/ui.ini', TRUE);

$tab = (isset($_GET['tab']) and ($_GET['tab'] == 'E' or $_GET['tab'] == 'R' or $_GET['tab'] == 'T')) ? $_GET['tab'] : 'E';

$db = new Database();
$dbaUsers = new dbaccessUsers($db);
$dbaPropositions = new dbaccessPropositions($db);

$template = new Template();
$template->SetPageVars($_GET['prj'], 'propositions', $dbaUsers);
$template->SetTrunkVar('TAB_TITLE', $ui['tabs'][$tab]);

$rank = $dbaUsers->getUserRankFor($_GET['prj']);

displaytable($dbaPropositions, $_GET['prj'], $tab, NULL, $template);

// display
$template->Grow('./html/div.head.tpl.html');
$template->Grow('./html/propositions.div.tpl.html');
$template->Grow('./html/div.foot.tpl.html');

?>
