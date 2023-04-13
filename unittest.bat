@echo off
rem Send the output to stderr so we can test functionality that uses stdout
php vendor\bin\phpunit --coverage-html reports/coverage-html --stderr