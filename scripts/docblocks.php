<?php

declare(strict_types=1);

/**
 * Strip comments from the PHP sources and give every function a one-line PHPDoc.
 *
 *   php scripts/docblocks.php analyse   → JSON of functions needing a summary
 *   php scripts/docblocks.php apply     → rewrite the files in place
 *
 * Rules:
 *  - `//`, `#` and `/* … *\/` comments are removed outright.
 *  - A docblock keeps its first sentence and its tags; every other line goes.
 *  - A function with no summary gets one: from scripts/summaries.json when the
 *    key `path::Class::method` is present, otherwise a generated one for test
 *    methods (from the name) and for standard CRUD names.
 */
$root = dirname(__DIR__);
$mode = $argv[1] ?? 'analyse';

$paths = ['app', 'database', 'routes', 'config', 'tests', 'bootstrap'];
$files = [];
foreach ($paths as $path) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$path"));
    foreach ($it as $f) {
        if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
            $files[] = $f->getPathname();
        }
    }
}
sort($files);

$summaries = file_exists("$root/scripts/summaries.json")
    ? json_decode((string) file_get_contents("$root/scripts/summaries.json"), true, 512, JSON_THROW_ON_ERROR)
    : [];

$needs = [];

$crud = [
    'index' => 'List the %s.',
    'create' => 'Show the form for creating %s.',
    'store' => 'Create %s from the submitted form.',
    'show' => 'Show one %s.',
    'edit' => 'Show the form for editing %s.',
    'update' => 'Update %s from the submitted form.',
    'destroy' => 'Delete %s.',
    'up' => 'Run the migration.',
    'down' => 'Reverse the migration.',
    'run' => 'Seed the database.',
    'definition' => 'Define the model\'s default state.',
    'handle' => 'Handle the incoming request.',
    'rules' => 'The validation rules that apply to the request.',
    'authorize' => 'Whether the user is authorised to make this request.',
    'messages' => 'The custom error messages for the validation rules.',
    'attributes' => 'The custom attribute names for the validation errors.',
    'prepareForValidation' => 'Normalise the input before it is validated.',
    'register' => 'Register application services.',
    'boot' => 'Bootstrap application services.',
    'setUp' => 'Prepare the test case.',
    'casts' => 'The attributes that should be cast.',
    '__construct' => 'Create a new instance.',
    '__invoke' => 'Handle the invocation.',
    'toArray' => 'Convert the object to an array.',
    'toMail' => 'Build the mail representation of the notification.',
    'via' => 'The channels the notification is delivered on.',
    'envelope' => 'The message envelope.',
    'content' => 'The message content.',
    'attachments' => 'The message attachments.',
    'headers' => 'The column headers of the spreadsheet.',
    'share' => 'The props shared with every Inertia page.',
    'version' => 'The current asset version.',
    'rootView' => 'The root Blade template.',
];

function resourceName(string $class): string
{
    $base = preg_replace('/Controller$/', '', class_basename_of($class));
    $words = strtolower(trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $base)));

    return match (true) {
        $words === '' => 'the resource',
        str_ends_with($words, 's') || $words === 'alumni' => 'the '.$words,
        default => 'a '.$words,
    };
}

function class_basename_of(string $class): string
{
    $parts = explode('\\', $class);

    return end($parts);
}

function firstSentence(string $text): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text));
    if ($text === '') {
        return '';
    }
    $parts = preg_split('/(?<=[.!?])\s+(?=[A-Z«"`(])/', $text, 2);

    $sentence = trim($parts[0]);

    return preg_match('/[.!?]$/', $sentence) ? $sentence : $sentence.'.';
}

/**
 * Reduce a docblock to summary + tags. Returns [summary, tagLines].
 *
 * @return array{0: string, 1: list<string>}
 */
function reduceDoc(string $doc): array
{
    $lines = preg_split('/\R/', $doc);
    $body = [];
    foreach ($lines as $line) {
        $line = preg_replace('/^\s*\/\*\*\s?/', '', $line);
        $line = preg_replace('/\s*\*\/\s*$/', '', $line);
        $line = preg_replace('/^\s*\*\s?/', '', $line);
        $body[] = rtrim($line);
    }

    $prose = [];
    $tags = [];
    $inTag = false;
    foreach ($body as $line) {
        if (preg_match('/^@\w/', $line)) {
            $inTag = true;
            $tags[] = $line;

            continue;
        }
        if ($inTag) {
            if (trim($line) === '') {
                $inTag = false;

                continue;
            }
            // Continuation of a wrapped tag.
            $tags[count($tags) - 1] .= ' '.trim($line);

            continue;
        }
        $prose[] = $line;
    }

    // First paragraph only.
    $paragraph = [];
    foreach ($prose as $line) {
        if (trim($line) === '' && $paragraph !== []) {
            break;
        }
        if (trim($line) !== '') {
            $paragraph[] = trim($line);
        }
    }

    $summary = firstSentence(implode(' ', $paragraph));

    // Tag-only docblocks with a leading description on the same line as @var are kept as is.
    return [$summary, $tags];
}

