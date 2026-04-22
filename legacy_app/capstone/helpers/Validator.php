<?php

class Validator {
    protected $errors = [];

    public function required($field, $value) {
        if (trim($value) === '') {
            $this->errors[$field][] = 'The ' . $field . ' field is required.';
        }
        return $this;
    }

    public function date($field, $value) {
        if (!strtotime($value)) {
            $this->errors[$field][] = 'The ' . $field . ' must be a valid date.';
        }
        return $this;
    }

    public function min($field, $value, $limit) {
        if ($value < $limit) {
            $this->errors[$field][] = "The $field must be at least $limit.";
        }
        return $this;
    }

    public function getErrors() {
        return $this->errors;
    }

    public function fails() {
        return !empty($this->errors);
    }
}
