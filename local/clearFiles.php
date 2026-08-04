<?php
$_SERVER['DOCUMENT_ROOT'] = '/var/www/bitrix/data/www/tempusshop.ru';
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

const PACKAGE_SIZE = 100000;
const SLEEP_BETWEEN_PACKAGES = 1;
$logFile = $_SERVER['DOCUMENT_ROOT'].'/local/cleanup_log.txt';

$startTime = microtime(true);
file_put_contents($logFile, "=== Cleanup started at ".date('Y-m-d H:i:s')." ===\n", FILE_APPEND);

echo "Loading used files from main database (Bitrix API)...\n";
$usedFiles = [];
$res = \Bitrix\Main\FileTable::getList([
    'select' => ['SUBDIR', 'FILE_NAME'],
    'order' => ['ID' => 'ASC']
]);
while ($file = $res->fetch()) {
    $usedFiles[] = '/upload/'.$file['SUBDIR'].'/'.$file['FILE_NAME'];
}

echo "Loading used files from second database (direct connection)...\n";
$secondDB = new mysqli('127.0.0.1', 'tempus', 'v}A20U~QPBTNwr', 'tempus_db');
if ($secondDB->connect_error) {
    die("Connection failed: " . $secondDB->connect_error);
}

$result = $secondDB->query("SELECT SUBDIR, FILE_NAME FROM b_file");
while ($file = $result->fetch_assoc()) {
    $usedFiles[] = '/upload/'.$file['SUBDIR'].'/'.$file['FILE_NAME'];
}
$secondDB->close();

$usedFiles = array_unique($usedFiles);

echo "Loaded ".count($usedFiles)." unique used files from both databases\n";

$protectedDirs = [
    '/upload/info_graph_image/',
    '/upload/resize_cache/',
    '/upload/medialibrary/'
];

$totalProcessed = 0;
$totalDeleted = 0;
$packageNumber = 1;

do {
    echo "\n=== Processing package #{$packageNumber} ===\n";
    $packageProcessed = 0;
    $packageDeleted = 0;

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
        $_SERVER['DOCUMENT_ROOT'].'/upload',
        RecursiveDirectoryIterator::SKIP_DOTS
    ));

    $iterator->rewind();

    if ($totalProcessed > 0) {
        for ($i = 0; $i < $totalProcessed; $i++) {
            $iterator->next();
        }
    }

    while ($iterator->valid() && $packageProcessed < PACKAGE_SIZE) {
        $file = $iterator->current();

        if ($file->isFile()) {
            $filePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $file->getPathname());

            $isProtected = false;
            foreach ($protectedDirs as $dir) {
                if (strpos($filePath, $dir) === 0) {
                    $isProtected = true;
                    break;
                }
            }

            if (!$isProtected && !in_array($filePath, $usedFiles)) {
                unlink($file->getPathname());
                $logMessage = "DELETE [{$totalProcessed}] {$filePath}\n";
                echo $logMessage;
                file_put_contents($logFile, $logMessage, FILE_APPEND);
                $packageDeleted++;
                $totalDeleted++;
            }

            $packageProcessed++;
            $totalProcessed++;

            if ($packageProcessed % 100 === 0) {
                $elapsed = microtime(true) - $startTime;
                $speed = $elapsed > 0 ? round($totalProcessed / $elapsed) : 0;
                $progress = "Package {$packageNumber}: {$packageProcessed}/".PACKAGE_SIZE;
                $progress .= " | Total: {$totalProcessed}";
                $progress .= " | Deleted: {$totalDeleted}";
                $progress .= " | Speed: {$speed} files/sec\n";
                echo $progress;
            }
        }

        $iterator->next();
    }

    $packageTime = microtime(true) - $startTime;
    $packageStats = "\nPackage #{$packageNumber} completed:\n";
    $packageStats .= "- Processed: {$packageProcessed} files\n";
    $packageStats .= "- Deleted: {$packageDeleted} files\n";
    $packageStats .= "- Total processed: {$totalProcessed}\n";
    $packageStats .= "- Total deleted: {$totalDeleted}\n";
    $packageStats .= "- Time elapsed: ".round($packageTime, 2)." sec\n";
    $packageStats .= "- Average speed: ".round($totalProcessed / max(1, $packageTime))." files/sec\n";

    echo $packageStats;
    file_put_contents($logFile, $packageStats, FILE_APPEND);

    $packageNumber++;

    if ($iterator->valid() && SLEEP_BETWEEN_PACKAGES > 0) {
        echo "Sleeping for ".SLEEP_BETWEEN_PACKAGES." seconds...\n";
        sleep(SLEEP_BETWEEN_PACKAGES);
    }

} while ($packageProcessed === PACKAGE_SIZE);

$totalTime = microtime(true) - $startTime;
$finalStats = "\n=== Cleanup completed ===\n";
$finalStats .= "- Total files processed: {$totalProcessed}\n";
$finalStats .= "- Total files deleted: {$totalDeleted}\n";
$finalStats .= "- Total time: ".round($totalTime, 2)." seconds\n";
$finalStats .= "- Average speed: ".round($totalProcessed / max(1, $totalTime))." files/sec\n";
$finalStats .= "Log saved to: {$logFile}\n";

echo $finalStats;
file_put_contents($logFile, $finalStats, FILE_APPEND);