function buildDoc(string $indent, string $summary, array $tags): string
{
    if ($summary === '' && $tags === []) {
        return '';
    }
    if ($summary === '' && count($tags) === 1 && ! str_starts_with($tags[0], '@param') && ! str_starts_with($tags[0], '@return')) {
        return "/** {$tags[0]} */";
    }
    $out = "/**\n";
    if ($summary !== '') {
        $out .= "$indent * $summary\n";
    }
    if ($summary !== '' && $tags !== []) {
        $out .= "$indent *\n";
    }
    foreach ($tags as $tag) {
        $out .= "$indent * $tag\n";
    }
    $out .= "$indent */";

    return $out;
}

foreach ($files as $file) {
    $rel = str_replace('\\', '/', substr($file, strlen($root) + 1));
    $src = (string) file_get_contents($file);
    $tokens = token_get_all($src);

    $class = '';
    $out = '';
    $n = count($tokens);

    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];

        if (is_array($t) && ($t[0] === T_CLASS || $t[0] === T_TRAIT || $t[0] === T_INTERFACE || $t[0] === T_ENUM)) {
            // Name follows.
            for ($j = $i + 1; $j < $n; $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    $class = $tokens[$j][1];
                    break;
                }
                if (! is_array($tokens[$j]) || $tokens[$j][0] !== T_WHITESPACE) {
                    if (is_string($tokens[$j]) && $tokens[$j] === '{') {
                        $class = 'anonymous';
                    }
                    break;
                }
            }
        }

        if (is_array($t) && $t[0] === T_COMMENT) {
            $out .= "\x00CMT\x00";

            continue;
        }

        if (is_array($t) && $t[0] === T_DOC_COMMENT) {
            [$summary, $tags] = reduceDoc($t[1]);

            // What does this docblock belong to?
            $k = $i + 1;
            while ($k < $n && is_array($tokens[$k]) && in_array($tokens[$k][0], [T_WHITESPACE, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_ABSTRACT, T_FINAL, T_READONLY, T_ATTRIBUTE], true)) {
                if ($tokens[$k][0] === T_ATTRIBUTE) {
                    // skip to closing ]
                    $depth = 0;
                    for (; $k < $n; $k++) {
                        if ($tokens[$k] === '[' || (is_array($tokens[$k]) && $tokens[$k][0] === T_ATTRIBUTE)) {
                            $depth++;
                        } elseif ($tokens[$k] === ']') {
                            $depth--;
                            if ($depth === 0) {
                                break;
                            }
                        }
                    }
                }
                $k++;
            }
            $isFunction = $k < $n && is_array($tokens[$k]) && $tokens[$k][0] === T_FUNCTION;
            $isClassLike = $k < $n && is_array($tokens[$k]) && in_array($tokens[$k][0], [T_CLASS, T_TRAIT, T_INTERFACE, T_ENUM], true);

            if ($isFunction) {
                $name = functionName($tokens, $k);
                $key = "$rel::$class::$name";
                if ($summary === '') {
                    $summary = $summaries[$key] ?? generated($name, $class, $rel);
                    if ($summary === '') {
                        $needs[$key] = ['file' => $rel, 'class' => $class, 'function' => $name, 'line' => $t[2], 'has_doc' => true];
                    }
                }
            } elseif (! $isClassLike) {
                // Property / constant / inline docblock: tags only, no prose.
                $summary = '';
            }

            $indent = '';
            if (preg_match('/([ \t]*)$/', $out, $m)) {
                $indent = $m[1];
            }
            $doc = buildDoc($indent, $summary, $tags);
            $out .= $doc === '' ? "\x00CMT\x00" : $doc;

            continue;
        }

        if (is_array($t) && $t[0] === T_FUNCTION) {
            $name = functionName($tokens, $i);
            if ($name !== null) {
                // Is there a docblock before (skipping whitespace, modifiers, attributes)?
                $k = $i - 1;
                while ($k >= 0 && is_array($tokens[$k]) && in_array($tokens[$k][0], [T_WHITESPACE, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_ABSTRACT, T_FINAL], true)) {
                    $k--;
                }
                // Attribute block: walk back over it.
                if ($k >= 0 && $tokens[$k] === ']') {
                    $depth = 0;
                    for (; $k >= 0; $k--) {
                        if ($tokens[$k] === ']') {
                            $depth++;
                        } elseif (is_array($tokens[$k]) && $tokens[$k][0] === T_ATTRIBUTE) {
                            $depth--;
                            if ($depth === 0) {
                                $k--;
                                break;
                            }
                        }
                    }
                    while ($k >= 0 && is_array($tokens[$k]) && in_array($tokens[$k][0], [T_WHITESPACE, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_ABSTRACT, T_FINAL], true)) {
                        $k--;
                    }
                }
                $hasDoc = $k >= 0 && is_array($tokens[$k]) && $tokens[$k][0] === T_DOC_COMMENT;
                if (! $hasDoc) {
                    $key = "$rel::$class::$name";
                    $summary = $summaries[$key] ?? generated($name, $class, $rel);
                    if ($summary === '') {
                        $needs[$key] = ['file' => $rel, 'class' => $class, 'function' => $name, 'line' => $t[2], 'has_doc' => false];
                    } else {
                        // Insert a docblock before the modifiers on this line.
                        $out = insertDocBeforeDeclaration($out, $summary);
                    }
                }
            }
            $out .= $t[1];

            continue;
        }

        $out .= is_array($t) ? $t[1] : $t;
    }

    if ($mode === 'apply') {
        $out = removeSentinels($out);
        if ($out !== $src) {
            file_put_contents($file, $out);
        }
    }
}

