<?php
$path = realpath('./');

$di = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($di as $name => $fio) {
    $newname = $fio->getPath() . DIRECTORY_SEPARATOR . strtolower(str_replace(" ","",str_replace("&","-",$fio->getFilename())));
    echo $newname, "\r\n";
    rename($name, $newname);
}
