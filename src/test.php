<?php
// TODO


class Handles {
  public $dir = ".";
  public $parser = "parse.php";
  public $intr = "interpret.py";
  public $xml = "/pub/courses/ipp/jexamxml/jexamxml.jar";
  public $cfg = "/pub/courses/ipp/jexamxml/options";
}

$handles = new Handles();
$recurs = false;

parse_opts($argc, $argv, $handles, $recurs);
check_files($handles);

$record = iterate_tests($handles, $recurs);
// echo "PASSED: " . $record[0] . "\nFAILED: " . $record[1] . "\n";
//
// echo $record[2];

handle_output($record);

function parse_opts($argc, $argv, &$handles, &$recurs) {
  for ($i = 1; $i < $argc; $i++) {
    switch ($argv[$i]) {
      case "--help":
        if ($argc > 2) {
          fwrite(STDERR, "INVALID OPTIONS: Cannot combine --help with others\n");
          exit(10);
        }
        else {
          echo "This is Help for module test.php\n";
          echo " - Run using `php7.4 test.php {options} <input`\n";
          echo "Options:\n";
          // TODO

        }
        exit(0);

      case "--recursive":
        $recurs = true;
        break;

      case "--parse-only":
        if (count(preg_grep("/^--int-/", $argv)) != 0) {
          fwrite(STDERR, "INVALID OPTIONS: Cannot combine --parse-only & interpret options\n");
          exit(10);
        }
        $handles->intr = "";
        break;

      case "--int-only":
        if (count(preg_grep("/^--parse-/", $argv)) != 0) {
          fwrite(STDERR, "INVALID OPTIONS: Cannot combine --int-only & parse options\n");
          exit(10);
        }
        $handles->parser = "";
        break;

      default: // check for opts with parameter
        if (preg_match("/^--directory=/", $argv[$i])) {
          $handles->dir = str_replace("--directory=", "", $argv[$i]);
        }
        else if (preg_match("/^--parse-script=/", $argv[$i])) {
          $handles->parser = str_replace("--parse-script=", "", $argv[$i]);
        }
        else if (preg_match("/^--int-script=/", $argv[$i])) {
          $handles->intr = str_replace("--int-script=", "", $argv[$i]);
        }
        else if (preg_match("/^--jexamxml=/", $argv[$i])) {
          $handles->xml = str_replace("--jexamxml=", "", $argv[$i]);
        }
        else if (preg_match("/^--jexamcfg=/", $argv[$i])) {
          $handles->cfg = str_replace("--jexamcfg=", "", $argv[$i]);
        }
        else {
          fwrite(STDERR, "INVALID OPTIONS: Unknown option\n");
          exit(10);
        }
        break;
    }
  }
}


function iterate_tests($handles, $recurs) {
  $passed = 0; $failed = 0; $failed_list = "";
  $ret = 0;

  if ($recurs) {
    // construct iterator
    $it = new RecursiveDirectoryIterator($handles->dir);
    // iterate files recursively
    foreach (new RecursiveIteratorIterator($it) as $file) {
      $out = "";  // for XML output

      if ($file->getExtension() == "src") {
        $filename = realpath($file);

        // EXECUTION
        $command = "php7.4 " . $handles->parser . " <$filename 2>/dev/null";
        exec($command, $out, $ret);

        // VALIDATION
        // check return values
        if ($ret == get_ret($filename)) {
          if ($ret != 0) { // test case passed && parsing failed
            $passed++; continue;
          } // XML output needs to be checked otherwise

          if (check_output($out, $filename, $handles)) {
            $passed++;
          }
          else { // TODO
            $failed++;
            $failed_list .= "$filename\n";
          }
        }
        else  {
          $failed++;
          $failed_list .= "$filename\n";
        }
      }
    }
  }
  else {
    // iterate files in specified dir
    foreach (new DirectoryIterator($handles->dir) as $file) {
      $out = "";  // collects XML output

      if ($file->getExtension() == "src") {
        $filename = $handles->dir . $file->getFilename();

        // EXECUTION
        $command = "php7.4 " . $handles->parser . " <$filename 2>/dev/null";
        exec($command, $out, $ret);

        // VALIDATION
        // check return values
        if ($ret == get_ret($filename)) {
          if ($ret != 0) { // test case passed && parsing failed
            $passed++; continue;
          } // XML output needs to be checked otherwise

          if (check_output($out, $filename, $handles)) {
            $passed++;
          }
          else { // TODO
            $failed++;
            $failed_list .= "$filename\n";
          }
        }
        else  { // TODO
          $failed++;
          $failed_list .= "$filename\n";
        }
      }
    }
  }

  return array($passed, $failed, $failed_list);
}


