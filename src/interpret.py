#!/usr/bin/env python3.8

# File: interpret.py
# Brief: Implementation of interpret
#
# Project: Interpret for imperative programing language IPPcode21
#
# Authors: Jakub Bartko    xbartk07@stud.fit.vutbr.cz


# TODO:
#   TF, frames

import sys
import argparse
import xml.etree.ElementTree as ET
from enum import Enum
import re

def error(msg, code):
    print("ERROR: " + msg, file=sys.stderr)
    sys.exit(code.value)


class Err(Enum):
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
Type = Enum("Type", "UNDEF INT BOOL STRING NIL")

class Var:
    def __init__(self):
        self.type = Type.UNDEF
        self.val = Type.UNDEF

    def set(self, type, value):
        # TODO del??
        if self.type != Type.UNDEF and type != Type.UNDEF and type != self.type:
            error("Incompatible variable types", Err.Semantics)
        if type == Type.UNDEF and self.type != Type.UNDEF:
            pass
        self.type = type
        self.val = value

class Interp:
    cnt = 0
    frames = {
        "GF": {},
        "LF": [],
        "TF": {}
    }
    labels = {}
    calls = []
    stack = []

    def get_arg(self, instr, n):
        return next(arg for arg in instr if arg.tag == "arg" + str(n))

    def is_unique(self, frame, name):
        if frame == "GF" or frame == "TF":
            return name not in self.frames[frame]
        else:
            return (not self.frames[frame]) or (name not in self.frames[frame][-1]),

    def store(self, frame, name, type, val):
        if self.is_unique(frame, name):
            error("Variable undefined", Err.UndefVar)
        if frame == "LF":
            try:
                self.frames[frame][-1][name].set(type, val)
            except:
                error("Missing Local Frame", Err.UndefFrame)
        else:
            self.frames[frame][name].set(type, val)

    def arithm(self, instr, op):
        frame, name = self.var(self.get_arg(instr, 1))
        type1, val1 = self.symb(self.get_arg(instr, 2))
        type2, val2 = self.symb(self.get_arg(instr, 3))
        if type1 not in {Type.INT, Type.UNDEF} or type2 not in {Type.INT, Type.UNDEF}:
            error(op + ": Both operands must be \"INT\"", Err.Operands)
        if op == "IDIV" and val2 == 0:
            error("IDIV: Zero division", Err.InvVal)
        val = {
            "ADD":  lambda x,y: x + y,
            "SUB":  lambda x,y: x - y,
            "MUL":  lambda x,y: x * y,
            "IDIV": lambda x,y: x // y
        }[op](val1, val2)
        self.store(frame, name, Type.INT, val)

    def relat(self, instr, op):
        frame, name = self.var(self.get_arg(instr, 1))
        type1, val1 = self.symb(self.get_arg(instr, 2))
        type2, val2 = self.symb(self.get_arg(instr, 3))
        if (    (type1 == Type.NIL or type2 == Type.NIL)
            and type1 != type2 and op != "EQ"
            ):
            error(op + " \"nil\" can only be compared using \"EQ\"", Err.Operands)
        elif type1 != type2:
            error(op + " : Operands must be of the same type", Err.Operands)
        val = {
            "LT": lambda x,y: x < y,
            "GT": lambda x,y: x > y,
            "EQ": lambda x,y: x == y
        }[op](val1, val2)
        self.store(frame, name, Type.BOOL, val)

    def bools(self, instr, op):
        frame, name = self.var(self.get_arg(instr, 1))
        type1, val1 = self.symb(self.get_arg(instr, 2))
        type2, val2 = self.symb(self.get_arg(instr, 3))
        if type1 != Type.BOOL or type2 != Type.BOOL:
            error(op + ": Both operands must be of type \"BOOL\"", Err.Operands)
        val = {
            "AND": lambda x,y: x and y,
            "OR": lambda x,y: x or y
        }[op](val1, val2)
        self.store(frame, name, Type.BOOL, val)

    def cond_jmp(self, instr, op):
        label = self.label(self.get_arg(instr, 1), False)
        type1, val1 = self.symb(self.get_arg(instr, 2))
        type2, val2 = self.symb(self.get_arg(instr, 3))
        if (type1 != type2) and type1 != Type.NIL and type2 != Type.NIL:
            error("JUMPIFEQ: Invalid operands", Err.Operands)
        cond = {
            "EQ": val1 == val2,
            "NEQ": val1 != val2
        }[op]
        if cond:
            return self.labels[label]


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
            var = self.frames[frame][name]
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
            or  t.text not in {"int", "string", "bool"}
            ):
            error("Invalid type", Err.Operands)
        return {
            "int": Type.INT,
            "string": Type.STRING,
            "bool": Type.BOOL,
        }[t.text]


    def MOVE(self, instr):
        frame, name = self.var(self.get_arg(instr, 1))
        type, val = self.symb(self.get_arg(instr, 2))
        self.store(frame, name, type, val)

    def CREATEFRAME(self, instr):
        pass

    def PUSHFRAME(self, instr):
        pass

    def POPFRAME(self, instr):
        pass

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

    def CALL(self, instr):
        try:
            self.calls.append(int(instr.attrib["order"]) + 1)
        except:
            error("CALL: Allocation Failed", 99)
        else:
            label = self.label(self.get_arg(instr, 1), False)
            return self.labels[label]

    def RETURN(self, instr):
        if not self.calls:
            error("RETURN: Missing destination", Err.UndefVal)
        return self.calls.pop()

    def PUSHS(self, instr):
        if len(instr) == 0:
            if not self.stack:
                error("PUSHS: Stack is Empty", Err.UndefVal)
            self.stack.append(self.stack[-1])
            return
        type, val = self.symb(self.get_arg(instr, 1))
        self.stack.append((type, val))

    def POPS(self, instr):
        if not self.stack:
            error("POPS: Stack is Empty", Err.UndefVal)
        if not len(instr) == 0:
            self.stack.append(self.stack[-1])
            return
        frame, name = self.var(self.get_arg(instr, 1))
        tmp = self.stack.pop()
        self.store(frame, name, tmp[0], tmp[1])

    def ADD(self, instr):
        self.arithm(instr, "ADD")

    def SUB(self, instr):
        self.arithm(instr, "SUB")

    def MUL(self, instr):
        self.arithm(instr, "MUL")

    def IDIV(self, instr):
        self.arithm(instr, "IDIV")

    def LT(self, instr):
        self.relat(instr, "LT")

    def GT(self, instr):
        self.relat(instr, "GT")

    def EQ(self, instr):
        self.relat(instr, "EQ")

    def AND(self, instr):
        self.bools(instr, "AND")

    def OR(self, instr):
        self.bools(instr, "OR")

    def NOT(self, instr):
        frame, name = self.var(self.get_arg(instr, 1))
        type, val = self.symb(self.get_arg(instr, 2))
        if type != Type.BOOL:
            error("NOT: Operand must be of type \"BOOL\"", Err.Operands)
        self.store(frame, name, Type.BOOL, not val)

    def INT2CHAR(self, instr):
        frame, name = self.var(self.get_arg(instr, 1))
        type, val = self.symb(self.get_arg(instr, 2))
        try:
            if type != Type.INT:
                raise Exception()
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
        if pos > len(str):
            error("STRI2INT: Position out of range", Err.Str)
        self.store(frame, name, Type.STRING, str[pos])

    def READ(self, instr):
        frame, name = self.var(self.get_arg(instr, 1))
        type = self.type(self.get_arg(instr, 2))
        try:
            in_val = input()
            val = {
                Type.INT: lambda x: int(x),
                Type.STRING: lambda x: str(x),
                Type.BOOL: lambda x: True if re.match(r"^true$", x, re.IGNORECASE) else False
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
            Type.INT: val,
            Type.STRING: val,
            Type.BOOL: "true" if val else "false",
            Type.NIL: "",
        }[type]
        print(str, end="")

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
        if pos > len(str):
            error("GETCHAR: Position out of range", Err.Str)
        self.store(frame, name, Type.STRING, str[pos])

    def SETCHAR(self, instr):
        frame, name = self.var(self.get_arg(instr, 1))
        type0, src = self.var(self.get_arg(instr, 1))
        type1, pos = self.symb(self.get_arg(instr, 2))
        type2, str = self.symb(self.get_arg(instr, 3))
        if (    type0 != Type.STRING
            or  type1 != Type.INT or type2 != Type.STRING
            ):
            error("SETCHAR: Invalid operand types", Err.Operands)
        if pos > len(src) or not str:
            error("SETCHAR: Position out of range", Err.Str)
        self.store(frame, name, Type.STRING, src[:pos] + str[0] + src[pos+1:])

    def TYPE(self, instr):
        frame, name = self.var(self.get_arg(instr, 1))
        type, _ = self.symb(self.get_arg(instr, 2))
        str_type = {
            Type.UNDEF: "",
            Type.INT: "int",
            Type.BOOL: "bool",
            Type.STRING: "string",
            Type.NIL: "nil"
        }[type]
        self.store(frame, name, Type.STRING, str_type)

    def LABEL(self, instr):
        label = self.label(self.get_arg(instr, 1), True)
        self.labels[label] = instr.attrib["order"]

    def JUMP(self, instr):
        label = self.label(self.get_arg(instr, 1), False)
        return self.labels[label]

    def JUMPIFEQ(self, instr):
        return self.cond_jmp(instr, "EQ")

    def JUMPIFNEQ(self, instr):
        return self.cond_jmp(instr, "NEQ")

    def EXIT(self, instr):
        type, val = self.symb(self.get_arg(instr, 1))
        if type != Type.INT:
            error("EXIT: Invalid operand", Err.Operands)
        if not (0 <= val <= 49):
            error("EXIT: Invalid exit value", Err.InvVal)
        sys.exit(val)

    def DPRINT(self, instr):
        _, val = self.symb(self.get_arg(instr, 1))
        print(val, file=sys.stderr)

    def BREAK(self, instr):
        print("INSTR N: [" + str(self.cnt) + "] with ORDER: [" + str(instr.attrib["order"]) + "]", file=sys.stderr)
        for frame in interp.frames:
            print("[" + frame + "]:", file=sys.stderr)
            for var in interp.frames[frame]:
                print("\t(" + interp.frames[frame][var].type.name + ")\t", var, "= [" + str(interp.frames[frame][var].val) + "]", file=sys.stderr)

        print("[STACK]:")
        for val in self.stack:
            print("\t(" + val[0].name + ")", str(val[1]))
        print("\n")


    # list of valid instructions in format:
    #   "OPCODE" : [run_func, "arg1", "arg2", "arg3"]
    instrs = {
        "MOVE": (MOVE, 2),
        "CREATEFRAME": (CREATEFRAME, 0), "PUSHFRAME": (PUSHFRAME, 0),
        "POPFRAME": (POPFRAME, 0), "DEFVAR": (DEFVAR, 1),
        "CALL": (CALL, 1), "RETURN": (RETURN, 0), "PUSHS": (PUSHS, 1),
        "POPS": (POPS, 1), "ADD": (ADD, 3), "SUB": (SUB, 3),
        "MUL": (MUL, 3), "IDIV": (IDIV, 3), "LT": (LT, 3), "GT": (GT, 3),
        "EQ": (EQ, 3),"AND": (AND, 3), "OR": (OR, 3), "NOT": (NOT, 2),
        "INT2CHAR": (INT2CHAR, 2), "STRI2INT": (STRI2INT, 3),
        "READ": (READ, 2), "WRITE": (WRITE, 1), "CONCAT": (CONCAT, 3),
        "STRLEN": (STRLEN, 2), "GETCHAR": (GETCHAR, 3),
        "SETCHAR": (SETCHAR, 3), "TYPE": (TYPE, 2), "LABEL": (lambda *args: None, 1),
        "JUMP": (JUMP, 1), "JUMPIFEQ": (JUMPIFEQ, 3), "JUMPIFNEQ": (JUMPIFNEQ, 3),
        "EXIT": (EXIT, 1), "DPRINT": (DPRINT, 1), "BREAK": (BREAK, 0)
    }

    def run(self, instr):
        """call func from list of instructions
        Return order_n of jmp destination in case of jmp instruction
        """
        self.cnt += 1
        return self.instrs[instr.attrib["opcode"]][0](self, instr)

    def check(self, instr):
        # validate attributes
        if (    len(instr.attrib) != 2
            or  any(a not in {"order", "opcode"} for a in instr.attrib)
            ):
            error("Invalid attributes", Err.UnexStruct)

        opcode = instr.attrib["opcode"]
        if opcode not in self.instrs:
            error("Invalid opcode", Err.UnexStruct)

        # validate ammount of args
        if len(instr) != self.instrs[opcode][1] and opcode not in {"PUSHS", "POPS"}:
            error("Invalid number of arguments for \"" + opcode + "\"", Err.UnexStruct)
        # validate format of args
        if (    any("arg" + str(i+1) not in [arg.tag for arg in instr] for i in range(0, len(instr)))
            or  any(not arg.attrib for arg in instr)
            or  any(not arg.attrib or attr != "type" for arg in instr for attr in arg.attrib)
            ):
            error("Invalid instruction argument", Err.UnexStruct)


