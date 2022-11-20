<?php

require_once 'db.php';
require_once 'StringHelper.php';
require_once 'Transaction.php';
require_once 'Operation.php';
require_once 'functions.php';
require_once __DIR__ . '/vendor/autoload.php';

use Garden\Cli\Cli;

try {

    $cli = new Cli();

    $cli->description('Implementa o mecanismo de log Redo com checkpoint usando o SGBD')
        ->opt('metadata:l', 'Caminho para um arquivo JSON com os dados para serem inseridos no SGBD.')
        ->opt('log:l', 'Caminho para um arquivo com os logs.');

    $args = $cli->parse($argv, true);

    $ds = DIRECTORY_SEPARATOR;
    $metadata_path = $args->getOpt('metadata', __DIR__ . $ds . 'metadata.json');
    $log_path = $args->getOpt('log', __DIR__ . $ds . 'log');

    $metadata = get_and_validate_metadata_file($metadata_path);

    $log_commands = get_and_validate_log_file($log_path);

    insert_metadata_into_database($db, $metadata);

    $transactions = read_transactions_in_reverse($log_commands);

    execute_redo($db, $transactions);

} catch (PDOException $e) {
    magenta('Erro ao executar comando no banco de dados: ' . $e->getMessage());
    exit();
}

function get_transaction_by_name($array, $name){
    for ( $j =0; $j < count($array); $j++){
        if ($array[$j]->get_name() == $name){
            return $j;
        }
    }

    return null;
}
