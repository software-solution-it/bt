<?php

namespace App\Core;

abstract class Service
{
    protected function validate(array $data, array $rules)
    {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            if (strpos($rule, 'required') !== false && empty($data[$field])) {
                $errors[$field] = "The $field field is required";
                continue;
            }
            
            if (!empty($data[$field])) {
                if (strpos($rule, 'email') !== false && !filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = "The $field must be a valid email";
                }
                
                if (strpos($rule, 'min:') !== false) {
                    preg_match('/min:(\d+)/', $rule, $matches);
                    $min = $matches[1];
                    if (strlen($data[$field]) < $min) {
                        $errors[$field] = "The $field must be at least $min characters";
                    }
                }
            }
        }
        
        return $errors;
    }

    protected function throwIfErrors(array $errors)
    {
        if (!empty($errors)) {
            throw new \Exception(json_encode($errors));
        }
    }
} 