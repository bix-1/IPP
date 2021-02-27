<?php
// TODO
// unfuck output

// NOTE
// tests:
//    iterate:  -- iterate filesys
//      exec
//      validate
//      record:   -- passed / failed + list_of_failed
//    output:   -- html


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
echo "PASSED: " . $record[0] . "\nFAILED: " . $record[1] . "\n";



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
  $out = ""; $ret = 0;

  if ($recurs) {
    // construct iterator
    $it = new RecursiveDirectoryIterator($handles->dir);
    // iterate files
    foreach (new RecursiveIteratorIterator($it) as $file) {
      if ($file->getExtension() == "src") {
        // EXECUTION
        $name = str_replace(".src", "", $file);
        $command = "php7.4 " . $handles->parser .
          " <" . $file . " 2>/dev/null";
        exec($command, $out, $ret);

        // VALIDATION
        // check return values
        if ($ret == get_ret($name . ".rc")) {
          out_tmp($out, $name);
          $command = "java -jar " . $handles->xml . " $name.out_new $name.out " . $handles->cfg;
          exec($command, $out, $res);
          echo implode("\n", $out) . "\n\n";
          // TEAR DOWN
          exec("rm $name.out_new");

          if ($res == 0) {
            $passed++;
          }
          else {
            $failed++;
          }
        }
        else  {
          // echo "$ret --- " . get_ret($handles->dir . $name . ".rc") . "\n";
          $failed++;
        }
      }
    }
  }
  else {
    foreach (new DirectoryIterator($handles->dir) as $file) {
      if ($file->getExtension() == "src") {
        // EXECUTION
        $name = str_replace(".src", "", $file);
        $command = "php7.4 " . $handles->parser .
          " <" . $handles->dir . $file . " 2>/dev/null";
        exec($command, $out, $ret);

        // VALIDATION
        // check return values
        if ($ret == get_ret($handles->dir . $name . ".rc")) {
          if ($ret != 0) {
            $passed++; continue;
          }

          out_tmp($out, $name);
          $command = "java -jar " . $handles->xml . " $name.out_new " . $handles->dir . "$name.out /dev/null " . $handles->cfg;
          exec($command, $out, $res);
          // echo implode("\n", $out) . "\n\n";
          // TEAR DOWN
          exec("rm $name.out_new");

          if ($res == 0) {
            $passed++;
          }
          else {
            $failed++;
          }
        }
        else  {
          // echo "$ret --- " . get_ret($handles->dir . $name . ".rc") . "\n";
          $failed++;
        }



        // implode("\n", $out)
        // "\n\n$ret\n";
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


function get_ret($filename) {
  $file = fopen($filename, "r");
  if (!$file) {
    fwrite(STDERR, "INVALID FILE: Expected return value (.rc)\n");
    exit(41);
  }
  return fgets($file);
}


function out_tmp($out, $name) {
  $name .= ".out_new";
  if (file_exists($name)) {
    fwrite(STDERR, "ERROR: Failed to create file\n");
    exit(41);
  }
  $file = fopen($name, "w");
  fwrite($file, implode("\n", $out));
}
?>
