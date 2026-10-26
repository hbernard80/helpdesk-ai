<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [];
foreach (['src', 'tests', 'tools'] as $directory) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}
sort($files);

$errors = [];
foreach ($files as $file) {
    $relative = str_replace('\\', '/', substr($file, strlen($root) + 1));
    $content = file_get_contents($file);
    if ($content === false) {
        $errors[] = $relative . ' : fichier illisible.';
        continue;
    }
    if (str_starts_with($content, "\xEF\xBB\xBF") || preg_match('//u', $content) !== 1) {
        $errors[] = $relative . ' : UTF-8 sans BOM attendu.';
    }
    if (str_contains($content, "\r")) {
        $errors[] = $relative . ' : fins de ligne LF attendues.';
    }
    if (!str_ends_with($content, "\n")) {
        $errors[] = $relative . ' : saut de ligne final manquant.';
    }
    if (preg_match('/[ \t]+$/m', $content) === 1) {
        $errors[] = $relative . ' : espaces en fin de ligne.';
    }
    if (preg_match('/^[ \t]*\t/m', $content) === 1) {
        $errors[] = $relative . ' : utiliser des espaces pour indenter.';
    }

    $process = proc_open(
        [PHP_BINARY, '-d', 'xdebug.mode=off', '-l', $file],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        $errors[] = $relative . ' : impossible de lancer le contrôle syntaxique.';
        continue;
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        $errors[] = $relative . ' : ' . trim($output . $stderr);
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

echo 'OK : syntaxe et conventions de base pour ' . count($files) . " fichiers PHP.\n";
