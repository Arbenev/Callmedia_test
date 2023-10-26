<?php
require_once './vendor/autoload.php';

const URLS = [
    'https://www.google.ru/',
    'https://maps.google.com/',
    'https://play.google.com/',
    'https://mail.google.com/mail/',
    'https://meet.google.com/',
    'https://chat.google.com/',
    'https://contacts.google.com/',
    'https://drive.google.com/',
    'https://calendar.google.com/calendar',
    'https://translate.google.com/',
    'stop',
];
const INI_FILE = 'config/config.ini';

echo "Init...\n";

$config = parse_ini_file(INI_FILE, true);

require_once 'src/CallMediaTest.php';
$testClass = new CallMediaTest($config);

sleep(10);
$testClass->initRabbit();

foreach (URLS as $url) {
    sleep(random_int(5, 30));
    $testClass->pushToRabbit($url);
}
$testClass->initMySql();
//$testClass->initClickHouse();

echo "Run!!!\n";
$testClass->listener();
echo "That\'s all!!!\n";
