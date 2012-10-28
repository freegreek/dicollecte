<?php

class dbaccessUsers {
    /*
        This object is an access to the db for users management.
        Modified tables are: users, prj_members
        
        Cookie vars:
        - login                                 user login
        - pw                                    md5(password)
        
        Session vars:
        - id_user
        - member_[project] = TRUE | FALSE       is the user a member of the project ?
        - rank_[project] = 0                    overall superadmin
                           1                    project admin
                           2                    project co-admin
                           3                    editor
                           4                    (unused)
                           5                    controller
                           6                    (unused)
                           7                    user
                           8                    guest (unused)
                           9                    banned.                                  
    */
    
    public $db;
    
    /* PUBLIC */
    function __construct ($db) {
        $this->db = $db;
    }

    // create a new user
    public function createUser ($login, $name, $pw, $email, $prj) {
        if ($this->doesLoginAlreadyExist($login)) {
            return array(FALSE, '_newlog');
        }
        $cryptedpw = md5($pw);
        try {
            $this->db->connx->exec('INSERT INTO dicl_users (login, name, pw, email) VALUES ' . "('$login','$name','$cryptedpw','$email')");
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport(FALSE, $e, 'create user');
            return array(FALSE, '_dberror');
        }
        $this->sendMailAccount($email, $login, $pw, $prj);
        return array(TRUE, 'inscrok');
    }
    
