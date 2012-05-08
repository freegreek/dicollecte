<?php

class dbaccessInstaller {
    /*
        This object is used to build and update the database.
        Modified tables: all
    */
    
    private $db;
    public $lTables = array();    // list of tables (string)
    public $lProjects = array();  // list of projects (string)
    public $projects = array();   // list of projects (objects) from table projects
    public $schemas = array();    // list of tables schemas (objects)
    
    /* PUBLIC */
    function __construct ($db) {
        $this->db = $db;
        $this->updateData();
    }
    
    // superadmin identification
    public function connectSuperAdmin ($login, $pw, $doSetCookies=FALSE) {
        $prefix = (in_array('users', $this->lTables)) ? '' : 'dicl_';
        try {
            $oQ = $this->db->connx->query("SELECT id_user, pw FROM {$prefix}users WHERE login = '$login'");
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport(FALSE, $e, 'connect as Admin');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        // unknown login ?
        if (count($result) == 0) {
            setcookie('login', '', 0);
            setcookie('pw', '', 0);
            session_destroy();
            return array(FALSE, '_nologin');
        }
        $data = $result[0];
        // is superadmin ?
        if ($data['id_user'] != 1) {
            setcookie('login', '', 0);
            setcookie('pw', '', 0);
            session_destroy();
            return array(FALSE, '_noaccess');
        }
        // password test ?
        if ($data['pw'] != $pw) {
            setcookie('login', '', 0);
            setcookie('pw', '', 0);
            session_destroy();
            return array(FALSE, '_pwerror');
        }
        // set session vars
        $_SESSION['id_user'] = 1;
        if ($doSetCookies) {
            setcookie('login', $login, time()+10368000);
            setcookie('pw', $pw, time()+10368000);
        }
        return array(TRUE, 'idok');
    }
    
    
    /* ANALYZE THE DB */
    
    function updateData() {
        $this->listTables();
        $this->getProjects();
        $this->getSchemasOfTables();
        //$this->db->record(print_r($this->schemas, TRUE));
    }
    
    // retrieve the list of all existing tables (except those beginning by "pg_" and "sql_")
    public function listTables () {
        try {
            $oQ = $this->db->connx->query("SELECT tablename FROM pg_tables WHERE tablename !~ '^(pg_|sql_)'");
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport(FALSE, $e, 'list tables');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        $this->lTables = array();
        foreach ($result as $data) {
            $this->lTables[] = $data['tablename'];
        }
    }
    
    public function getProjects () {
        $this->projects = array();
        $this->lProjects = array();
        if (in_array('dicl_projects', $this->lTables)) {
            try {
                $oQ = $this->db->connx->query('SELECT prj, label, version, hidden, closed FROM dicl_projects');
            }
            catch (PDOException $e) {
                $this->db->dbErrorReport(FALSE, $e, 'list projects');
            }
            $result = $oQ->fetchAll(PDO::FETCH_OBJ);
            foreach ($result as $data) {
                $this->projects[] = $data;
                $this->lProjects[] = $data->prj;
            }
        }
    }
    
    // retrieve the table of columns of a database table
    public function getSchemasOfTables () {
        try {
            $oQ = $this->db->connx->query("SELECT table_name, column_name, data_type, character_maximum_length, is_nullable, column_default FROM information_schema.columns WHERE table_name ~ '^dicl_';");
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport(FALSE, $e, 'list schemas');
        }
        $this->schemas = array();
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as $data) {
            $this->schemas[$data['table_name']][$data['column_name']] = array('data_type' => $data['data_type'],
                                                                              'character_maximum_length' => $data['character_maximum_length'],
                                                                              'is_nullable' => ($data['is_nullable'] == 'YES') ? TRUE : FALSE,
                                                                              'column_default' => $data['column_default']);
        }

    }
    
    // does all the project tables exist in the array tables ?
    public function isProjectInstalled ($prj, $tablesuffixes, $isOld=FALSE) {
        if (!$isOld) {
            // we look for the tables of the project
            foreach ($tablesuffixes as $suffix) {
                if (!in_array('dicl_' . $prj . $suffix, $this->lTables)) {
                    return FALSE;
                }
            }
        }
        else {
            // we look for old tables of the project
            foreach ($tablesuffixes as $suffix) {
                if (!in_array($prj . $suffix, $this->lTables)) {
                    return FALSE;
                }
            }
        }
        return TRUE;
    }
    
    
    /* CREATION AND UPDATE OF THE NEW DATABASE SYSTEM */
    
    // create tables as described in the file dbtables.php
    public function createTables ($dbTables, $prj = '', $label = '') {
        $this->db->connx->beginTransaction();
        try {
            foreach ($dbTables as $tablesuffix => $table) {
                if (!in_array('dicl_'.$prj.$tablesuffix, $this->lTables)) {
                    $qQuery = $this->buildCreationTableQuery($tablesuffix, $table, $prj);
                    $this->db->record($qQuery);
                    $this->db->connx->exec($qQuery);
                }
            }
            if ($prj != '' and !in_array($prj, $this->lProjects)) {
                $version = DB_VERSION;
                $this->db->connx->exec("INSERT INTO dicl_projects (prj, label, version) VALUES ('$prj', '$label', $version)");
                $this->db->connx->exec("INSERT INTO dicl_{$prj}_members (id_member, rk) VALUES (1, 0)");
            }
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport(FALSE, $e, 'create tables');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    // create the administrator account
    public function createAdminAccount ($pw) {
        $cryptedpw = md5($pw);
        try {
            $this->db->connx->exec("INSERT INTO dicl_users (login, pw, name, email) VALUES ('Admin', '$cryptedpw', '[?]', '[?]')");
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport(FALSE, $e, 'create admin account');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    // insert the project in the database
    public function insertProject ($prj, $label, $version) {
        try {
            $this->db->connx->exec("INSERT INTO dicl_projects (prj, label, version) VALUES ('$prj', '$label', $version)");
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport(FALSE, $e, 'insert project in projects table');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    // destroy all tables of a project
    public function destroyTablesOfPrj ($dbTables, $prj) {
        $qQuery = 'DROP TABLE IF EXISTS ';
        $i = 0;
        foreach ($dbTables as $tablename => $table) {
            if ($i > 0) $qQuery .= ', ';
            $qQuery .= 'dicl_' . $prj . $tablename;
            $i++;
        }
        $qQuery .= ' CASCADE';       
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec($qQuery);
            $this->db->connx->exec("DELETE FROM dicl_projects WHERE prj = '$prj'");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport(FALSE, $e, 'destroy tables');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    // update existing common tables as described in the file dbtables.php
    public function updateCommonTables ($dbTables) {
        $ok = TRUE;
        foreach ($dbTables as $tablesuffix => $table) {
            $action = $this->buildUpdateTableQuery($tablesuffix, $table);
            if ($action != '') {
                $qAlter = 'ALTER TABLE dicl_' . $tablesuffix . "\n" . $action;
                $this->db->record($qAlter . ";\n");
                try {
                    $this->db->connx->exec($qAlter);
                }
                catch (PDOException $e) {
                    $this->db->dbErrorReport(FALSE, $e, 'alter table: dicl_' . $tablesuffix);
                    $ok = FALSE;
                }
                $action = '';
            }
        }
        if ($ok) {
            return array(TRUE, 'ok');
        }
        else {
            return array(FALSE, '_dberror');
        }
    }
    
    // update existing tables of a project as described in the file dbtables.php
    public function updatePrjTables ($prj, $dbTables, $currentVersion) {
        $this->listTables();
        $this->getSchemasOfTables();
        $ok = TRUE;
        foreach ($dbTables as $tablesuffix => $table) {
            $action = $this->buildUpdateTableQuery($tablesuffix, $table, $prj);
            if ($action != '') {
                $qAlter = 'ALTER TABLE dicl_' . $prj . $tablesuffix . "\n" . $action;
                $this->db->record($qAlter . ";\n");
                try {
                    $this->db->connx->exec($qAlter);
                }
                catch (PDOException $e) {
                    $this->db->dbErrorReport(FALSE, $e, 'alter tables in project ' . $prj);
                    $ok = FALSE;
                }
                
                $action = '';
            }
        }
        if (!$ok) {
            return array(FALSE, '_dberror');
        }
        
        // update content of tables
        $this->db->connx->beginTransaction();
        try {
            if ($currentVersion < 1) {
                // set prop_user to all messages
                $this->db->connx->exec('UPDATE dicl_'.$prj.'_comments c SET prop_user = p.id_user  FROM dicl_'.$prj.'_prop p WHERE c.id_prop = p.id_prop');
                $this->db->connx->exec('UPDATE dicl_users SET email = FALSE  WHERE email LIKE '. "'[%'");
                $this->db->connx->exec('UPDATE dicl_users SET email = '."''".'  WHERE email LIKE '. "'[%'");
                
                // notes id is now id_entry and not a lemma
                /*
                UPDATE dicl_fr_notes n  SET id_entry = d.id_entry  FROM dicl_fr_dic d WHERE n.lemma = d.lemma;
                DELETE FROM dicl_fr_notes WHERE id_entry IS NULL;
                CREATE TEMP TABLE tmpnotes ON COMMIT DROP AS (SELECT id_entry, count(id_note) AS nbnotes FROM dicl_fr_notes GROUP BY id_entry);
                UPDATE dicl_fr_dic d SET nbnotes = 0;
                UPDATE dicl_fr_dic d SET nbnotes = t.nbnotes  FROM tmpnotes t  WHERE d.id_entry = t.id_entry
                */
                
                // count current suggestions and validated ones
                /*
                BEGIN;
                CREATE TEMP TABLE tmpusersval ON COMMIT DROP AS
                (SELECT id_user, count(id_user) AS nbpropval FROM dicl_fr_prop WHERE tab = 'E' AND value = 'V' GROUP BY id_user);
                CREATE TEMP TABLE tmpusersev ON COMMIT DROP AS
                (SELECT id_user, count(id_user) AS nbpropev FROM dicl_fr_prop WHERE tab = 'E' GROUP BY id_user);
                UPDATE dicl_fr_members m SET nbpropval = t.nbpropval  FROM tmpusersval t  WHERE m.id_member = t.id_user;
                UPDATE dicl_fr_members m SET nbpropev = t.nbpropev  FROM tmpusersev t  WHERE m.id_member = t.id_user;
                COMMIT;
                */
            }
            $this->db->connx->exec('UPDATE dicl_projects SET version = ' . DB_VERSION . " WHERE prj = '$prj'");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport(FALSE, $e, 'version db content updates');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    
    /* PRIVATE */
    
    private function buildCreationTableQuery ($tablesuffix, $table, $prj) {
        // create tables
        $qCreate = 'CREATE TABLE dicl_' . $prj . $tablesuffix . " (\n";
        $i = 0;
        foreach ($table as $column => $params) {
            if ($i > 0) $qCreate .= ",\n";
            $qCreate .= '    ' . $this->buildColumnQuery($column, $params, $prj);
            $i++;
        }
        $qCreate .= "\n);\n";
        // create indexes
        foreach ($table as $column => $params) {
            if ($params[6]) {
                // CREATE INDEX dicl_[prj]_[table]_index_[column] ON dicl_[prj]_[table] ([column]);
                $qCreate .= 'CREATE INDEX dicl_' . $prj . $tablesuffix . '_index_' . $column . ' ON dicl_' . $prj . $tablesuffix . '(' . $column . ");\n";
            }
        }   
        return $qCreate;
    }
    
    private function buildUpdateTableQuery ($tablesuffix, $newSchemaOfTable, $prj='') {
        /*
            see dbtables.php
            
            newParams fields
            0   data_type,                 -- integer, character varying, etc.
            1   character_maximum_length,  -- must be 0 if cannot be defined
            2   is_nullable,               -- FALSE = NOT NULL
            3   column_default,            -- Value by default.
            4   creation_command           -- NULL or specify optional command at table creation
            5   conversion_spec            -- NULL or specify clause USING in ALTER TABLE xxxxx ALTER COLUMN xxxxxxx TYPE xxxxx [USING conversion_spec]
        */ 
        $tablename = 'dicl_' . $prj . $tablesuffix;
        $action = '';
        foreach ($newSchemaOfTable as $column => $newParams) {
            if (array_key_exists($column, $this->schemas[$tablename])) {
                // the column exists, update
                $curParams = $this->schemas[$tablename][$column];
                switch ($newParams[0]) {
                    case 'integer':
                    case 'character varying':
                    case 'character':
                    case 'smallint':
                    case 'boolean':
                        // column changes ?
                        if ($curParams['data_type'] != $newParams[0]) {
                            // change of data_type
                            if ($action != '') $action .= ",\n";
                            if ($newParams[3] != NULL) {
                                $action .= 'ALTER COLUMN "' . $column . '" DROP DEFAULT,' . "\n";
                                $action .= 'ALTER COLUMN "' . $column . '" TYPE ' . $newParams[0];
                                if ($newParams[5]) $action .= ' USING ' . $newParams[5];
                                $action .= ",\n" . 'ALTER COLUMN "' . $column . '" SET DEFAULT ' . $newParams[3];
                            }
                            else {
                                $action .= 'ALTER COLUMN "' . $column . '" DROP DEFAULT,' . "\n";
                                $action .= 'ALTER COLUMN "' . $column . '" TYPE ' . $newParams[0];
                            }
                        }
                        else {
                            if ($newParams[0] == 'character varying' and $curParams['character_maximum_length'] != $newParams[1]) {
                                if ($action != '') $action .= ",\n";
                                if ($newParams[3] != NULL) {
                                    $action .= 'ALTER COLUMN "' . $column . '" DROP DEFAULT,' . "\n";
                                    $action .= 'ALTER COLUMN "' . $column . '" TYPE ' . $newParams[0] . '(' . $newParams[1] . ')';
                                    if ($newParams[5]) $action .= ' USING ' . $newParams[5];
                                    $action .= ",\n" . 'ALTER COLUMN "' . $column . '" SET DEFAULT ' . $newParams[3];
                                }
                                else {
                                    $action .= 'ALTER COLUMN "' . $column . '" DROP DEFAULT,' . "\n";
                                    $action .= 'ALTER COLUMN "' . $column . '" TYPE ' . $newParams[0] . '(' . $newParams[1] . ')';
                                }
                            }
                        }
                        if ($curParams['is_nullable'] != $newParams[2]) {
                            if ($action != '') $action .= ",\n";
                            $action .= 'ALTER COLUMN "' . $column . '"';
                            $action .= ($newParams[2]) ?  ' DROP NOT NULL' : ' SET NOT NULL';
                        }
                        break;
                    default:
                        // no change!! serial or primary keys
                }
            }
            else {
                // the column does not exist, create it
                if ($action != '') $action .= ",\n";
                $action .= 'ADD COLUMN ' . $this->buildColumnQuery($column, $newParams, $prj);
            }
        }
        foreach ($this->schemas[$tablename] as $column => $curParams) {
            if (!array_key_exists($column, $newSchemaOfTable)) {
                // the column does not exist in the new scheme, delete it
                if ($action != '') $action .= ",\n";
                $action .= 'DROP COLUMN "' . $column . '"';
            }
        }
        return $action;
    }
    
    private function buildColumnQuery ($column, $params, $prj='') {
        /*
            $params[0] = data_type
            $params[1] = character_maximum_length
            $params[2] = is_nullable ? TRUE|FALSE
            $params[3] = column_default
            $params[4] = creation add-on
            $params[5] = conversion_specification (not used in this case)
            $params[6] = create an index? TRUE|FALSE [NOT USED HERE!] 
        */
        $query = '"' . $column . '" ';
        $query .= (strpos($params[0], '%1$s') !== FALSE) ? sprintf($params[0], $prj) : $params[0]; 
        if ($params[1] > 0) $query .= '(' . (string) $params[1] . ')';
        if (!$params[2]) $query .= ' NOT NULL';
        if ($params[3] != NULL) $query .= ' DEFAULT ' . $params[3];
        if ($params[4] != NULL) $query .= ' ' . $params[4];
        return $query;
    }
}

?>
