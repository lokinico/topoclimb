<?php
// src/Core/Validation/Validator.php

namespace TopoclimbCH\Core\Validation;

use ReflectionClass;
use ReflectionMethod;

class Validator
{
    /**
     * Messages d'erreur
     */
    protected array $errors = [];
    
    /**
     * Valide les données selon les règles spécifiées
     */
    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            $fieldRulesArray = explode('|', $fieldRules);
            
            foreach ($fieldRulesArray as $rule) {
                $parameters = [];
                
                // Vérifier si la règle a des paramètres
                if (strpos($rule, ':') !== false) {
                    [$ruleName, $ruleParams] = explode(':', $rule, 2);
                    $parameters = explode(',', $ruleParams);
                } else {
                    $ruleName = $rule;
                }
                
                // Déterminer la méthode de validation à appeler
                $methodName = 'validate' . ucfirst($ruleName);
                
                if (!method_exists($this, $methodName)) {
                    throw new \InvalidArgumentException("Règle de validation inconnue: {$ruleName}");
                }
                
                $value = $data[$field] ?? null;
                
                // Appliquer la règle de validation
                if (!$this->$methodName($field, $value, $parameters, $data)) {
                    break;
                }
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Retourne les erreurs de validation
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Ajoute une erreur de validation
     */
    protected function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
    
    /**
     * Règle: required
     */
    protected function validateRequired(string $field, $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            $this->addError($field, "Le champ {$field} est obligatoire.");
            return false;
        }
        
        return true;
    }
    
    /**
     * Règle: min (longueur minimale pour les chaînes, valeur minimale pour les nombres)
     */
    protected function validateMin(string $field, $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Ignorer si la valeur est vide (sauf si required)
        }
        
        $min = (int) $parameters[0];
        
        if (is_string($value) && mb_strlen($value) < $min) {
            $this->addError($field, "Le champ {$field} doit comporter au moins {$min} caractères.");
            return false;
        } else if (is_numeric($value) && $value < $min) {
            $this->addError($field, "Le champ {$field} doit être au moins {$min}.");
            return false;
        }
        
        return true;
    }
    
    /**
     * Règle: max (longueur maximale pour les chaînes, valeur maximale pour les nombres)
     */
    protected function validateMax(string $field, $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Ignorer si la valeur est vide
        }
        
        $max = (int) $parameters[0];
        
        // TODO: Bug fix - Ne traiter que la longueur des chaînes, pas les valeurs numériques 
        // pour éviter que '123456' soit traité comme le nombre 123456
        if (is_string($value) && mb_strlen($value) > $max) {
            $this->addError($field, "Le champ {$field} ne doit pas dépasser {$max} caractères.");
            return false;
        }
        // Supprimé: } else if (is_numeric($value) && $value > $max) {
        // Ce traitement causait un bug où les passwords numériques étaient 
        // comparés comme nombres au lieu de longueur de chaîne
        
        return true;
    }
    
    /**
     * Règle: email
     */
    protected function validateEmail(string $field, $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Ignorer si la valeur est vide
        }
        
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, "Le champ {$field} doit être une adresse email valide.");
            return false;
        }
        
        return true;
    }
    
    /**
     * Règle: numeric
     */
    protected function validateNumeric(string $field, $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Ignorer si la valeur est vide
        }
        
        if (!is_numeric($value)) {
            $this->addError($field, "Le champ {$field} doit être un nombre.");
            return false;
        }
        
        return true;
    }
    
    /**
     * Règle: alpha
     */
    protected function validateAlpha(string $field, $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Ignorer si la valeur est vide
        }
        
        if (!preg_match('/^[a-zA-Z]+$/', $value)) {
            $this->addError($field, "Le champ {$field} ne doit contenir que des lettres.");
            return false;
        }
        
        return true;
    }
    
    /**
     * Règle: alpha_num
     */
    protected function validateAlphaNum(string $field, $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Ignorer si la valeur est vide
        }
        
        if (!preg_match('/^[a-zA-Z0-9]+$/', $value)) {
            $this->addError($field, "Le champ {$field} ne doit contenir que des lettres et des chiffres.");
            return false;
        }
        
        return true;
    }
    
    /**
     * Règle: date
     */
    protected function validateDate(string $field, $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Ignorer si la valeur est vide
        }
        
        if (!strtotime($value)) {
            $this->addError($field, "Le champ {$field} doit être une date valide.");
            return false;
        }
        
        return true;
    }
    
    /**
     * Règle: in (valeur dans une liste)
     */
    protected function validateIn(string $field, $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Ignorer si la valeur est vide
        }
        
        if (!in_array($value, $parameters)) {
            $allowed = implode(', ', $parameters);
            $this->addError($field, "Le champ {$field} doit être l'une des valeurs suivantes: {$allowed}.");
            return false;
        }
        
        return true;
    }
    
    /**
     * Règle: unique (valeur unique dans la base de données)
     */
    protected function validateUnique(string $field, $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Ignorer si la valeur est vide
        }
        
        $table = $parameters[0];
        $column = $parameters[1] ?? $field;
        $exceptId = $parameters[2] ?? null;
        
        $pdo = \TopoclimbCH\Core\Database::getInstance()->getConnection();
        
        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = :value";
        $params = [':value' => $value];
        
        if ($exceptId !== null) {
            $sql .= " AND id != :id";
            $params[':id'] = $exceptId;
        }
        
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $count = (int) $statement->fetchColumn();
        
        if ($count > 0) {
            $this->addError($field, "La valeur du champ {$field} est déjà utilisée.");
            return false;
        }
        
        return true;
    }
}