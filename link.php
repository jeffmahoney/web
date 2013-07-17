<?php

ini_set('display_errors', 1);
ini_set('error_log', "/tmp/link.errors");
ini_set('log_errors', 1);
error_reporting(E_ALL);

$conf = parse_ini_file('config.ini');

if (!isset($conf))
        die();

$username = $conf['username'];
$password = $conf['password'];
$database = $conf['db'];
$hostname = $conf['hostname'];

session_name("linkbot_session");
session_set_cookie_params(1800, "/", "ice-nine.org");
session_start();
#session_regenerate_id();

class LinkBase
{
    public $user = Null;
    function __construct(array $array) {
        $to = time();
        $from = $to - 24*60*60;

        if (isset($array)) {
            if (isset($array['expand']))
                $expand = $array['expand'];
        }

        if (array_key_exists('USER', $_SESSION))
                $this->user = $_SESSION['USER'];
    }

    public static function regenerate_uri($array)
    {
        $uri = $_SERVER['SCRIPT_NAME'];
        $vars = http_build_query($array);
        if ($vars)
            $uri .= "?" . $vars;
        return $uri;
    }

    public static function shorten_url ($url)
    {
        $str = $url;
        $len = strlen ($url);
        if ($len > 63) {
            $mid = $len - 60;
            $mid /= 2;
            $str = substr ($url, 0, ($len / 2) - $mid);
            $str .=  "...";
            $str .= substr ($url, ($len / 2) + $mid,
                    $len - ($len / 2) - $mid);
            return $str;
        }
        return $url;
    }

    public static function urlstr_to_id ($str)
    {
        $map = "ABCDEFGHIJKLMNOPQRSTUVWXYZ" .
               "abcdefghijklmnopqrstuvwxyz1234567890";
        $base = strpos ($map, $str[0]);
        $num = 0;
        if ($base <= 1)
            throw new Exception("Invalid URLID base [$base] [$str]");

        for ($i = 1; $i < strlen ($str); $i++) {
            $pos = strpos ($map, $str[$i]);
            if ($pos >= $base)
                throw new Exception("URL ID Character out of range $pos >= $base");
            $num *= $base;
            $num += $pos;
        }

        return $num;
    }

    public static function id_to_urlstr ($id)
    {
        $map = "ABCDEFGHIJKLMNOPQRSTUVWXYZ" .
               "abcdefghijklmnopqrstuvwxyz1234567890";
        $base = $id % 34;
        $base += 26;
        #$base = rand (26, 60);
        $out = "";
        do {
            $index = $id % $base;
            $out = substr ($map, $index, 1) . $out;
            $id = $id / $base;
        } while ($id > $base);
        $out = substr ($map, $id, 1) . $out;
        return substr ($map, $base, 1) . $out;
    }

    public function authenticated() {
        if ($this->user)
            return 1;
        else
            return 0;
    }

    protected function header_start() {
?>
<!DOCTYPE html
     PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
               "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
  <title>Imaginary Bridges link archive</title>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta name="author" content="Jeffrey Mahoney" />
  <link rel="stylesheet" href="/jeffm/link.css" type="text/css" />
  <link rel="alternate" type="application/rss+xml" title="RSS" href="/l?rss" />
<?php
    }

    protected function header_finish() {
        ?></head><?php
    }

    protected function header() {
        $this->header_start();
        $this->header_finish();
    }

    protected function body_start() {
        $uri = $_SERVER['SCRIPT_NAME'];

        if ($this->authenticated()) {
            $_GET['logout'] = "";
            $link = "Welcome, $this->user [<a class=\"header\" href=\"$uri?logout\">logout</a>]";
        } else {
            $link = "<a class=\"header\" href=\"$uri?login\">Login</a>";
        }
?>

<body>
  <div class="mainbox">
  <div class="headerbox">
  <h1><a class="header" href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">Imaginary Bridges URL database</a>  |  <?php echo $link; ?></h1>
  </div>
<?php
    }

