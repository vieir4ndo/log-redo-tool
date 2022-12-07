<?php

require_once __DIR__ . '/vendor/autoload.php';

use Colors\Color;

function yellow($str, $eol = false)
{
    $c = new Color();
    echo $c($str)->yellow . ($eol ? PHP_EOL : '') . "\n";
}

function magenta($str, $eol = false)
{
    $c = new Color();
    echo $c($str)->magenta . ($eol ? PHP_EOL : '') . "\n";
}

function green($str, $eol = false)
{
    $c = new Color();
    echo $c($str)->green . ($eol ? PHP_EOL : '') . "\n";
}

function white($str, $eol = false)
{
    $c = new Color();
    echo $c($str)->white . ($eol ? PHP_EOL : '') . "\n";
}

function get_and_validate_metadata_file($path)
{
    @$file = file_get_contents($path);

    if ($file === false) {
        magenta("Erro ao ler arquivo informado em --metadata: '$path'");
        exit(1);
    }

    $metadata = @json_decode($file);

    if ($metadata === null) {
        magenta('Erro processar lista de metadata: ' . json_last_error_msg());
        exit(3);
    }

    return $metadata;
}

function get_and_validate_log_file($path)
{
    @$file = file_get_contents($path);

    if ($file === false) {
        magenta("Erro ao ler arquivo informado em --log: '$path'.");
        exit(1);
    }

    return array_filter(explode("\n", $file));
}

function insert_metadata_into_database($db, $metadata)
{

    $count_total = count($metadata->INITIAL->A);
    $count_actual = 0;

    $comando = $db->prepare("DELETE FROM metadata");
    $comando->execute();

    white("Inserindo {$count_total} registros no SGBD... ");

    for ($i = 0; $i < $count_total; $i++) {
        $id = $i + 1;
        try {
            $comando = $db->prepare("INSERT INTO metadata (id, A, B) VALUES (:id, :A, :B)");
            $comando->bindParam(':id', $id);
            $comando->bindParam(':A', $metadata->INITIAL->A[$i]);
            $comando->bindParam(':B', $metadata->INITIAL->B[$i]);
            $comando->execute();
            green("Inserido registro {$id} A={$metadata->INITIAL->A[$i]} e B={$metadata->INITIAL->B[$i]}");
            $count_actual++;
        } catch (Exception $e) {
            yellow("Houve um problem ao inserir registro {$id} A={$metadata->INITIAL->A[$i]} e B={$metadata->INITIAL->B[$i]}: " . $e->getMessage());
        }
    }

    white("Finalizado inserção de {$count_actual}/{$count_total} registros no SGBD");

}

