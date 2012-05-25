<?php

class Pagination {
    private $baseURL        = '';
    private $zoneSize       = 5; // > 0
    private $no_middle      = 10; // should be $zoneSize * 2
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
        // urls
        $url = $this->createURL();
        $link1 = '<a href="' . $url . '&amp;' . $this->pageName . '=%d' . $urlAddon . '">%d</a>';
        $link2 = '<a href="' . $url . '&amp;' . $this->pageName . '=%d' . $urlAddon . '" style="color: #FFF; text-shadow: 0px 0px 2px #FFF;"><b>%d</b></a>';
        // number of pages
        $nbPages = (int) ($nbitems / $this->nbElemsByPage);
        if ($nbitems % $this->nbElemsByPage != 0) $nbPages++;
        // create links
        $pagelinks = '';
        if ($nbPages < $this->nbMaxPages) {
            // all pages displayed
            $pagelinks .= $this->generateZoneLinks($link1, $link2, 1, $nbPages);
        }
        else {
            // display only a selection of pages
            $zone1b = 1;
            $zone1e = $this->borderSize;
            $zone2b = $this->pageNum - $this->zoneSize;
            $zone2e = $this->pageNum + $this->zoneSize;
            $zone3b = $nbPages - $this->borderSize + 1;
            $zone3e = $nbPages;
            $m1 = (int) (($zone1e+$zone2b)/2); // between zone1 and zone2
            $m2 = (int) (($zone2e+$zone3b)/2); // between zone3 and zone3
            if ($zone2b <= $zone1e) {
                $zone2e += $this->zoneSize;
                $pagelinks .= $this->generateZoneLinks($link1, $link2, $zone1b, $zone2e);
                $pagelinks .= $this->generateMiddleLinks ($link1, $zone2e, $m2, $zone3b);
                $pagelinks .= $this->generateSameLinks($link1, $zone3b, $zone3e);
            }
            elseif ($zone2e >= $zone3b) {
                $zone2b -= $this->zoneSize;
                $pagelinks .= $this->generateSameLinks($link1, $zone1b, $zone1e);
                $pagelinks .= $this->generateMiddleLinks($link1, $zone1e, $m1, $zone2b);
                $pagelinks .= $this->generateZoneLinks($link1, $link2, $zone2b, $zone3e);
            }
            else {
                $pagelinks .= $this->generateSameLinks($link1, $zone1b, $zone1e);
                $pagelinks .= $this->generateMiddleLinks($link1, $zone1e, $m1, $zone2b);
                $pagelinks .= $this->generateZoneLinks($link1, $link2, $zone2b, $zone2e);
                $pagelinks .= $this->generateMiddleLinks($link1, $zone2e, $m2, $zone3b);
                $pagelinks .= $this->generateSameLinks($link1, $zone3b, $zone3e);
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
    
    private function generateSameLinks ($link, $from, $to) {
        $s = '';
        for ($i=$from; $i<=$to; $i++) {
            $s .= sprintf($link, $i, $i);
        }
        return $s;
    }
    
    private function generateZoneLinks ($link1, $link2, $from, $to) {
        $s = '';
        $s .= $this->generateSameLinks($link1, $from, $this->pageNum-1);
        $s .= sprintf($link2, $this->pageNum, $this->pageNum);
        $s .= $this->generateSameLinks($link1, $this->pageNum+1, $to);
        return $s;
    }
    
    private function generateMiddleLinks ($link1, $previous, $n, $next) {
        if (($previous+1) >= $next) {
            return '';
        }
        $s = ' ... ';
        if (($next-$previous) > $this->no_middle) {
            $s .= sprintf($link1, $n, $n);
            $s .= ' ... ';
        }
        return $s;
    }
}