    protected function body_finish() {
?>
  <p class="validations">
      <a href="http://validator.w3.org/check?uri=referer"><img
          src="/jeffm/images/xhtml.gif"
                  alt="Valid XHTML 1.0 Strict" height="15" width="80" /></a>
      <a href="http://jigsaw.w3.org/css-validator/validator?uri=<?php echo htmlspecialchars($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>"><img src="/jeffm/images/css.gif" alt="Valid CSS" /></a>
      <a href="<?php echo $_SERVER['PHP_SELF'];?>?rss"><img src="/jeffm/images/rssatom.gif" alt="RSS Feed" /></a>
  </p>
  </div>
</body>
</html>
<?php
    }

    public function body() {
        $this->body_start();
        $this->body_finish();
    }

    public function output() {
        $this->header();
        $this->body();
    }

    protected function __print_html_links($result, $field, $desc)
    {
        $self = $_SERVER['PHP_SELF'];

	while ($row = mysql_fetch_assoc($result)) {
	    $str = $this->id_to_urlstr($row['id']);
	    $count = $row[$field];
	    $url = $row['url'];
	    $nick = $row['nick'];
	    $title = $row['title'];
	    $alive = $row['alive'];
            if (!$this->expand)
                $surl = $this->shorten_url($url);

?>         <tr>
         <td><a href="<?php echo htmlspecialchars("$self?info&id=$str"); ?>" title="Get info on this link"><?php echo $count . " " . $desc ; ?></a></td>
         <td><?php echo $this->searchlink('nick', $nick); ?></td>
         <td><a title="<?php echo htmlspecialchars($url); ?>" <?php echo $alive ? "href=\"http://ice-nine.org/l/$str\">" : "><strike>" ?> <?php echo (($title) ? $this->shorten_url($title) : htmlspecialchars($surl)); ?><?php echo $alive ? "" : "</strike>" ?></a></td>
           </tr>
<?php

        };
    }

    protected function print_html_links($result, $field, $desc)
    {
        echo "  <table>\n";
        $this->__print_html_links($result, $field, $desc);
        echo "  </table>\n";
    }

    public function searchlink ($field, $text)
    {
         if (!$this->authenticated())
             return "";
         return "<a href=\" " . htmlspecialchars($_SERVER['PHP_SELF'] .
            "?search&field=$field&terms=" . urlencode($text)) .
            "\" title=\"Search for links from $text\">$text</a>";
    }

    public $expand = '';
}

class LinkSearcher extends LinkBase
{
    public $field;
    public $page = 1;
    public $terms;
    public $results = 10;
    public $to;
    public $from;
    function __construct(array $array) {
        parent::__construct($array);

        if (isset($array['field'])) {

            $this->field = $array['field'];
        }
        if (isset($array['terms']))
            $this->terms = $array['terms'];
        if (isset($array['results']))
            $this->results = $array['results'];
        if (isset($array['page']))
            $this->page = $array['page'];
        if (isset($array['to']))
            $this->to = $array['to'];
        if (isset($array['from']))
            $this->from = $array['from'];
    }

