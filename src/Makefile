# Makefile for IPP project 2021, FIT BUT
# Authors: Jakub Bartko, xbartk07@stud.fit.vutbr.cz

LOGIN = xbartk07
SCRIPTS = parse.php interpret.py test.php
# TESTS =
DOC = readme1.pdf readme2.pdf

.PHONY: pack clean

pack:	clean
	zip $(LOGIN).zip $(SCRIPTS) rozsireni
	cd ../doc/ && zip ../src/$(LOGIN).zip $(DOC)

check:	pack
	mkdir testDir/
	bash is_it_ok.sh $(LOGIN).zip testDir/ 1
	bash is_it_ok.sh $(LOGIN).zip testDir/ 2:
	rm -rf testDir/

clean:
	rm -f *.zip
	rm -rf testDir/
