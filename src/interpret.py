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




# handle command line arguments
aparser = argparse.ArgumentParser(description="Interpret XML input.")
aparser.add_argument(
    "--source",
    required = False,
    default = "",
    help="input file with XML representation of IPPcode21")
aparser.add_argument(
    "--input",
    required = False,
    default = "",
    help="input file with XML representation of IPPcode21")

args = aparser.parse_args()

if not (args.source or args.input):
    error("At least one of --source, --input needs to be specified", 11)

# get source stream
if args.source:
    src = open(args.source, "r")
else:
    src = sys.stdin
# get input stream
if args.input:
    input = open(args.input, "r")
else:
    input = sys.stdin


tree = ET.parse(src)
root = tree.getroot()
for child in root.iter():
    print(child.tag, child.attrib)



# teardown
if src is not sys.stdin:
    src.close()
if input is not sys.stdin:
    input.close()