    public function body() {

        $this->body_start();
        /* We already have the code for handling dates in the archive
         * section - just use that. If we can't figure out if it's
         * a valid date format, we can fail out further down. */
        if (isset($this->field)) {
            if ($this->field == 'date') {
                $date = $this->terms;
                $self = $_SERVER['PHP_SELF'];
                $ts = strtotime($date);
                if ($ts) {
                    $to = $ts + 24 * 60 * 60;
                    $url = "$self?archive&from=$ts&to=$this->to";
                    echo "Link to $url ($ts)\n";
                    return;
    //                header ("Location: $url");
                }
            }
            if (!$this->authenticated() && $this->field == "nick") {
                echo '<h2 class="error">You do not have permission to search by user.</h2>';
                $this->display_search_form();
                $this->body_finish();
                return;
            }

            $curpage = 0;
            $results = 10;
            $curpage = $this->page - 1;
            $results = $this->results ? $this->results : 10;


            $start = $curpage * $results;
            if ($start < 0)
                $start = 0;
            $q = "";

            if ($this->field == "date") {
                echo '<p class="error">ERROR: Invalid date format "' . $this->terms . '"</p>';
                $this->body_finish();
                return;
            }

            if ($this->field != "url" && $this->field != "nick") {
                echo "<p class=\"error\">ERROR: unknown field ($this->field) specified</p>";
                $this->body_finish();
                return;
            }

            if ($this->field == "url")
                $q .= "$this->field LIKE '%$this->terms%'";
            else
                $q .= "$this->field = '$this->terms'";

            $query  = "SELECT SQL_CALC_FOUND_ROWS * FROM url ";
            $query .= "WHERE $q GROUP BY url LIMIT 1";
            if ($result = mysql_query ($query)) {
                $result = mysql_query ("SELECT FOUND_ROWS() AS num");

                $rows = mysql_result ($result, 0, "num");
            } else
                echo "ERROR ($query) " . mysql_error() . "<br/>";

            $npages = ceil($rows / $results);

            if ($curpage < $npages) {
                $query  = "SELECT SUM(count) ";
                $query .= "AS sum, id, url, nick, last_seen, title, ";
                $query .= "alive FROM url WHERE $q GROUP BY url ";
                $query .= "ORDER BY last_seen DESC";

                if ($results > 0) $query .= " LIMIT $start, $results";

    //            echo "Query: $query<br/>\n";
                $result = mysql_query ($query);

                $num = mysql_numrows ($result);

                $end = $start + $results;
                if ($end > $rows || $results == 0)
                    $end = $rows;

                if ($rows > 0) {
                    echo "<h2>";
                    if ($this->field == "url")
                        echo "$rows URLs containing \"$this->terms\"";
                    else if ($this->field == "nick")
                        echo "$rows URLs posted by $this->terms";

                    echo ":</h2>\n";

                    $this->print_html_links($result, "sum", "views");

                    $this->handle_results_pagination($result, $rows);
                } else
                    echo "No results found.";
            } else {
                if ($rows > 0)
                    echo "Invalid page $curpage - must be between 0 and " . ($npages - 1);
                else
                    echo "No results found.";
            }
        }

        $this->display_search_form();

        $this->body_finish();
    }

    private function display_search_form() {
?>

    <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']) ?>" method="get">
    <p>Search
    <select name="field">
    <option <?php echo ($this->field == 'url') ? "selected=\"\"" : "" ?>value="url">URLs</option>
<?php if ($this->authenticated()) { ?>
        <option <?php echo ($this->field == 'nick') ? "selected=\"\"" : "" ?>value="nick">Nick</option>
<?php } ?>
    <option <?php echo ($this->field == 'date') ? "selected=\"\"" : "" ?>value="date">Date</option>
    </select>
    <input type="hidden" name="search" />
    <input name="terms" value="<?php echo $this->terms ?>" />
    <select name="results">
      <option <?php echo ($this->results == 10) ? "selected=\"\"" : "" ?>value="10">10 per page</option>
      <option <?php echo ($this->results == 20) ? "selected=\"\"" : "" ?>value="20">20 per page</option>
      <option <?php echo ($this->results == 50) ? "selected=\"\"" : "" ?>value="50">50 per page</option>
      <option <?php echo ($this->results == 100) ? "selected=\"\"" : "" ?>value="100">100 per page</option>
      <option <?php echo ($this->results == 0) ? "selected=\"\"" : "" ?>value="0">All Results</option>
    </select>
    <input type="submit" value="Search" />
    </p>
    </form>

<?php
    }

