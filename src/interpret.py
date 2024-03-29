#!/usr/bin/env python3.8

# File: interpret.py
# Brief: Implementation of interpret
#
# Project: Interpret for imperative programing language IPPcode21
#
# Authors: Jakub Bartko    xbartk07@stud.fit.vutbr.cz


import sys                          # exits, STDIN, STDERR
import argparse                     # CL options
import xml.etree.ElementTree as ET  # XML parsing
from enum import Enum               # enumerators
import re                           # regex

def error(msg, code):
    """Prints message to STDERR & exits with given code"""
    print("ERROR: " + msg, file=sys.stderr)
    sys.exit(code.value)

class Err(Enum):
    """Error codes Enumerator"""
    Parameter = 10
    FileIn = 11
    FileOut = 12
    WellFormed = 31
    UnexStruct = 32
    Semantics = 52
    Operands = 53
    UndefVar = 54
    UndefFrame = 55
    UndefVal = 56
    InvVal = 57
    Str = 58
    Internal = 99

"""Variable type Enumerator"""
Type = Enum("Type", "UNDEF INT BOOL STRING FLOAT NIL")

class Var:
    """Represents Value & Type of single variable"""
    def __init__(self):
        self.type = Type.UNDEF
        self.val = Type.UNDEF

    def set(self, interp, type, value):
        if self.type == Type.UNDEF:
            interp.vars_update(+1)
        self.type = type
        self.val = value

