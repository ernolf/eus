#!/usr/bin/env php
<?php
// phpscan_fix_nullable.php
// Detect and optionally fix implicitly nullable typed parameters (Type $p = null).
// Default: insert ?Type. Option --union: insert Type|null.
// This version is robust: it records function-occurrence index + parameter index during scan,
// and then patches by function/param index — attributes (#[...]) are preserved / skipped.

// -------------------- helpers --------------------
function tokenText($t) { return is_array($t) ? $t[1] : $t; }
function tokenId($t)   { return is_array($t) ? $t[0] : null; }

// parse excludes from argv like --exclude=a,b or multiple --exclude
function parse_argv_for_excludes(array $argv): array {
    $excludes = [];
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if (strpos($arg, '--exclude=') === 0) {
            $val = substr($arg, strlen('--exclude='));
            $parts = array_filter(array_map('trim', explode(',', $val)));
            foreach ($parts as $p) $excludes[] = $p;
        } elseif ($arg === '--exclude') {
            $next = $argv[$i+1] ?? null;
            if ($next !== null) {
                $parts = array_filter(array_map('trim', explode(',', $next)));
                foreach ($parts as $p) $excludes[] = $p;
                $i++;
            }
        }
    }
    return $excludes;
}

// split parameter tokens into leading tokens (attributes/whitespace/comments) and body tokens (type, var, default)
function splitLeadingAttributes(array $paramTokens): array {
    $i = 0; $n = count($paramTokens);
    $leading = [];
    // initial whitespace/comment
    while ($i < $n && is_array($paramTokens[$i]) && in_array($paramTokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
        $leading[] = $paramTokens[$i]; $i++;
    }
    // T_ATTRIBUTE token (PHP might provide it)
    if ($i < $n && is_array($paramTokens[$i]) && $paramTokens[$i][0] === T_ATTRIBUTE) {
        $leading[] = $paramTokens[$i]; $i++;
        while ($i < $n && is_array($paramTokens[$i]) && $paramTokens[$i][0] === T_WHITESPACE) { $leading[] = $paramTokens[$i]; $i++; }
        $body = array_slice($paramTokens, $i);
        return [$leading, $body];
    }
    // hash-bracket form '#[' ... ']' detection (we collect all tokens until matching ']')
    while (true) {
        while ($i < $n && is_array($paramTokens[$i]) && in_array($paramTokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) { $leading[] = $paramTokens[$i]; $i++; }
        if ($i >= $n) break;
        $txt = tokenText($paramTokens[$i]);
        if ($txt === '#') {
            // include '#' token and everything until first ']' token (simple, works for typical attribute syntax)
            $leading[] = $paramTokens[$i]; $i++;
            while ($i < $n) {
                $leading[] = $paramTokens[$i];
                $t = tokenText($paramTokens[$i]);
                $i++;
                if ($t === ']') break;
            }
            continue;
        }
        break;
    }
    // trailing whitespace after attributes
    while ($i < $n && is_array($paramTokens[$i]) && $paramTokens[$i][0] === T_WHITESPACE) { $leading[] = $paramTokens[$i]; $i++; }
    $body = array_slice($paramTokens, $i);
    return [$leading, $body];
}

// detect whether leading tokens indicate an attribute
function leadingHasAttribute(array $leadingTokens): bool {
    foreach ($leadingTokens as $t) {
        if (is_array($t) && isset($t[0]) && $t[0] === T_ATTRIBUTE) return true;
        $txt = tokenText($t);
        // if a '#' or '#[' occurs in leading tokens, treat as attribute
        if (strpos($txt, '#') !== false || strpos($txt, '#[') !== false) return true;
    }
    return false;
}

// find type start index and tokens in a body token array
function findTypeStartInBody(array $bodyTokens): array {
    $modTokenIds = [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_READONLY, T_VAR, T_STATIC, T_FINAL];
    $validTypeIds = [T_STRING];
    if (defined('T_NAME_QUALIFIED')) $validTypeIds[] = T_NAME_QUALIFIED;
    if (defined('T_NAME_FULLY_QUALIFIED')) $validTypeIds[] = T_NAME_FULLY_QUALIFIED;
    if (defined('T_NAME_RELATIVE')) $validTypeIds[] = T_NAME_RELATIVE;

    // find variable index
    $varIdx = -1;
    for ($i = 0; $i < count($bodyTokens); $i++) {
        if (is_array($bodyTokens[$i]) && $bodyTokens[$i][0] === T_VARIABLE) { $varIdx = $i; break; }
    }
    if ($varIdx === -1) return [null, []];

    for ($m = 0; $m < $varIdx; $m++) {
        $pt = $bodyTokens[$m];
        if (is_array($pt) && in_array($pt[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) continue;
        if (is_array($pt) && in_array($pt[0], $modTokenIds)) continue;
        $txt = tokenText($pt);
        if ($txt === '&' || $txt === '...' || $txt === '(' || $txt === ')' || $txt === ',') continue;
        $isValid = false;
        if (is_array($pt) && in_array($pt[0], $validTypeIds)) $isValid = true;
        $tText = tokenText($pt);
        if ($tText === '?' || $tText === '\\') $isValid = true;
        if (!$isValid && preg_match('/^[A-Za-z_\\\\\\\\][A-Za-z0-9_\\\\\\\\]*$/', $tText)) $isValid = true;
        if ($isValid) {
            $typeTokens = [];
            for ($k = $m; $k < $varIdx; $k++) {
                $tt = $bodyTokens[$k];
                if (is_string($tt) && (tokenText($tt) === '&' || tokenText($tt) === '...')) break;
                if (is_array($tt) && in_array($tt[0], $modTokenIds)) break;
                $typeTokens[] = $tt;
            }
            return [$m, $typeTokens];
        }
    }
    return [null, []];
}

// -------------------- CLI parsing --------------------
$argvExcludes = parse_argv_for_excludes($GLOBALS['argv']);

$options = getopt('', [
    'path::', 'list', 'dry-run', 'apply',
    'extensions::', 'help', 'exclude::', 'union',
    'restore', 'undo', 'suffix::'
]);

if (isset($options['help'])) {
    echo "Usage: phpscan_fix_nullable.php --path=/path/to/scan [--list] [--dry-run] [--apply] [--exclude=pat1,pat2] [--extensions=php] [--union] [--restore|--undo] [--suffix=sfx]\n";
    exit(0);
}

// suffix handling
$suffix = $options['suffix'] ?? '.phpscan_fix_nullable.bak';

// path handling
$path = $options['path'] ?? '.';
if ($path === '' || $path === false) $path = '.';
if (!is_dir($path) || !is_readable($path)) { fwrite(STDERR, "Error: invalid path\n"); exit(1); }

// restore mode
if (isset($options['restore']) || isset($options['undo'])) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $file = $f->getPathname();
        if (substr($file, -strlen($suffix)) === $suffix) {
            $orig = substr($file, 0, -strlen($suffix));
            echo "Restoring $orig\n";
            if (!@rename($file, $orig)) {
                echo "Failed to restore $orig\n";
            }
        }
    }
    echo "Restore done.\n";
    exit(0);
}

$doApply = isset($options['apply']);
$doDryRun = isset($options['dry-run']);
$doList = isset($options['list']) || (! $doApply && ! $doDryRun);
$useUnion = isset($options['union']);
$exts = array_map('trim', explode(',', $options['extensions'] ?? 'php'));

// build exclude regexes
$excludes = [];
if (!empty($argvExcludes)) $excludes = array_merge($excludes, $argvExcludes);
if (isset($options['exclude']) && $options['exclude'] !== false) $excludes = array_merge($excludes, array_filter(array_map('trim', explode(',', $options['exclude']))));
$excludeRegexes = [];
foreach ($excludes as $e) { if ($e === '') continue; $excludeRegexes[] = "#/".preg_quote($e,'#')."/#"; }

// -------------------- gather files --------------------
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
foreach ($it as $f) {
    if (!$f->isFile()) continue;
    $ext = strtolower(pathinfo($f->getFilename(), PATHINFO_EXTENSION));
    if (!in_array($ext, $exts)) continue;
    $full = $f->getPathname();
    if (preg_match('#/(node_modules|\.git)/#', $full)) continue;
    $skip = false;
    foreach ($excludeRegexes as $r) { if (preg_match($r, $full)) { $skip = true; break; } }
    if ($skip) continue;
    $files[] = $full;
}

// -------------------- scan: record function-occurrence index + param index --------------------
$issuesByFile = []; // file => funcIndex => array of paramIndex => typeStartInBody
foreach ($files as $file) {
    $code = file_get_contents($file);
    if ($code === false) continue;
    $tokens = token_get_all($code);
    $count = count($tokens);
    $funcCount = 0;
    for ($i = 0; $i < $count; $i++) {
        if (is_array($tokens[$i]) && $tokens[$i][0] === T_FUNCTION) {
            // find '('
            $j = $i+1;
            while ($j < $count && tokenText($tokens[$j]) !== '(') $j++;
            if ($j >= $count) { $funcCount++; continue; }
            // collect params between matching parentheses
            $depth = 0; $paramTokens = [];
            for ($k = $j; $k < $count; $k++) {
                $paramTokens[] = $tokens[$k];
                $ch = tokenText($tokens[$k]);
                if ($ch === '(') $depth++; elseif ($ch === ')') { $depth--; if ($depth === 0) { $endIndex = $k; break; } }
            }
            if (!isset($endIndex)) { $funcCount++; continue; }
            if (count($paramTokens) && tokenText($paramTokens[0]) === '(') array_shift($paramTokens);
            if (count($paramTokens) && tokenText(end($paramTokens)) === ')') array_pop($paramTokens);
            // split params by top-level commas
            $params = []; $cur = []; $lvl = 0;
            foreach ($paramTokens as $pt) {
                $txt = tokenText($pt);
                if ($txt === '(') { $lvl++; $cur[] = $pt; continue; }
                if ($txt === ')') { $lvl--; $cur[] = $pt; continue; }
                if ($txt === ',' && $lvl === 0) { $params[] = $cur; $cur = []; continue; }
                $cur[] = $pt;
            }
            if (count($cur) > 0) $params[] = $cur;
            // analyze each param
            foreach ($params as $pIdx => $pTokens) {
                $pFlat = ''; foreach ($pTokens as $pt) $pFlat .= tokenText($pt);
                if (stripos($pFlat, '=') === false || stripos($pFlat, 'null') === false) continue;
                list($leading, $body) = splitLeadingAttributes($pTokens);
                // SKIP parameters that have an attribute in leading tokens
                if (leadingHasAttribute($leading)) continue;
                if (count($body) === 0) continue;
                // must have variable and literal null default
                $hasVar = false; foreach ($body as $bt) if (is_array($bt) && $bt[0] === T_VARIABLE) { $hasVar = true; break; }
                if (!$hasVar) continue;
                $hasNullDefault = false;
                for ($m = 0; $m < count($body); $m++) {
                    if (is_string($body[$m]) && trim($body[$m]) === '=') {
                        for ($n = $m+1; $n < count($body); $n++) {
                            if (is_array($body[$n]) && in_array($body[$n][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) continue;
                            if (strtolower(tokenText($body[$n])) === 'null') { $hasNullDefault = true; break 2; }
                            break;
                        }
                    }
                }
                if (!$hasNullDefault) continue;
                list($typeStart, $typeTokens) = findTypeStartInBody($body);
                if ($typeStart === null || count($typeTokens) === 0) continue;
                $typeStr = ''; foreach ($typeTokens as $tt) $typeStr .= tokenText($tt);
                $typeTrim = trim($typeStr);
                if ($typeTrim === '' || $typeTrim[0] === '?' || strpos($typeTrim, '|') !== false || strcasecmp($typeTrim, 'mixed') === 0) continue;
                // record: file => funcCount => array of entries with paramIndex and typeStart
                $issuesByFile[$file][$funcCount][] = ['paramIndex' => $pIdx, 'typeStart' => $typeStart];
            }
            $funcCount++;
        }
    }
}

// report
$total = 0; foreach ($issuesByFile as $f => $arr) { foreach ($arr as $x) $total += count($x); }
if ($total === 0) {
    echo "No implicit-nullable typed parameters found.\n";
    exit(0);
}
echo "Found {$total} candidate(s):\n";
$n = 0;
foreach ($issuesByFile as $file => $funcs) {
    foreach ($funcs as $funcIdx => $entries) {
        foreach ($entries as $entry) {
            $n++;
            echo "---\n";
            echo "#{$n}: {$file}  function-occurrence: {$funcIdx}  param-index: {$entry['paramIndex']}  typeStartInBody: {$entry['typeStart']}\n";
        }
    }
}
if ($doList && !($doApply || $doDryRun)) {
    echo "\nList mode. To preview changes use --dry-run, to apply use --apply.\n";
    exit(0);
}

// -------------------- apply/preview: patch by function-occurrence + param index --------------------
foreach ($issuesByFile as $file => $funcs) {
    $orig = file_get_contents($file);
    if ($orig === false) continue;
    $tokens = token_get_all($orig);
    $count = count($tokens);
    $out = '';
    $i = 0;
    $funcCount = 0;
    while ($i < $count) {
        $t = $tokens[$i];
        if (is_array($t) && $t[0] === T_FUNCTION) {
            // copy until '('
            $out .= tokenText($t); $i++;
            while ($i < $count && tokenText($tokens[$i]) !== '(') { $out .= tokenText($tokens[$i]); $i++; }
            if ($i >= $count) { break; }
            $out .= '('; $i++;
            // collect params buffer
            $depth = 1; $paramBuf = [];
            while ($i < $count && $depth > 0) {
                $tok = $tokens[$i];
                $txt = tokenText($tok);
                if ($txt === '(') { $depth++; $paramBuf[] = $tok; $i++; continue; }
                if ($txt === ')') { $depth--; if ($depth === 0) { $i++; break; } $paramBuf[] = $tok; $i++; continue; }
                $paramBuf[] = $tok; $i++;
            }
            // split paramBuf into params by top-level commas
            $params = []; $cur = []; $lvl = 0;
            foreach ($paramBuf as $pt) {
                $ptext = tokenText($pt);
                if ($ptext === '(') { $lvl++; $cur[] = $pt; continue; }
                if ($ptext === ')') { $lvl--; $cur[] = $pt; continue; }
                if ($ptext === ',' && $lvl === 0) { $params[] = $cur; $cur = []; continue; }
                $cur[] = $pt;
            }
            if (count($cur) > 0) $params[] = $cur;
            // if this function has recorded issues, prepare a quick lookup by paramIndex
            $toFix = [];
            if (isset($funcs[$funcCount])) {
                foreach ($funcs[$funcCount] as $e) $toFix[$e['paramIndex']] = $e['typeStart'];
            }
            // rebuild params: if param index in toFix and parameter has no attribute, modify body accordingly
            foreach ($params as $pIdx => $pTokens) {
                if (!isset($toFix[$pIdx])) {
                    foreach ($pTokens as $pt) $out .= tokenText($pt);
                    if ($pTokens !== end($params)) $out .= ',';
                    continue;
                }
                // split leading/body
                list($leading, $body) = splitLeadingAttributes($pTokens);
                // if leading has attribute, skip modification (safety)
                if (leadingHasAttribute($leading)) {
                    foreach ($pTokens as $pt) $out .= tokenText($pt);
                    if ($pTokens !== end($params)) $out .= ',';
                    continue;
                }
                $typeStart = $toFix[$pIdx];
                // ensure typeStart within body bounds
                if ($typeStart < 0 || $typeStart >= count($body)) {
                    foreach ($pTokens as $pt) $out .= tokenText($pt);
                    if ($pTokens !== end($params)) $out .= ',';
                    continue;
                }
                // re-check: if already '?', skip
                if (tokenText($body[$typeStart]) === '?') {
                    foreach ($pTokens as $pt) $out .= tokenText($pt);
                    if ($pTokens !== end($params)) $out .= ',';
                    continue;
                }
                // perform modification
                if (!$useUnion) {
                    foreach ($leading as $lt) $out .= tokenText($lt);
                    for ($b = 0; $b < count($body); $b++) {
                        if ($b === $typeStart) $out .= '?';
                        $out .= tokenText($body[$b]);
                    }
                    if ($pTokens !== end($params)) $out .= ',';
                } else {
                    // union mode: append '|null' after last non-whitespace/comment token before variable
                    $varIdx = -1; for ($bi = 0; $bi < count($body); $bi++) if (is_array($body[$bi]) && $body[$bi][0] === T_VARIABLE) { $varIdx = $bi; break; }
                    if ($varIdx === -1) { foreach ($pTokens as $pt) $out .= tokenText($pt); if ($pTokens !== end($params)) $out .= ','; continue; }

//                    $endIdx = $varIdx - 1; while ($endIdx >= 0 && is_array($body[$endIdx]) && in_array($body[$endIdx][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) $endIdx--;
                    $endIdx = $varIdx - 1;
                    // skip whitespace/comments and also skip reference (&) and variadic (...) tokens
                    while ($endIdx >= 0) {
                        $tok = $body[$endIdx];
                        // skip whitespace/comments tokens
                        if (is_array($tok) && in_array($tok[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                            $endIdx--;
                            continue;
                        }
                        // get token text (works for strings and token arrays)
                        $ttext = tokenText($tok);
                        // skip reference '&' and variadic '...' that can appear between type and variable
                        if ($ttext === '&' || $ttext === '...') {
                            $endIdx--;
                            continue;
                        }
                        // otherwise we've found the last token that belongs to the type
                        break;
                    }

                    if ($endIdx < 0) { foreach ($pTokens as $pt) $out .= tokenText($pt); if ($pTokens !== end($params)) $out .= ','; continue; }

                    $typeConcise = ''; for ($x = $typeStart; $x <= $endIdx; $x++) $typeConcise .= tokenText($body[$x]);
                    if (strpos($typeConcise, '|') !== false || stripos($typeConcise, 'null') !== false) { foreach ($pTokens as $pt) $out .= tokenText($pt); if ($pTokens !== end($params)) $out .= ','; continue; }
                    foreach ($leading as $lt) $out .= tokenText($lt);
                    for ($b = 0; $b <= $endIdx; $b++) $out .= tokenText($body[$b]);
                    $out .= '|null';
                    for ($b = $endIdx+1; $b < count($body); $b++) $out .= tokenText($body[$b]);
                    if ($pTokens !== end($params)) $out .= ',';
                }
            }
            $out .= ')';
            $funcCount++;
            continue;
        } else {
            $out .= tokenText($t);
            $i++;
        }
    } // end tokens loop

    if ($out === $orig) continue;

    echo "File: $file -- changes detected\n";
    if ($doDryRun) {
        $oLines = explode("\n", $orig);
        $nLines = explode("\n", $out);
        $max = max(count($oLines), count($nLines));
        for ($l = 0; $l < $max; $l++) {
            $a = $oLines[$l] ?? ''; $b = $nLines[$l] ?? '';
            if ($a !== $b) {
                echo sprintf("-%4d: %s\n", $l+1, $a);
                echo sprintf("+%4d: %s\n", $l+1, $b);
            }
        }
        echo "---- end preview ----\n";
    } elseif ($doApply) {
        $bak = $file . $suffix;
        if (!copy($file, $bak)) { echo "Failed to backup $file\n"; continue; }
        if (file_put_contents($file, $out) === false) { echo "Failed to write modified file $file\n"; copy($bak, $file); continue; }
        echo "Applied fix and backed up original to $bak\n";
    } else {
        echo "Preview available. Run with --dry-run or --apply.\n";
    }
}

echo "Done.\n";