    function handle_results_pagination($result, $rows)
    {
        $expr = $this->terms;
        $results_per_page = $this->results ? $this->results : 10;
        $curpage = $this->page -1;

        if ($rows <= $results_per_page)
            return;

        $npages = ceil($rows / $results_per_page) - 1;

        echo "<p class=\"pagination\">Results by page: [ ";

        /* print "<" link */
        if ($curpage != 0) {
            echo "<a href=\"" . htmlspecialchars($_SERVER['PHP_SELF'] . "?search&field=$this->field&terms=" . urlencode($expr) . "&results=$results_per_page&page=" . ($curpage)) . "\" title=\"Results " . (($curpage - 1) * $results_per_page) . " - " . (($curpage * $results_per_page) - 1) . "\">";
            echo "&lt;";
            echo "</a>\n";
        }

        /* Print numbered links */
        $dots = 1;
        for ($i = 0; $i <= $npages; $i++) {
            if ($npages <= 10 || /* < 10 pages, always print */
                (($i < 3 || $i > $npages - 3) || /* first and last 3 */
                 (abs($curpage - $i) <= 4))) { /* 4 on either side */
                if ($curpage != $i) {
                    $np = min((($i + 1) * $results_per_page) - 1, $rows);;
                    echo "<a href=\"" . htmlspecialchars($_SERVER['PHP_SELF'] . "?search&field=$this->field&terms=" . urlencode($expr) . "&results=$results_per_page&page=" . ($i + 1)) . "\" title=\"Results " . ($i * $results_per_page) . " - $np\">";
                }
                echo ($i + 1);
                if ($curpage != $i)
                    echo "</a>";
                echo " ";
                $dots = 0;
            } else {
                if ($dots == 0)
                    echo " ... ";
                $dots = 1;
            }
        }

        /* Print ">" link */
        if ($curpage < $npages) {
            $np = min((($curpage + 2) * $results_per_page) - 1, $rows);;
            echo "<a href=\"" . htmlspecialchars($_SERVER['PHP_SELF'] . "?search&field=$this->field&terms=" . urlencode($expr) . "&results=$results_per_page&page=" . ($curpage + 2)) . "\" title=\"Results " . (($curpage + 1) * $results_per_page) . " - $np\">";
            echo "&gt;";
            echo "</a>\n";
        }
        echo "]</p>\n";
    }


}

class LinkRSSFeed extends LinkBase
{
    public $count = 40;
    function __construct(array $array) {
        parent::__construct($array);

        if (isset($array['count']))
            $this->count = $array['count'];

    }

    public function output() {
        header ('Content-Type: text/xml; charset=utf-8');
        echo "<?xml version='1.0' encoding='utf-8' ?>\n";
?>
<rss version='2.0' xmlns:atom="http://www.w3.org/2005/Atom">
 <channel>
  <title>Imaginary Bridges link archive</title>
  <description>A collection of links posted by bored nerds.</description>
  <link>http://ice-nine.org/l</link>
  <atom:link href="<?php echo htmlentities("http://" . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']);?>" rel="self" type="application/rss+xml" />
  <language>en-us</language>
  <copyright>Copyright (C) 2004-2012, Jeffrey Mahoney</copyright>
  <webMaster>jeffm@jeffreymahoney.com (Jeff Mahoney)</webMaster>
  <ttl>2</ttl>

<?php
        $count = 40;
        $frequency = 24 / 4;
        $now = time();

	$q  = "SELECT id, url, nick, last_seen, title, type, ";
	$q .= "description FROM url ORDER BY last_seen DESC ";
	$q .= "LIMIT 0,$this->count";

	$result = mysql_query ($q);
	$last = mysql_result ($result, 0, 'last_seen');
	$last = strtotime($last);

	$date = date( "r", $last);
	echo "<lastBuildDate>$date</lastBuildDate>\n";


	$q  = "SELECT id, url, nick, last_seen, title, type, nsfw, description FROM url ";
	$q .= "ORDER BY last_seen DESC ";
	$q .= "LIMIT 0,$this->count";

	$result = mysql_query($q);
	$this->print_rss_links($result);

        echo '</channel>';
        echo '</rss>';
    }

    function sanitize($string)
    {
	    $string = htmlentities($string);
	    $string = str_replace("&bull;", "&#149;", $string);
	    $string = str_replace("&laquo;", "&#171;", $string);
	    $string = str_replace("&raquo;", "&#187;", $string);
	    $string = str_replace("&mdash;", "--", $string);
	    $string = str_replace("&nbsp;", " ", $string);
	    return utf8_encode($string);
    }