class Interpret:
    """Represents internal functionality of interpret
    Contains methods for:
        argument & instruction validation,
        instruction execution,
        handling frames & data stack,
        program flow control,
        collection of code statistics
    """
    frames = {
        "GF": {},
        "LF": [],
        "TF": Type.UNDEF
    }
    labels = {}
    calls = []
    stack = []
    # stats
    var_cnt = 0
    var_max = 0
    cnt = 0
    hots = {}
    stats = []
    stats_file = ""

    def __init__(self, stats, file):
        self.stats = stats
        self.stats_file = file

    """_____control_____"""
    def check(self, instr):
        """Validates instruction
        Specific instruction arguments are validated by execution methods
        """
        # validate tag
        if instr.tag != "instruction":
            error("Invalid instruction tag", Err.UnexStruct)
        # validate attributes
        if (    len(instr.attrib) != 2
            or  any(a not in {"order", "opcode"} for a in instr.attrib)
            ):
            error("Invalid attributes", Err.UnexStruct)
        # validate opcode
        opcode = instr.attrib["opcode"].upper()
        if opcode.upper() not in self.instrs:
            error("Invalid opcode \"" + str(opcode) + "\"", Err.UnexStruct)

        # validate ammount of args
        if len(instr) != self.instrs[opcode][1] and opcode not in {"PUSHS", "POPS"}:
            error("Invalid number of arguments for \"" + opcode + "\"", Err.UnexStruct)
        # validate format of args
        if (    any("arg" + str(i+1) not in [arg.tag for arg in instr] for i in range(0, len(instr)))
            or  any(not arg.attrib for arg in instr)
            or  any(not arg.attrib or attr != "type" for arg in instr for attr in arg.attrib)
            ):
            error("Invalid instruction argument", Err.UnexStruct)

    def run(self, instr):
        """Call function from list of instructions
        Return order_n of jmp destination in case of jmp instruction
        """
        opcode = instr.attrib["opcode"].upper()
        order = instr.attrib["order"]
        # update stats
        if opcode not in {"LABEL", "DPRINT", "BREAK"}:
            self.cnt += 1
            self.hots[order] = self.hots.get(order, 0) + 1

        # operation functions require additional parameter
        if opcode in {
                "ADD", "SUB", "MUL", "IDIV", "DIV", "ADDS", "SUBS",
                "MULS", "IDIVS", "DIVS", "LT", "GT", "EQ", "LTS",
                "GTS", "EQS", "AND", "OR", "ANDS", "ORS",
                "JUMPIFEQ", "JUMPIFEQS", "JUMPIFNEQ", "JUMPIFNEQS"
            }:
            args = self, instr, opcode
        else:
            args = self, instr

        return self.instrs[opcode][0](*args)

    def get_arg(self, instr, n):
        return next(arg for arg in instr if arg.tag == "arg" + str(n))

    def is_unique(self, frame, name):
        if frame == "GF":
            return name not in self.frames[frame]
        elif frame == "TF":
            if self.frames["TF"] == Type.UNDEF:
                error("Undefined Temporary Frame", Err.UndefFrame)
            return name not in self.frames[frame]
        else:
            if len(self.frames["LF"]) == 0:
                error("Undefined Local Frame", Err.UndefFrame)
            return len(self.frames[frame]) == 0 or (name not in self.frames[frame][-1])

    def store(self, frame, name, type, val):
        if self.is_unique(frame, name):
            error("Variable undefined", Err.UndefVar)
        if frame == "LF":
            try:
                self.frames[frame][-1][name].set(self, type, val)
            except:
                error("Missing Local Frame", Err.UndefFrame)
        else:
            self.frames[frame][name].set(self, type, val)

    def operations(self, instr, op):
        # get arguments
        if op[-1] != "S":   # non-stack version
            stack = False
            frame, name = self.var(self.get_arg(instr, 1))
            type1, val1 = self.symb(self.get_arg(instr, 2))
            type2, val2 = self.symb(self.get_arg(instr, 3))
        else:               # stack version
            if len(self.stack) == 0:
                error(op + ": Stack is Empty", Err.UndefVal)
            stack = True
            op = op[:-1]    # remove stack suffix 'S'
            symb2 = self.stack.pop()
            symb1 = self.stack.pop()
            type1, val1 = symb1[0], symb1[1]
            type2, val2 = symb2[0], symb2[1]

        # validate
        if op in {"ADD", "SUB", "MUL", "IDIV", "DIV"}:
            if (    type1 != type2 or type1 not in {Type.INT, Type.FLOAT}
                or  type2 not in {Type.INT, Type.FLOAT}
                ):
                error(op + ": Operand types not matching", Err.Operands)
            if op in {"IDIV", "DIV"} and val2 == 0:
                error("IDIV/DIV: Zero division", Err.InvVal)
        elif op in {"LT", "GT", "EQ"}:
            if (type1 == Type.NIL or type2 == Type.NIL):
                if op != "EQ":
                    error(op + " \"nil\" can only be compared using \"EQ\"", Err.Operands)
            elif type1 != type2:
                error(op + ": Operands must be of the same type", Err.Operands)
        elif op in {"AND", "OR"}:
            if type1 != Type.BOOL or type2 != Type.BOOL:
                error(op + ": Both operands must be of type \"BOOL\"", Err.Operands)

        # get result
        res = {
            # arithmetic
            "ADD":  lambda x,y: x + y,
            "SUB":  lambda x,y: x - y,
            "MUL":  lambda x,y: x * y,
            "IDIV": lambda x,y: x // y,
            "DIV": lambda x,y: x / y,
            # relational
            "LT": lambda x,y: x < y,
            "GT": lambda x,y: x > y,
            "EQ": lambda x,y: x == y,
            # logical
            "AND": lambda x,y: x and y,
            "OR": lambda x,y: x or y
        }[op](val1, val2)

        # store result
        if op in {"ADD", "SUB", "MUL", "IDIV", "DIV"}:
            res_type = type1
        else:
            res_type = Type.BOOL
        if not stack:
            self.store(frame, name, res_type, res)
        else:
            self.stack.append((res_type, res))

    def cond_jmp(self, instr, op):
        label = self.label(self.get_arg(instr, 1), False)
        if op[-1] != "S":   # non-stack version
            type1, val1 = self.symb(self.get_arg(instr, 2))
            type2, val2 = self.symb(self.get_arg(instr, 3))
        else:               # stack version
            if len(self.stack) == 0:
                error(op + ": Stack is Empty", Err.UndefVal)
            op = op[:-1]    # remove stack suffix 'S'
            symb2 = self.stack.pop()
            symb1 = self.stack.pop()
            type1, val1 = symb1[0], symb1[1]
            type2, val2 = symb2[0], symb2[1]
        if (type1 != type2) and type1 != Type.NIL and type2 != Type.NIL:
            error("JUMPIFEQ: Invalid operands", Err.Operands)
        cond = {
            "JUMPIFEQ": val1 == val2,
            "JUMPIFNEQ": val1 != val2
        }[op]
        if cond:
            return self.labels[label]

    """_____stats_____"""
    def vars_update(self, n):
        self.var_cnt += n
        self.var_max = max(self.var_cnt, self.var_max)

    def stats_out(self):
        try:
            with open(self.stats_file, "w") as file:
                for s in self.stats:
                    val = {
                        "insts": self.cnt,
                        "hot": max(self.hots, key=self.hots.get),
                        "vars": self.var_max
                    }[s]
                    file.write(str(val) + "\n")
        except:
            error("Failed to open file for stats output", Err.Parameter)

    """_____arguments_____"""
    def var(self, v):
        if v.attrib["type"] != "var":
            error("Invalid operand type -- Expected \"var\"", Err.UnexStruct);
        try:
            frame, name = v.text.split("@")
            if (not re.match(r"^[a-zA-Z_\-$&%*!?][a-zA-Z0-9_\-$&%*!?]*$", name)):
                raise Exception()
        except:
            error("Invalid variable name", Err.UnexStruct)
        if frame not in {"GF", "LF", "TF"}:
            error("Invalid frame", Err.UnexStruct)

        return frame, name

    def symb(self, s):
        """check for constant and variable & return value"""
        type = s.attrib["type"]
        if type == "var":
            frame, name = self.var(s)
            if self.is_unique(frame, name):
                error("Variable undefined", Err.UndefVar)
            elif frame == "GF" and self.frames[frame][name].type == Type.UNDEF:
                error("Undefined Value", Err.UndefVal)
            elif frame == "TF" and self.frames[frame][name].type == Type.UNDEF:
                error("Undefined Value", Err.UndefVal)
            elif frame in {"GF", "TF"}:
                var = self.frames[frame][name]
            else:
                var = self.frames[frame][-1][name]
            if var.type == Type.UNDEF:
                error("Accessing Undefined Variable", Err.UndefVal)
            return var.type, var.val
        elif type == "nil":
            if s.text == "nil":
                return Type.NIL, s.text
        elif type == "bool":
            if s.text == "true":
                return Type.BOOL, True
            elif s.text == "false":
                return Type.BOOL, False
        elif type == "int":
            try:
                return Type.INT, int(s.text)
            except:
                error("Invalid integer value", Err.UnexStruct)
        elif type == "string":
            if not s.text:
                return Type.STRING, ""
            else:
                return Type.STRING, re.sub(r"\\(\d{3})", lambda x: chr(int(x.group(1))), s.text)
        elif type == "float":
            try:
                return Type.FLOAT, float.fromhex(s.text)
            except:
                error("Invalid float value", Err.UnexStruct)

        error("Invalid constant value", Err.UnexStruct)

    def label(self, l, defining):
        if (
                l.attrib["type"] != "label" or not l.text
            or  not re.match(r"^[a-zA-Z_\-$&%*!?][a-zA-Z0-9_\-$&%*!?]*$", l.text)
            ):
            error("LABEL: Invalid label", Err.Operands)
        if defining and l.text in self.labels:
            error("LABEL: Redefinition of label", Err.Semantics)
        elif not defining and l.text not in self.labels:
            error("LABEL: Undefined label", Err.Semantics)
        return l.text

    def type(self, t):
        if (
                t.attrib["type"] != "type" or not t.text
            or  t.text not in {"int", "string", "bool", "float"}
            ):
            error("Invalid type", Err.Operands)
        return {
            "int": Type.INT,
            "string": Type.STRING,
            "bool": Type.BOOL,
            "float": Type.FLOAT,
        }[t.text]

    """_____variables_____"""
    def MOVE(self, instr):
        frame, name = self.var(self.get_arg(instr, 1))
        type, val = self.symb(self.get_arg(instr, 2))
        self.store(frame, name, type, val)

    def DEFVAR(self, instr):
        frame, name = self.var(self.get_arg(instr, 1))
        if not self.is_unique(frame, name):
            error("DEFVAR: Variable already defined", Err.Semantics)
        if frame == "GF" or frame == "TF":
            self.frames[frame][name] = Var()
        else:
            try:
                self.frames[frame][-1][name] = Var()
            except:
                error("DEFVAR: Missing Local Frame", Err.UndefFrame)

    def TYPE(self, instr):
        frame, name = self.var(self.get_arg(instr, 1))
        elem = self.get_arg(instr, 2)
        type = elem.attrib["type"]
        if type == "var":
            fr, nm = self.var(elem)
            if self.is_unique(fr, nm):
                error("Variable undefined", Err.UndefVar)
            elif fr in {"GF", "TF"}:
                str_type = self.frames[fr][nm].type.name
            else:
                str_type = self.frames[fr][-1][nm].type.name
            self.store(frame, name, Type.STRING, str_type.lower() if str_type != "UNDEF" else "")
        else:
            self.store(frame, name, Type.STRING, type)

    def NOT(self, instr):
        frame, name = self.var(self.get_arg(instr, 1))
        type, val = self.symb(self.get_arg(instr, 2))
        if type != Type.BOOL:
            error("NOT: Operand must be of type \"BOOL\"", Err.Operands)
        self.store(frame, name, Type.BOOL, not val)

    def NOTS(self, instr):
        if len(self.stack) == 0:
            error("NOTS: Stack is Empty", Err.UndefVal)
        tmp = self.stack.pop()
        if tmp[0] != Type.BOOL:
            error("NOTS: Operand must be of type \"BOOL\"", Err.Operands)
        self.stack.append((Type.BOOL, not tmp[1]))

    """_____frames_____"""
    def CREATEFRAME(self, instr):
        if self.frames["TF"] != Type.UNDEF:
            frame = self.frames["TF"]
            self.vars_update(-len([var for var in frame if frame[var].type != Type.UNDEF]))
        self.frames["TF"] = {}

    def PUSHFRAME(self, instr):
        if self.frames["TF"] == Type.UNDEF:
            error("PUSHFRAME: Undefined Temporary Frame", Err.UndefFrame)
        # sub defined vars from top LF
        if len(self.frames["LF"]) > 0:
            frame = self.frames["LF"][-1]
            self.vars_update(-len([var for var in frame if frame[var].type != Type.UNDEF]))

        self.frames["LF"].append(self.frames["TF"])
        self.frames["TF"] = Type.UNDEF

    def POPFRAME(self, instr):
        if len(self.frames["LF"]) == 0:
            error("POPFRAME: Missing Local Frame", Err.UndefFrame)
        # sub defined vars from TF
        if self.frames["TF"] != Type.UNDEF:
            frame = self.frames["TF"]
            self.vars_update(-len([var for var in frame if frame[var].type != Type.UNDEF]))

        self.frames["TF"] = self.frames["LF"].pop()
        # add defined vars from now top LF
        if len(self.frames["LF"]) > 0:
            frame = self.frames["LF"][-1]
            self.vars_update(+len([var for var in frame if frame[var].type != Type.UNDEF]))

    """_____flow_control_____"""
    def LABEL(self, instr):
        label = self.label(self.get_arg(instr, 1), True)
        self.labels[label] = instr.attrib["order"]

    def CALL(self, instr):
        try:
            self.calls.append(int(instr.attrib["order"]))
        except:
            error("CALL: Allocation Failed", Err.Internal)
        else:
            label = self.label(self.get_arg(instr, 1), False)
            return self.labels[label]

    def RETURN(self, instr):
        if len(self.calls) == 0:
            error("RETURN: Missing destination", Err.UndefVal)
        return self.calls.pop()

    def JUMP(self, instr):
        label = self.label(self.get_arg(instr, 1), False)
        return self.labels[label]

    def EXIT(self, instr):
        type, val = self.symb(self.get_arg(instr, 1))
        if type != Type.INT:
            error("EXIT: Invalid operand", Err.Operands)
        if not (0 <= val <= 49):
            error("EXIT: Invalid exit value", Err.InvVal)
        if self.stats:
            self.stats_out()
        sys.exit(val)

    """_____data_stack_____"""
    def PUSHS(self, instr):
        if len(instr) == 0:
            if len(self.stack) == 0:
                error("PUSHS: Stack is Empty", Err.UndefVal)
            self.stack.append(self.stack[-1])
        else:
            type, val = self.symb(self.get_arg(instr, 1))
            self.stack.append((type, val))

    def POPS(self, instr):
        if len(self.stack) == 0:
            error("POPS: Stack is Empty", Err.UndefVal)
        if len(instr) == 0:
            self.stack.append(self.stack[-1])
        else:
            frame, name = self.var(self.get_arg(instr, 1))
            tmp = self.stack.pop()
            self.store(frame, name, tmp[0], tmp[1])

    def CLEARS(self, instr):
        self.stack.clear()

    """_____strings_____"""
    def CONCAT(self, instr):
        frame, name = self.var(self.get_arg(instr, 1))
        type1, val1 = self.symb(self.get_arg(instr, 2))
        type2, val2 = self.symb(self.get_arg(instr, 3))
        val1 = "" if val1 == Type.UNDEF else val1
        val2 = "" if val2 == Type.UNDEF else val2
        if (
                type1 not in {Type.UNDEF, Type.STRING}
            or  type2 not in {Type.UNDEF, Type.STRING}
            ):
            error("CONCAT: Both operands must be of type \"STRING\"", Err.Operands)
        self.store(frame, name, Type.STRING, val1 + val2)

    def STRLEN(self, instr):
        frame, name = self.var(self.get_arg(instr, 1))
        type, val = self.symb(self.get_arg(instr, 2))
        if type != Type.STRING:
            error("STRLEN: Operand must be of type \"STRING\"", Err.Operands)
        self.store(frame, name, Type.INT, len(val))

    def GETCHAR(self, instr):
        frame, name = self.var(self.get_arg(instr, 1))
        type1, str = self.symb(self.get_arg(instr, 2))
        type2, pos = self.symb(self.get_arg(instr, 3))
        if type1 != Type.STRING or type2 != Type.INT:
            error("GETCHAR: Operands must be of types \"STRING\" & \"INT\"", Err.Operands)
        if not (0 <= pos < len(str)):
            error("GETCHAR: Position out of range", Err.Str)
        self.store(frame, name, Type.STRING, str[pos])

    def SETCHAR(self, instr):
        frame, name = self.var(self.get_arg(instr, 1))
        type0, src = self.symb(self.get_arg(instr, 1))
        type1, pos = self.symb(self.get_arg(instr, 2))
        type2, str = self.symb(self.get_arg(instr, 3))
        if (    type0 != Type.STRING
            or  type1 != Type.INT or type2 != Type.STRING
            ):
            error("SETCHAR: Invalid operand types", Err.Operands)
        if not (0 <= pos < len(src)) or not str:
            error("SETCHAR: Position out of range", Err.Str)
        self.store(frame, name, Type.STRING, src[:pos] + str[0] + src[pos+1:])

    """_____conversions_____"""
    def INT2CHAR(self, instr):
        frame, name = self.var(self.get_arg(instr, 1))
        type, val = self.symb(self.get_arg(instr, 2))
        if type != Type.INT:
            error("INT2CHAR: Invalid operands", Err.Operands)
        try:
            c = chr(val)
        except:
            error("INT2CHAR: Invalid Unicode value", Err.Str)
        self.store(frame, name, Type.STRING, c)

    def STRI2INT(self, instr):
        frame, name = self.var(self.get_arg(instr, 1))
        type1, str = self.symb(self.get_arg(instr, 2))
        type2, pos = self.symb(self.get_arg(instr, 3))
        if type1 != Type.STRING or type2 != Type.INT:
            error("STRI2INT: Invalid operands", Err.Operands)
        if not (0 <= pos < len(str)):
            error("STRI2INT: Position out of range", Err.Str)
        self.store(frame, name, Type.INT, ord(str[pos]))

    def INT2FLOAT(self, instr):
        frame, name = self.var(self.get_arg(instr, 1))
        type, val = self.symb(self.get_arg(instr, 2))
        if type != Type.INT:
            error("INT2FLOAT: Invalid operands", Err.Operands)
        self.store(frame, name, Type.FLOAT, float(val))

    def FLOAT2INT(self, instr):
        frame, name = self.var(self.get_arg(instr, 1))
        type, val = self.symb(self.get_arg(instr, 2))
        if type != Type.FLOAT:
            error("FLOAT2INT: Invalid operands", Err.Operands)
        self.store(frame, name, Type.INT, int(val))

    def INT2CHARS(self, instr):
        if len(self.stack) == 0:
            error("INT2CHARS: Stack is Empty", Err.UndefVal)
        tmp = self.stack.pop()
        if tmp[0] != Type.INT:
            error("INT2CHARS: Invalid operads", Err.Operands)
        try:
            c = chr(tmp[1])
        except:
            error("INT2CHARS: Invalid Unicode value", Err.Str)
        self.stack.append((Type.STRING, c))

    def STRI2INTS(self, instr):
        if len(self.stack) == 0:
            error("STRI2INTS: Stack is Empty", Err.UndefVal)
        tmp2 = self.stack.pop()
        tmp1 = self.stack.pop()
        type1, str = tmp1[0], tmp1[1]
        type2, pos = tmp2[0], tmp2[1]
        if type1 != Type.STRING or type2 != Type.INT:
            error("STRI2INTS: Invalid operands", Err.Operands)
        if not (0 <= pos < len(str)):
            error("STRI2INTS: Position out of range", Err.Str)
        self.stack.append((Type.INT, ord(str[pos])))

    def INT2FLOATS(self, instr):
        if len(self.stack) == 0:
            error("INT2FLOATS: Stack is Empty", Err.UndefVal)
        tmp = self.stack.pop()
        if tmp[0] != Type.INT:
            error("INT2FLOATS: Invalid operands", Err.Operands)
        self.stack.append((Type.FLOAT, float(tmp[1])))

    def FLOAT2INTS(self, instr):
        if len(self.stack) == 0:
            error("FLOAT2INTS: Stack is Empty", Err.UndefVal)
        tmp = self.stack.pop()
        if tmp[0] != Type.FLOAT:
            error("FLOAT2INTS: Invalid operands", Err.Operands)
        self.stack.append((Type.INT, int(tmp[1])))

    """_____input/output_____"""
    def READ(self, instr):
        frame, name = self.var(self.get_arg(instr, 1))
        type = self.type(self.get_arg(instr, 2))
        try:
            in_val = input()
            val = {
                Type.INT: lambda x: int(x),
                Type.STRING: lambda x: str(x),
                Type.BOOL: lambda x: True if re.match(r"^true$", x, re.IGNORECASE) else False,
                Type.FLOAT: lambda x: float.fromhex(x),
            }[type](in_val)
        except:
            self.store(frame, name, Type.NIL, Type.NIL)
        else:
            self.store(frame, name, type, val)

    def WRITE(self, instr):
        type, val = self.symb(self.get_arg(instr, 1))
        if type == Type.UNDEF:
            error("WRITE: Undefined Value", Err.UndefVal)
        str = {
            Type.INT: lambda x: x,
            Type.STRING: lambda x: x,
            Type.BOOL: lambda x: "true" if x else "false",
            Type.NIL: lambda x: "",
            Type.FLOAT: lambda x: float.hex(x),
        }[type](val)
        print(str, end="")

    """_____debugging_____"""
    def DPRINT(self, instr):
        _, val = self.symb(self.get_arg(instr, 1))
        print(val, file=sys.stderr)

    def BREAK(self, instr):
        print("INSTR N: [" + str(self.cnt) + "] with ORDER: [" + str(instr.attrib["order"]) + "]", file=sys.stderr)
        print("[GF]:", file=sys.stderr)
        for var in self.frames["GF"]:
            print("\t(" + self.frames["GF"][var].type.name + ")\t", var, "= [" + str(self.frames["GF"][var].val) + "]", file=sys.stderr)

        print("[LF]:", file=sys.stderr)
        for i in range(len(self.frames["LF"])):
            print("\t{", file=sys.stderr)
            for var in self.frames["LF"][i]:
                print("\t    (" + self.frames["LF"][i][var].type.name + ")\t", var, "= [" + str(self.frames["LF"][i][var].val) + "]", file=sys.stderr)
            print("\t}", file=sys.stderr)

        print("[TF]:", file=sys.stderr)
        if self.frames["TF"] != Type.UNDEF:
            for var in self.frames["TF"]:
                print("\t(" + self.frames["TF"][var].type.name + ")\t", var, "= [" + str(self.frames["TF"][var].val) + "]", file=sys.stderr)

        print("[STACK]:", file=sys.stderr)
        for val in self.stack:
            print("\t(" + val[0].name + ")", str(val[1]), file=sys.stderr)
        print("\n", file=sys.stderr)

    # list of valid instructions in format:
    #   "OPCODE" : [exec_func, n_args]
    instrs = {
        "MOVE": (MOVE, 2), "DEFVAR": (DEFVAR, 1),
        "CREATEFRAME": (CREATEFRAME, 0), "PUSHFRAME": (PUSHFRAME, 0),
        "POPFRAME": (POPFRAME, 0), "CALL": (CALL, 1), "RETURN": (RETURN, 0),
        "PUSHS": (PUSHS, 1), "POPS": (POPS, 1), "CLEARS": (CLEARS, 0),
        "ADD": (operations, 3), "SUB": (operations, 3), "MUL": (operations, 3),
        "IDIV": (operations, 3), "DIV": (operations, 3),
        "ADDS": (operations, 0), "SUBS": (operations, 0), "MULS": (operations, 0),
        "IDIVS": (operations, 0), "DIVS": (operations, 0),
        "LT": (operations, 3), "GT": (operations, 3), "EQ": (operations, 3),
        "LTS": (operations, 0), "GTS": (operations, 0), "EQS": (operations, 0),
        "AND": (operations, 3), "OR": (operations, 3), "ANDS": (operations, 0),
        "ORS": (operations, 0), "NOT": (NOT, 2), "NOTS": (NOTS, 0),
        "INT2CHAR": (INT2CHAR, 2), "STRI2INT": (STRI2INT, 3),
        "INT2FLOAT": (INT2FLOAT, 2), "FLOAT2INT": (FLOAT2INT, 2),
        "INT2CHARS": (INT2CHARS, 0), "STRI2INTS": (STRI2INTS, 0),
        "INT2FLOATS": (INT2FLOATS, 0), "FLOAT2INTS": (FLOAT2INTS, 0),
        "READ": (READ, 2), "WRITE": (WRITE, 1), "CONCAT": (CONCAT, 3),
        "STRLEN": (STRLEN, 2), "GETCHAR": (GETCHAR, 3),
        "SETCHAR": (SETCHAR, 3), "TYPE": (TYPE, 2), "LABEL": (lambda *args: None, 1),
        "JUMP": (JUMP, 1), "JUMPIFEQ": (cond_jmp, 3), "JUMPIFNEQ": (cond_jmp, 3),
        "JUMPIFEQS": (cond_jmp, 1), "JUMPIFNEQS": (cond_jmp, 1),
        "EXIT": (EXIT, 1), "DPRINT": (DPRINT, 1), "BREAK": (BREAK, 0)
    }


