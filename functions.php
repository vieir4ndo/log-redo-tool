<?php

require_once __DIR__ . '/vendor/autoload.php';

use Colors\Color;

function yellow($str, $eol = false) {
    $c = new Color();
    return $c($str)->yellow . ($eol ? PHP_EOL : '');
}

function magenta($str, $eol = false) {
    $c = new Color();
    return $c($str)->magenta . ($eol ? PHP_EOL : '');
}

function green($str, $eol = false) {
    $c = new Color();
    return $c($str)->green . ($eol ? PHP_EOL : '');
}

function white($str, $eol = false) {
    $c = new Color();
    return $c($str)->white . ($eol ? PHP_EOL : '');
}

function get_and_validate_metadata_file($path){
    @$file = file_get_contents($path);

    if ($file === false) {
        echo "Erro ao ler arquivo informado em --metadata: '$path'.\n";
        exit(1);
    }

    $metadata = @json_decode($file);

    if ($metadata === null) {
        echo 'Erro processar lista de metadata: ' . json_last_error_msg() . "\n";
        exit(3);
    }

    return $metadata;
}

function get_and_validate_log_file($path){
    @$file = file_get_contents($path);

    if ($file === false) {
        echo "Erro ao ler arquivo informado em --log: '$path'.\n";
        exit(1);
    }

    return array_filter(explode("\n", $file));
}

function insert_metadata_into_database($db, $metadata){

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

}

function read_transactions_in_order($log_commands){
    $transactions = [];

    foreach ($log_commands as $log) {
        if (StringHelper::contains($log, "start")){
            $transaction_name = trim(StringHelper::regex("/<start (.*?)>/i", $log));
            $transactions[] = new Transaction($transaction_name);
        }
        else if (StringHelper::contains($log, "commit")){
            $transaction_name = trim(StringHelper::regex("/<commit (.*?)>/i", $log));
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
        else if (empty($log) || StringHelper::contains($log, "crash")) {
            continue;
        }
        else {
            $log = str_replace(["<", ">"], "", $log);
            $params = explode(",", $log);
            $transaction_name = trim($params[0]);
            $transaction_index = get_transaction_by_name($transactions, $transaction_name);

            $id = intval(trim($params[1]));
            $variable = trim($params[2]);
            $old_value = intval(trim($params[3]));
            $new_value = intval(trim($params[4]));

            $transactions[$transaction_index]->add_operation(new Operation($id, $variable, $old_value, $new_value));
        }
    }

    return $transactions;
}

function read_transactions_in_reverse($log_commands)
{
    $transactions = [];

    for ($i = count($log_commands); $i==0 ; $i--) {

        $log = $log_commands[$i];

        if (StringHelper::contains($log, "start")){
            continue;
        }
        else if (StringHelper::contains($log, "commit")){
            $transaction_name = trim(StringHelper::regex("/<commit (.*?)>/i", $log));
            $transaction_index = get_transaction_by_name($transactions, $transaction_name);

            if ($transaction_index == null){
                $transactions[] = new Transaction($transaction_name);
                $transaction_index = get_transaction_by_name($transactions, $transaction_name);
            }

            $transactions[$transaction_index]->finish();
        }
        else if (StringHelper::contains($log, "CKPT")){

            $transactions_opened = StringHelper::regex("/<CKPT (.*?)>/i", $log);

            $transactions_opened = explode($transactions_opened, "\n");
            $transactions_opened = array_filter($transactions_opened);

            if (empty($transactions_opened)){
                return [];
            }

            for ($i =0; $i < count($transactions_opened); $i++){
                $transactions_opened[$i] = trim($transactions_opened[$i]);
            }

            foreach ($transactions as $transaction){
                if (!in_array($transactions_opened, $transaction->get_name()) && !$transaction->is_saved()){
                    $transaction->save();
                }
            }
        }
        else if (empty($log) || StringHelper::contains($log, "crash")) {
            continue;
        }
        else {
            $log = str_replace(["<", ">"], "", $log);
            $params = explode(",", $log);
            $transaction_name = trim($params[0]);
            $transaction_index = get_transaction_by_name($transactions, $transaction_name);

            if ($transaction_index == null){
                $transactions[] = new Transaction($transaction_name);
                $transaction_index = get_transaction_by_name($transactions, $transaction_name);
            }

            $id = intval(trim($params[1]));
            $variable = trim($params[2]);
            $old_value = intval(trim($params[3]));
            $new_value = intval(trim($params[4]));

            $transactions[$transaction_index]->add_operation(new Operation($id, $variable, $old_value, $new_value));
        }
    }

    return $transactions;
}

function execute_redo($db, $transactions){
    foreach ($transactions as $transaction){
        if ($transaction->is_commited() and !$transaction->is_saved()){

            $operations = $transaction->get_operations();

            if (count($operations)>0){
                foreach ($operations as $operation) {
                    $var = $operation->get_variable();
                    $id = $operation->get_id();
                    $old_value = $operation->get_old_value();
                    $new_value = $operation->get_new_value();

                    $comando = $db->prepare("SELECT {$var} FROM metadata WHERE id=(:id)");
                    $comando->bindParam(':id', $id);
                    $comando->execute();

                    $resultado = $comando->fetchAll(PDO::FETCH_ASSOC);

                    if ($resultado[0][$var] == $old_value) {
                        $comando = $db->prepare("UPDATE metadata SET {$var}=(:new_B) WHERE id=(:id)");
                        $comando->bindParam(':new_B', $new_value);
                        $comando->bindParam(':id', $id);
                        $comando->execute();
                        echo green("Dado {$operation->get_variable()} atualizado pela transação {$transaction->get_name()} de {$operation->get_old_value()} para {$operation->get_new_value()}\n");
                    }
                }
            }

            echo green("Transação {$transaction->get_name()} realizou  REDO.\n");
        }
        elseif(!$transaction->is_commited()){
            echo yellow("Transação {$transaction->get_name()} não realizou  REDO.\n");
        }
    }
}