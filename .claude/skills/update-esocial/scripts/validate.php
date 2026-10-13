<?php
/**
 * Gera cada exemplo de examples/Fake/<leiaute>/Fake_s*.php e valida o XML no XSD de schemes/<leiaute>.
 * Uso: php .claude/skills/update-esocial/scripts/validate.php [v_S_01_03_00]
 */
$root = realpath(__DIR__ . '/../../../../');
$layout = $argv[1] ?? 'v_S_01_03_00';
$dir = "$root/examples/Fake/$layout";
$schemes = "$root/schemes/$layout";
if (!is_dir($dir) || !is_dir($schemes)) {
    fwrite(STDERR, "Pasta não encontrada: $dir ou $schemes\n");
    exit(2);
}
$files = glob("$dir/Fake_s*.php");
sort($files);
$fail = 0;
foreach ($files as $file) {
    $name = basename($file, '.php');
    $cmd = 'cd ' . escapeshellarg($dir) . ' && php -d xdebug.mode=off ' . escapeshellarg(basename($file)) . ' 2>/dev/null';
    $out = (string) shell_exec($cmd);
    $pos = strpos($out, '<eSocial');
    if ($pos === false) {
        $fail++;
        $msg = trim(preg_replace('/\s+/', ' ', substr(strip_tags($out), -200)));
        echo "FALHA  $name: não gerou XML ($msg)\n";
        continue;
    }
    $xml = substr($out, $pos);
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    libxml_clear_errors();
    if (!$dom->loadXML($xml)) {
        $fail++;
        echo "FALHA  $name: XML mal formado\n";
        continue;
    }
    $evt = $dom->documentElement->firstElementChild?->localName;
    $xsd = "$schemes/$evt.xsd";
    if (!$evt || !is_file($xsd)) {
        $fail++;
        echo "FALHA  $name: XSD não encontrado para '$evt'\n";
        continue;
    }
    if ($dom->schemaValidate($xsd)) {
        echo "ok     $name ($evt)\n";
        continue;
    }
    $fail++;
    $errs = array_map(fn($e) => trim($e->message), libxml_get_errors());
    echo "FALHA  $name ($evt): " . implode(' | ', array_slice($errs, 0, 3)) . "\n";
}
echo "\n" . (count($files) - $fail) . "/" . count($files) . " exemplos válidos no XSD de $layout\n";
exit($fail ? 1 : 0);
