<?php

require_once 'Scraper.php';
use Sunra\PhpSimple\HtmlDomParser;

class ScraperByDom extends Scraper
{

    protected function parseToplist($cont) {
        $html = HtmlDomParser::str_get_html($cont);
        if (!$html) return false;
        $a_urls = array();
        foreach ($html->find('td.titleColumn') as $i => $cell) {
            $link = $cell->find('a[href]', 0);
            if ($link && $link->href)
                $a_urls[$i] = $this->reduceUrl($link->href);
        }
        $html->clear(); unset($html);
        return $a_urls;
    }

    protected function parseMovie($cont) {
        $html = HtmlDomParser::str_get_html($cont);
        if (!$html) return false;
        $title = count($els = $html->find('h1.header span')) >= 2?
            trim($els[0]->plaintext).' '.trim($els[1]->plaintext) : '';
        $rating = ($el = $html->find('div.star-box-giga-star', 0))?
            str_replace(',', '.', trim($el->plaintext)) : '';
        $a_urls = array();
        foreach ($html->find('div.txt-block[itemprop=actors] a') as $i => $link) {
            if (!$link->find('span', 0)) continue;
            if ($url = $this->reduceUrl($link->href))
                $a_urls[] = $url;
        }
        $n = 3;
        foreach ($html->find('table.cast_list td[itemprop=actor]') as $i => $cell) {
            $link = $cell->find('a', 0);
            if (!$link) continue;
            $url = $this->reduceUrl($link->href);
            if ($url && !in_array($url, $a_urls))
                $a_urls[$n++] = $url;
        }
        $html->clear(); unset($html);
        return $title? array(array($title, $rating), $a_urls) : false;
    }

    protected function parseActor($cont) {
        $html = HtmlDomParser::str_get_html($cont);
        if (!$html) return false;
        $title = ($el = $html->find('h1.header span', 0))? $el->plaintext : '';
        $head = $html->find('#filmo-head-actor,#filmo-head-actress', 0);
        $y_now = intval(date('Y'));
        $n = $y_last = $y_first = 0;
        foreach ($head? $head->next_sibling()->find('div.filmo-row') : array() as $i => $row) {
            $el = $row->find('.year_column', 0);
            if (!$el) continue;
            $year = preg_match('/\d+/', $el->plaintext, $arr)? intval($arr[0]) : 0;
            if (!$year) continue;
            $a_parts = explode('<br', $row->innertext, 2);
            if (strpos(strip_tags($a_parts[0]), '(') !== false) continue;
            $n++;
            if (!$y_last) $y_last = $year;
            if (!$y_first || $year < $y_first) $y_first = $year;
        }
        if ($head) $head->clear(); unset($head);
        $html->clear(); unset($html);
        return $title && $n?
            array($title, $n, min($y_last, $y_now)-$y_first+1, $y_first) : false;
    }
}