def get_args():
    """Returns source file, stats options & output file
    Redirects intput stream to STDIN
    """
    # define CL arguments
    aparser = argparse.ArgumentParser(description="Interprets XML representation of IPPcode21 & generates outputs.")
    aparser.add_argument(
        "-s",
        "--source",
        required = False,
        default = "",
        help="specify source for XML representation of IPPcode21")
    aparser.add_argument(
        "-i",
        "--input",
        required = False,
        default = "",
        help="specify source for inputs of interpreted code")

    aparser.add_argument(
        "--stats",
        required = False,
        help="specify file for output of code statistics")
    aparser.add_argument(
        "--insts",
        required = False,
        action="store_true",
        help="number of executed instructions")
    aparser.add_argument(
        "--hot",
        required = False,
        action="store_true",
        help="\"order\" attribute of most executed instruction")
    aparser.add_argument(
        "--vars",
        required = False,
        action="store_true",
        help="max number of simultaneously initialized variables")

    if ("--help" in sys.argv or "-h" in sys.argv) and len(sys.argv) > 2:
        error("--help cannot be combined with other options", Err.Parameter)

    # parse CL arguments
    args = aparser.parse_args()

    # get source & input files
    if not (args.source or args.input):
        error("At least one of --source, --input needs to be specified", Err.Parameter)
    if args.source:
        try:
            tmp = open(args.source)
        except:
            error("Invalid source file", Err.FileIn)
        else:
            tmp.close()
    else:
        args.source = sys.stdin
    # redirect specified input to STDIN
    if args.input:
        try:
            sys.stdin = open(args.input)
        except:
            error("Invalid input file", Err.FileIn)

    # get stats options
    if any([args.insts, args.hot, args.vars]) and not args.stats:
        error("Stats options missing --stats", Err.Parameter)
    if args.stats:
        stats = [s[2:] for s in sys.argv if s in {"--insts", "--hot", "--vars"}]
    else:
        stats = []

    return args.source, stats, args.stats