    // select user
    public function selectUser ($id_user) {
        try {
            $oQ = $this->db->connx->query('SELECT * FROM dicl_users WHERE id_user = ' . $id_user);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport(FALSE, $e, 'select user');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        if (count($result) > 0) {
            return array(TRUE, $result);
        }
        return array(FALSE, '_nouser');
    }
    
    // select member
    public function selectMember ($id_user, $prj) {
        $qSelect = 'SELECT * FROM dicl_users, dicl_'.$prj.'_members WHERE id_user = ' . $id_user . ' AND id_member = id_user';
        try {
            $oQ = $this->db->connx->query($qSelect);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'select member');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        if (count($result) > 0) {
            return array(TRUE, $result);
        }
        return array(FALSE, '_nouser');
    }
    
    // update user
    public function updateUser ($id_user, $name, $email, $pw, $doNotify, $doNotifAuto, $prj) {
        list($ok, $result) = $this->selectUser($id_user);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $sqlDoNotify = ($doNotify) ? 'TRUE' : 'FALSE';
        $sqlDoNotifyAuto = ($doNotifAuto) ? 'TRUE' : 'FALSE';
        $sqlUpdatePW = ($pw == '') ? '' : ', pw = \''.md5($pw).'\'';
        $qUpdate = 'UPDATE dicl_users SET name = ' . "'$name', email = '$email'" . ', emailnotif = ' . $sqlDoNotify . ', enotifauto = ' . $sqlDoNotifyAuto
                 . $sqlUpdatePW . ' WHERE id_user = '.$id_user;
        try {
            $this->db->connx->exec($qUpdate);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport(FALSE, $e, 'update user');
            return array(FALSE, '_dberror');
        }
        /*if ($pw != '') {
            $this->sendMailAccount($email, $result[0]['login'], $pw, $prj);
        }*/
        return array(TRUE, 'ok');
    }
    
    // change member rank
    public function setRank ($id_user, $prj, $rank) {
        // the first account cannot be changed (super-admin) 
        if ($id_user == 1) {
            return array(FALSE, '_admin1');
            exit;
        }
        if (!is_numeric($rank)) {
            return array(FALSE, '_error');
            exit;
        }
        $qExec = ($this->isMember($id_user, $prj)) ? 'UPDATE dicl_'.$prj.'_members SET rk = ' . $rank . ' WHERE id_member = '. $id_user
                                                   : 'INSERT INTO dicl_'.$prj.'_members (id_member, rk) VALUES ' . "($id_user, $rank)";
        try {
            $this->db->connx->exec($qExec);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'set rank');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    // list members of a project
    public function listMembers ($prj, $offset, $nbEntriesByPage) {
        $qSelect = 'SELECT id_user, login, emailnotif, m.*'
                 . ' FROM dicl_users u, dicl_'.$prj.'_members m  WHERE id_user = id_member  ORDER BY rk, nbprop DESC,login  OFFSET ' . $offset . ' LIMIT ' . $nbEntriesByPage;
        try {
            $oQ = $this->db->connx->query($qSelect);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'list members');
            return array(FALSE, '_dberror', 0);
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        $nbOccur = count($result);
        if ($nbOccur > 0) {
            $qSelect = 'SELECT COUNT(id_member) AS nbusers FROM dicl_'.$prj.'_members';
            try {
                $oQ2 = $this->db->connx->query($qSelect);
                $result2 = $oQ2->fetchAll(PDO::FETCH_ASSOC);
                $nbOccur = $result2[0]['nbusers'];
            }
            catch (PDOException $e) {
                $this->db->dbErrorReport($prj, $e, 'count dictionary entries');
            }
        }
        return array(TRUE, $result, $nbOccur);
    }
    
    // list all users
    public function listUsers () {
        try {
            $oQ = $this->db->connx->query('SELECT * FROM dicl_users ORDER BY id_user DESC');
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport(FALSE, $e, 'list users');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, $oQ->fetchAll(PDO::FETCH_ASSOC));
    }
    
    public function deleteUser ($id_user) {
        try {
            $oQ = $this->db->connx->query('DELETE FROM dicl_users WHERE id_user = ' . $id_user);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport(FALSE, $e, 'delete user');
            return array(FALSE, '_dberror');
        }
        return array(TRUE, 'ok');
    }
    
    // get user rank (see above), identification not required
    public function getUserRankFor ($prj) {
        if (!$this->db->connx) return 7;
        if (isset($_SESSION['id_user']) and isset($_SESSION['rank_' . $prj])) {
            return $_SESSION['rank_' . $prj];
        }
        if (isset($_COOKIE['login']) and isset($_COOKIE['pw'])) {
            list($id_user, $rank, $error) = $this->connectUser($_COOKIE['login'], $_COOKIE['pw'], $prj);
            if ($id_user == -1) {
                setSysMsg($error);
                header(URL_HEADER . 'home.php?prj=' . $prj);
                exit;
            }
            return $rank;
        }
        return 7;
    }
    
    // user idenfication
    public function connectUser ($login, $pw, $prj, $registerToPrj=False) {
        // returns : - id_user or -1 if an error occured,  user's ranks,  message
        $msg = 'idok';
        if ($login == '') {
            return array(-1, '', '_emptylogin');
        }
        if (!isset($_SESSION['id_user'])) {
            list($ok, $msg) = $this->checkUser($login, $pw);
            if (!$ok) return array(-1, '', $msg);
        } 
        if (!isset($_SESSION['rank_' . $prj])) {
            list($ok, $msg) = $this->checkRank($_SESSION['id_user'], $prj);
            if (!$ok) return array(-1, '', $msg);
        }
        if ($registerToPrj and ($_SESSION['member_' . $prj] === FALSE)) {
            list($ok, $msg) = $this->newMember($_SESSION['id_user'], $prj);
            if (!$ok) return array(-1, '', $msg);
        }
        
        // all is OK
        return array($_SESSION['id_user'], $_SESSION['rank_' . $prj], $msg);
    }
    
    // write cookies login, pw
    public function setCookies ($login, $pw) {
        setcookie('login', $login, time()+10368000);
        setcookie('pw', md5($pw), time()+10368000);
    }
    
    // no e-mail notification
    public function noEmailNotif ($id_user, $codepw) {
        list($ok, $result) = $this->selectUser($id_user);
        if (!$ok) {
            return FALSE;
        }
        // Check password code. The password (pw) is md5 encrypted, and “codepw” = md5(md5(password)) 
        if (md5($result[0]['pw']) === $codepw) {
            // update
            try {
                $this->db->connx->exec('UPDATE dicl_users  SET emailnotif = FALSE  WHERE id_user = ' . $id_user);
            }
            catch (PDOException $e) {
                $this->db->dbErrorReport(FALSE, $e, 'no e-mail notification: update');
                return FALSE;
            }
            return TRUE;
        }
        return FALSE;
    }
    
    // send e-mail for password reinitialization
    public function askPasswordReinit ($login, $prj) {
        try {
            $oQ = $this->db->connx->query('SELECT * FROM dicl_users WHERE login = \'' . $login . "'");
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport(FALSE, $e, 'select user by login');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        if (count($result) > 0) {
            $data = $result[0];
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return array(FALSE, '_invalidemail');
            }
            $this->sendMailConfirmPwReinit($data['email'], $data['id_user'], $data['login'], $data['pw'], $prj);
            return array(TRUE, 'ok');
        }
        return array(FALSE, '_nouser');
    }
    
    // change the password of id_user and send an e-mail to him
    public function reinitPassword ($id_user, $codepw, $newpw, $prj) {
        list($ok, $result) = $this->selectUser($id_user);
        if (!$ok) {
            return array(FALSE, $result);
        }
        $data = $result[0];
        // Check password code. The password (pw) is md5 encrypted, and “codepw” = md5(md5(password)) 
        if (md5($data['pw']) == $codepw) {
            // update
            try {
                $this->db->connx->exec('UPDATE dicl_users  SET pw = \'' . md5($newpw) . '\'  WHERE id_user = ' . $id_user);
            }
            catch (PDOException $e) {
                $this->db->dbErrorReport(FALSE, $e, 'reinit password of user '.$id_user);
                return array(FALSE, '_dberror');
            }
            $this->sendMailAccount($data['email'], $data['login'], $newpw, $prj);
            return array(TRUE, 'reinitpw');
        }
        return array(FALSE, '_reinitpw');
    }
    
    
    /* PRIVATE */
    
    // check if the login already exists
    private function doesLoginAlreadyExist ($login) {
        try {
            $oQ = $this->db->connx->query('SELECT login FROM dicl_users WHERE login = ' . "'" . $login . "'");
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport(FALSE, $e, 'does login exist?');
            return FALSE;
        }
        $result = $oQ->fetchAll(PDO::FETCH_NUM);
        if (count($result) > 0) {
            return TRUE;
        }
        return FALSE;
    }

    // check if the user is a member of prj
    private function isMember ($id_user, $prj) {
        try {
            $oQ = $this->db->connx->query('SELECT * FROM dicl_'.$prj.'_members WHERE id_member = ' . $id_user);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'is member?');
            return FALSE;
        }
        $result = $oQ->fetchAll(PDO::FETCH_NUM);
        if (count($result) > 0) {
            return TRUE;
        }
        return FALSE;
    }

    // check the user settings in the database and write the id_user session var
    private function checkUser ($login, $pw) {
        // returns:
        // - TRUE, or FALSE if something was wrong
        // - a message-code
        
        // user checking
        try {
            $oQ = $this->db->connx->query('SELECT id_user, pw, enotifauto FROM dicl_users WHERE login = ' . "'" . $login . "'");
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport(FALSE, $e, 'check user');
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
        // password test ?
        if ($result[0]['pw'] != $pw) {
            setcookie('login', '', 0);
            setcookie('pw', '', 0);
            session_destroy();
            return array(FALSE, '_pwerror');
        }
        // set session vars
        $_SESSION['id_user'] = $result[0]['id_user'];
        $_SESSION['enotifauto'] = $result[0]['enotifauto'];
        return array(TRUE, 'idok');
    }
    
    // rank checking in the database, write the rank_project and the member_project sessions var
    private function checkRank ($id_user, $prj) {
        // returns :
        // - TRUE, or FALSE if something was wrong
        // - a message-code
        try {
            $oQ = $this->db->connx->query('SELECT rk FROM dicl_'.$prj.'_members WHERE id_member = ' . $id_user);
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'check user rank');
            return array(FALSE, '_dberror');
        }
        $result = $oQ->fetchAll(PDO::FETCH_ASSOC);
        // unknown ?
        if (count($result) == 0) {
            $_SESSION['member_' . $prj] = FALSE;
            $_SESSION['rank_' . $prj] = 7;
            return array(TRUE, 'idok');
        }
        // known member
        $_SESSION['member_' . $prj] = TRUE;
        $_SESSION['rank_' . $prj] = $result[0]['rk'];
        // banned ?
        if ($result[0]['rk'] == 9) {
            return array(FALSE, '_banned');
        }
        return array(TRUE, 'idok');
    }
    
    // register user as a new member of the project
    private function newMember ($id_user, $prj) {
        try {
            $this->db->connx->exec('INSERT INTO dicl_'.$prj.'_members (id_member, rk) VALUES ('.$id_user.', 7)');
        }
        catch (PDOException $e) {
            $this->db->dbErrorReport($prj, $e, 'newmember - id_user: ' . $id_user);
            return array(FALSE, '_dberror');
        }
        $_SESSION['member_' . $prj] = TRUE;
        return array(TRUE, 'newmember');
    }
    
    // mails
    private function sendMailConfirmPwReinit ($to, $id_user, $login, $pw, $prj) {
        $uiMail = parse_ini_file('./html/' . $prj . '/mail.ini', TRUE);
        $subject = 'Dicollecte - ' . sprintf($uiMail['pwrecup']['label'], $login);
        $msg = sprintf($uiMail['pwrecup']['label'], $login) . PHP_EOL . $uiMail['pwrecup']['expl'] . PHP_EOL
             . '<' . URL_ROOT . 'user_c.php?prj='.$prj.'&cmd=reinitpw&id_user=' . $id_user . '&code=' . md5($pw) . '>' . PHP_EOL . PHP_EOL
             . $uiMail['pwrecup']['expl2'];
        $this->sendMail($to, $subject, $msg);
    }
    
    private function sendMailAccount ($to, $login, $pw, $prj) {
        $uiMail = parse_ini_file('./html/' . $prj . '/mail.ini', TRUE);
        $subject = $uiMail['account']['label'];
        $msg = $uiMail['account']['label'] . PHP_EOL . PHP_EOL
             . $uiMail['account']['yourid'] . $login . ' ' . PHP_EOL
             . $uiMail['account']['yourpw'] . $pw . ' ' . PHP_EOL;
        $this->sendMail($to, $subject, $msg);
    }
    
    private function sendMail ($to, $subject, $msg) {
        $headers = 'From: Dicollecte <' . SENDMAIL_FROM . '>' . PHP_EOL . 'MIME-Version: 1.0' . PHP_EOL
                    . 'Content-type: text/plain; charset=utf-8' . PHP_EOL . 'Content-transfer-encoding: 8bit' . PHP_EOL . 'X-Mailer: PHP' . PHP_EOL;
        mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $msg, $headers);
    }
}

?>