    function print_rss_links($result)
    {
	while ($row = mysql_fetch_assoc($result)) {
            $id    = $row["id"];
            $nick  = $row["nick"];
            $url   = $row["url"];
            $seen  = $row["last_seen"];
            $title = trim($row["title"]);
            if ($title == "")
                $title = $this->shorten_url($url);
            $type  = $row["type"];
            $desc  = $row["description"];
	    $nsfw  = $row["nsfw"];

            $slink = "http://ice-nine.org/l/" . $this->id_to_urlstr($id);

	    if ($title == $url and (isset($desc) or $desc == "")) {
		$title = trim($desc);
		unset($desc);
	    }
	    $link = "<a href=\"$slink\" title=\"$url\">";
	    echo "  <item>\n";
	    echo "    <title>";
	    if ($nsfw > 0) {
		echo "(";
	    }
	    if ($nsfw & 2) {
		echo "NSFW ";
	    } else if ($nsfw & 1) {
		echo "~NSFW ";
	    }
	    if ($nsfw & 4) {
		if ($nsfw != 4) {
		    echo ",";
		}
		echo "SPOILERS";
	    }
	    if ($nsfw > 0) {
		echo ")";
	    }

	    if (isset($title) and $title != "") {
		    $title = $this->sanitize($title);
		    echo $title;
	    } else {
		    echo htmlentities($url);
	    }
	    echo "</title>\n";
	    echo "    <link>$slink</link>\n";
	    echo "    <guid isPermaLink=\"false\">$id</guid>\n";
	    echo "    <description>\n";
	    if (isset($desc)) {
		echo "    " . $this->sanitize($desc) . " . . .\n";
	    } else {
		if ($nsfw == 0) {
			if ($type != "" && strncmp($type, "image/", 6) == 0) {
			    echo htmlentities("$link<img src=\"$url\" ></a>\n");
			} else if (strncmp($type, "audio/", 6) == 0) {
			    $audio  = "<audio controls=\"controls\" preload=\"metadata\">\n";
			    $audio .= " <source src=\"$url\" type=\"$type\" />\n";
			    $audio .= "</audio>\n";
			    echo htmlentities($audio);
			} else if ($type == "application/x-shockwave-flash") {
			    $flash = "" .
	"<object classid=\"clsid:d27cdb6e-ae6d-11cf-96b8-444553540000\" width=\"550\" height=\"400\" id=\"movie_name\" align=\"middle\">\n" .
	"    <param name=\"movie\" value=\"$url\"/>\n" .
	"    <!--[if !IE]>-->\n" .
	"    <object type=\"application/x-shockwave-flash\" data=\"$url\" width=\"550\" height=\"400\">\n" .
	"        <param name=\"movie\" value=\"$url\"/>\n" .
	"	<param name=\"play\" value=\"false\" />\n" .
	"    <!--<![endif]-->\n" .
	"        <a href=\"http://www.adobe.com/go/getflash\">\n" .
	"            <img src=\"http://www.adobe.com/images/shared/download_buttons/get_flash_player.gif\" alt=\"Get Adobe Flash player\"/>\n" .
	"        </a>\n" .
	"    <!--[if !IE]>-->\n" .
	"    </object>\n" .
	"    <!--<![endif]-->\n" .
	"</object>\n";
			    echo htmlentities($flash);
			} else {
			    echo "      $title\n";
			}
		} else {
		    echo "      $title\n";
		}
	    }
	    if ($this->authenticated())
		echo htmlentities ("      <br/>Link posted by $nick");
	    echo "    </description>\n";
	    if ($this->authenticated())
		echo "    <author>$nick</author>\n";
	    echo "    <pubDate>" .
		 date("r", strtotime($seen)) .
		 "</pubDate>\n";
	    echo "  </item>\n";
        }
    }
}