def get_args():
    # define CL arguments
    aparser = argparse.ArgumentParser(description="Interpret XML representation of IPPcode21 & generate outputs.")
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

    # parse CL arguments
    args = aparser.parse_args()
    if not (args.source or args.input):
        error("At least one of --source, --input needs to be specified", 10)
    if args.source:
        try:
            tmp = open(args.source)
        except:
            error("Invalid source file", Err.FileIn)
    else:
        args.source = sys.stdin
    # redirect specified input to STDIN
    if args.input:
        try:
            sys.stdin = open(args.input)
        except:
            error("Invalid input file", Err.FileIn)

    return args.source


interp = Interp()
src = get_args()

def main():
    # get source code in XML tree
    try:
        root = ET.parse(src).getroot()
    except ET.ParseError:
        error("XML input is not well-formed", Err.WellFormed)

    # validate root
    if (    root.tag != "program"
        or  "language" not in root.attrib
        or  root.attrib["language"].lower() != "ippcode21"
        or  not all(a in {"language", "name", "description"} for a in root.attrib)
       ):
        error("Invalid root element", Err.UnexStruct)

    # sort instructions by [attribute] order
    try:
        root[:] = sorted(root, key=lambda x: int(x.attrib["order"]))
        # validate all positive & no duplicates
        if (    int(root[0].attrib["order"]) < 1
            or  len(root) != len(set([x.attrib["order"] for x in root]))
           ):
            raise ValueError
    except KeyError:
        error("Instruction missing attribute \"order\"", Err.UnexStruct)
    except ValueError:
        error("Instruction's attribute \"order\" has invalid value", Err.UnexStruct)

    # handle instr validation & labels
    for child in root:
        interp.check(child)
        if child.attrib["opcode"] == "LABEL":
            interp.LABEL(child)

    i = 0
    N = len(root)
    while i < N:
        jmp = interp.run(root[i])   # check for jump dest
        # assign jmp destination OR next instruction
        i = i + 1 if not jmp else 1 + next((j for j in range(len(root)) if root[j].attrib["order"] == jmp), N)

    sys.stdin.close()

if __name__ == "__main__":
    main()
