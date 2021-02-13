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

  "ADD" => array("var", "symb_int", "symb_int"),
  "SUB" => array("var", "symb_int", "symb_int"),
  "MUL" => array("var", "symb_int", "symb_int"),
  "IDIV" => array(),

  "LT" => array("var", "symb_same", "symb_same"),
  "GT" => array("var", "symb_same", "symb_same"),
  "EQ" => array("var", "symb_same", "symb_same"),

  "AND" => array("var", "symb_bool", "symb_bool"),
  "OR" => array("var", "symb_bool", "symb_bool"),
  "NOT" => array("var", "symb_bool", "symb_bool"),

  "INT2CHAR" => array("var", "symb_int"),
  "STRI2INT" => array("var", "symb_str", "symb_int"),

  "READ" => array("var", "type"),
  "WRITE" => array("symb"),
  "CONCAT" => array("var", "symb_str", "symb_str"),
  "STRLEN" => array("var", "symb_str"),

  "GETCHAR" => array("var", "symb_str", "symb_int"),
  "SETCHAR" => array("var", "symb_int", "symb_str"),
  "TYPE" => array("var", "symb"),
  "LABEL" => array("label"),
  "JUMP" => array("label"),

  "JUMPIFEQ" => array("label", "symb_same", "symb_same"),
  "JUMPIFNEQ" => array("label", "symb_same", "symb_same"),
  "EXIT" => array("symb_int"),
  "DPRINT" => array("symb_int"),
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
      echo "<arg$i type=\"$arg[0]\">$arg[1]</arg$i>\n";
    }
  }
}

// checks whether type of given arg & defined type match
// returns arg as array of [_type_, _value_] if types match
// return ["INV"] if types do not match
function match_type($arg, $type) {



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
