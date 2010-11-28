<?php

/* Copyright (c) 2010, Benjamin Bittner; All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

  * Redistributions of source code must retain the above copyright notice,
    this list of conditions and the following disclaimer.

  * Redistributions in binary form must reproduce the above copyright notice,
    this list of conditions and the following disclaimer in the documentation
    and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA,
OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY,
WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE. */


/***************************************************************************** SETTINGS */

$title = "Simplicissimus";
$subtitle = "The essential blogging system.";
$entries_per_page = 5;
$theme = "earthrise";

/******************************************************************** PARSE URL REQUEST */

$blogpage = 1;
$show_next_page = FALSE;
$blogentry = "";
$namedpage = "";

if (count($_GET) == 1) {

  $valid_parameters = array("blogpage", "blogentry", "namedpage");
  foreach ($_GET as $key => $value)
  {
    /* check parameter name */
    if (!in_array($key, $valid_parameters))
      break;

    /* check value: blogpage */
    elseif ($key == "blogpage") {
      if (is_int($value) and ($value > 0))
        $blogpage = (int)$value;
    }

    /* check value: blogentry */
    elseif ($key == "blogentry") {
      // format: YYYYMMDD + ID
      if (is_numeric($value) and (strlen($value) > 8)) {

        $year = substr($value,0,4);
        $month = substr($value,4,2);
        $day = substr($value,6,2);
        $id = substr($value,8);

        $blogentry = "entries/$year/$month/$day/$id.txt";
        if (!file_exists($blogentry))
          $blogentry = "";
      }
    }

    /* check value: namedpage */
    elseif ($key == "namedpage") {
      $nVal = preg_replace("/[^a-zA-Z0-9_]/", "", $value);
      $namedpage = "pages/$nVal/$nVal.php";
      if (!file_exists($namedpage))
        $namedpage = "";
      else
        $namedpage = $nVal;
    }

    /* fallback option */
    else {
      $blogpage = 1;
      $show_next_page = FALSE;
      $blogentry = "";
      $namedpage = "";
      break;
    }
  } 
}


/************************************************** RENDERING FUNCTION: SHOW BLOG ENTRY */

function showEntry ($year, $month, $day, $id, $hide_paragraphs)
{
  echo "<div class=\"entry\">\n";
  $file = fopen("entries/$year/$month/$day/$id.txt", "r");

  if ($file) {
    $parsing_state = 0;

    // parser state machine
    //
    // 0: start
    // 1: reading title
    // 2: title done
    // 3: reading lead-in
    // 4: lead-in done
    // 5: reading paragraph
    // 6: paragraph done

    while (!feof($file) and (!$hide_paragraphs or ($parsing_state < 4))) {
      $line = trim(fgets($file));
      if (strlen($line) == 0) {
        if ($parsing_state == 1) {
          if ($hide_paragraphs)
            echo "</a>";
          echo "</div>\n";
          $parsing_state++;
        }
        elseif ($parsing_state == 3 or $parsing_state == 5) {
          echo "</div>\n";
          $parsing_state++;
        }
      }
      else {
        if ($parsing_state == 0) {
          echo "<div class=\"title\">\n";
          if ($hide_paragraphs)
            echo "<a href=\"index.php?blogentry=$year$month$day$id\">";
          $parsing_state = 1;
        }
        elseif ($parsing_state == 2) {
          echo "<div class=\"date\">$day-$month-$year</div>\n";
          echo "<div class=\"leadin\">\n";
          $parsing_state = 3;
        }
        elseif ($parsing_state == 4 or $parsing_state == 6) {
          echo "<div class=\"paragraph\">\n";
          $parsing_state = 5;
        }

        echo "$line \n";
      }
    }

    if ($parsing_state == 1 or $parsing_state == 3 or $parsing_state == 5)
      echo "</div>\n";
    if ($parsing_state == 0)
      echo "<p>Empty blog post.</p>";

    fclose($file);
  }
  else {
    echo "Error: Cannot read blog entry file.";
  }

  echo "</div>\n";
}

/**************************************************** RENDERING FUNCTION: BLOG BROWSING */

function myscandir($folder) {
  $dh  = opendir($folder);
  while (false !== ($filename = readdir($dh))) {
      $files[] = $filename;
  }
  rsort($files);
  return $files;
}

