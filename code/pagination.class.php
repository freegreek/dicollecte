<?php

class Pagination {
    private $baseURL        = '';
    private $zoneSize       = 5; // > 0
    private $borderSize     = 1; // > 0
    private $nbMaxPages     = 25; // shrink if more than $nbMaxPages, must be > $zoneSize
    
    private $script         = NULL; // example: 'comments.php'
    private $pageName       = '';   // pagename in $_GET, i.e. 'page', for comment.php?page=3
    private $pageNum        = 1;
    private $offset         = 0;    // offset in database
    private $nbElemsByPage  = 0;
    
    function __construct ($script, $pageName, $nbElemsByPage) {
        // returns page number and offset
        $this->script = $script;
        $this->pageName = $pageName;
        $this->nbElemsByPage = $nbElemsByPage;
        if (isset($_GET[$pageName])) {
            $this->pageNum = (int) $_GET[$pageName];
            $this->offset = ($this->pageNum - 1) * $nbElemsByPage;
        }
    }

    public function getOffset () {
        return $this->offset;
    }

    public function createLinks ($nbitems, $urlAddon='') {
        $pagelinks = '';
        $nbPages = (int) ($nbitems / $this->nbElemsByPage);
        if ($nbitems % $this->nbElemsByPage != 0) $nbPages++;
        $pagenumbers = $this->setPagination($nbPages);
        $url = $this->createURL();
        foreach ($pagenumbers as $i) {
            if ($i == 0) {
                $pagelinks .= ' ... ';
            }
            elseif ($this->pageNum != $i) {
                $pagelinks .= '<a href="' . $url . '&amp;' . $this->pageName . '=' . $i . $urlAddon . '">' . $i . '</a>';
            }
            else {
                $pagelinks .= '<a href="' . $url . '&amp;' . $this->pageName . '=' . $i . $urlAddon . '" style="color: #FFF;"><b>' . $i . '</b></a>';
            }
        }
        return $pagelinks;
    }


    /* PRIVATE */
    
    private function createURL () {
        $url = $this->script . '?';
        $i = 0;
        $params = array_merge($_GET, $_POST);
        foreach ($params as $key => $value) {
            if ($key != $this->pageName) {
                if ($i > 0) $url .= '&amp;';
                $url .= $key . '=' . $value;
                $i++;
            }
        }
        return $url;
    }
    
    private function setPagination ($nbPages) {
        // returns a array with a list of page numbers, and 0 for ...
        $pagenumbers = array();
        if ($nbPages < $this->nbMaxPages) {
            // all pages displayed
            for ($i=1; $i<=$nbPages; $i++) { $pagenumbers[] = $i; }
        }
        else {
            // display only a selection of pages
            $zone1b = 1;
            $zone1e = $this->borderSize;
            $zone2b = $this->pageNum - $this->zoneSize;
            $zone2e = $this->pageNum + $this->zoneSize;
            $zone3b = $nbPages - $this->borderSize + 1;
            $zone3e = $nbPages;
            if ($zone2b <= $zone1e) {
                $zone2e += $this->zoneSize;
                for ($i=$zone1b; $i<=$zone2e; $i++) { $pagenumbers[] = $i; }
                $pagenumbers[] = 0;
                for ($i=$zone3b; $i<=$zone3e; $i++) { $pagenumbers[] = $i; }
            }
            elseif ($zone2e >= $zone3b) {
                $zone2b -= $this->zoneSize;
                for ($i=$zone1b; $i<=$zone1e; $i++) { $pagenumbers[] = $i; }
                $pagenumbers[] = 0;
                for ($i=$zone2b; $i<=$zone3e; $i++) { $pagenumbers[] = $i; }
            }
            else {
                for ($i=$zone1b; $i<=$zone1e; $i++) { $pagenumbers[] = $i; }
                if (($zone1e + 1) != $zone2b) $pagenumbers[] = 0;
                for ($i=$zone2b; $i<=$zone2e; $i++) { $pagenumbers[] = $i; }
                if (($zone2e + 1) != $zone3b) $pagenumbers[] = 0;
                for ($i=$zone3b; $i<=$zone3e; $i++) { $pagenumbers[] = $i; }
            }
        }
        return $pagenumbers;
    }
}
