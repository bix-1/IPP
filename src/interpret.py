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


def error(msg, code):
    print("ERROR: " + msg, file=sys.stderr)
    sys.exit(code)


Type = Enum("Type", "UNDEF INT BOOL STRING NIL")

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

    def var_(self, v):
        if "type" not in v.attrib:
            error("Missing \"type\" attribute", 32);
        if v.attrib["type"] != "var":
            error("Invalid type", 32);
        try:
            frame, name = v.text.split("@")
        except:
            error("Invalid variable name", 32)
        if frame not in {"GF", "LF", "TF"}:
            error("Invalid frame", 32);

        return frame, name

    def symb_():
        pass

    def move_(self, instr):
        frame, name = self.var_(self.get_arg(instr, 1))
        if self.is_unique(frame, name):
            error("Variable undefined", 52)


    def defvar_(self, instr):
        frame, name = self.var_(self.get_arg(instr, 1))
        if not self.is_unique(frame, name):
            error("Variable already defined", 52)
        if frame == "GF" or frame == "TF":
            self.frames[frame][name] = [Type.UNDEF.value, Type.UNDEF.value],
        else:
            try:
                self.frames[frame][-1][name] = [Type.UNDEF.value, Type.UNDEF.value],
            except:
                error("Missing Local Frame", 52)


    # list of valid instructions in format: "OPCODE" : [functions],
    #   where functions are:
    #       interpret_function, check_arg1, check_arg2, check_arg3
    instrs = {
        "MOVE": (move_, var_, symb_),

        "DEFVAR": (defvar_, var_)
    }

    def run(self, instr):
        # get opcode
        try:
            opcode = instr.attrib["opcode"]
        except KeyError:
            error("Missing \"opcode\" attribute", 32)
        try:
            call = self.instrs[opcode][0]
            args = self.instrs[opcode][1:]
        except KeyError:
            error("Invalid opcode \"" + opcode + "\"", 32)

        # validate ammount of args
        if len(args) != len(instr):
            error("Invalid number of arguments for \"" + opcode + "\"", 32)

        call(self, instr)


interp = Interp()


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


def iterate_instructions(root):
    for child in root:
        # print(child.tag, child.attrib)
        # for attr in child:
        #     print("\t", attr.attrib["type"], ": ", attr.text)

        interp.run(child)


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

    iterate_instructions(root)

if __name__ == "__main__":
    main()
