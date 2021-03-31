<?php
/*
 * File: test.php
 * Brief: Testing script
 *
 * Project: Interpret for imperative programing language IPPcode21
 *
 * Authors: Jakub Bartko    xbartk07@stud.fit.vutbr.cz
 */


// file handles & global flags
class Handles {
  public $dir = ".";              // starting directory
  public $parser = "parse.php";   // parser script
  public $intr = "interpret.py";  // interpret script
  // XML diff tool
  public $xml = "/pub/courses/ipp/jexamxml/jexamxml.jar";
  // XML diff tool config
  public $cfg = "/pub/courses/ipp/jexamxml/options";
  // flags
  public $recurs = false; // recursive traversal of files
  public $parse = true;   // run src files through: parser
  public $interp = true;  //                        interpret
}

// counters & trackers of tests
class Outputs {
  public $passed = 0;
  public $failed = 0;
  public $test_list = "";
  public $passed_list = "";
  public $failed_list = "";
}


$handles = new Handles();
$outputs = new Outputs();

handle_opts($argc, $argv, $handles);
iterate_tests($handles, $outputs);
print_output($outputs);


// handles commandline options
// expects options (as argc & argv) & [object ref] handles
// returns specified options as [object] handles & flags -- in given reference
function handle_opts($argc, $argv, &$handles) {
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
          echo "\nOptions:\n";
          echo "  --help\t\tDisplays this help\n";
          echo "  --directory=[path]\tLooks for tests in specified dir; Uses current dir otherwise\n";
          echo "  --recursive\t\tIterates from specified dir recursively\n";
          echo "  --parse-script=[file]\tSpecifies parser script; Uses parse.php in current dir othwerise\n";
          echo "  --int-script=[file]\tSpecifies interpreter script; Uses interpret.php in current dir othwerise\n";
          echo "  --parse-only\t\tUses only parsing script; Does not combine with `--int-only` nor `--int-script`\n";
          echo "  --int-only\t\tUses only interpreter script\n\t\t\tDoes not combine with `--parse-only` nor `--parse-script`\n";
          echo "  --jexamxml=[file]\tSpecifies location of A7Soft JExamXML tool\n\t\t\tUses /pub/courses/ipp/jexamxml/jexamxml.jar otherwise\n";
          echo "  --jexamcfg=[file]\tSpecifies location of A7Soft JExamXML configuration\n\t\t\tUses /pub/courses/ipp/jexamxml/options otherwise\n\n";
        }
        exit(0);

      case "--recursive":
        $handles->recurs = true;
        break;

      case "--parse-only":
        if (count(preg_grep("/^--int-/", $argv)) != 0) {
          fwrite(STDERR, "INVALID OPTIONS: Cannot combine --parse-only & interpret options\n");
          exit(10);
        }
        $handles->interp = false;
        break;

      case "--int-only":
        if (count(preg_grep("/^--parse-/", $argv)) != 0) {
          fwrite(STDERR, "INVALID OPTIONS: Cannot combine --int-only & parse options\n");
          exit(10);
        }
        $handles->parse = false;
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
  check_files($handles);
}


// iterates specified tests
//  & generates test filesys trees in HTML format
// expects [object] file handles & [object ref] output counters & logs
// returns stats of run tests in given [object ref] outputs trackers
function iterate_tests($handles, &$outputs) {
  if ($handles->recurs) {
    // construct iterator
    $it = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($handles->dir, FilesystemIterator::SKIP_DOTS),
      RecursiveIteratorIterator::SELF_FIRST
    );
    // iterate files recursively
    foreach ($it as $file) {
      if ($file->isDir()) {
        $outputs->test_list .= str_repeat("&emsp;", $it->getDepth() * 2) . $file->getFilename() . "/<br>";
      } elseif ($file->getExtension() == "src") {
        $outputs->test_list .= str_repeat("&emsp;", $it->getDepth() * 2);
        if (run_test($file, $handles)) {
          $outputs->passed++;
          $outputs->test_list .= "<span style=\"color: green\">&#9635;</span>";
          $outputs->passed_list .= "&emsp;" . $file . "<br>";
        }
        else {
          $outputs->failed++;
          $outputs->test_list .= "<span style=\"color: red\">&#9635;</span>";
          $outputs->failed_list .= "&emsp;" . $file . "<br>";
        }
        $outputs->test_list .= $file->getFilename() . "<br>";
      }
      continue;
    }
  }
  else {
    // add main dir to test list
    $outputs->test_list .= $handles->dir . "<br>";

    // iterate files in specified dir
    foreach (new DirectoryIterator($handles->dir) as $file) {
      if ($file->getExtension() == "src") {
        if (run_test($file->getPathname(), $handles)) {
          $outputs->passed++;
          $outputs->test_list .= "<span style=\"color: green\">&#9635;</span>";
          $outputs->passed_list .= "&emsp;" . $file->getPathname() . "<br>";
        }
        else {
          $outputs->failed++;
          $outputs->test_list .= "<span style=\"color: red\">&#9635;</span>";
          $outputs->failed_list .= "&emsp;" . $file->getPathname() . "<br>";
        }
        $outputs->test_list .= "&emsp;" . $file->getFilename() . "<br>";
      }
    }
  }
}


