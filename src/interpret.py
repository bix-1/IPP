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
        # TODO check type
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

    def symb():
        """check for constant and variable & return value"""
        pass

    def label():
        pass

    def move_(self, instr):
        frame, name = self.var(self.get_arg(instr, 1))
        if self.is_unique(frame, name):
            error("Variable \"" + name + "\" undefined", 52)
        # self.frames[frame][name].set()

    def defvar_(self, instr):
        frame, name = self.var(self.get_arg(instr, 1))
        if not self.is_unique(frame, name):
            error("Variable already defined", 52)
        if frame == "GF" or frame == "TF":
            self.frames[frame][name] = Var()
        else:
            try:
                self.frames[frame][-1][name] = Var()
            except:
                error("Missing Local Frame", 52)


    # list of valid instructions in format:
    #   "OPCODE" : [run_func, "arg1", "arg2", "arg3"]
    instrs = {
        "MOVE": (move_, "var", "symb"),

        "DEFVAR": (defvar_, "var")
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
        if len(instr) != len(self.instrs[opcode])-1:
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
