<?php

/* To Do

   - code review, testing

        ~ invalid parameter names
        ~ invalid parameter values
        ~ multiple correct parameters
        ~ multiple semi-correct parameters

        ~ invalid folder structure
        ~ invalid blog entry filename
        ~ invalid page filename

        ~ various directory/file permissions
        ~ determine minimum necessary permissions

        ~ test corner cases in showEntry parser

        ~ test with 9,10,11,19,20,21,29,30,31 entries

   - features

        ~ improve html print format for getting nicer html code
        ~ blog entry files with alphanumeric filenames

*/

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
$entriesPerPage = 10;
$theme = "earthrise";

/******************************************************************** PARSE URL REQUEST */

function getUrlParameters()
{
  /* default values */
  $blogpage = 1; // page number
  $namedpage = NULL; // "name"
  $blogentry = NULL; // array($year, $month, $day, $id)

  /* only 1 parameter is allowed */
  if (count($_GET) == 1)
  {
    /* sanitize and validate parameter key */
    $parameter_key = array_keys($_GET);
    $parameter_key = preg_replace("/[^a-z]/", "", $parameter_key[0]);
    $valid_parameters = array("blogpage", "blogentry", "namedpage");

    if (in_array($parameter_key, $valid_parameters))
    {
      /* check value: blog page */
      if ($parameter_key == "blogpage")
      {
        $value = preg_replace("/[^0-9]/", "", $_GET[$parameter_key]);
        if ($value > 0)
          $blogpage = (int)$value;
      }

      /* check value: blog entry */
      elseif ($parameter_key == "blogentry")
      {
        // format: YYYYMMDD + ID
        $value = preg_replace("/[^0-9]/", "", $_GET[$parameter_key]);
        if (strlen($value) > 8)
        {
          $year = substr($value,0,4);
          $month = substr($value,4,2);
          $day = substr($value,6,2);
          $id = substr($value,8);

          if (file_exists("entries/$year/$month/$day/$id.txt"))
            $blogentry = array("year" => $year, "month" => $month,
                               "day" => $day, "id" => $id);
        }
      }

      /* check value: named page */
      elseif ($parameter_key == "namedpage")
      {
        $value = preg_replace("/[^a-zA-Z0-9_]/", "", $_GET[$parameter_key]);
        if (file_exists("pages/$value/$value.php"))
          $namedpage = $value;
      }
    }
  }

  return array("blogpage" => $blogpage,
               "namedpage" => $namedpage,
               "blogentry" => $blogentry);
}


/************************************************** RENDERING FUNCTION: SHOW BLOG ENTRY */