class LinkRedirector extends LinkBase
{
    public $id;
    public $info = 0;

    function __construct(array $array, $id) {
        parent::__construct($array);


        $this->id = $this->urlstr_to_id($id);

        if (isset($array['info']))
            $this->info = 1;
    }

    private function getlink() {
        $query  = "SELECT SUM(usecount) AS viewed, SUM(count) AS " .
                  "posted, id,url,nick,first_seen," .
              "last_seen,title,alive FROM url WHERE id = " .
              "$this->id GROUP BY url";
        $result = mysql_query ($query);

	$values = mysql_fetch_assoc($result);
	$values['posted'] -= 1;
        return $values;
    }

    private function print_info() {
        echo '<p class="linkinfo">';

        $values = $this->getlink();
        $tsval = strtotime($values['first_seen']);
        $ts = date("r", $tsval);
        echo "Link: <a href=\"http://ice-nine.org/l/" .
             $this->id_to_urlstr($this->id) . "\" title=\"" .
             htmlspecialchars($values['url']) . "\">";

        if ($values['title'])
            echo $values['title'];
        else
            echo htmlspecialchars($this->shorten_url($values['url']));
        echo "</a>" . ($values['alive'] ? "" : " (Dead link)") . "<br/>";
        echo "Random facts:</p>\n";
        echo "<ul>\n";
        echo "<li>First posted ";
        if ($this->authenticated()) {
            echo " by ";
            echo $this->searchlink('nick', $values['nick']);
        }
        echo " at " .
             "<a href=\"" . htmlspecialchars($_SERVER['PHP_SELF']) .
             "?archive&from=$tsval\">" . date("r", $tsval) .
             "</a></li>\n";


        if ($values['posted'] > 0) {
            echo "<li>It has been posted " . $values['posted'] .
            " time" . (($values['posted'] != 1) ? "s" : "") .
            " since, most recently at <a href=\"" .
                 htmlspecialchars($_SERVER['PHP_SELF'] .
                 "?archive&from=$ts") . "\">" .
                 date("r", strtotime($values['last_seen'])) .
                 "</a></li>";
        }
        echo "<li>It has been followed " .
             (($values['viewed'] == 1) ? "once" : $values['viewed'] ." times") . "</li>\n";

        $ord = "th";
        if ($this->id < 10 || $this->id > 20) {
            if ($this->id % 10 == 1)
                $ord = "st";
            else if ($this->id % 10 == 2)
                $ord = "nd";
            else if ($this->id % 10 == 3)
                $ord = "rd";
        }

        echo "<li>It was the $this->id$ord link posted.</li>";
        echo "</ul>\n";
    }

    private function update_count() {
        $query  = "UPDATE url SET usecount = usecount + 1, " .
                  "last_seen = last_seen WHERE id = $this->id";
        $rq = @mysql_query($query);
    }

    private function check_cookies() {
        if (isset($_COOKIE['forward']) &&
             $_COOKIE['forward'] == 'direct')
            return 1;

        if (isset($_SERVER['HTTP_REFERER']) &&
            ($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ==
            preg_replace ("#http://#", "", $_SERVER['HTTP_REFERER']))) {
            $ts = 0x7fffffff;
            if (isset($_COOKIE['forward']) &&
                $_COOKIE['forward'] == "direct") {
                setcookie ('forward', '', $ts);
            } else {
                setcookie ('forward', 'direct', $ts);
            }
            return 1;
        }
        return 0;
    }

    private function url() {
        $values = $this->getlink();

        if (isset($values))
            return $values['url'];

        throw new Exception("Invalid URL requested");
    }

    public function header() {
        if ($this->info == 0) {
            $this->update_count();

            if ($this->check_cookies()) {
                header("Location: " . $this->url());
                return;
            }
            $this->header_start();
            echo '<meta http-equiv="refresh" ' .
                 'content="1;url=' . $this->url() . '" />';
            $this->header_finish();
        } else {
            parent::header();
        }
    }

