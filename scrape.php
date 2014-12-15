<?php
require_once 'vendor/autoload.php'; 

$parser = isset($_GET['parser'])? strtolower($_GET['parser']) : '';
$timeout = isset($_GET['timeout'])? $_GET['timeout'] : 0;
$CLASSES = array('regex' => 'ScraperByRegex', 'dom' => 'ScraperByDom');
$class = $parser && isset($CLASSES[$parser])? $CLASSES[$parser] : $CLASSES['regex'];
require_once "$class.php";

function outResults($aResults) {
    if (count($aResults) != 3) return array();
    list($aMovies, $aActors, $aCastlists) = $aResults;
    $a_result = array();
    foreach ($aActors as $i_actor => $a_rec) {
        if (count($a_rec) < 2 || !$a_rec[3]) continue;
        $a_result[$i_actor] = array_combine(
            array('url', 'title', 'n_movies', 'n_years', 'begin', 'rating', 'appears'),
            array_merge($a_rec, array(0, array()))
        );
    }
    $a_cast_ratios = array(1, 0.8, 0.7);
    $i_castmax = count($a_cast_ratios) - 1;
    foreach ($aCastlists as $i_mov => $a_list) {
        ksort($a_list);
        $r_mov = $aMovies[$i_mov][2];
        foreach ($a_list as $i_cast => $i_actor) {
            if (!isset($a_result[$i_actor])) continue;
            $a_rec = &$a_result[$i_actor];
            $v = $r_mov * $a_cast_ratios[min($i_cast, $i_castmax)];
            $a_rec['rating'] += $v;
            $a_rec['appears'][] = $i_mov;
        }
    }
    usort($a_result, function ($a, $b) {
        return $a['rating'] != $b['rating']?
            ($a['rating'] > $b['rating']? -1 : 1) :
            ($a['n_movies'] > $b['n_movies']? -1 : 1);
    });
    return array($a_result, $aMovies);
}

$scraper = new $class(array(
    'scrape_life' => 180,
    'run_timeout' => $timeout,
    'cast_limit' => 3,
));
$b = $scraper->scrape();
list($t_start, $t_end, , $t_run, , $n_total, $n_passed) = $scraper->getStateProgress();
$status = $t_end?
    sprintf('Completed (at %s)', date('Y.m.d, H:i:s', $t_end)):
    ($b?
        sprintf('In progress: %d/%d pages processed', $n_passed, $n_total) :
        sprintf('Cancelled since another script instance is still running (%d secs elapsed)', time() - $t_run)
    );

?>
<h2 style="text-align: center">IMDB scraper</h2>
<pre><b>Status:</b> <u><?= $status ?></u></pre>
<?php
if ($t_end && ($a_result = outResults($scraper->getResults()))) {
?>
<h3>Result:<br>
    Leading Actors of the <a href="http://www.imdb.com/chart/top">Top 250</a></h3>
<table cellpadding="3" border="1">
    <thead>
        <tr>
            <th rowspan="2">#</th>
            <th rowspan="2">Actor/Actress</th>
            <th rowspan="2">Appearances</th>
            <th rowspan="2">Rating</th>
            <th colspan="3">Career</th>
        </tr>
        <tr>
            <th>Movies</th>
            <th>Years</th>
            <th>Begin</th>
        </tr>
    </thead>
    <tbody>
<?php
function outLink($url, $text) {
    return '<a href="'.$url.'">'.$text.'</a>';
}

    $i = 0;
    list($a_rows, $a_urls) = $a_result;
    foreach ($a_rows as $a_rec) {
?>
        <tr>
<?php
        $list = '';
        foreach ($a_rec['appears'] as $i_mov) {
            $list .= '<li>'. outLink($a_urls[$i_mov][0], $a_urls[$i_mov][1].
                ' (#'.($i_mov+1).')'). '</li>';
        }
        $list = str_replace('%%', count($a_rec['appears'])>1? 'ol' : 'ul',
            "<%%>$list</%%>"
        );
        $arr = array(
            ++$i, outLink($a_rec['url'],$a_rec['title']),
            $list, $a_rec['rating'],
            $a_rec['n_movies'], $a_rec['n_years'], $a_rec['begin']
        );
        foreach ($arr as $val) {
?>
            <td<?= is_numeric($val)? ' align="right"' : '' ?>>
                <?= is_numeric($val) && !is_int($val)? sprintf('%4.2f', $val): $val ?>
            </td>
<?php
        }
?>
        </tr>
<?php
    }
?>
    </tbody>
</table>
<?php
}
