<?php

class Database {
    public $connx = NULL;
    public $altconnx = NULL;
    
    // PUBLIC
    
    function __construct () {
        try {
            $this->connx = new PDO (DB_BASE, DB_USER, DB_PASSWORD);
            $this->connx->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        catch (PDOException $e) {
            if (isset($_GET['prj'])) {
                $this->record($e, 'no connexion to the db');
                setcookie('login', '', 0);
                setcookie('pw', '', 0);
            }
            else {
                echo '<h1>dicollecte</h1><p>Sorry: unable to connect to the database.</p>';
                echo $e->getMessage();
                exit;
            }
        }
    }
    
    public function openAltConnx () {
        // useful for: COPY table FROM stdin
        $this->altconnx = pg_connect('host='.DB_HOST . ' dbname='.DB_NAME . ' user='.DB_USER . ' password='.DB_PASSWORD);
    }
    
    public function close () {
        $this->connx = NULL;
        if ($this->altconnx) pg_close($this->altconnx);
    }
    
    public function createSelectionSubQuery ($aOptions) {
        $qSubQuery = '';
        $nbElems = count($aOptions); 
        if ($nbElems > 0) {
            $qSubQuery = ' WHERE ' . $aOptions[0];
            for ($i = 1;  $i < $nbElems;  $i++) {
                $qSubQuery .= ' AND ' . $aOptions[$i];
            }
            $qSubQuery .= ' ';
        }
        return $qSubQuery;
    }
    
    // write database errors in a file (/log/prj/log.txt) 
    public function dbErrorReport ($prj, $e, $msg='') {
        $filePath = ($prj) ? './log/'.$prj.'/log.txt' : './log/log.txt';
        $file = fopen($filePath, 'a');
        $login = (isset($_COOKIE['login'])) ? $_COOKIE['login'] : '[nologin]';
        fwrite($file, date("Y-m-d H:i:s -- ") . $msg . ' -- user: ' . $login . PHP_EOL);
        fwrite($file, $e->getMessage() . PHP_EOL . PHP_EOL);
        fclose($file);
    }
    
    // error report. write error messages in a file (/log/prj/log.txt) 
    public function errorReport ($prj, $msg) {
        $file = fopen('./log/'.$prj.'/log.txt', 'a');
        $login = (isset($_COOKIE['login'])) ? $_COOKIE['login'] : '[nologin]';
        fwrite($file, date("Y-m-d H:i:s") . ' -- user: ' . $login . PHP_EOL);
        fwrite($file, $msg . PHP_EOL . PHP_EOL);
        fclose($file);
    }
    
    // debugging. record in file
    public function record ($msg='', $filename = 'debug.txt') {
        $file = fopen('./log/'.$filename, 'a');
        $login = (isset($_COOKIE['login'])) ? $_COOKIE['login'] : '[nologin]';
        fwrite($file, date("Y-m-d H:i:s") . ' -- user: ' . $login . PHP_EOL);
        fwrite($file, $msg . PHP_EOL . PHP_EOL);
        fclose($file);
    }
}

?>
