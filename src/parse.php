<?php
/*
 * File: parse.php
 * Brief: Implementation of parser
 *
 * Project: Interpret for imperative programing language IPPcode21
 *
 * Authors: Jakub Bartko    xbartk07@stud.fit.vutbr.cz
 */


// counters for stats options
class Counters {
  public $loc = 0;
  public $comments = 0;
  public $labels = 0;
  public $jumps = 0;
  public $fwjumps = 0;
  public $backjumps = 0;
  public $badjumps = 0;
  public $label_list = array();
  public $jmp_list = array();
}

// states of main FSM
abstract class State {
  const Start = 0;
  const Instr = 1;
}

// list of all viable operation codes
// & types of their arguments
$opcodes = array(
  "MOVE" => array("var", "symb"),
  "CREATEFRAME" => array(),
  "PUSHFRAME" => array(),
  "POPFRAME" => array(),
  "DEFVAR" => array("var"),
  "CALL" => array("label"),
  "RETURN" => array(),
  "PUSHS" => array("symb"),
  "POPS" => array("var"),
  "ADD" => array("var", "symb", "symb"),
  "SUB" => array("var", "symb", "symb"),
  "MUL" => array("var", "symb", "symb"),
  "IDIV" => array("var", "symb", "symb"),
  "LT" => array("var", "symb", "symb"),
  "GT" => array("var", "symb", "symb"),
  "EQ" => array("var", "symb", "symb"),
  "AND" => array("var", "symb", "symb"),
  "OR" => array("var", "symb", "symb"),
  "NOT" => array("var", "symb"),
  "INT2CHAR" => array("var", "symb"),
  "STRI2INT" => array("var", "symb", "symb"),
  "READ" => array("var", "type"),
  "WRITE" => array("symb"),
  "CONCAT" => array("var", "symb", "symb"),
  "STRLEN" => array("var", "symb"),
  "GETCHAR" => array("var", "symb", "symb"),
  "SETCHAR" => array("var", "symb", "symb"),
  "TYPE" => array("var", "symb"),
  "LABEL" => array("label"),
  "JUMP" => array("label"),
  "JUMPIFEQ" => array("label", "symb", "symb"),
  "JUMPIFNEQ" => array("label", "symb", "symb"),
  "EXIT" => array("symb"),
  "DPRINT" => array("symb"),
  "BREAK" => array(),
);


$counters = new Counters();

$opts = handle_opts($argc, $argv);
$output = parse($counters);
echo $output;
print_stats($opts, $counters);

exit(0);


// handles command line options
// expects [int] number of & [array] list of options
// returns [associative array] of statp options -- [filename]: {options}
function handle_opts($argc, $argv) {
  // array of statp options
  $opts = array();

  if ($argc > 1) {
    if (!strcmp($argv[1], "--help")) {
      if ($argc == 2) {
        echo "This is Help for module parse.php\n";
        echo " - Run using `php7.4 parse.php {options} <input`\n";
        echo "\nOptions:\n";
        echo "  --help\t\t\tDisplays this help\n";
        echo "  --stats=file {options}\tPrints statistics to given file\n";
        echo "\t- NOTE: Both --stats & its options are repeatable; files must be unique\n";
        echo "\tViable options: [Number of ...]\n";
        echo "\t--loc\t\t\tLines of code\n";
        echo "\t--comments\t\tLines with comments\n";
        echo "\t--labels\t\tDefined labels\n";
        echo "\t--jumps\t\t\tJump instructions\n";
        echo "\t--fwjumps\t\tForward jumps\n";
        echo "\t--backjumps\t\tBackward jumps\n";
        echo "\t--badjumps\t\tInvalid jumps\n";
        echo "\n";
        exit(0);
      }
      else {
        fwrite(STDERR, "ERROR: Invalid Options\n");
        exit(10);
      }
    }
    else if (preg_match("/^--stats=/", $argv[1])) {
      // iterate over opts
      for ($i = 1; $i < $argc; $i++) {
        // check for new file opt
        if (preg_match("/^--stats=/", $argv[$i])) {
          if (preg_match("/^--stats=\s*$/", $argv[$i])) {
            fwrite(STDERR, "ERROR: Invalid Options\n");
            exit(10);
          }

          // check whether filename unique
          $filename = str_replace("--stats=", "", $argv[$i]);
          if (in_array($filename, array_column($opts, 0))) {
            fwrite(STDERR, "ERROR: --stats files must by unique\n");
            exit(12);
          }

          // add new stats set
          array_push($opts, array($filename));
        }
        else { // check for --stats opts
          if (preg_match("/^\-\-(loc|comments|labels|jumps|fwjumps|backjumps|badjumps)/", $argv[$i])) {
            array_push(
              $opts[count($opts)-1],
              str_replace("--", "", $argv[$i])
            );
          }
          else {
            fwrite(STDERR, "ERROR: Invalid Options\n");
            exit(10);
          }
        }
      } // end interation
    } // end opt parsing
    else {
      fwrite(STDERR, "ERROR: Invalid Options\n");
      exit(10);
    }
  }
  return $opts;
}


