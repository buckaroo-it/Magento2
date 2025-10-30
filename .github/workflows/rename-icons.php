<?php
// phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
$path = realpath('./');

$di = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($di as $name => $fio) {
    // phpcs:ignore Generic.Files.LineLength.TooLong
    $newname = $fio->getPath() . DIRECTORY_SEPARATOR . strtolower(str_replace(" ", "", str_replace("&", "-", $fio->getFilename())));
    // phpcs:ignore Magento2.Security.LanguageConstruct.DirectOutput
    echo $newname, "\r\n";
    // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
    rename($name, $newname);
}
