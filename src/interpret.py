#!/usr/bin/env python3.8

# File: interpret.py
# Brief: Implementation of interpret
#
# Project: Interpret for imperative programing language IPPcode21
#
# Authors: Jakub Bartko    xbartk07@stud.fit.vutbr.cz


# TODO:
# arg parsing
# reading source & input


import sys
import argparse
import xml.etree.ElementTree as ET


def error(msg, code):
    print("ERROR: " + msg, file=sys.stderr)
    sys.exit(code)


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
        print(child.tag, child.attrib)
        for attr in child:
            print("\t", attr.attrib["type"], ": ", attr.text)


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
        if root[0].attrib["order"] != "1":
            raise ValueError
    except KeyError:
        error("Instruction missing attribute \"order\"", 32)
    except ValueError:
        error("Instruction's attribute \"order\" has invalid value", 32)

    iterate_instructions(root)

if __name__ == "__main__":
    main()