function showEntry ($blogentry, $hide_paragraphs)
{
  $year = $blogentry["year"];
  $month = $blogentry["month"];
  $day = $blogentry["day"];
  $id = $blogentry["id"];

  echo "<div class=\"entry\">\n";
  $entryfile = fopen("entries/$year/$month/$day/$id.txt", "r");

  if ($entryfile)
  {
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

    while (!feof($entryfile) and (!$hide_paragraphs or ($parsing_state < 4)))
    {
      $line = trim(fgets($entryfile));
      if (strlen($line) == 0)
      {
        if ($parsing_state == 1)
        {
          if ($hide_paragraphs)
            echo "</a>";
          echo "</div>\n";
          $parsing_state++;
        }
        elseif ($parsing_state == 3 or $parsing_state == 5)
        {
          echo "</div>\n";
          $parsing_state++;
        }
      }
      else
      {
        if ($parsing_state == 0) {
          echo "<div class=\"title\">\n";
          if ($hide_paragraphs)
            echo "<a href=\"index.php?blogentry=$year$month$day$id\">";
          $parsing_state = 1;
        }
        elseif ($parsing_state == 2)
        {
          echo "<div class=\"date\">$day-$month-$year</div>\n";
          echo "<div class=\"leadin\">\n";
          $parsing_state = 3;
        }
        elseif ($parsing_state == 4 or $parsing_state == 6)
        {
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

    fclose($entryfile);
  }
  else
  {
    echo "Error: Cannot read blog entry file.";
  }

  echo "</div>\n";
}

/**************************************************** RENDERING FUNCTION: BLOG BROWSING */

function scanDirectory($path, $onlyNumericDirectories) {
  $dh  = opendir($path);
  while (false !== ($filename = readdir($dh))) {
      if ($filename == "..")
        continue;
      if ($filename == ".")
        continue;
      if ($onlyNumericDirectories and preg_match ('/[^0-9]/', $filename))
        continue;
      if ($onlyNumericDirectories and !is_dir($path.$filename))
        continue;
      $files[] = $filename;
  }
  closedir($dh);
  if ($files != NULL)
    rsort($files);
  return $files;
}

function showOverview ($blogpage, $entriesPerPage)
{
  $numEntriesCounted = 0;
  $numEntriesPrinted = 0;

  /* YEARS */
  $arrayYears = scanDirectory("entries/",TRUE);
  foreach ($arrayYears as $currentYear)
  {
    $yearPath = "entries/$currentYear/";

    /* MONTHS */
    $arrayMonths = scanDirectory($yearPath,TRUE);
    foreach ($arrayMonths as $currentMonth)
    {
      $monthPath = $yearPath . "$currentMonth/";

      /* DAYS */
      $arrayDays = scanDirectory($monthPath,TRUE);
      foreach ($arrayDays as $currentDay)
      {
        $dayPath = $monthPath . "$currentDay/";

        /* ENTRIES */
        $arrayEntries = scanDirectory($dayPath,FALSE);
        foreach ($arrayEntries as $currentEntry)
        {
          if (is_dir($dayPath . $currentEntry))
            continue;

          preg_match("/\.([^\.]+)$/", $currentEntry, $matches);
          if ($matches[1] != "txt")
            continue;

          if ($numEntriesCounted < (($blogpage - 1) * $entriesPerPage)) {
            $numEntriesCounted += 1;
            continue;
          }

          $blogentry = array("year" => $currentYear,
                             "month" => $currentMonth,
                             "day" => $currentDay,
                             "id" => substr($currentEntry,0,-4));
          showEntry($blogentry, TRUE);
          $numEntriesPrinted += 1;
          if ($numEntriesPrinted >= $entriesPerPage)
            return $numEntriesPrinted;
        }
      }
    }
  }

  if ($numEntriesPrinted == 0)
    echo "[no content]";

  return $numEntriesPrinted;
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

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">

<html>
  <head>
    <title><?php echo $title ?></title>
    <link rel="stylesheet" type="text/css" href="themes/<?php echo $theme ?>/style.css" />
  </head>
  <body><div id="container">

<!-- MENU -->

    <div id="menu">
      <span><a href="index.php?blogpage=1">blog</a></span>
      <?php
      $arrayPages = scanDirectory("pages/",FALSE);
      foreach ($arrayPages as $curFile) {
        if (is_dir("pages/$curFile") && file_exists("pages/$curFile/$curFile.php"))
          echo "<span>|</span><span><a href=\"index.php?namedpage=$curFile\">$curFile</a></span>";
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

        $urlParameters = getUrlParameters();

        if ($urlParameters["namedpage"])
          showPage($urlParameters["namedpage"]);
        elseif ($urlParameters["blogentry"])
          showEntry($urlParameters["blogentry"], FALSE);
        else
          $numEntriesPrinted = showOverview($urlParameters["blogpage"], $entriesPerPage);

        ?>
      </div>

      <div class="pagelink">
        <?php
          if (!$urlParameters["blogentry"] and
              !$urlParameters["namedpage"] and
              $numEntriesPrinted != 0 and
              (($blogpage > 1) | $showNextPage))
          {
            if ($blogpage > 1)
              echo "<a href=\"index.php?blogpage=".($blogpage - 1)."\">&lt;&lt;</a>";
            echo "page ".$blogpage;
            if ($numEntriesPrinted == $entriesPerPage)
              echo " <a href=\"index.php?blogpage=".($blogpage + 1)."\">&gt;&gt</a>";
          }
        ?>
      </div>

    </div>

<!-- FOOTER -->

    <div id="footer"></div>

  </div></body>
</html>

