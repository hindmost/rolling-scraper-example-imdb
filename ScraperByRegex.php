<?php

require_once 'Scraper.php';

class ScraperByRegex extends Scraper
{
    const RX_LINK = '/<a +[^<>]*href="([^"]+)"/u';
    const RX_TITLE = '/<meta +property=["\']og\:title["\'] +content=["\']([^"]+)["\']/u';
    const RX_TOP_ITEM_SEP = '/<td +class="titleColumn/u';

    protected function parseToplist($cont) {
        $a_blocks = preg_split(self::RX_TOP_ITEM_SEP, $cont);
        if (count($a_blocks) < 2) return false;
        $a_urls = array();
        foreach (array_slice($a_blocks, 1) as $i => $s) {
            if ($url = $this->match1($s, self::RX_LINK))
                $a_urls[$i] = $this->reduceUrl($url);
        }
        return $a_urls;
    }

    const RX_RATING = '/<div +class="titlePageSprite star-box-giga-star">([^<>]+)</u';
    const RX_FLOAT_VAL = '/(\d+[.,]?\d*)/u';
    const RX_STARRING_START = '/<div +class="txt-block" +itemprop="actors"/u';
    const RX_STARRING_END = '/<span +class="see-more/u';
    const RX_CAST_START = '/<table +class="cast_list/u';
    const RX_CAST_SEP = '/<td +class="itemprop" +itemprop="actor/u';

    protected function parseMovie($cont) {
        $title = $this->match1($cont, self::RX_TITLE);
        $rating = ($s = $this->match1($this->match1($cont, self::RX_RATING), self::RX_FLOAT_VAL))?
            str_replace(',', '.', $s) : 0;
        if (!$title || !$rating) return false;
        $a_blocks = preg_split(self::RX_STARRING_START, $cont, 2);
        if (count($a_blocks) < 2) return false;
        $a_blocks = preg_split(self::RX_STARRING_END, $a_blocks[1], 2);
        if (count($a_blocks) < 2) return false;
        if (!preg_match_all(self::RX_LINK, $a_blocks[0], $arr)) return false;
        $a_urls = array_map(array($this, 'reduceUrl'), array_slice($arr[1], 0, 3));
        $a_blocks = preg_split(self::RX_CAST_START, $cont, 2);
        if (count($a_blocks) < 2) return false;
        $a_blocks = preg_split(self::RX_CAST_SEP, $a_blocks[1]);
        $n = 3;
        foreach (array_slice($a_blocks, 1) as $s) {
            $url = $this->match1($s, self::RX_LINK);
            if ($url && !in_array($url = $this->reduceUrl($url), $a_urls))
                $a_urls[$n++] = $url;
        }
        return array(array($title, $rating), $a_urls);
    }

    const RX_FILMO_START = '/<div +id="filmo-head-(?:actor|actress)"/u';
    const RX_FILMO_SEP = '/<div +id="filmo-head-/u';
    const RX_FILMO_ITEM_SEP = '/<div +class="filmo-row/u';
    const RX_FILMO_ITEM_YEAR = '/<span +class="year_column">([^<>]+)</u';
    const RX_INT_VAL = '/(\d+)/u';

    protected function parseActor($cont) {
        $title = $this->match1($cont, self::RX_TITLE);
        if (!$title) return false;
        $a_blocks = preg_split(self::RX_FILMO_START, $cont, 2);
        if (count($a_blocks) < 2) return false;
        $a_blocks = preg_split(self::RX_FILMO_SEP, $a_blocks[1], 2);
        $a_blocks = preg_split(self::RX_FILMO_ITEM_SEP, $a_blocks[0]);
        if (count($a_blocks) < 2) return false;
        $y_now = intval(date('Y'));
        $n = $y_last = $y_first = 0;
        foreach (array_slice($a_blocks, 1) as $i => $s) {
            $year = intval($this->match1($this->match1($s, self::RX_FILMO_ITEM_YEAR), self::RX_INT_VAL));
            if (!$year) continue;
            $a_parts = explode('<br', $s, 2);
            if (strpos(strip_tags($a_parts[0]), '(') !== false) continue;
            $n++;
            if (!$y_last) $y_last = $year;
            if (!$y_first || $year < $y_first) $y_first = $year;
        }
        return $n?
            array($title, $n, min($y_last, $y_now)-$y_first+1, $y_first) : false;
    }

    protected function match1($cont, $regex) {
        return $cont && preg_match($regex, $cont, $arr)?
            (count($arr) > 1? trim($arr[1]) : true) : '';
    }
}
