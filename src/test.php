<?php
// TODO
//    dirs ??

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

parse_opts($argc, $argv, $filenames);
$handles = get_handles($filenames);





function parse_opts($argc, $argv, &$filenames) {
  for ($i = 1; $i < $argc; $i++) {
    switch ($argv[$i]) {
      case "--help":
        if ($argc > 2) {
          fwrite(STDERR, "ERROR: Invalid Options\n");
          exit(10);
        }
        else {
          echo "This is Help for module test.php\n";
          echo " - Run using `php7.4 test.php {options} <input`\n";
          echo "Options:\n";
          // TODO

        }
        exit(0);

      // case "":

      default: // check for opts with parameter using regex
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
          fwrite(STDERR, "ERROR: Invalid Options\n");
          exit(10);
        }
        break;
    }
  }
}


function get_handles($filenames) {
  $handles = new Handles();
  foreach ($filenames as $key => $file) {
    if ($file == "") continue;

    $handles->$file = fopen($file, "r");
    if (!$handles->$file) {
      fwrite(STDERR, "ERROR: Failed to open [$file]\n");
      exit(41);
    }
  }

  return $handles;
}
?>