// main function -- parses source code in IPPcode21 from STDIN
//  & handles collection of statistics about source code
// expects [object] stats counters
// returns [string] parsed code in XML format
function parse(&$counters) {
  $output = ""; // generating parsing output in XML format
  $output .= "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
  $output .= "<program language=\"IPPcode21\">\n";
  $state = State::Start;

  while (true) {  // main FSM
    // handle EOF
    if (feof(STDIN)) {
      if ($state == State::Start) {
        fwrite(STDERR, "ERROR: Missing header in src file\n");
        exit(21);
      }
      else break;
    }

    $line = fgets(STDIN);

    // remove comments
    $n = 0;
    $line = preg_replace("/#.*/", "", $line, -1, $n);
    if ($n != 0) $counters->comments++;
    // skip empty line
    if (preg_match("/^\s*$/", $line)) continue;

    // parse HEADER
    if ($state == State::Start) {
      if (preg_match("/^\s*\.IPPcode21\s*$/i", "$line")) {
        $state = State::Instr;
        continue;
      }
      else {
        fwrite(STDERR, "ERROR: Missing header in src file\n");
        exit(21);
      }
    }
    // parse INSTRUCTION
    else if ($state == State::Instr) {
      $counters->loc++;
      // split into words & remove empty elements
      $instr = array_filter(preg_split("/\s+/", ltrim($line)));
      // parse instruction
      $output .= handle_instr($instr, $counters);
    }
  }

  $output .= "</program>\n";
  return $output;
}


// prints collected stats to specified files
// expects [associative array {filename, {stats_opts}}] options
//  & [object] stats counters
function print_stats($opts, $counters) {
  // handle bad jumps
  foreach ($counters->jmp_list as $n) {
    $counters->badjumps += $n;
  }
  // output to files
  foreach ($opts as $set) {
    // get file handle
    $file = fopen($set[0], "w");
    if (!$file) {
      fwrite(STDERR, "ERROR: Failed to open file\n");
      exit(12);
    }
    // print stats
    for ($i = 1; $i < count($set); $i++) {
      fwrite($file, ${$set[$i]}."\n");
    }

    fclose($file);
  }
}


