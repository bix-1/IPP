#!/usr/bin/env python3.8

# File: interpret.py
# Brief: Implementation of interpret
#
# Project: Interpret for imperative programing language IPPcode21
#
# Authors: Jakub Bartko    xbartk07@stud.fit.vutbr.cz


# TODO:
#   symb_
#   finish MOVE

import sys
import argparse
import xml.etree.ElementTree as ET
from enum import Enum
import re

def error(msg, code):
    print("ERROR: " + msg, file=sys.stderr)
    sys.exit(code)


Type = Enum("Type", "UNDEF INT BOOL STRING NIL")

class Var:
    def __init__(self):
        self.type = Type.UNDEF
        self.val = Type.UNDEF

    def set(self, type, value):
        # TODO del??
        if self.type != Type.UNDEF and type != self.type:
            error("Incompatible variable types", 52)
        self.type = type
        self.val = value

class Interp:
    frames = {
        "GF": {},
        "LF": [],
        "TF": {}
    }

    def get_arg(self, instr, n):
        return next(arg for arg in instr if arg.tag == "arg" + str(n))

    def is_unique(self, frame, name):
        if frame == "GF" or frame == "TF":
            return name not in self.frames[frame]
        else:
            return (not self.frames[frame]) or (name not in self.frames[frame][-1]),

    def var(self, v):
        if v.attrib["type"] != "var":
            error("Invalid type -- Expected \"var\"", 32);
        try:
            frame, name = v.text.split("@")
            if (not re.match(r"^[a-zA-Z_\-$&%*!?][a-zA-Z0-9_\-$&%*!?]*$", name)):
                raise Exception()
        except:
            error("Invalid variable name", 32)
        if frame not in {"GF", "LF", "TF"}:
            error("Invalid frame", 32);

        return frame, name

    def symb(self, s):
        """check for constant and variable & return value"""
        type = s.attrib["type"]
        if type == "var":
            frame, name = self.var(s)
            if self.is_unique(frame, name):
                error("Variable \"" + name + "\" undefined", 52)
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
                error("Invalid integer value", 32)
        elif type == "string":
            return Type.STRING, re.sub(r"\\(\d{3})", lambda x: chr(int(x.group(1))), s.text)

        error("Invalid constant value", 32)

    def label():
        pass

    def MOVE(self, instr):
        frame, name = self.var(self.get_arg(instr, 1))
        if self.is_unique(frame, name):
            error("Variable \"" + name + "\" undefined", 52)
        type, val = self.symb(self.get_arg(instr, 2))
        self.frames[frame][name].set(type, val)

    def CREATEFRAME:
		pass

    def PUSHFRAME:
		pass

    def POPFRAME:
		pass

    def DEFVAR(self, instr):
        frame, name = self.var(self.get_arg(instr, 1))
        if not self.is_unique(frame, name):
            error("Variable already defined", 52)
        if frame == "GF" or frame == "TF":
            self.frames[frame][name] = Var()
        else:
            try:
                self.frames[frame][-1][name] = Var()
            except:
                error("Missing Local Frame", 55)

    def CALL:
		pass

    def RETURN:
		pass

    def PUSHS:
		pass

    def POPS:
		pass

    def ADD:
		pass

    def SUB:
		pass

    def MUL:
		pass

    def IDIV:
		pass

    def LT:
		pass

    def GT:
		pass

    def EQ:
		pass

    def AND:
		pass

    def OR:
		pass

    def NOT:
		pass

    def INT2CHAR:
		pass

    def STRI2INT:
		pass

    def READ:
		pass

    def WRITE:
		pass

    def CONCAT:
		pass

    def STRLEN:
		pass

    def GETCHAR:
		pass

    def SETCHAR:
		pass

    def TYPE:
		pass

    def LABEL:
		pass

    def JUMP:
		pass

    def JUMPIFEQ:
		pass

    def JUMPIFNEQ:
		pass

    def EXIT:
		pass

    def DPRINT:
		pass

    def BREAK:
        pass


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
        "SETCHAR": (SETCHAR, 3), "TYPE": (TYPE, 2), "LABEL": (LABEL, 1),
        "JUMP": (JUMP, 1), "JUMPIFEQ": (JUMPIFEQ, 3), "JUMPIFNEQ": (JUMPIFNEQ, 3),
        "EXIT": (EXIT, 1), "DPRINT": (DPRINT, 1), "BREAK": (BREAK, 0)
    }

    def run(self, instr):
        """call func from list of instructions"""
        self.instrs[instr.attrib["opcode"]][0](self, instr)

    def check(self, instr):
        # validate attributes
        if (    len(instr.attrib) != 2
            or  any(a not in {"order", "opcode"} for a in instr.attrib)
            ):
            error("Invalid attributes", 32)

        opcode = instr.attrib["opcode"]
        if opcode not in self.instrs:
            error("Invalid opcode \"" + opcode + "\"", 32)

        # validate ammount of args
        if len(instr) != self.instrs[opcode][1]:
            error("Invalid number of arguments for \"" + opcode + "\"", 32)
        # validate format of args
        if (    any("arg" + str(i+1) not in [arg.tag for arg in instr] for i in range(0, len(instr)))
            or  any(not arg.attrib for arg in instr)
            or  any(not arg.attrib or attr != "type" for arg in instr for attr in arg.attrib)
            ):
            error("Invalid instruction argument", 32)


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
        error("At least one of --source, --input needs to be specified", 11)
    if not args.source:
        args.source = sys.stdin
    if not args.input:
        args.input = sys.stdin

    return args.source, args.input


def main():
    src, input = get_args()

    # get source code in XML tree
    try:
        root = ET.parse(src).getroot()
    except ET.ParseError:
        error("XML input is not well-formed", 31)

    # validate root
    if (    root.tag != "program"
        or  "language" not in root.attrib
        or  root.attrib["language"].lower() != "ippcode21"
        or  not all(a in {"language", "name", "description"} for a in root.attrib)
       ):
        error("Invalid root element", 32)

    # sort instructions by [attribute] order
    try:
        root[:] = sorted(root, key=lambda x: int(x.attrib["order"]))
        # validate starting number & no duplicates
        if (    root[0].attrib["order"] != "1"
            or  len(root) != len(set([x.attrib["order"] for x in root]))
           ):
            raise ValueError
    except KeyError:
        error("Instruction missing attribute \"order\"", 32)
    except ValueError:
        error("Instruction's attribute \"order\" has invalid value", 32)

    interp = Interp()
    for child in root:
        interp.check(child)
        interp.run(child)

if __name__ == "__main__":
    main()
