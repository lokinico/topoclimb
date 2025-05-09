<?php
// src/Models/Model.php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Database;

abstract class Model
{
    /**
     * Nom de la table
     *
     * @var string
     */
    protected string $table;
    
    /**
     * Clé primaire
     *
     * @var string
     */
    protected string $primaryKey = 'id';
    
    /**
     * Champs autorisés
     *
     * @var array
     */
    protected array $fillable = [];
    
    /**
     * Instance de la base de données
     *
     * @var Database
     */
    protected Database $db;
    
    /**
     * Attributs du modèle
     *
     * @var array
     */
    protected array $attributes = [];

    /**
     * Constructeur
     *
     * @param Database $db
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
    }
    
    /**
     * Définit un attribut
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }
    
    /**
     * Récupère un attribut
     *
     * @param string $key
     * @return mixed
     */
    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }
    
    /**
     * Vérifie si un attribut existe
     *
     * @param string $key
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }
    
    /**
     * Remplit le modèle avec des données
     *
     * @param array $data
     * @return $this
     */
    public function fill(array $data): self
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $this->fillable)) {
                $this->attributes[$key] = $value;
            }
        }
        
        return $this;
    }
    
    /**
     * Récupère tous les attributs
     *
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
    
    /**
     * Enregistre le modèle
     *
     * @return bool
     */
    public function save(): bool
    {
        if (isset($this->attributes[$this->primaryKey])) {
            // Update
            $id = $this->attributes[$this->primaryKey];
            $data = array_filter($this->attributes, function ($key) {
                return $key !== $this->primaryKey && in_array($key, $this->fillable);
            }, ARRAY_FILTER_USE_KEY);
            
            return $this->db->update($this->table, $data, "{$this->primaryKey} = ?", [$id]) > 0;
        } else {
            // Insert
            $data = array_filter($this->attributes, function ($key) {
                return in_array($key, $this->fillable);
            }, ARRAY_FILTER_USE_KEY);
            
            $id = $this->db->insert($this->table, $data);
            if ($id) {
                $this->attributes[$this->primaryKey] = $id;
                return true;
            }
            
            return false;
        }
    }
    
    /**
     * Trouve un modèle par son ID
     *
     * @param int $id
     * @return static|null
     */
    public function find(int $id): ?static
    {
        $data = $this->db->fetchOne("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?", [$id]);
        
        if (!$data) {
            return null;
        }
        
        $model = new static($this->db);
        $model->attributes = $data;
        
        return $model;
    }
    
    /**
     * Récupère tous les modèles
     *
     * @return array
     */
    public function all(): array
    {
        $data = $this->db->fetchAll("SELECT * FROM {$this->table}");
        
        $models = [];
        foreach ($data as $row) {
            $model = new static($this->db);
            $model->attributes = $row;
            $models[] = $model;
        }
        
        return $models;
    }
    
    /**
     * Supprime le modèle
     *
     * @return bool
     */
    public function delete(): bool
    {
        if (!isset($this->attributes[$this->primaryKey])) {
            return false;
        }
        
        $id = $this->attributes[$this->primaryKey];
        return $this->db->delete($this->table, "{$this->primaryKey} = ?", [$id]) > 0;
    }
}