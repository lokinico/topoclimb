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
     */
    protected bool $timestamps = false;

    /**
     * IMPORTANT: Ajouter autorisation dans fillable pour permettre son assignation
     */
    protected array $fillable = [
        'nom',
        'prenom',
        'ville',
        'email',
        'username',
        'autorisation' // AJOUT: Permet l'assignation de l'autorisation
    ];

    /**
     * Liste des attributs protégés contre le remplissage en masse
     * RETIRER autorisation de guarded
     */
    protected array $guarded = ['id', 'password', 'reset_token', 'date_registered'];

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
        'email' => 'required|email|max:255',
        'username' => 'required|max:100',
        'password' => 'required|min:8'
    ];

    /**
     * NOUVEAU: Constantes pour les niveaux d'autorisation
     */
    const AUTH_ADMIN = '0';
    const AUTH_MODERATOR = '1';
    const AUTH_VIEWER = '2';
    const AUTH_RESTRICTED = '3';
    const AUTH_NEW_MEMBER = '4';
    const AUTH_BANNED = '5';

    /**
     * NOUVEAU: Labels pour les niveaux d'autorisation
     */
    const AUTH_LABELS = [
        '0' => 'Administrateur',
        '1' => 'Modérateur',
        '2' => 'Membre actif',
        '3' => 'Compte d\'essai',
        '4' => 'Nouveau membre',
        '5' => 'Banni'
    ];

    /**
     * OVERRIDE: Constructeur pour s'assurer que autorisation est accessible
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // S'assurer que l'autorisation est définie
        if (!isset($this->attributes['autorisation']) && isset($attributes['autorisation'])) {
            $this->attributes['autorisation'] = $attributes['autorisation'];
        }
    }

    /**
     * NOUVEAU: Accesseur pour l'autorisation
     */
    public function getAutorisationAttribute(): string
    {
        return $this->attributes['autorisation'] ?? self::AUTH_RESTRICTED;
    }

    /**
     * NOUVEAU: Mutateur pour l'autorisation
     */
    public function setAutorisationAttribute($value): void
    {
        // Valider que c'est un niveau valide
        if (in_array($value, ['0', '1', '2', '3', '4', '5'])) {
            $this->attributes['autorisation'] = (string)$value;
        }
    }

    /**
     * NOUVEAU: Obtenir le label du niveau d'autorisation
     */
    public function getAuthLabelAttribute(): string
    {
        $auth = $this->autorisation ?? self::AUTH_RESTRICTED;
        return self::AUTH_LABELS[$auth] ?? 'Inconnu';
    }

    /**
     * Vérifie si l'utilisateur est administrateur
     */
    public function isAdmin(): bool
    {
        return $this->autorisation === self::AUTH_ADMIN;
    }

    /**
     * Vérifie si l'utilisateur est modérateur (inclut admin)
     */
    public function isModerator(): bool
    {
        return in_array($this->autorisation, [self::AUTH_ADMIN, self::AUTH_MODERATOR]);
    }

    /**
     * NOUVEAU: Vérifie si l'utilisateur est un membre actif
     */
    public function isActiveMember(): bool
    {
        return in_array($this->autorisation, [
            self::AUTH_ADMIN,
            self::AUTH_MODERATOR,
            self::AUTH_VIEWER
        ]);
    }

    /**
     * NOUVEAU: Vérifie si l'utilisateur est banni
     */
    public function isBanned(): bool
    {
        return $this->autorisation === self::AUTH_BANNED;
    }

    /**
     * NOUVEAU: Vérifie si l'utilisateur a des restrictions
     */
    public function hasRestrictions(): bool
    {
        return in_array($this->autorisation, [
            self::AUTH_RESTRICTED,
            self::AUTH_NEW_MEMBER,
            self::AUTH_BANNED
        ]);
    }

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
        return "{$this->prenom} {$this->nom}";
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
     * Récupère un utilisateur par son email
     */
    public static function findByEmail(string $email): ?User
    {
        return static::findWhere(['email' => $email]);
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
        $user = static::findByEmail($login) ?? static::findByUsername($login);

        if ($user !== null && $user->checkPassword($password)) {
            return $user;
        }

        return null;
    }

    /**
     * OVERRIDE: Méthode pour créer une instance depuis la DB
     */
    public static function fromDatabase(array $data): static
    {
        $instance = new static();

        // Assigner directement tous les attributs
        foreach ($data as $key => $value) {
            $instance->attributes[$key] = $value;
        }

        // S'assurer que les attributs critiques sont présents
        if (isset($data['id'])) {
            $instance->attributes['id'] = $data['id'];
        }
        if (isset($data['autorisation'])) {
            $instance->attributes['autorisation'] = $data['autorisation'];
        }

        $instance->syncOriginal();

        return $instance;
    }

    /**
     * Événement après la création
     */
    protected function onCreated(): void
    {
        // Par défaut, nouveau membre = niveau 4
        if (!isset($this->attributes['autorisation'])) {
            $this->attributes['autorisation'] = self::AUTH_NEW_MEMBER;
            $this->save();
        }
    }

    /**
     * Événement avant la suppression
     */
    protected function onDeleting(): bool
    {
        // Empêcher la suppression du dernier administrateur
        if ($this->isAdmin()) {
            $adminCount = static::where('autorisation', self::AUTH_ADMIN)->count();
            if ($adminCount <= 1) {
                error_log("Impossible de supprimer le dernier administrateur");
                return false;
            }
        }

        return true;
    }

    /**
     * Méthode personnalisée pour enregistrer l'utilisateur
     */
    public function save(): bool
    {
        // Si l'utilisateur est nouveau
        if (!isset($this->attributes[static::$primaryKey]) || empty($this->attributes[static::$primaryKey])) {
            // Ajouter la date d'inscription
            if (!isset($this->attributes['date_registered'])) {
                $this->attributes['date_registered'] = date('Y-m-d H:i:s');
            }
            // Définir l'autorisation par défaut si non définie
            if (!isset($this->attributes['autorisation'])) {
                $this->attributes['autorisation'] = self::AUTH_NEW_MEMBER;
            }
        }

        return parent::save();
    }
}
