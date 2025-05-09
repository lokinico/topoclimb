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
     * Règles de validation
     */
    protected array $rules = [
        'nom' => 'required|max:255',
        'prenom' => 'required|max:255',
        'ville' => 'required|max:255',
        'mail' => 'required|email|max:255|unique:users,mail',
        'username' => 'required|alpha_num|max:100|unique:users,username',
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
        return password_hash($value, PASSWORD_DEFAULT);
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
}