function read_transactions_in_order($log_commands)
{
    $transactions = [];

    foreach ($log_commands as $log) {
        if (StringHelper::contains($log, "start")) {
            $transaction_name = trim(StringHelper::regex("/<start (.*?)>/i", $log));
            $transactions[] = new Transaction($transaction_name);
        } else if (StringHelper::contains($log, "commit")) {
            $transaction_name = trim(StringHelper::regex("/<commit (.*?)>/i", $log));
            $transaction_index = get_transaction_by_name($transactions, $transaction_name);
            $transactions[$transaction_index]->finish();
        } else if (StringHelper::contains($log, "CKPT")) {
            foreach ($transactions as $transaction) {
                if ($transaction->is_commited()) {
                    $transaction->save();
                }
            }
        } else if (empty($log) || StringHelper::contains($log, "crash")) {
            continue;
        } else {
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
    $index = null;
    $checkpoint = null;

    for ($i = count($log_commands) - 1; $i >= 0; $i--) {
        $log = $log_commands[$i];

        if (StringHelper::contains($log, "CKPT")) {
            $index = $i;
            $checkpoint = $log;
            break;
        }
    }

    if ($index == null) {
        return read_transactions_in_order($log_commands);
    }

    $transactions_opened_string = StringHelper::regex("/<CKPT \((.*?)\)>/i", $checkpoint);
    $transactions_opened = explode(",", $transactions_opened_string);

    $transactions_opened = array_filter($transactions_opened);

    for ($i = 0; $i < count($transactions_opened); $i++) {
        $transactions_opened[$i] = trim($transactions_opened[$i]);
    }

    for ($i = $index+1; $i < count($log_commands); $i++){
        if (StringHelper::contains($log_commands[$i], "start")) {
            $transactions_opened[] = trim(StringHelper::regex("/<start (.*?)>/i", $log_commands[$i]));
        }
    }

    if (empty($transactions_opened)) {
        return $transactions;
    }

    foreach ($transactions_opened as $transaction_opened_name) {

        foreach ($log_commands as $log) {
            if (StringHelper::contains($log, $transaction_opened_name)) {
                if (StringHelper::contains($log, "start")) {
                    $transactions[] = new Transaction($transaction_opened_name);
                } else if (StringHelper::contains($log, "commit")) {
                    $transaction_index = get_transaction_by_name($transactions, $transaction_opened_name);
                    $transactions[$transaction_index]->finish();
                } else if (empty($log) || StringHelper::contains($log, "crash") || StringHelper::contains($log, "CKPT")) {
                    continue;
                } else {
                    $log = str_replace(["<", ">"], "", $log);
                    $params = explode(",", $log);
                    $transaction_index = get_transaction_by_name($transactions, $transaction_opened_name);

                    $id = intval(trim($params[1]));
                    $variable = trim($params[2]);
                    $old_value = intval(trim($params[3]));
                    $new_value = intval(trim($params[4]));

                    $transactions[$transaction_index]->add_operation(new Operation($id, $variable, $old_value, $new_value));
                }
            }
        }
    }

    return $transactions;
}

function execute_redo($db, $transactions)
{
    $dados_atualizados = [];

    $count = count($transactions);

    white("Listando as {$count} transações...");

    foreach ($transactions as $transaction) {
        if ($transaction->is_commited() and !$transaction->is_saved()) {

            $operations = $transaction->get_operations();

            if (count($operations) > 0) {
                foreach ($operations as $operation) {
                    $var = $operation->get_variable();
                    $id = $operation->get_id();
                    $old_value = $operation->get_old_value();
                    $new_value = $operation->get_new_value();

                    $comando = $db->prepare("SELECT {$var} FROM metadata WHERE id=(:id)");
                    $comando->bindParam(':id', $id);
                    $comando->execute();

                    $resultado = $comando->fetchAll(PDO::FETCH_ASSOC);

                    // redo should not consider old value
                    //if ($resultado[0][$var] == $old_value) {
                        $comando = $db->prepare("UPDATE metadata SET {$var}=(:new_B) WHERE id=(:id)");
                        $comando->bindParam(':new_B', $new_value);
                        $comando->bindParam(':id', $id);
                        $comando->execute();
                        $dados_atualizados[] = "Dado {$operation->get_variable()} atualizado pela transação {$transaction->get_name()} de {$operation->get_old_value()} para {$operation->get_new_value()} na tupla id={$operation->get_id()}";
                   // }
                }
            }

            green("Transação {$transaction->get_name()} realizou  REDO.");
        } elseif (!$transaction->is_commited()) {
            yellow("Transação {$transaction->get_name()} não realizou  REDO.");
        }
    }

    white("Finalizado a listagem das transações...");

    white("Listando as alterações realizadas no SGBD pelas transações...");

    foreach ($dados_atualizados as $dado){
        green($dado);
    }

    white("Finalizado a listagem das alterações realizadas no SGBD pelas transações...");
}

function get_metadata_from_database($db){
    $comando = $db->prepare("SELECT * FROM metadata");
    $comando->execute();

    $resultado = $comando->fetchAll(PDO::FETCH_ASSOC);

    $ids =[]; $As = []; $Bs=[];

    foreach ($resultado as $tupla){
        $ids[] = $tupla["id"];
        $As[] = $tupla["A"];
        $Bs[] = $tupla["B"];
    }

    green(json_encode([
        "id" => $ids,
        "A" => $As,
        "B" => $Bs
    ]));

}