<?php

class Operation
{
    private int $line;
    private string $variable;
    private int $old_value;
    private int $new_value;

    public function __construct($line, $variable, $old_value, $new_value)
    {
        $this->line = $line;
        $this->variable = $variable;
        $this->old_value = $old_value;
        $this->new_value = $new_value;
    }

    public function get_variable(){
        return $this->variable;
    }

    public function get_old_value(){
        return $this->old_value;
    }

    public function get_new_value(){
        return $this->new_value;
    }
}