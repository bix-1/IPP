<?php
$output = "";

abstract class State {
  const Start = 0;
  const Command = 1;
}

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


function parse() {
  $state = State::Start;
  global $output;
  $output .= "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
  $output .= "<program language=\"IPPcode21\">\n";

  while (true) {
    if (feof(STDIN)) {
      if ($state == State::Start) {
        fwrite(STDERR, "ERROR: Missing header in src file\n");
        exit(21);
      }
      else break;
    }

    $line = fgets(STDIN);
    // remove comments
    $line = preg_replace("/#.*/", "", $line);
    // skip empty line
    if ( preg_match("/^\s*$/", $line)) {
      continue;
    }

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
      // split into words
      $instr = preg_split("/\s+/", ltrim($line));
      // remove last _blank_ element
      array_pop($instr);
      // parse instruction
      handle_instr($instr);
    }
  }

  $output .= "</program>\n";
  echo $output;
}

function handle_instr($instr) {
  global $output, $opcodes;
  static $cnt = 0;
  $cnt++;
  $opcode = $instr[0] = strtoupper($instr[0]);

  // check for OPCODE
  if (!array_key_exists($opcode, $opcodes)) {
    fwrite(STDERR, "ERROR: Invalid OPCODE: \"$opcode\"\n");
    exit(22);
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


# OPTION HANDLING
if ($argc > 2) {
  fwrite(STDERR, "ERROR: Invalid options\n");
  exit(10);
}
else if ($argc == 2) {
  if (!strcmp($argv[1],"--help")) {
    $output .= "This is help\n";            # TODO
    exit(0);
  }
  else {
    fwrite(STDERR, "ERROR: Invalid options\n");
    exit(1);
  }
}

# PARSING
parse();
exit(0);
?>
