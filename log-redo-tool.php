<?php

require_once 'db.php';
require_once 'StringHelper.php';
require_once 'Transaction.php';
require_once 'Operation.php';
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

    $comando = $db->prepare("DELETE FROM metadata");
    $comando->execute();

    echo white("Inserindo {$count_total} registros no SGBD... \n");

    for ($i = 0; $i < $count_total; $i++) {
        $id = $i + 1;
        try {
            $comando = $db->prepare("INSERT INTO metadata (id, A, B) VALUES (:id, :A, :B)");
            $comando->bindParam(':id', $id);
            $comando->bindParam(':A', $metadata->INITIAL->A[$i]);
            $comando->bindParam(':B', $metadata->INITIAL->B[$i]);
            $comando->execute();
            echo green("Inserido registro {$id} A={$metadata->INITIAL->A[$i]} e B={$metadata->INITIAL->B[$i]}\n");
            $count_actual++;
        }
        catch (Exception $e){
            echo yellow("Houve um problem ao inserir registro {$id} A={$metadata->INITIAL->A[$i]} e B={$metadata->INITIAL->B[$i]}: " . $e->getMessage() . "\n");
        }
    }

    echo white("Finalizado inserção de {$count_actual}/{$count_total} registros no SGBD\n");

    $log_commands = explode("\n", $log_file);

    $transactions = [];

    foreach ($log_commands as $log){
        // extract to a clean function
        if (empty($log))
            continue;

        if (StringHelper::contains($log, "start")){
            $transaction_name = StringHelper::regex("/<start (.*?)>/i", $log);
            $transactions[] = new Transaction($transaction_name);
        }
        else if (StringHelper::contains($log, "commit")){
            $transaction_name = StringHelper::regex("/<commit (.*?)>/i", $log);
            $transaction_index = get_transaction_by_name($transactions, $transaction_name);
            $transactions[$transaction_index]->finish();
        }
        else if (StringHelper::contains($log, "CKPT")){
            foreach ($transactions as $transaction){
                if ($transaction->is_commited()){
                    $transaction->save();
                }
            }
        }
        else {
            $transaction_name = StringHelper::regex("/<(.*?),/i", $log);
            $transaction_index = get_transaction_by_name($transactions, $transaction_name);

            $line = intval(StringHelper::regex("/<\S+,(.*?), /i", $log));
            $variable = StringHelper::regex("/<\S+,\S+, (.*?),/i", $log);
            $old_value = intval(StringHelper::regex("/<\S+,\S+, \S+,(.*?),/i", $log));
            $new_value = intval(StringHelper::regex("/<\S+,\S+, \S+,\S+,(.*?)>/i", $log));

            $transactions[$transaction_index]->add_operation(new Operation($line, $variable, $old_value, $new_value));
        }
    }

    foreach ($transactions as $transaction){
        if ($transaction->is_commited() and !$transaction->is_saved()){

            $operations_A = $transaction->get_operations_for_A();

            if (!empty($operations_A)){
                foreach ($operations_A as $operation_A){
                    $comando = $db->prepare("SELECT * FROM metadata WHERE A=(:A)");
                    $comando->bindParam(':A', $operation_A->get_old_value());
                    $comando->execute();

                    $resultado = $comando->fetchAll(PDO::FETCH_ASSOC);

                    if (count($resultado) > 0) {
                        $comando = $db->prepare("UPDATE metadata SET A=:new_A WHERE A=(:A)");
                        $comando->bindParam(':new_A', $operation_A->get_new_value());
                        $comando->bindParam(':A', $operation_A->get_old_value());
                        $comando->execute();
                        echo green("Dado A atualizado pela transação {$transaction->get_name()} de {$operation_A->get_old_value()} para {$operation_A->get_new_value()}\n");
                    }
                }
            }

            $operations_B = $transaction->get_operations_for_B();

            if (!empty($operations_B)){
                foreach ($operations_B as $operation_B) {
                    $comando = $db->prepare("SELECT * FROM metadata WHERE B=(:B)");
                    $comando->bindParam(':B', $operation_B->get_old_value());
                    $comando->execute();

                    $resultado = $comando->fetchAll(PDO::FETCH_ASSOC);

                    if (count($resultado) > 0) {
                        $comando = $db->prepare("UPDATE metadata SET B=:new_B WHERE B=(:B)");
                        $comando->bindParam(':new_B', $operation_B->get_new_value());
                        $comando->bindParam(':B', $operation_B->get_old_value());
                        $comando->execute();
                        echo green("Dado B atualizado pela transação {$transaction->get_name()} de {$operation_B->get_old_value()} para {$operation_B->get_new_value()}\n");
                    }
                }
            }

            echo green("Transação {$transaction->get_name()} realizou  REDO.\n");
        }
        elseif(!$transaction->is_commited()){
            echo yellow("Transação {$transaction->get_name()} não realizou  REDO.\n");
        }
    }

} catch (PDOException $e) {
    echo 'Erro ao executar comando no banco de dados: ' . $e->getMessage() . "\n";
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
