<?php

// Sets one variable in the server's .env from STDIN, so a secret never appears in a command line or a log.
// Usage on the server: <value on stdin> | php deploy/set_env.php NAME
$name = $argv[1] ?? '';
if (! preg_match('/^[A-Z][A-Z0-9_]*$/', $name)) {
    fwrite(STDERR, "nom de variable invalide\n");
    exit(1);
}
$value = trim((string) stream_get_contents(STDIN));
if ($value === '' || preg_match('/\s|"|\'/', $value)) {
    fwrite(STDERR, "valeur vide ou invalide\n");
    exit(1);
}

$file = __DIR__.'/../.env';
$env = file_get_contents($file);
$line = $name.'='.$value;
$env = preg_match('/^'.$name.'=.*$/m', $env)
    ? preg_replace('/^'.$name.'=.*$/m', str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $env)
    : rtrim($env)."\n".$line."\n";
file_put_contents($file, $env);
echo "$name mis à jour\n";
