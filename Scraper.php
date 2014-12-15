<?php

class Scraper extends RollingScraperAbstract
{
    const URL_ROOT = 'http://www.imdb.com';
    const URL_INDEX = 'http://www.imdb.com/chart/top';
    const FILE_STATE_TIME = 'scraper-state-time.json';
    const FILE_STATE_DATA = 'scraper-state-data.json';
    const FILE_RESULTS = 'scraper-results.json';

    static protected $aCurl = array(
        CURLOPT_TIMEOUT => 40,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_AUTOREFERER => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:12.0) Gecko/20100101 Firefox/12.0',
        CURLINFO_HEADER_OUT => true,
        CURLOPT_HEADER => true,
    );

    protected $aCfg = array(
        'scrape_life' => 0, // 
        'cast_limit' => 0, // max. cast position in movie to scrape 
    );
    protected $aMovies = array();
    protected $aActors = array();
    protected $aCastlists = array();


    public function __construct($aCfg = array()) {
        $this->aCfg = array_merge($this->aCfg, $aCfg);
        $this->aCfg['scrape_life'] *= 3600* 24;
        $this->modConfig(array_merge(array(
            'state_time_storage' => self::FILE_STATE_TIME,
            'state_data_storage' => self::FILE_STATE_DATA,
            'curl_options' => self::$aCurl,
        ), $this->aCfg));
        parent::__construct();
    }

    function scrape() {
        $this->restoreResults();
        if (!$this->run()) return false;
        $this->saveResults();
        return true;
    }

    protected function _initPages() {
        $this->addPage(self::URL_INDEX, array(0));
    }

    protected function _beforeRun() {
        $a_state = $this->getStateProgress();
        if (($t_end = $a_state[0]) && time()- $t_end <= $this->aCfg['scrape_life']) {
            $this->resetResults(); $this->saveResults();
        }
    }

    public function _handlePage($cont, $url, $aInfo, $iPage, $aData) {
        $aData = array_map('intval', $aData);
        if (!$cont) return false;
        switch ($aData[0]) {
        case 0:
            $a_ret = $this->parseToplist($cont);
            if (!$a_ret) return false;
            foreach ($a_ret as $i => $url) {
                $this->addPage($url, array(1, $i), $iPage);
            }
            return true;
        case 1:
            $i_movie = $aData[1];
            $a_ret = $this->parseMovie($cont);
            if (!$a_ret) return false;
            $this->aMovies[$i_movie] = array_merge(array($iPage), $a_ret[0]);
            $max = $this->aCfg['cast_limit'];
            $a_cast = array();
            foreach (array_slice($a_ret[1], 0, $max) as $i_cast => $url) {
                $i_actor = count($this->aActors);
                $i_page = $this->addPage($url, array(2, $i_actor), $iPage);
                if ($i_page !== false)
                    $this->aActors[] = array($i_page);
                else
                    $i_actor = $this->findActor($url);
                if ($i_actor !== false)
                    $a_cast[$i_cast] = $i_actor;
            }
            $this->aCastlists[$i_movie] = $a_cast;
            return true;
        case 2:
            $i_actor = $aData[1];
            $a_ret = $this->parseActor($cont);
            if (!$a_ret) return false;
            $this->aActors[$i_actor] = array_merge(array($iPage), $a_ret);
            return true;
        default:
            return false;
        }
    }

    protected function _beforeEnd($aUrls) {
        foreach ($this->aActors as &$a_rec) {
            $i_page = $a_rec[0];
            $a_rec[0] = $this->_fixReqUrl($aUrls[$i_page]);
        }
        foreach ($this->aMovies as &$a_rec) {
            if (!$a_rec) continue;
            $i_page = $a_rec[0];
            $a_rec[0] = $this->_fixReqUrl($aUrls[$i_page]);
        }
    }

    /**
     * Parse content of the Top 250 list page
     * @param string $cont - content of response
     * @return array|false list of movie URLs
     */
    protected function parseToplist($cont) {
        return false;
    }

    /**
     * Parse content of movie page
     * @param string $cont - content of response
     * @return array|false array(array($title, $rating), $casted_actor_urls)
     */
    protected function parseMovie($cont) {
        return false;
    }

    /**
     * Parse content of actor page
     * @param string $cont - content of response
     * @return array|false array($title, $career_movies, $career_years, $career_begin)
     */
    protected function parseActor($cont) {
        return false;
    }

    /**
     * Find actor by its URL
     * @param string $url - URL
     * @return int # of actor
     */
    protected function findActor($url) {
        $i_page = $this->getPageByUrl($url);
        if ($i_page === false) return false;
        foreach ($this->aActors as $i => $el) {
            if ($el[0] == $i_page) return $i;
        }
        return false;
    }

    /**
     * Get results of scraping
     * @return array array($movies, $actors, $castlists)
     */
    function getResults() {
        return array($this->aMovies, $this->aActors, $this->aCastlists);
    }

    protected function resetResults() {
        $this->aMovies = $this->aActors = $this->aCastlists = array();
    }

    protected function restoreResults() {
        $arr = $this->_restore(self::FILE_RESULTS);
        if (!$arr || count($arr) != 3) return false;
        list($this->aMovies, $this->aActors, $this->aCastlists) = $arr;
        return true;
    }

    protected function saveResults() {
        $this->_save(self::FILE_RESULTS, $this->getResults());
    }

    protected function _encode($v) {
        return json_encode($v);
    }

    protected function _decode($s) {
        return json_decode($s, true);
    }

    protected function _fixReqUrl($url) {
        return (!$url || strpos($url, 'http') === 0)? $url : self::URL_ROOT. $url;
    }

    protected function reduceUrl($url) {
        return ($i = strpos($url, '?')) !== false? substr($url, 0, $i) : $url;
    }
}
