<?php
//__________GLOBALS__________
$output = "";
// counters for _stats_ opt
$loc = $comments = $labels = $jumps = $fwjumps = $backjumps = $badjumps = 0;
// trackers _stats_ opt
$label_list = array();
$jmp_list = array();

abstract class State {
  const Start = 0;
  const Command = 1;
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


function parse($opts) {
  global $loc, $comments, $labels, $jumps, $fwjumps, $backjumps, $badjumps;
  global $output;

  $output .= "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
  $output .= "<program language=\"IPPcode21\">\n";
  $state = State::Start;

  while (true) {
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
    if ($n != 0) $comments++;
    // skip empty line
    if (preg_match("/^\s*$/", $line)) continue;

    // HEADER
    if ($state == State::Start) {
      if (preg_match("/^\s*\.IPPcode21\s*$/i", "$line")) {
        $state = State::Command;
        continue;
      }
      else {
        fwrite(STDERR, "ERROR: Missing header in src file\n");
        exit(21);
      }
    }
    // COMMANDS
    else if ($state == State::Command) {
      $loc++;
      // split into words & remove empty elements
      $instr = array_filter(preg_split("/\s+/", ltrim($line)));
      // parse instruction
      handle_instr($instr);
    }
  }

  // print output in XML to STDIN
  $output .= "</program>\n";
  echo $output;

  //    handle _stats_ opts
  // handle bad jumps
  global $jmp_list;
  foreach ($jmp_list as $n) {
    $badjumps += $n;
  }
  // output to files
  if (count($opts) > 0) {
    foreach ($opts as $set) {
      // get file handle
      $file = fopen($set[0], "w");
      if (!$file) {
        fwrite(STDERR, "ERROR: Failed to open file\n");
        exit(12);
      }

      for ($i = 1; $i < count($set); $i++) {
        fwrite($file, ${$set[$i]}."\n");
      }

      fclose($file);
    }
  }

}

function handle_instr($instr) {
  global $output, $opcodes;
  static $cnt = 0;
  $cnt++;
  $opcode = $instr[0] = strtoupper($instr[0]);

  // check validity of OPCODE
  if (!array_key_exists($opcode, $opcodes)) {
    fwrite(STDERR, "ERROR: Invalid OPCODE: \"$opcode\"\n");
    exit(22);
  }

  // handle counters & trackers for _stats_ opt
  switch ($opcode) {
    case "LABEL":
      global $labels, $label_list, $jmp_list, $fwjumps;
      $label = $instr[1];
      $labels++;
      array_push($label_list, $label);
      if ($key = array_search($label, $jmp_list)) {
        $fwjumps++;
        unset($jmp_list[$key]);
      }
      break;

    case "JUMP": case "JUMPIFEQ": case "JUMPIFNEQ": case "CALL":
      global $label_list, $jmp_list;
      $label = $instr[1];
      if (in_array($label, $label_list)) {
        global $backjumps; $backjumps++;
        unset($jmp_list[$label]);
      }
      else {
        if ($key = array_search($label, $jmp_list)) {
          $jmp_list[$label]++;
        }
        else {
          $jmp_list[$label] = 1;
        }
      }
    case "RETURN":
      global $jumps; $jumps++;
      break;
  }

  $output .= "\t<instruction order=\"$cnt\" opcode=\"$opcode\">\n";
  handle_args($instr);
  $output .= "\t</instruction>\n";
}

function handle_args($instr) {
  global $output, $opcodes;
  $types = $opcodes[$instr[0]];
  $n = count($instr)-1;
  $N = count($types);

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
}

// checks whether type of given arg & defined type match
// returns arg as array of [_type_, _value_] if types match
// else error
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

function correct_string($str) {
  $str = str_replace("\"", "&quot;", $str);
  $str = str_replace("&", "&amp;", $str);
  $str = str_replace("'", "&apos;", $str);
  $str = str_replace("<", "&lt;", $str);
  $str = str_replace(">", "&gt;", $str);
  return $str;
}


// OPTION HANDLING
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

        $filename = str_replace("--stats=", "", $argv[$i]);
        // check whether filename unique
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

# PARSING
parse($opts);
exit(0);
?>
