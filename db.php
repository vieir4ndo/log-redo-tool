<?php

$arquivo = 'database.sqlite';

$db = null;

try {
    $deve_inicializar_banco = false;

    if (!file_exists($arquivo)) {
        $deve_inicializar_banco = true;
    }

    $db = new PDO("sqlite:$arquivo");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($deve_inicializar_banco) {
        $db->exec("CREATE TABLE metadata (
            id INTEGER PRIMARY KEY,
            a INTEGER,
            b INTEGER
        )");
    }
} catch (PDOException $e) {
    echo 'Erro com o banco de dados: ' . $e->getMessage();
    exit();
}

?>