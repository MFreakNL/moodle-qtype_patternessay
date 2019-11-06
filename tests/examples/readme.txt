You should add pairs of files to this folder like

<name>.rules.txt - contains a single patternessay expression.
<name>.responses.csv - a CSV file with two to five columns:
    Response - a student response.
    Matches? - whether the response should match the rule, 1 or 0.
    Ignore Case (optional) - ignore case, 1 or 0.
    Sentence dividers (optional)
    Word Divider (optional)

There should be a row in the CSV file with the column headings, but the actual
text of the column headings is ignored.

These example files are read by the test script ../testexamples.php, and
used to test the patternessay library.

Tim Hunt, March 2011.
