<?php
// TODO
//  problematicke znaky XML <>$ ...
//  type matching

$output = "";

abstract class State {
  const Start = 0;
  const Command = 1;
}

/*
abstract class Type {
  const INV = -1;
  const Int     = 0;
  const Bool    = 1;
  const String  = 2;
  const Nil     = 3;
  const Label   = 4;
  const Type    = 5;
  const Var     = 6;
}
*/


$opcodes = array(
  "MOVE" => array("var", "symb"),
  "CREATEFRAME" => array(),
  "PUSHFRAME" => array(),
  "POPFRAME" => array(),
  "DEFVAR" => array("var"),

  "CALL" => array("label"),
  "RETURN" => array("symb"),
  "PUSHS" => array("var"),
  "POPS" => array("var"),

  "ADD" => array("var", "symb", "symb"),
  "SUB" => array("var", "symb", "symb"),
  "MUL" => array("var", "symb", "symb"),
  "IDIV" => array(),

  "LT" => array("var", "symb", "symb"),
  "GT" => array("var", "symb", "symb"),
  "EQ" => array("var", "symb", "symb"),

  "AND" => array("var", "symb_bool", "symb_bool"),
  "OR" => array("var", "symb_bool", "symb_bool"),
  "NOT" => array("var", "symb_bool", "symb_bool"),

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


  while (!feof(STDIN) && $line = fgets(STDIN)) {
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
      $instr = preg_split("/\s+/", $line);
      // remove last _blank_ element
      array_pop($instr);

      handle_instr($instr);
    }
  }

  $output .= "</program>\n";

  echo "$output";
  exit(0);
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
    if (!strcmp($arg[0], "INV")) {
      fwrite(STDERR, "ERROR: Invalid type of arg [$i] for \"$instr[0]\"\n");
      exit(23);
    }
    else {
      $output .= "\t\t<arg".($i+1)." type=\"$arg[0]\">$arg[1]</arg".($i+1).">\n";
    }
  }
}

// checks whether type of given arg & defined type match
// returns arg as array of [_type_, _value_] if types match
// return ["INV"] if types do not match
function handle_type($arg, $type) {
  switch ($type) {
    case "var":
      if (!preg_match("/^(GF|LF|TF)@[a-zA-Z_\-$&%*!?][a-zA-Z0-9_\-$&%*!?]*$/", $arg)) {
        fwrite(STDERR, "ERROR: Invalid variable\n");
        exit(23);
      }
      return array("var", $arg);

    case "label":
      if (!preg_match("/^[a-zA-Z_\-$&%*!?][a-zA-Z0-9_\-$&%*!?]*$/", $arg)) {
        fwrite(STDERR, "ERROR: Invalid label\n");
        exit(23);
      }
      return array("label", $arg);

    case "symb":
    // check for var
    if (
      preg_match("/^(GF|LF|TF)@[a-zA-Z_\-$&%*!?][a-zA-Z0-9_\-$&%*!?]*$/", $arg)
    ) {
      return array("var", $arg);
    }
    else if (
      preg_match("/^((int@([+-][0-9]+|[0-9]+))|(bool@(true|false))|(string@(|([^\s#\\\\]|\\\\([0-9][0-9][0-9]))+))|(nil@nil))$/", $arg)
    ) {
      $tmp = preg_split("/@/", $arg, 2);
      return array($tmp[0], $tmp[1]);
    }
    else {
      fwrite(STDERR, "ERROR: Invalid symbol \"$arg\"\n");
      exit(23);
    }

    case "type":  // TODO

    default:
      break;
  }


}



# OPTION HANDLING
if ($argc > 2) {
  fwrite(STDERR, "ERROR: Invalid arguments\n");
  exit(10);
}
else if ($argc == 2) {
  if (!strcmp($argv[1],"--help")) {
    $output .= "This is help\n";            # TODO
    exit(0);
  }
  else {
    fwrite(STDERR, "ERROR: Invalid arguments\n");
    exit(1);
  }
}


# PARSING
parse();
exit(0);

?>
