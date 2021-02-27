<?php
// TODO
//    dirs ??

// NOTE
// tests:
//    iterate:  -- iterate filesys
//      exec
//      validate
//      record:   -- passed / failed + list_of_failed
//    output:   -- html


class Filenames {
  public $dir = ".";
  public $parser = "parse.php";
  public $intr = "interpret.py";
  public $xml = "/pub/courses/ipp/jexamxml/jexamxml.jar";
  public $cfg = "/pub/courses/ipp/jexamxml/options";
}

class Handles {
  public $dir;
  public $parser;
  public $intr;
  public $xml;
  public $cfg;
}


$filenames = new Filenames();
$recurs = false;

parse_opts($argc, $argv, $filenames, $recurs);
$handles = get_handles($filenames);

$record = iterate_tests($handles, $filenames->dir, $recurs);




function parse_opts($argc, $argv, &$filenames, &$recurs) {
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
        $filenames->intr = "";
        break;

      case "--int-only":
        if (count(preg_grep("/^--parse-/", $argv)) != 0) {
          fwrite(STDERR, "INVALID OPTIONS: Cannot combine --int-only & parse options\n");
          exit(10);
        }
        $filenames->parser = "";
        break;

      default: // check for opts with parameter
        if (preg_match("/^--directory=/", $argv[$i])) {
          $filenames->dir = str_replace("--directory=", "", $argv[$i]);
        }
        else if (preg_match("/^--parse-script=/", $argv[$i])) {
          $filenames->parser = str_replace("--parse-script=", "", $argv[$i]);
        }
        else if (preg_match("/^--int-script=/", $argv[$i])) {
          $filenames->intr = str_replace("--int-script=", "", $argv[$i]);
        }
        else if (preg_match("/^--jexamxml=/", $argv[$i])) {
          $filenames->xml = str_replace("--jexamxml=", "", $argv[$i]);
        }
        else if (preg_match("/^--jexamcfg=/", $argv[$i])) {
          $filenames->cfg = str_replace("--jexamcfg=", "", $argv[$i]);
        }
        else {
          fwrite(STDERR, "INVALID OPTIONS: Unknown option\n");
          exit(10);
        }
        break;
    }
  }
}


function get_handles($filenames) {
  $handles = new Handles();
  foreach ($filenames as $key => $file) {
    if ($key == "dir" || $file == "") continue;

    $handles->$key = fopen($file, "r");
    if (!$handles->$key) {
      fwrite(STDERR, "ERROR: Failed to open [$file]\n");
      exit(41);
    }
  }

  return $handles;
}


function iterate_tests($dir, $recurs) {
  // $out = ""; $ret = 0;
  // exec("php7.4 parse.php <in", $out, $ret);
  // echo implode("\n", $out) . "\n\n$ret\n";
  // exit(0);

  $out = ""; $ret = 0;

  if ($recurs) {
    // construct iterator
    $it = new RecursiveDirectoryIterator($dir);
    // iterate files
    foreach (new RecursiveIteratorIterator($it) as $file) {
      if ($file->getExtension() == "src") {
        // echo "$file\n";
      }
    }
  }
  else {
    foreach (new DirectoryIterator($dir) as $file) {
      if($file->isDot()) continue;
      // echo $file->getFilename() . "\n";
      exec("php7.4 parse.php <", $out, $ret);
    }
  }


  return "";  // TODO records
}
?>