// test execution & validation
// expects [string] test filename (.src) & [object] file handles
// returns TRUE if test passed, FALSE otherwise
function run_test($filename, $handles) {
  check_test_files($filename);

  $out = "";  // for XML output
  $ret = 0;   // return value

  // EXECUTION
  if ($handles->parse) {
    if ($handles->interp) { // parse && interpret
      $command = "php7.4 " . $handles->parser . " <$filename 2>/dev/null | python3.8 " . $handles->intr . " --input=" . str_replace(".src", ".in", $filename);
    }
    else {                  // parse only
      $command = "php7.4 " . $handles->parser . " <$filename 2>/dev/null";
    }
  }
  else {                    // interpret only
    $command = "python3.8 " . $handles->intr . " --source=" . $filename . " --input=" . str_replace(".src", ".in", $filename) . " 2>/dev/null";
  }

  exec($command, $out, $ret);

  // VALIDATION
  // check return values
  if ($ret == get_ret($filename)) {
    if ($ret != 0) { // test case passed && parsing failed
      return true;
    } // XML output needs to be checked otherwise
    if (check_output($out, $filename, $handles)) {
      return true;
    }
    else return false;
  }
  else {
    return false;
  }
}


// checks whether all necessary test files for
//  given [string] filename are present
// generates default {.in, .out, .rc} files if missing
function check_test_files($filename) {
  if (!file_exists($tmp = str_replace(".src", ".in", $filename))) {
    touch($tmp);
  }
  if (!file_exists($tmp = str_replace(".src", ".out", $filename))) {
    touch($tmp);
  }
  if (!file_exists($tmp = str_replace(".src", ".rc", $filename))) {
    $file = fopen($tmp, "w");
    if (!$file) {
      fwrite(STDERR, "ERROR: Failed to create \"$tmp\"\n");
      exit(41);
    }
    fwrite($file, 0);
    fclose($file);
  }
}


// checks the validity of given [object] filenames
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
  $ret = fgets($file);
  fclose($file);

  return $ret;
}


// compares generated output with reference file
// expects [string] output & [string] filename (.src) & [object] file handles
// returns [bool] TRUE if files matched, FALSE otherwise
function check_output($out, $filename, $handles) {
  $filename = str_replace(".src", ".out", $filename);

  // generate file with output
  if (file_exists("$filename.new")) {
    fwrite(STDERR, "ERROR: Failed to create file \"$filename.new\"\n");
    exit(41);
  }
  $file = fopen("$filename.new", "w");
  if (!$file) {
    fwrite(STDERR, "ERROR: Failed to open file\n");
    exit(41);
  }
  fwrite($file, implode("\n", $out));
  fclose($file);

  // compare files
  $ret = 0;
  if ($handles->interp) { // compare files using unix diff
    $command = "diff $filename $filename.new >/dev/null";
    exec($command, $out, $ret);
  }
  else {  // compare files using A7Soft JExamXML
    $command = "java -jar " . $handles->xml .
    " $filename $filename.new /dev/null " . $handles->cfg;
    exec($command, $out, $ret);

    // TEAR DOWN
    exec("rm -f $filename.new $filename.log");
  }

  return $ret == 0;
}


// prints output of testing in HTML format to STDIN
// expects [object] output handles
function print_output($outputs) {
  $passed = $outputs->passed;
  $failed = $outputs->failed;
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

      .collapsible {
        background-color: #eee;
        color: #444;
        cursor: pointer;
        padding: 18px;
        width: 100%;
        border: none;
        text-align: left;
        outline: none;
        font-size: 15px;
      }

      .active, .collapsible:hover {
        background-color: #ccc;
      }

      .content {
        padding: 0 18px;
        display: none;
        overflow: hidden;
        background-color: #f1f1f1;
      }
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
      <th style=\"text-align:right\">". get_perc($outputs->failed, $total) ."</th>
    </tr>
    <tr id=\"ROW3\">
      <th style=\"text-align:left\"><b>PASSED</b></th>
      <th style=\"text-align:right\">$passed</th>
      <th style=\"text-align:right\">". get_perc($outputs->passed, $total) ."</th>
    </tr>
  </table><br>
  <button type=\"button\" class=\"collapsible\">All</button>
  <div class=\"content\">
    <p>" . $outputs->test_list . "</p>
  </div>
  <button type=\"button\" class=\"collapsible\">Passed</button>
  <div class=\"content\">
    <p>" . $outputs->passed_list . "</p>
  </div>
  <button type=\"button\" class=\"collapsible\">Failed</button>
  <div class=\"content\">
    <p>" . $outputs->failed_list . "</p>
  </div>
  <script>
  var coll = document.getElementsByClassName(\"collapsible\");
  var i;

  for (i = 0; i < coll.length; i++) {
    coll[i].addEventListener(\"click\", function() {
      this.classList.toggle(\"active\");
      var content = this.nextElementSibling;
      if (content.style.display === \"block\") {
        content.style.display = \"none\";
      } else {
        content.style.display = \"block\";
      }
    });
  }
  </script>
  </body>
  </html>";
}


// returns [float] percentage of given [int] value in [int] total
//  rounded to 2 decimal places
function get_perc($val, $total) {
  if ($total == 0) return number_format(0, 2);
  else return number_format(($val / $total) * 100, 2);
}
?>