function functionName(array $tokens, int $i): ?string
{
    $n = count($tokens);
    for ($j = $i + 1; $j < $n; $j++) {
        if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
            continue;
        }
        if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
            return $tokens[$j][1];
        }
        if (is_string($tokens[$j]) && $tokens[$j] === '&') {
            continue;
        }

        return null; // closure
    }

    return null;
}

function generated(string $name, string $class, string $file): string
{
    global $crud;

    if (str_starts_with($name, 'test_')) {
        $words = str_replace('_', ' ', substr($name, 5));

        return ucfirst($words).'.';
    }
    if (str_starts_with($file, 'tests/') && in_array($name, ['setUp', 'tearDown'], true)) {
        return $name === 'setUp' ? 'Prepare the test case.' : 'Clean up after the test.';
    }
    if (isset($crud[$name])) {
        $template = $crud[$name];

        return str_contains($template, '%s') ? sprintf($template, resourceName($class)) : $template;
    }

    return '';
}

function insertDocBeforeDeclaration(string $out, string $summary): string
{
    // Find the start of the current line (the declaration's modifiers are already in $out).
    $lineStart = strrpos($out, "\n");
    $lineStart = $lineStart === false ? 0 : $lineStart + 1;
    $line = substr($out, $lineStart);
    preg_match('/^([ \t]*)/', $line, $m);
    $indent = $m[1];
    $doc = "/**\n$indent * $summary\n$indent */\n$indent";

    return substr($out, 0, $lineStart).$indent.$doc.ltrim($line);
}

function removeSentinels(string $out): string
{
    $lines = preg_split('/(\R)/', $out, -1, PREG_SPLIT_DELIM_CAPTURE);
    $result = '';
    for ($i = 0; $i < count($lines); $i += 2) {
        $line = $lines[$i];
        $nl = $lines[$i + 1] ?? '';
        if (str_contains($line, "\x00CMT\x00")) {
            $stripped = str_replace("\x00CMT\x00", '', $line);
            if (trim($stripped) === '') {
                continue; // the whole line was a comment
            }
            $line = rtrim($stripped);
        }
        $result .= $line.$nl;
    }

    // Collapse runs of blank lines to one, and blank lines right after `{` or before `}`.
    $result = preg_replace("/\n{3,}/", "\n\n", $result);
    $result = preg_replace("/\{\n\n/", "{\n", $result);
    $result = preg_replace("/\n\n(\s*)\}/", "\n$1}", $result);

    return $result;
}

if ($mode === 'analyse') {
    echo json_encode(array_values($needs), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
    fwrite(STDERR, count($needs)." functions need a summary\n");
}
