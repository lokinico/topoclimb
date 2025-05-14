<?php
// src/Services/ValidationService.php

namespace TopoclimbCH\Services;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ValidationService
{
    private ValidatorInterface $validator;
    
    public function __construct()
    {
        $this->validator = Validation::createValidator();
    }
    
    /**
     * Valide les données selon les contraintes définies
     * 
     * @param array $data Les données à valider
     * @param array $rules Les règles de validation
     * @return array Tableau d'erreurs (vide si pas d'erreurs)
     */
    public function validate(array $data, array $rules): array
    {
        // Créer les contraintes à partir des règles
        $constraints = $this->createConstraints($rules);
        
        // Valider les données
        $violations = $this->validator->validate($data, new Assert\Collection($constraints));
        
        // Si pas d'erreurs, retourner un tableau vide
        if (count($violations) === 0) {
            return [];
        }
        
        // Formater les erreurs
        $errors = [];
        foreach ($violations as $violation) {
            $propertyPath = $violation->getPropertyPath();
            $field = str_replace(['[', ']'], '', $propertyPath);
            if (!isset($errors[$field])) {
                $errors[$field] = [];
            }
            $errors[$field][] = $violation->getMessage();
        }
        
        return $errors;
    }
    
    /**
     * Ajoute manuellement une erreur
     * 
     * @param array $errors Le tableau d'erreurs actuel
     * @param string $field Le champ avec erreur
     * @param string $message Le message d'erreur
     * @return array Le tableau d'erreurs mis à jour
     */
    public function addError(array $errors, string $field, string $message): array
    {
        if (!isset($errors[$field])) {
            $errors[$field] = [];
        }
        
        $errors[$field][] = $message;
        return $errors;
    }
    
    /**
     * Convertit les règles simplifiées en contraintes Symfony
     * 
     * @param array $rules Les règles de validation
     * @return array Les contraintes Symfony
     */
    private function createConstraints(array $rules): array
    {
        $constraints = [];
        
        foreach ($rules as $field => $fieldRules) {
            $fieldConstraints = [];
            $rulesArray = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);
            
            foreach ($rulesArray as $rule) {
                $params = [];
                $ruleName = $rule;
                
                // Vérifier si la règle a des paramètres (format: règle:paramètre)
                if (is_string($rule) && strpos($rule, ':') !== false) {
                    [$ruleName, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }
                
                switch ($ruleName) {
                    case 'required':
                        $fieldConstraints[] = new Assert\NotBlank(['message' => 'Le champ est obligatoire']);
                        break;
                        
                    case 'email':
                        $fieldConstraints[] = new Assert\Email(['message' => 'L\'adresse email n\'est pas valide']);
                        break;
                        
                    case 'min':
                        $min = (int)($params[0] ?? 0);
                        $fieldConstraints[] = new Assert\Length([
                            'min' => $min,
                            'minMessage' => 'Le champ doit contenir au moins {{ limit }} caractères'
                        ]);
                        break;
                        
                    case 'max':
                        $max = (int)($params[0] ?? 255);
                        $fieldConstraints[] = new Assert\Length([
                            'max' => $max,
                            'maxMessage' => 'Le champ ne doit pas dépasser {{ limit }} caractères'
                        ]);
                        break;
                        
                    case 'numeric':
                        $fieldConstraints[] = new Assert\Type([
                            'type' => 'numeric',
                            'message' => 'Le champ doit être un nombre'
                        ]);
                        break;
                        
                    case 'in':
                        $values = $params;
                        $fieldConstraints[] = new Assert\Choice([
                            'choices' => $values,
                            'message' => 'La valeur doit être l\'une des suivantes : {{ choices }}'
                        ]);
                        break;
                        
                    case 'date':
                        $fieldConstraints[] = new Assert\Date([
                            'message' => 'Le format de date n\'est pas valide'
                        ]);
                        break;
                }
            }
            
            $constraints[$field] = $fieldConstraints;
        }
        
        return $constraints;
    }
    
    /**
     * Valide la correspondance de deux champs
     * 
     * @param array $data Les données
     * @param string $field1 Premier champ
     * @param string $field2 Second champ
     * @return bool True si identiques
     */
    public function validateEquals(array $data, string $field1, string $field2): bool
    {
        return isset($data[$field1]) && isset($data[$field2]) && $data[$field1] === $data[$field2];
    }
}