// parsers given instruction & handles stats options
// expects [array {OPCODE, arguments}] instruction
//  & [object] stats counters
// returns [string] parsed instruction in XML format
function handle_instr($instr, &$counters) {
  global $opcodes;
  static $cnt = 0;
  $cnt++;
  $output = "";
  $opcode = $instr[0] = strtoupper($instr[0]);

  // check validity of OPCODE
  if (!array_key_exists($opcode, $opcodes)) {
    fwrite(STDERR, "ERROR: Invalid OPCODE: \"$opcode\"\n");
    exit(22);
  }

  // handle counters for statp opts
  switch ($opcode) {
    case "LABEL":
      $label = $instr[1];
      $counters->labels++;
      array_push($counters->label_list, $label);
      if ($key = array_search($label, $counters->jmp_list)) {
        $counters->fwjumps++;
        unset($counters->jmp_list[$key]);
      }
      break;

    case "JUMP": case "JUMPIFEQ": case "JUMPIFNEQ": case "CALL":
      $label = $instr[1];
      if (in_array($label, $counters->label_list)) {
        $counters->backjumps++;
        unset($counters->jmp_list[$label]);
      }
      else {
        if ($key = array_search($label, $counters->jmp_list)) {
          $counters->jmp_list[$label]++;
        }
        else {
          $counters->jmp_list[$label] = 1;
        }
      }
    case "RETURN":
      $counters->jumps++;
      break;
  }

  $output .= "\t<instruction order=\"$cnt\" opcode=\"$opcode\">\n";
  $output .= handle_args($instr);
  $output .= "\t</instruction>\n";

  return $output;
}


// handles arguments of given instruction
// expects [array {OPCODE, arguments}] instruction
// returns [string] parsed arguments in XML format
function handle_args($instr) {
  global $opcodes;
  $output = "";
  $types = $opcodes[$instr[0]];
  $n = count($instr)-1; // number of given args

  // match number of args
  if ($n != count($types)) {
    fwrite(STDERR, "ERROR: Invalid number of args for \"$instr[0]\"\n");
    exit(23);
  }

  // match types of args
  for ($i = 0; $i < $n; $i++) {
    $arg = handle_type($instr[$i+1], $types[$i]);
    if (!strcmp($arg[0],"var") || !strcmp($arg[0],"string")) {
      $arg[1] = correct_string($arg[1]);
    }
    $output .= "\t\t<arg".($i+1)." type=\"$arg[0]\">$arg[1]</arg".($i+1).">\n";
  }

  return $output;
}


// checks whether type of given arg matches defined type
// returns arg as array of [_type_, _value_] if types match
// else throws error
function handle_type($arg, $type) {
  switch ($type) {
    case "var":
      if (!preg_match("/^\s*(GF|LF|TF)@[a-zA-Z_\-$&%*!?][a-zA-Z0-9_\-$&%*!?]*$/", $arg)) {
        fwrite(STDERR, "ERROR: Invalid variable\n");
        exit(23);
      }
      return array("var", $arg);

    case "label":
      if (!preg_match("/^\s*[a-zA-Z_\-$&%*!?][a-zA-Z0-9_\-$&%*!?]*$/", $arg)) {
        fwrite(STDERR, "ERROR: Invalid label\n");
        exit(23);
      }
      return array("label", $arg);

    case "symb":
    // check for var
    if (
      preg_match("/^\s*(GF|LF|TF)@[a-zA-Z_\-$&%*!?][a-zA-Z0-9_\-$&%*!?]*$/", $arg)
    ) {
      return array("var", $arg);
    }
    // check for const
    else if (
      preg_match("/^\s*((int@([+-][0-9]+|[0-9]+))|(bool@(true|false))|(string@(|([^\s#\\\\]|\\\\([0-9][0-9][0-9]))+))|(nil@nil))$/", $arg)
    ) {
      $tmp = preg_split("/@/", $arg, 2);
      return array($tmp[0], $tmp[1]);
    }
    else {
      fwrite(STDERR, "ERROR: Invalid symbol \"$arg\"\n");
      exit(23);
    }

    case "type":
      if (preg_match("/^\s*(int|string|bool)$/", $arg)) {
        return array("type", $arg);
      }
      else {
        fwrite(STDERR, "ERROR: Invalid type \"$arg\"\n");
        exit(23);
      }

    default:
      return array("INV");
      break;
  }
}


// replaces problematic chars (for XML) with corresponding XML entities
// expects [string] input
// returns [string] corrected input
function correct_string($str) {
  $str = str_replace("\"", "&quot;", $str);
  $str = str_replace("&", "&amp;", $str);
  $str = str_replace("'", "&apos;", $str);
  $str = str_replace("<", "&lt;", $str);
  $str = str_replace(">", "&gt;", $str);
  return $str;
}
?>
