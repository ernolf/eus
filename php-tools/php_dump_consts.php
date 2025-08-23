#!/usr/bin/env php
<?php
// php_dump_consts.php
// Dump all defined constants with optional filters (--prefix, --suffix, --pattern, --search).
// Features: grouping by category, type annotation, CLI options, help message.

function usage() {
    echo <<<HELP
Usage:
  php_dump_consts.php [OPTIONS] [CONSTNAME]

Options:
  --all            Show all constants grouped by category
  --category=NAME  Show constants only from given category (case insensitive)
  --prefix=STR     Show constants starting with STR
  --suffix=STR     Show constants ending with STR
  --pattern=REGEX  Show constants matching regex (PCRE)
  --search=SUBSTR  Show constants containing SUBSTR anywhere
  --help           Show this help

Notes:
  - If invoked without options, a summary of categories with counts is displeyed.
  - Use --all to display all constants in all categories.
  - If CONSTNAME is given without option, its value is displayed if it exists,
    otherwise a hint is printed.
  - Constants are grouped by category (core, pcre, standard, etc.).
  - Each constant shows its type: [int], [string], [bool], [array], [float], [null].

Examples:
  php_dump_consts.php                 # List categories with counts
  php_dump_consts.php --category=core # Show constants in 'core'
  php_dump_consts.php --all           # Show all constants
  php_dump_consts.php --prefix=E_     # Show all error constants
  php_dump_consts.php PHP_EOL         # Show value of constant PHP_EOL
  php_dump_consts.php --suffix=_ERROR # All constants ending with _ERROR
  php_dump_consts.php --pattern='/^STD/' # Regex match
  php_dump_consts.php --search=HTTP   # All constants containing 'HTTP'

HELP;
    exit(0);
}

function format_value($v) {
    switch (gettype($v)) {
        case "boolean":
            return "[bool]   = " . ($v ? "true" : "false");
        case "integer":
            return "[int]    = $v";
        case "double":
            return "[float]  = $v";
        case "string":
            // escape newlines etc
            $escaped = addcslashes($v, "\n\r\t\\'");
            return "[string] = '$escaped'";
        case "NULL":
            return "[null]   = null";
        case "array":
            return "[array]  = array(" . count($v) . ")";
        default:
            return "[" . gettype($v) . "] = (unprintable)";
    }
}

// parse args
$args = $argv;
array_shift($args);

$filters = [
    'all'      => false,
    'category' => null,
    'prefix'   => null,
    'suffix'   => null,
    'pattern'  => null,
    'search'   => null
];
$singleConst = null;

foreach ($args as $a) {
    if ($a === "--help") usage();
    elseif ($a === "--all") $filters['all'] = true;
    elseif (str_starts_with($a, "--category=")) $filters['category'] = substr($a, 11);
    elseif (str_starts_with($a, "--prefix="))   $filters['prefix'] = substr($a, 9);
    elseif (str_starts_with($a, "--suffix="))   $filters['suffix'] = substr($a, 9);
    elseif (str_starts_with($a, "--pattern="))  $filters['pattern'] = substr($a, 10);
    elseif (str_starts_with($a, "--search="))   $filters['search'] = substr($a, 9);
    elseif (str_starts_with($a, "--")) {
        fwrite(STDERR, "Unknown option '$a'. Use --help for usage.\n");
        exit(1);
    } else {
        $singleConst = $a;
    }
}

$consts = get_defined_constants(true);

// **1. Default behavior: no arguments**
if (empty($argv) || count($argv) === 1) { // only sckriptname
//if (!$args) {
    // list categories with number of constants
    foreach ($consts as $category => $arr) {
        printf("== %-15s %d\n", $category, count($arr));
    }
    exit(0);
}

// **2. Ensure 'all' filter exists**
if (!isset($filters['all'])) $filters['all'] = false;

if ($singleConst) {
    foreach ($consts as $category => $arr) {
        if (array_key_exists($singleConst, $arr)) {
            echo "== $category ==\n";
            printf("%-40s %s\n", $singleConst, format_value($arr[$singleConst]));
            exit(0);
        }
    }
    fwrite(STDERR, "Constant '$singleConst' not found. Use --prefix, --suffix, --pattern or --search, or --help for usage.\n");
    exit(1);
}

foreach ($consts as $category => $arr) {
    // Category filter here, before checking individual constants
    if ($filters['category'] && strcasecmp($category, $filters['category']) !== 0) continue;

    $matches = [];
    foreach ($arr as $name => $value) {
        $ok = true;
        if (!$filters['all']) {
            if ($filters['prefix'] && !str_starts_with($name, $filters['prefix'])) $ok = false;
            if ($filters['suffix'] && !str_ends_with($name, $filters['suffix'])) $ok = false;
            if ($filters['pattern'] && !preg_match('/'.$filters['pattern'].'/', $name)) $ok = false;
            if ($filters['search'] && stripos($name, $filters['search']) === false) $ok = false;
        }
        if ($ok) $matches[$name] = $value;
    }

    if ($matches) {
        echo "== $category ==\n";
        foreach ($matches as $name => $value) {
            printf("%-40s %s\n", $name, format_value($value));
        }
    }
}

