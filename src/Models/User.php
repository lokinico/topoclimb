<?php
// src/Models/User.php

namespace TopoclimbCH\Models;

use TopoclimbCH\Core\Model;

class User extends Model
{
    /**
     * Nom de la table en base de données
     */
    protected static string $table = 'users';
    
    /**
     * Désactiver les timestamps automatiques car la table users utilise "date_registered"
     * au lieu de "created_at" et "updated_at"
     */
    protected bool $timestamps = false;
    
    /**
     * Liste des attributs remplissables en masse
     */
    protected array $fillable = [
        'nom', 'prenom', 'ville', 'mail', 'username'
    ];
    
    /**
     * Liste des attributs protégés contre le remplissage en masse
     */
    protected array $guarded = ['id', 'password', 'autorisation', 'reset_token', 'date_registered'];
    
    /**
     * Attributs qui ne doivent pas être sérialisés
     */
    protected array $hidden = ['password', 'reset_token'];
    
    /**
     * Règles de validation simplifiées pour éviter les problèmes avec
     * les règles non implémentées (alpha_num, unique)
     */
    protected array $rules = [
        'nom' => 'required|max:255',
        'prenom' => 'required|max:255',
        'mail' => 'required|email|max:255',
        'username' => 'required|max:100',
        'password' => 'required|min:8'
    ];
    
    /**
     * Relation avec les ascensions
     */
    public function ascents(): array
    {
        return $this->hasMany(UserAscent::class);
    }
    
    /**
     * Relation avec les routes favorites
     */
    public function favoriteRoutes(): array
    {
        return $this->belongsToMany(
            Route::class, 
            'user_routes', 
            'user_id', 
            'route_id'
        );
    }
    
    /**
     * Relation avec les événements organisés
     */
    public function organizedEvents(): array
    {
        return $this->hasMany(Event::class, 'created_by');
    }
    
    /**
     * Relation avec les événements auxquels l'utilisateur participe
     */
    public function events(): array
    {
        return $this->belongsToMany(
            Event::class, 
            'climbing_event_participants', 
            'user_id', 
            'event_id'
        );
    }
    
    /**
     * Accesseur pour le nom complet
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->attributes['prenom']} {$this->attributes['nom']}";
    }
    
    /**
     * Mutateur pour le mot de passe (hachage automatique)
     */
    public function setPasswordAttribute($value): string
    {
        $this->attributes['password'] = password_hash($value, PASSWORD_BCRYPT, ['cost' => 12]);
        return $this->attributes['password'];
    }
    
    /**
     * Vérifie si le mot de passe est correct
     */
    public function checkPassword(string $password): bool
    {
        return password_verify($password, $this->attributes['password'] ?? '');
    }
    
    /**
     * Méthode pour générer un token de réinitialisation de mot de passe
     */
    public function generateResetToken(): string
    {
        $token = bin2hex(random_bytes(10));
        $this->attributes['reset_token'] = $token;
        $this->save();
        
        return $token;
    }
    
    /**
     * Vérifie si l'utilisateur est administrateur
     */
    public function isAdmin(): bool
    {
        return $this->attributes['autorisation'] === '1';
    }
    
    /**
     * Vérifie si l'utilisateur est modérateur
     */
    public function isModerator(): bool
    {
        return in_array($this->attributes['autorisation'], ['1', '2']);
    }
    
    /**
     * Récupère un utilisateur par son email
     */
    public static function findByEmail(string $email): ?User
    {
        return static::findWhere(['mail' => $email]);
    }
    
    /**
     * Récupère un utilisateur par son nom d'utilisateur
     */
    public static function findByUsername(string $username): ?User
    {
        return static::findWhere(['username' => $username]);
    }
    
    /**
     * Authentifie un utilisateur
     */
    public static function authenticate(string $login, string $password): ?User
    {
        // Recherche par email
        $user = static::findByEmail($login);
        
        // Si non trouvé, recherche par nom d'utilisateur
        if ($user === null) {
            $user = static::findByUsername($login);
        }
        
        // Vérifier que l'utilisateur existe et que le mot de passe est correct
        if ($user !== null && $user->checkPassword($password)) {
            return $user;
        }
        
        return null;
    }
    
    /**
     * Événement après la création
     */
    protected function onCreated(): void
    {
        // Logique après création d'un utilisateur (envoyer un email de bienvenue, etc.)
    }
    
    /**
     * Événement avant la suppression
     */
    protected function onDeleting(): bool
    {
        // Empêcher la suppression des administrateurs
        if ($this->isAdmin()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Méthode personnalisée pour enregistrer l'utilisateur avec date_registered
     * au lieu de created_at/updated_at
     */
    public function save(): bool
    {
        // Si l'utilisateur est nouveau (pas encore en base)
        if (!isset($this->attributes[static::$primaryKey]) || empty($this->attributes[static::$primaryKey])) {
            // Ajouter la date d'inscription si elle n'est pas définie
            if (!isset($this->attributes['date_registered'])) {
                $this->attributes['date_registered'] = date('Y-m-d H:i:s');
            }
        }
        
        return parent::save();
    }
}