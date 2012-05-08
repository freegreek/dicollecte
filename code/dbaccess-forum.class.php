<?php

class dbaccessForum {
    /*
        This object is an access to the db for forum.
        Modified tables are: prj_forum, prj_thread, prj_msg
    */

    public  $db;
    
    /* PUBLIC */
    
    function __construct ($db) {
        $this->db = $db;
    }

    // Forums
    
    public function listForums ($prj) {
        $qSelect = 'SELECT f.*, u.login FROM dicl_'.$prj.'_forum f  LEFT OUTER JOIN dicl_users u ON f.id_user_up = u.id_user ORDER BY label';
        try {
            $oQ = $this->db->connx->query($qSelect);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'list forums');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        return array(TRUE, $result);
    }

    public function createForum ($prj, $label, $descr) {
        if ($label == '' or $descr == '') {
            return array(FALSE, '_emptyfields');
        }
        try {
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_forum (label, descr) VALUES ' . "('$label', '$descr')");
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'create forum');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }

    public function deleteForum ($prj, $id_forum) {
        if (!is_numeric($id_forum)) {
            return array(FALSE, '_data');
        }
        list($ok, $result) = $this->getForum($prj, $id_forum);
        if (!$ok) {
            return array(FALSE, $result);
        }
        if ($result[0]['nbthreads'] !== 0) {
            return array(FALSE, '_forumnotempty');
        }
        try {
            $oQ = $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_forum  WHERE id_forum = ' . $id_forum);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'delete forum');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }

    public function renameForum ($prj, $id_forum, $name, $descr) {
        if (!is_numeric($id_forum)) {
            return array(FALSE, '_data');
        }
        try {
            $oQ = $this->db->connx->exec('UPDATE dicl_'.$prj.'_forum  SET label = \'' . $name . '\', descr = \'' . $descr . '\' WHERE id_forum = ' . $id_forum);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'rename forum');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }

    public function reInitForum ($prj, $id_forum) {
        if (!is_numeric($id_forum)) {
            return array(FALSE, '_data');
        }
        list($ok, $result) = $this->getLastUpdatedThread($prj, $id_forum);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $nbThread = count($result);
        $data = $result[0];
        $id_thread = ($nbThread) ? $data['id_thread'] : 'NULL';
        $id_user_up = ($nbThread) ? $data['id_user_up'] : 'NULL';
        $updatedt = ($nbThread) ? $data['updatedt'] : '0';
        $qUpdate = 'UPDATE dicl_'.$prj.'_forum  SET id_thread = ' . $id_thread . ', id_user_up = ' . $id_user_up . ', updatedt = ' . $updatedt . ' WHERE id_forum = ' . $id_forum;
        try {
            $this->db->connx->exec($qUpdate);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'reinit forum');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }


    // Threads 
    
    public function listThreads ($prj, $limit, $offset, $id_forum = NULL) {
        if ($id_forum and !is_numeric($id_forum)) {
            return array(FALSE, '_data');
        }
        if ($id_forum) { $qSelectF = 'SELECT * FROM dicl_'.$prj.'_forum  WHERE id_forum = ' . $id_forum; }
        $qSelectT = 'SELECT t.*, u.login FROM dicl_'.$prj.'_thread t  LEFT OUTER JOIN dicl_users u ON t.id_user_up = u.id_user';
        if ($id_forum) { $qSelectT .= ' WHERE id_forum = ' . $id_forum; }
        $qSelectT .= ($id_forum) ? ' ORDER BY flow, updatedt DESC' : ' ORDER BY updatedt DESC';
        $qSelectT .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        try {
            $oQT = $this->db->connx->query($qSelectT);
            if ($id_forum) { $oQF = $this->db->connx->query($qSelectF); }
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'list threads');
            return array(FALSE, '_dberror', NULL);
        }
        $resultF = ($id_forum) ? $oQF->fetchAll(PDO::FETCH_ASSOC) : NULL;
        $resultT = $oQT->fetchAll(PDO::FETCH_ASSOC);
        $nbOccurT = count($resultT);
        if ($nbOccurT > 0) {
            $qSelect = 'SELECT COUNT(id_thread) AS nbentries FROM dicl_'.$prj.'_thread t ';
            if ($id_forum) { $qSelect .= ' WHERE id_forum = ' . $id_forum; }
            try {
                $oQ2 = $this->db->connx->query($qSelect);
                $result2 = $oQ2->fetchAll(PDO::FETCH_ASSOC);
                $nbOccurT = $result2[0]['nbentries'];
            }
            catch (PDOException $e) {
                $this->db->dbErrorReport($prj, $e, 'count messages');
            }
        }
        return array(TRUE, $resultT, $resultF, $nbOccurT);
    }

    public function createThread ($prj, $id_forum, $id_user, $label) {
        if (!is_numeric($id_forum) or !is_numeric($id_user)) {
            return array(FALSE, '_data');
        }
        if ($label == '') {
            return array(FALSE, '_emptyfields');
        }
        $label = strip_tags($label, '<b><i><u><s>');
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_thread (label, id_user, id_forum, id_user_up) VALUES ' . "('$label', $id_user, $id_forum, $id_user)");
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_forum  SET nbthreads = nbthreads + 1  WHERE id_forum = ' . $id_forum);
            $id_thread = $this->db->connx->lastInsertId('dicl_'.$prj.'_thread_id_thread_seq');
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'create thread');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, $id_thread);
    }

    public function eraseThread ($prj, $id_thread) {
        if (!is_numeric($id_thread)) {
            return array(FALSE, '_data');
        }
        list($ok, $result) = $this->getThread($prj, $id_thread);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $data = $result[0];
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_threadsub  WHERE id_thread = ' . $id_thread);
            $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_msg  WHERE id_thread = ' . $id_thread);
            $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_thread  WHERE id_thread = ' . $id_thread);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_forum  SET nbthreads = nbthreads - 1  WHERE id_forum = ' . $data['id_forum']);
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'erase thread');
            return array(FALSE, '_dberror');
        }
        list($ok, $msgcode) = $this->reInitForum($_GET['prj'], $data['id_forum']);
        if (!$ok) {
            return array(FALSE, $msgcode);
        }
        return array(TRUE, $data['id_forum']);
    }
    
    public function switchThreadLock ($prj, $id_thread) {
        if (!is_numeric($id_thread)) {
            return array(FALSE, '_data');
        }
        list($ok, $result) = $this->getThread($prj, $id_thread);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $dbLockValue = ($result[0]['locked']) ? 'FALSE' : 'TRUE';
        try {
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_thread SET locked = ' . $dbLockValue . '  WHERE id_thread = ' . $id_thread);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'switch thread lock');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    public function switchThreadSolved ($prj, $id_thread) {
        if (!is_numeric($id_thread)) {
            return array(FALSE, '_data');
        }
        list($ok, $result) = $this->getThread($prj, $id_thread);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $dbSolvedValue = ($result[0]['solved']) ? 'FALSE' : 'TRUE';
        try {
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_thread SET solved = ' . $dbSolvedValue . '  WHERE id_thread = ' . $id_thread);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'switch thread solved');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    public function moveThread ($prj, $id_thread, $id_forum) {
        if (!is_numeric($id_thread) or !is_numeric($id_forum)) {
            return array(FALSE, '_data');
        }
        list($ok, $result) = $this->getThread($prj, $id_thread);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_thread SET id_forum = ' . $id_forum . '  WHERE id_thread = ' . $id_thread);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_forum SET nbthreads = nbthreads + 1   WHERE id_forum = ' . $id_forum);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_forum SET nbthreads = nbthreads - 1   WHERE id_forum = ' . $result[0]['id_forum']);
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'move thread');
            return array(FALSE, '_dberror');
        }
        list($ok, $msgcode) = $this->reInitForum($_GET['prj'], $result[0]['id_forum']);
        if (!$ok) {
            setSysMsg($msgcode);
        }
        list($ok, $msgcode) = $this->reInitForum($_GET['prj'], $id_forum);
        if (!$ok) {
            setSysMsg($msgcode);
        }
        return array(TRUE, 'threadmoved');
    }
    
    public function copyThread ($prj, $id_thread) {
        if (!is_numeric($id_thread)) {
            return array(FALSE, '_data');
        }
        list($ok, $result) = $this->getThread($prj, $id_thread);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $qInsertT = 'INSERT INTO dicl_'.$prj.'_thread (id_forum, id_user, label, nbmsg, msgcount, creationdt, updatedt, id_user_up, msgnum, flow, tag, locked)'
                  . ' SELECT id_forum, id_user, label, nbmsg, msgcount, creationdt, updatedt, id_user_up, msgnum, flow, tag, locked FROM dicl_'.$prj.'_thread'
                  . ' WHERE id_thread = ' . $id_thread;
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec($qInsertT);
            $newThread = $this->db->connx->lastInsertId('dicl_'.$prj.'_thread_id_thread_seq');
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_msg (id_thread, id_user, msg, msgnum, creationdt, updatedt)'
                                 . ' SELECT '.$newThread.', id_user, msg, msgnum, creationdt, updatedt FROM dicl_'.$prj.'_msg  WHERE id_thread = ' . $id_thread);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_forum SET nbthreads = nbthreads + 1   WHERE id_forum = ' . $result[0]['id_forum']);
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'copy thread');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    public function cutThread ($prj, $id_thread, $msgnum) {
        if (!is_numeric($id_thread) or !is_numeric($msgnum) ) {
            return array(FALSE, '_data');
        }
        list($ok, $result) = $this->getThread($prj, $id_thread);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $data = $result['0'];
        addSlashesOnArray($data);
        list($ok, $id_new_thread) = $this->createThread($prj, $data['id_forum'], $data['id_user'], $data['label']);
        if (!$ok) {
            return array(FALSE, $id_new_thread);
        }
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_msg  SET id_thread = '.$id_new_thread.'  WHERE id_thread = ' . $id_thread . ' AND msgnum >= ' . $msgnum);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_thread  SET msgcount = ' . $data['msgcount'] . ', nbmsg = (SELECT count(*) FROM dicl_'.$prj.'_msg  WHERE id_thread = ' . $id_new_thread . ')  WHERE id_thread = ' . $id_new_thread);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_thread  SET nbmsg = (SELECT count(*) FROM dicl_'.$prj.'_msg  WHERE id_thread = ' . $id_thread . ')  WHERE id_thread = ' . $id_thread);
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'cut thread');
            return array(FALSE, '_dberror');
        }
        list($ok, $id_forum) = $this->reInitThread($_GET['prj'], $id_thread);
        if (!$ok) {
            return array(FALSE, $id_forum);
        }
        list($ok, $id_forum) = $this->reInitThread($_GET['prj'], $id_new_thread);
        if (!$ok) {
            return array(FALSE, $id_forum);
        }
        list($ok, $msgcode) = $this->reInitForum($_GET['prj'], $id_forum);
        if (!$ok) {
            return array(FALSE, $msgcode);
        }
        return array(TRUE, 'ok');
    }
    
    public function joinThread ($prj, $id_thread_src, $id_thread_dst) {
        if (!is_numeric($id_thread_src) or !is_numeric($id_thread_dst) ) {
            return array(FALSE, '_data');
        }
        list($ok, $resultSrc) = $this->getThread($prj, $id_thread_src);
        if (!$ok) {
            return array(FALSE, $resultSrc);
        }
        list($ok, $resultDst) = $this->getThread($prj, $id_thread_dst);
        if (!$ok) {
            return array(FALSE, $resultDst);
        }
        $this->db->connx->beginTransaction();
        try {
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_msg  SET msgnum = msgnum + ' . $resultDst['0']['msgcount'] . ', id_thread = '.$id_thread_dst.'  WHERE id_thread = ' . $id_thread_src);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_thread  SET msgcount = msgcount + ' . $resultSrc['0']['msgcount']
                                 . ', nbmsg = (SELECT count(*) FROM dicl_'.$prj.'_msg  WHERE id_thread = ' . $id_thread_dst . ')  WHERE id_thread = ' . $id_thread_dst);
            $this->db->connx->exec('DELETE FROM dicl_'.$prj.'_thread  WHERE id_thread = ' . $id_thread_src);
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_forum  SET nbthreads = nbthreads - 1  WHERE id_forum = ' . $resultSrc['0']['id_forum']);
            $this->db->connx->commit();
        }
        catch (PDOException $e) {
            $this->db->connx->rollBack();
            $this->db->dbErrorReport($prj, $e, 'join thread');
            return array(FALSE, '_dberror');
        }
        list($ok, $id_forum) = $this->reInitThread($_GET['prj'], $id_thread_dst);
        if (!$ok) {
            return array(FALSE, $id_forum);
        }
        list($ok, $msgcode) = $this->reInitForum($_GET['prj'], $id_forum);
        if (!$ok) {
            return array(FALSE, $msgcode);
        }
        list($ok, $msgcode) = $this->reInitForum($_GET['prj'], $resultSrc['0']['id_forum']);
        if (!$ok) {
            return array(FALSE, $msgcode);
        }
        return array(TRUE, 'ok');
    }
    
    public function renameThread ($prj, $id_thread, $label) {
        if (!is_numeric($id_thread)) {
            return array(FALSE, '_data');
        }
        list($ok, $result) = $this->getThread($prj, $id_thread);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $label = strip_tags($label, '<b><i><u><s>');
        try {
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_thread SET label = \'' . $label . '\'  WHERE id_thread = ' . $id_thread);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'rename thread');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    public function setFlowTag ($prj, $id_thread, $flowTag) {
        if (!is_numeric($id_thread) or !preg_match('`^[A-E]$`', $flowTag)) {
            return array(FALSE, '_data');
        }
        list($ok, $result) = $this->getThread($prj, $id_thread);
        if (!$ok) {
            return array(FALSE, $result);
        }
        try {
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_thread SET flow = \'' . $flowTag . '\'  WHERE id_thread = ' . $id_thread);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'set flow tag');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    public function setTag ($prj, $id_thread, $tag) {
        if (!is_numeric($id_thread) or !preg_match('`^[A-R?]$`', $tag)) {
            return array(FALSE, '_data');
        }
        list($ok, $result) = $this->getThread($prj, $id_thread);
        if (!$ok) {
            return array(FALSE, $result);
        }
        try {
            $this->db->connx->exec('UPDATE dicl_'.$prj.'_thread SET tag = \'' . $tag . '\'  WHERE id_thread = ' . $id_thread);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'set tag');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    public function reInitThread ($prj, $id_thread) {
        if (!is_numeric($id_thread)) {
            return array(FALSE, '_data');
        }
        list($ok, $result) = $this->getLastMsgFromThread($prj, $id_thread);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $nbMsg = count($result);
        $data = $result[0];
        $id_user_up = ($nbMsg) ? $data['id_user'] : 'NULL';
        $updatedt = ($nbMsg) ? $data['creationdt'] : '0';
        $msgnum = ($nbMsg) ? $data['msgnum'] : '0';
        $qUpdate = 'UPDATE dicl_'.$prj.'_thread SET id_user_up = ' . $id_user_up . ', updatedt = ' . $updatedt . ', msgnum = ' . $msgnum
                 . ' WHERE id_thread = ' . $id_thread;
        try {
            $this->db->connx->exec($qUpdate);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'reinit thread');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, $data['id_forum']);
    }
    
    
    /* PRIVATE */
    
    private function getForum ($prj, $id_forum) {
        try {
            $oQ = $this->db->connx->query('SELECT *  FROM dicl_'.$prj.'_forum   WHERE id_forum = ' . $id_forum);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'get forum');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        if (count($result) == 0) {
            return array(FALSE, '_noforum');
        }
        return array(TRUE, $result);
    }
    
    private function getThread ($prj, $id_thread) {
        try {
            $oQ = $this->db->connx->query('SELECT *  FROM dicl_'.$prj.'_thread   WHERE id_thread = ' . $id_thread);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'get thread');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        if (count($result) == 0) {
            return array(FALSE, '_nothread');
        }
        return array(TRUE, $result);
    }
    
    private function getLastMsgFromThread ($prj, $id_thread) {
        $qSelect = 'SELECT m.*, t.id_forum  FROM dicl_'.$prj.'_msg m  JOIN dicl_'.$prj.'_thread t  ON m.id_thread = t.id_thread'
                 . ' WHERE m.id_thread = ' . $id_thread . ' ORDER BY creationdt DESC  LIMIT 1';
        try {
            $oQ = $this->db->connx->query($qSelect);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'get last message from thread');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        return array(TRUE, $result);
    }
    
    private function getLastUpdatedThread ($prj, $id_forum) {
        try {
            $oQ = $this->db->connx->query('SELECT *  FROM dicl_'.$prj.'_thread  WHERE id_forum = ' . $id_forum . ' ORDER BY updatedt DESC  LIMIT 1');
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'get last updated thread');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        return array(TRUE, $result);
    }
}

?>
