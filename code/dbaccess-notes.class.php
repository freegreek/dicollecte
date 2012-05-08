<?php

class dbaccessNotes {
    /*
        This object is an access to the db for notes.
        Modified tables are: prj_notes, prj_members
    */

    public $db;
    
    
    /* PUBLIC */
    
    function __construct ($db) {
        $this->db = $db;
    }
    
    // select notes by lemma
    public function selectNotesByLemma ($prj, $lemma) {
        try {
            $oQ = $this->db->connx->query('SELECT * FROM dicl_'.$prj.'_notes WHERE lemma LIKE ' . "'" . $lemma . "%'" . ' ORDER BY lemma');
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'select notes by lemma');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, $oQ->fetchAll(PDO::FETCH_ASSOC));
    }
    
    // select notes
    public function selectNotes ($prj, $id_entry) {
        try {
            $oQ = $this->db->connx->query('SELECT * FROM dicl_'.$prj.'_notes WHERE id_entry = ' . $id_entry . ' ORDER BY datetime');
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'select notes');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, $oQ->fetchAll(PDO::FETCH_ASSOC));
    }
    
    // create a new note
    public function createNote ($prj, $lemma, $note, $id_user, $id_entry, $login) {
        $this->remixNote($note);
        $label = (mb_strlen($note, 'utf-8') < 400) ? $note : mb_substr(strip_tags($note), 0, 398, 'utf-8') . '…';
        $label = '<b>' . $lemma . '</b> | ' . $label;
        $now = time();
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_notes (id_entry, id_user, login, lemma, note) VALUES ' . "($id_entry, $id_user, '$login', '$lemma', '$note')");
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_dic SET nbnotes = nbnotes + 1 WHERE id_entry = ' . $id_entry);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_members SET nbnotes = nbnotes + 1 WHERE id_member = ' . $id_user);
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES '. "($id_user, $id_entry, 'N', '+', '$label', $now)");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'create note');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'notesaved');
    }
    
    // delete a note
    public function deleteNote ($prj, $id_note, $id_entry) {
        list($ok, $result) = $this->getNote($prj, $id_note);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $data = $result[0];
        addSlashesOnArray($data);
        if (!($data['id_user'] == $_SESSION['id_user'] or $_SESSION['rank_'.$prj] <= 2)) {
            return array(FALSE, '_noaccess');
        }
        $label = (mb_strlen($data['note'], 'utf-8') < 400) ? $data['note'] : mb_substr(strip_tags($data['note']), 0, 398, 'utf-8') . '…';
        $label = '<b>' . $data['lemma'] . '</b> | ' . $label;
        $now = time();
        $id_user = $_SESSION['id_user'];
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('DELETE FROM dicl_' . $prj . '_notes WHERE id_note = ' . $id_note);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_dic SET nbnotes = nbnotes - 1 WHERE id_entry = ' . $id_entry);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_members SET nbnotes = nbnotes - 1 WHERE id_member = ' . $data['id_user']);
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES ' . "($id_user, $id_entry, 'N', '-', '$label', $now)");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'delete note');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    // edit a note
    public function editNote ($prj, $id_note, $newnote, $id_entry) {
        if ($newnote == '') {
            return array(TRUE, 'ok');
        }
        list($ok, $result) = $this->getNote($prj, $id_note);
        if (!$ok) {
            return array(FALSE, $msgcode);
        }
        $data = $result[0];
        addSlashesOnArray($data);
        if (!($data['id_user'] == $_SESSION['id_user'] or $_SESSION['rank_'.$prj] <= 2)) {
            return array(FALSE, '_noaccess');
        }
        $this->remixNote($newnote);
        $label = (mb_strlen($data['note'], 'utf-8') < 400) ? $data['note'] : mb_substr(strip_tags($data['note']), 0, 398, 'utf-8') . '…';
        $label = '<b>' . $data['lemma'] . '</b> | ' . $label;
        $now = time();
        $id_user = $_SESSION['id_user'];
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_notes SET note = ' . "'$newnote'" . ' WHERE id_note = ' . $id_note);
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_log (id_user, id, cat, action, label, datetime) VALUES ' . "($id_user, $id_entry, 'N', '>', '$label', $now)");
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'edit note');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }

    
    /* PRIVATE */
    
    private function getNote ($prj, $id_note) {
        try {
            $oQ = $this->db->connx->query('SELECT * FROM dicl_'.$prj.'_notes WHERE id_note = ' . $id_note);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'does the note exist?');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        if (count($result) == 0) {
            return array(FALSE, '_nocomm');
        }
        return array(TRUE, $result);
    }
    
    private function remixNote (&$note) {
        $note = strip_tags($note, '<b><i><u><s>');
        $note = preg_replace('`[a-zA-Z]+:\/\/([a-z][a-z0-9_\..-]*[a-z]{2,6})[^\s]*`i', ' <a href="$0">$1…</a> ', $note);
        $note = str_replace('&', '&amp;', $note);
        $note = nl2br($note);
    }
}

?>
