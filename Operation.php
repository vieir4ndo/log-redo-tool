<?php

class Operation
{
    private int $id;
    private string $variable;
    private int $old_value;
    private int $new_value;

    public function __construct($id, $variable, $old_value, $new_value)
    {
        $this->id = $id;
        $this->variable = $variable;
        $this->old_value = $old_value;
        $this->new_value = $new_value;
    }

    public function get_variable()
    {
        return $this->variable;
    }

    public function get_old_value()
    {
        return $this->old_value;
    }

    public function get_new_value()
    {
        return $this->new_value;
    }

    public function get_id()
    {
        return $this->id;
    }
}