// check the validity of given filenames
function check_files($filenames) {
  if (
    !is_dir($filenames->dir)
    || !file_exists($filenames->parser)
    || !file_exists($filenames->intr)
    || !file_exists($filenames->xml)
    || !file_exists($filenames->cfg)
  ){
    fwrite(STDERR, "ERROR: Invalid File was specified\n");
    exit(41);
  }
}

// returns expected return value of test case
// expects [string] filename (.src)
function get_ret($filename) {
  $filename = str_replace(".src", ".rc", $filename);
  $file = fopen($filename, "r");
  if (!$file) {
    fwrite(STDERR, "INVALID FILE: Expected return value (.rc)\n");
    exit(41);
  }
  return fgets($file);
}


// compares generated output with reference file
// expects [string] output & [string] filename (.src) & file handles
// returns [bool] TRUE if files matched, FALSE otherwise
function check_output($out, $filename, $handles) {
  $filename = str_replace(".src", ".out", $filename);

  // generate file with output
  if (file_exists("$filename.new")) {
    fwrite(STDERR, "ERROR: Failed to create file\n");
    exit(41);
  }
  $file = fopen("$filename.new", "w");
  if (!$file) {
    fwrite(STDERR, "ERROR: Failed to open file\n");
    exit(41);
  }
  fwrite($file, implode("\n", $out));

  // compare files using A7Soft JExamXML
  $ret = 0;
  $command = "java -jar " . $handles->xml .
    " $filename $filename.new /dev/null " . $handles->cfg;
  exec($command, $out, $ret);

  // TEAR DOWN
  exec("rm -f $filename.new $filename.new.log");

  return $ret == 0;
}


function handle_output($record) {
  $passed = $record[0];
  $failed = $record[1];
  $total = $passed + $failed;
  echo "<!DOCTYPE html>
  <html>
  <head>
    <style>
      th {
        width: 100px;
        padding: 5px;
      }
      table tr#ROW1  {background-color:#7bb6ed}
      table tr#ROW2  {background-color:#ed7b7b}
      table tr#ROW3  {background-color:#7bed7f}
    </style>
  </head>
  <body>
  <table>
    <tr id=\"ROW1\">
      <th style=\"text-align:left\"><b>TOTAL</b></th>
      <th style=\"text-align:right\">$total</th>
      <th style=\"text-align:right\">%</th>
    </tr>
    <tr id=\"ROW2\">
      <th style=\"text-align:left\"><b>FAILED</b></th>
      <th style=\"text-align:right\">$failed</th>
      <th style=\"text-align:right\">". get_perc($failed, $total) ."</th>
    </tr>
    <tr id=\"ROW3\">
      <th style=\"text-align:left\"><b>PASSED</b></th>
      <th style=\"text-align:right\">$passed</th>
      <th style=\"text-align:right\">". get_perc($passed, $total) ."</th>
    </tr>
  </table>
  </body>
  </html>";
}

function get_perc($val, $total) {
  if ($total == 0) return number_format(0, 2);
  return number_format(($val / $total) * 100, 2);
}
?>