    public function body() {
        $results = $this->getlink();
        $this->body_start();

        if (!isset($results['url'])) {
            echo '<p class="error">ERROR: Unknown URL ID ' .
                 'specified.</p>';
            $this->body_finish();
            return;
        }

        $this->print_info();
        if ($this->info == 0)
            echo '<p class="validations">Click <a href="">here' .
                 '</a> to skip this screen next time.</p>';

    }
}

class LinkPage extends LinkBase
{
    public $limit = 15;
    public $expand = 0;
    public $archive = 0;
    public $to;
    public $from;

    function __construct(array $array) {
        parent::__construct($array);

        $this->to = time();
        $this->from = $this->to - 6*60*60;

        if (isset($array['limit']))
            $this->limit = $array['limit'];
        if (isset($array['expand']))
            $this->expand = $array['expand'];
        if (isset($array['archive']))
            $this->archive = 1;
        if (isset($array['from'])) {
            echo "val=" . $array['from'];
            $this->from = $array['from'];
            $this->to = $this->from - 6*60*60;
        }
        if (isset($array['to']))
            $this->to = $array['to'];
    }

    public function body() {
        $this->body_start();

        if ($this->archive)
            $this->print_archive();
        else
            $this->print_top_lists();

        $this->body_finish();
    }

    private function print_html_options($limit)
    {
        $self = $_SERVER['PHP_SELF'];


        echo '<p class="html_opts">[';

        if ($limit >= 15)
            echo "<a href=\"$self?limit=" .
                 ($limit + 15) . "\">Show More</a> | ";

        if (max($this->limit - 15, 15) >= 15 && $this->limit > 15) {
            echo "<a href=\"$self?limit=" .
                 max(($this->limit - 15), 15) . "\">Show Less</a> | ";
        }
        echo "<a href=\"$self?search\">Search URLs</a> ]</p>";
    }

    public function print_archive() {
        $mult = 60 * 60 * 6; /* 6 hours */
        $from_ts = strftime ("%Y-%m-%d %H:%M:%S", $this->from);
        $to_ts = strftime ("%Y-%m-%d %H:%M:%S", $this->to);

        $query = "SELECT SUM(count) AS sum,id,url,nick,title,alive " .
                 "FROM url WHERE " .
             "first_seen >= \"$from_ts\" AND first_seen <= " .
             "\"$to_ts\" GROUP BY url ORDER BY last_seen ASC ";
        if ($this->limit > 0)
            $query .= "LIMIT 0, $this->limit";
        $result = mysql_query ($query);
        echo "  <h2>Links posted between $from_ts and $to_ts</h2>\n";
        $this->print_html_links($result, "sum", "views");
        $this->print_html_options($this->limit);
    }

    public function print_top_lists() {
        ?>
        <p class="jump_to_link">
        <form method=get>
        Jump to Link: <input name=id>
        </form></br>
        </p>
        <?php

        $this->print_html_options($this->limit);
        echo "  <h2>Last $this->limit posted links</h2>\n";
        $query  = "SELECT id, url, nick, last_seen, title, alive FROM url ";
        $query .= "ORDER BY last_seen DESC ";
        $query .= "LIMIT 0,$this->limit;";
        $result = mysql_query ($query);
        $this->print_html_links($result, "last_seen", "");

        $query  = "SELECT SUM(usecount) AS sum,id,url,nick,title," .
                  "alive FROM url";
        if ($this->limit == 0)
            $query .= " AND usecount >= 5";
               $query .= " GROUP BY url ORDER BY sum DESC, last_seen ASC";
        if ($this->limit > 0)
            $query .= " LIMIT 0, $this->limit";

        $result = mysql_query ($query);

        $this->print_html_options($this->limit);

        echo "  <h2>Viewed most often (top $this->limit)</h2>\n";
        $this->print_html_links($result, "sum", "views");

        $this->print_html_options($this->limit);
        $query = "SELECT SUM(count) AS sum,id,url,nick,title," .
                 "alive FROM url ";
        if ($this->limit == 0)
            $query .= "WHERE usecount >= 5 ";
        $query .= "GROUP BY url ORDER BY sum DESC, last_seen ASC ";
        if ($this->limit > 0)
            $query .= "LIMIT 0, $this->limit";

        $result = mysql_query ($query);

          echo "  <h2>Posted most often (top $this->limit)</h2>\n";
        $this->print_html_links($result, "sum", "posts");

        $this->print_html_options($this->limit);
    }

}

