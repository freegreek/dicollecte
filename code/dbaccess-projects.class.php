<?php

class dbaccessProjects {
    /*
        This object is an access to the db for projects.
        Modified tables are: dicl_project
    */

    public $db;
    
    /* PUBLIC */
    
    function __construct ($db) {
        $this->db = $db;
    }

    public function selectProject ($prj) {
        try {
            $oQ = $this->db->connx->query('SELECT * FROM dicl_projects WHERE prj = ' . "'".$prj."'");
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'select project ' . $prj);
            return array(FALSE, '_dberror');
        }
        return array(TRUE, $oQ->fetchAll(PDO::FETCH_ASSOC));
    }
    
    public function listProjects () {
        try {
            $oQ = $this->db->connx->query('SELECT * FROM dicl_projects  ORDER BY id');
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport(FALSE, $e, 'list projects');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, $oQ->fetchAll(PDO::FETCH_ASSOC));
    }
    
    public function updateStats ($prj) {
        // count synonyms
        try {
            $oQ = $this->db->connx->query('SELECT syn FROM dicl_'.$prj.'_thes');
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'count synonyms for ' . $prj);
            return array(FALSE, '_dberror');
        }
        $count = 0;
        while ($data = $oQ->fetch(PDO::FETCH_ASSOC)) {
            $count += mb_substr_count($data['syn'], '|', 'UTF-8');
        }
        // update
        $qUpdate = 'UPDATE dicl_projects SET'
                 . ' nbdictent = (SELECT count(id_entry) FROM dicl_'.$prj.'_dic),'
                 . ' nbentgramtag = (SELECT count(id_entry) FROM dicl_'.$prj.'_dic WHERE po != ' . "''" . '),'
                 . ' nbentsemtag = (SELECT count(id_entry) FROM dicl_'.$prj.'_dic WHERE sem != ' . "''" . '),'
                 . ' nbnotes = (SELECT count(id_note) FROM dicl_'.$prj.'_notes),'
                 . ' nbprop = (SELECT count(id_prop) FROM dicl_'.$prj.'_prop WHERE tab = ' . "'E'" . '),'
                 . ' nbthesent = (SELECT count(id_word) FROM dicl_'.$prj.'_thes),'
                 . ' nbsyns = ' . $count . ','
                 . ' lastupdate = extract(epoch FROM now())'
                 . ' WHERE prj = ' . "'".$prj."'";
        //$qRecountNotes = 'CREATE TEMP TABLE tmpnotes ON COMMIT DROP AS (SELECT lemma, count(id_note) AS nb FROM dicl_'.$prj.'_notes GROUP BY lemma); '
        //               . 'UPDATE dicl_'.$prj.'_dic d SET nbnotes = nb  FROM tmpnotes t WHERE d.lemma = t.lemma';  
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec($qUpdate);
            //$this->db->connx->exec($qRecountNotes);
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'update stats for ' . $prj);
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    /* PRIVATE */
    
}

?>