function showOverview ($blogpage)
{
  $num_entries_counted = 0;
  $num_entries_printed = 0;

  /* YEARS */
  $array_years = myscandir("entries/");
  for ($i = 0; $i < count($array_years); $i++)
  {
    $current_year = (int)$array_years[$i];
    if (!is_int($current_year) or !is_dir("entries/$current_year"))
      continue;

    /* MONTHS */
    $array_months = myscandir("entries/$current_year/");
    for ($j = 0; $j < count($array_months); $j++)
    {
      $current_month = $array_months[$j];
      if (!is_numeric($current_month) or !is_dir("entries/$current_year/$current_month"))
        continue;

      /* DAYS */
      $array_days = myscandir("entries/$current_year/$current_month/");
      for ($k = 0; $k < count($array_days); $k++)
      {
        $current_day = $array_days[$k];
        if (!is_numeric($current_day) or !is_dir("entries/$current_year/$current_month/$current_day"))
          continue;

        /* ENTRIES */
        $array_entries = myscandir("entries/$current_year/$current_month/$current_day/");
        for ($l = 0; $l < count($array_entries); $l++)
        {
          $current_entry = $array_entries[$l];

          if (is_dir("entries/$current_year/$current_month/$current_day/$current_entry"))
            continue;

          preg_match("/\.([^\.]+)$/", $current_entry, $matches);
          if ($matches[1] != "txt")
            continue;

          if ($num_entries_counted < (($blogpage - 1) * $entries_per_page)) {
            $num_entries_counted += 1;
            continue;
          }

          showEntry($current_year, $current_month, $current_day, substr($current_entry,0,-4), TRUE);
          $num_entries_printed += 1;
          if ($num_entries_printed >= $entries_per_page)
            return;
        }
      }
    }
  }

  if ($num_entries_printed >= $entries_per_page)
    $show_next_page = TRUE;

  if ($num_entries_printed == 0)
    echo "[no content found]";
}

/************************************************************* RENDERING FUNCTION: PAGE */

function showPage ($pagename)
{
  echo "<div id=\"page\">";
  if (file_exists("pages/$pagename/$pagename.php"))
    include("pages/$pagename/$pagename.php");
  else
    echo "<div id=\"warning\">File not found.</div>";
  echo "</div>";
}

/******************************************************************* START RENDERING */ ?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
"http://www.w3.org/TR/html4/strict.dtd">

<html>
  <head>
    <title><?php echo $title ?></title>
    <link rel="stylesheet" type="text/css"
          href="themes/<?php echo $theme ?>/style.css" />
  </head>
  <body><div id="container">

<!-- MENU -->

    <div id="menu">
      <span><a href="index.php?blogpage=1">blog</a></span>
      <?php
      $array_pages = myscandir("pages/");
      foreach ($array_pages as $cur_file) {
        if (is_dir("pages/$cur_file") && file_exists("pages/$cur_file/$cur_file.php"))
          echo "<span>|</span><span><a href=\"index.php?namedpage=$cur_file\">$cur_file</a></span>";
      }?>
    </div>

<!-- HEADER -->

    <div id="header">
      <div class="titles">
        <div class="title"><?php echo $title ?></div>
        <div class="subtitle"><?php echo $subtitle ?></div>
        <div class="logo"><img src="themes/<?php echo "$theme/logo.png" ?>"></div>
      </div>
    </div>

<!-- MAIN -->

    <div id="main">

      <div class="content">
        <?php

        if ($namedpage)
          showPage($namedpage);
        elseif ($blogentry)
          showEntry($year, $month, $day, $id, FALSE);
        else
          showOverview($blogpage);

        ?>
      </div>

      <div class="pagelink">
        <?php
        if ((!$blogentry & !$namedpage) and (($blogpage > 1) | $show_next_page)) {
          if ($blogpage > 1)
            echo "<a href=\"index.php?blogpage=".($blogpage - 1)."\">&lt;&lt;</a>";
          echo "page ".$blogpage;
          if ($show_next_page)
            echo " <a href=\"index.php?blogpage=".($blogpage + 1)."\">&gt;&gt</a>";
        }
        ?>
      </div>

    </div>

<!-- FOOTER -->

    <div id="footer"></div>

  </div></body>
</html>