class LinkLogin extends LinkBase
{
    public $auth_failed = 0;
    public $auth_success = 0;
    public $user = False;
    public $pass = False;
    function __construct(array $array) {
        parent::__construct($array);
        if (isset($_POST['username']))
            $this->user = $_POST['username'];
        if (isset($_POST['password']))
            $this->pass = $_POST['password'];

        $this->auth();
    }
    function auth()
    {
        if (!$this->user || !$this->pass)
            return;
        $user = $this->user;
        $pass = $this->pass;

        try {
            $user = stripslashes($user);
            $pass = stripslashes($pass);

            $user = mysql_real_escape_string($user);
            $pass = mysql_real_escape_string($pass);

            $q = "SELECT * FROM auth WHERE username = '$user' and password = PASSWORD('$pass')";
            $result = mysql_query($q);

            if ($result) {
                $count = mysql_num_rows($result);

                if ($count == 1) {
                    $this->auth_success = 1;
                } else {
                    $this->auth_failed = 1;
                }
            } else {
                $this->auth_failed = 1;
            }

        } catch (Exception $e) {
            exit($e->getMessage());
        }
    }

    function output() {
        unset($_GET['login']);
        $uri = $this->regenerate_uri($_GET);
        if ($this->auth_success) {
            $_SESSION['USER'] = $this->user;
            session_commit();
            header("Location: $uri");
            exit();
        }
        $this->header();
        $this->body_start();

        if ($this->auth_failed) {
            echo '<h2 class="error">Invalid username/password combination.</h2>';
        }

        if (isset($_SESSION['USER'])) {
            echo '<h2 class="error">You are already logged in.</h2>';
        } else {
            $uri = $_SERVER['REQUEST_URI'];

?>
    <form action="<?php echo $uri;?>" name="login" method="post">
    <label for="username" id="label_username">Username:</label>
    <input type="text" name="username"><br/>
    <label for="password" id="label_password">Password:</label>
    <input type="password" name="password"><br/>
    <label></label><input type="submit" value="Login" name="login">
    </form>
<?php
        }
        $this->body_finish();
    }
}

class LinkLogout extends LinkBase
{
    function __construct(array $array) {
        parent::__construct($array);
    }

    function output() {
#        $_SESSION['USER'] = False;
        unset($_SESSION['USER']);
        unset($_GET['logout']);
        $uri = $this->regenerate_uri($_GET);
        session_commit();
        header("Location: $uri");
        exit();
    }
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
} else {
    $name = $_SERVER['SCRIPT_NAME'];
    $uri = preg_replace ('#^http://[^/]*#', "", $_SERVER['SCRIPT_URI']);
    $pattern = "#^${name}#";
    $args = preg_replace ($pattern, "", $uri);
    $id = preg_replace ('#^/#', "", $args);
}

$mysql_link = mysql_connect ($hostname, $username, $password) or die( "Couldn't connect to db: " . mysql_error());
@mysql_select_db ($database) or die ("Unable to select database");

try {

    if (isset($_GET['login']))
        $session = new LinkLogin($_GET);
    else if (isset($_GET['logout']))
        $session = new LinkLogout($_GET);
    else if (isset($_GET['search']))
        $session = new LinkSearcher($_GET);
    else if ($id)
        $session = new LinkRedirector($_GET, $id);
    else if (isset($_GET['rss']))
        $session = new LinkRSSFeed($_GET);
    else
        $session = new LinkPage($_GET);

} catch (Exception $e) {
    exit($e->getMessage());
}

$session->output();
session_commit();

?>
