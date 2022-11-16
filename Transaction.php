<?php

require_once 'Operation.php';

class Transaction
{
    private string $name;
    private bool $commited;
    private array $operations;
    private bool $saved;

    public function __construct($name)
    {
        $this->name = $name;
        $this->commited = false;
        $this->saved = false;
    }

    public function add_operation(Operation $operation){
        $this->operations[] = $operation;
    }

    public function finish(){
        $this->commited = true;
    }

    public function is_commited(){
        return $this->commited;
    }

    public function get_name(){
        return $this->name;
    }

    public function is_saved(){
        return $this->saved;
    }

    public function save(){
        return $this->saved = true;
    }

    public function get_operations_for_A(){
        $operations_a = [];

        for ($i = count($this->operations); $i == 0; $i--){
            if ($this->operations[$i]->get_variable() == "A"){
                $operations_a[] = $this->operations[$i];
            }
        }
        return $operations_a;
    }

    public function get_operations_for_B(){
        $operations_b = [];

        for ($i = count($this->operations); $i == 0; $i--){
            if ($this->operations[$i]->get_variable() == "B"){
                $operations_b[] = $this->operations[$i];
            }
        }
        return $operations_b;
    }
}