def main():
    """SETUP"""
    src, stats, stats_file = get_args()
    interp = Interpret(stats, stats_file)
    # get source code as XML tree
    try:
        root = ET.parse(src).getroot()
    except ET.ParseError:
        error("XML input is not well-formed", Err.WellFormed)

    """VALIDATION"""
    # validate root
    if (    root.tag != "program"
        or  "language" not in root.attrib
        or  root.attrib["language"].upper() != "IPPCODE21"
        or  not all(a in {"language", "name", "description"} for a in root.attrib)
       ):
        error("Invalid root element", Err.UnexStruct)
    # skip if empty
    if len(root) == 0:
        sys.exit(0)

    # validate order of instructions
    try:
        # sort instructions by order
        root[:] = sorted(root, key=lambda x: int(x.attrib["order"]))
        # all positive & no duplicates
        if (    int(root[0].attrib["order"]) < 1
            or  len(root) != len(set([x.attrib["order"] for x in root]))
           ):
            raise ValueError
    except KeyError:
        error("Instruction missing attribute \"order\"", Err.UnexStruct)
    except ValueError:
        error("Instruction's attribute \"order\" has invalid value", Err.UnexStruct)

    # validate instructions & set up labels
    for child in root:
        interp.check(child)
        if child.attrib["opcode"] == "LABEL":
            interp.LABEL(child)

    """EXECUTION"""
    i = 0
    N = len(root)
    while i < N:    # iterate instructions
        jmp = interp.run(root[i])   # check for jump
        # assign following instruction OR jump destination
        i = i + 1 if not jmp else 1 + next((j for j in range(len(root)) if int(root[j].attrib["order"]) == int(jmp)), N)

    # stats output
    if interp.stats:
        interp.stats_out()

    sys.stdin.close()

if __name__ == "__main__":
    main()
