<?php

/**
 * Standalone Crossref XSD validator.
 *
 * Run OUT OF PROCESS from the test suite, on purpose.
 *
 * Crossref's real schema graph is 21 files (~800KB, including the whole JATS + MathML3
 * element set). Compiling that grammar inside a booted Laravel test process SEGFAULTS
 * libxml on this platform — not an exception, a hard crash that kills the runner and
 * reports nothing. Given the choice between deleting the test and isolating it, isolate:
 * a fresh PHP process has a clean heap, and if it does die, the parent still gets a
 * usable failure message instead of an empty screen.
 *
 * Usage:  php validate.php <xml-file>
 * Exit:   0 = valid, 1 = invalid (errors on stdout), 2 = could not run
 */
$xmlPath = $argv[1] ?? null;

if ($xmlPath === null || ! is_file($xmlPath)) {
    fwrite(STDERR, "usage: php validate.php <xml-file>\n");
    exit(2);
}

$schema = __DIR__.'/crossref5.3.1.xsd';

if (! is_file($schema)) {
    fwrite(STDERR, "missing schema: {$schema}\n");
    exit(2);
}

libxml_use_internal_errors(true);

$doc = new DOMDocument;

if (! $doc->load($xmlPath)) {
    echo "XML is not well-formed.\n";
    exit(1);
}

$valid = $doc->schemaValidate($schema);

$errors = [];

foreach (libxml_get_errors() as $error) {
    $message = trim($error->message);

    // Not a defect in OUR document: libxml resolves the MathML namespace once and then
    // reports the second (identical) import as skipped. Nothing we emit uses MathML.
    if (str_contains($message, 'Skipping import of schema')) {
        continue;
    }

    $errors[] = "line {$error->line}: {$message}";
}

if ($valid && $errors === []) {
    echo "VALID\n";
    exit(0);
}

echo implode("\n", $errors ?: ['unknown validation failure'])."\n";
exit(1);
