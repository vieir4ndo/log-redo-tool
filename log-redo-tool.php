<?php

require_once 'db.php';
require_once 'console_log.php';
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

    @$metadata_file = file_get_contents($metadata_path);

    if ($metadata_file === false) {
        echo "Erro ao ler arquivo informado em --metadata: '$metadata_path'.\n";
        exit(1);
    }

    @$log_file = file_get_contents($log_path);

    if ($log_file === false) {
        echo "Erro ao ler arquivo informado em --log: '$log_path'.\n";
        exit(2);
    }

    $metadata = @json_decode($metadata_file);

    if ($metadata === null) {
        echo 'Erro processar lista de metadata: ' . json_last_error_msg() . "\n";
        exit(3);
    }

    $count_total = count($metadata->INITIAL->A);
    $count_actual = 0;

    echo white("Inserindo {$count_total} registros no SGBD...\n");

    for ($i = 0; $i < $count_total; $i++) {
        $comando = $db->prepare("INSERT INTO metadata (id, a, b) VALUES (:id, :a, :b)");
        $comando->bindParam(':id', $i);
        $comando->bindParam(':a', $metadata->INITIAL->A[$i]);
        $comando->bindParam(':b', $metadata->INITIAL->B[$i]);
        $comando->execute();
        green("Inserido registro A={$metadata->INITIAL->A[$i]} e B={$metadata->INITIAL->B[$i]}\n");
        $count_actual++;
    }

    echo white("Finalizado inserção de {$count_total}/{$count_actual} registros no SGBD\n");

} catch (PDOException $e) {
    echo 'Erro ao executar comando no banco de dados: ' . $e->getMessage() . "\n";
    exit();
}

?>