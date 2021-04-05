# Makefile for IPP project 2021, FIT BUT
# Authors: Jakub Bartko, xbartk07@stud.fit.vutbr.cz

LOGIN = xbartk07
SCRIPTS = parse.php interpret.py test.php
# TESTS =
DOC = readme1.pdf readme2.pdf

.PHONY: pack clean

pack:	clean doc
	zip $(LOGIN).zip $(SCRIPTS) rozsireni
	cd ../doc/ && zip ../src/$(LOGIN).zip $(DOC)

doc:
	cd ../doc/ && pdflatex readme1.tex
	cd ../doc/ && pdflatex readme1.tex
	cd ../doc/ && pdflatex readme2.tex
	cd ../doc/ && pdflatex readme2.tex
	cd ../doc/ && rm -f *.log *.out *.aux

check:	pack
	mkdir testDir/
	bash is_it_ok.sh $(LOGIN).zip testDir/ 1
	bash is_it_ok.sh $(LOGIN).zip testDir/ 2:
	rm -rf testDir/

test:
	@echo "<h1>Parser Only</h1>" >x.html
	./test.php --directory=../tests/parse-only/ --recursive --parse-only >>x.html
	@echo "<h1>Interpret Only</h1>" >>x.html
	./test.php --directory=../tests/interpret-only/ --recursive --int-only >>x.html
	@echo "<h1>Both</h1>" >>x.html
	./test.php --directory=../tests/both/ --recursive >>x.html

clean:
	rm -rf testDir/ *.zip *.pdf *.html
