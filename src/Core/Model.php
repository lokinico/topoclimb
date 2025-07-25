<?php
// src/Core/Model.php

namespace TopoclimbCH\Core;

use PDO;
use PDOException;
use ReflectionClass;
use TopoclimbCH\Core\Database;
use TopoclimbCH\Exceptions\ModelException;
use TopoclimbCH\Core\Events\EventDispatcher;
use TopoclimbCH\Core\Validation\Validator;

abstract class Model
{
    /**
     * Attributs du modèle
     */
    protected array $attributes = [];

    /**
     * Attributs originaux (pour comparer les changements)
     */
    protected array $original = [];

    /**
     * Attributs qui ont été modifiés
     */
    protected array $dirty = [];

    /**
     * Nom de la table en base de données
     */
    protected static string $table = '';

    /**
     * Clé primaire
     */
    protected static string $primaryKey = 'id';

    /**
     * Relations chargées
     */
    protected array $relations = [];

    /**
     * Liste des attributs remplissables en masse
     */
    protected array $fillable = [];

    /**
     * Liste des attributs protégés contre le remplissage en masse
     */
    protected array $guarded = ['id'];

    /**
     * Règles de validation
     */
    protected array $rules = [];

    /**
     * Indique si le modèle utilise les timestamps automatiques
     */
    protected bool $timestamps = true;

    /**
     * Nom des colonnes de timestamps
     */
    protected string $createdAtColumn = 'created_at';
    protected string $updatedAtColumn = 'updated_at';

    /**
     * Liste des événements disponibles
     */
    protected const EVENT_CREATING = 'creating';
    protected const EVENT_CREATED = 'created';
    protected const EVENT_UPDATING = 'updating';
    protected const EVENT_UPDATED = 'updated';
    protected const EVENT_SAVING = 'saving';
    protected const EVENT_SAVED = 'saved';
    protected const EVENT_DELETING = 'deleting';
    protected const EVENT_DELETED = 'deleted';

    /**
     * Gestionnaire d'événements
     */
    protected static ?EventDispatcher $eventDispatcher = null;

    /**
     * Instance de base de données injectée (statique pour être partagée entre tous les modèles)
     */
    protected static ?Database $db = null;

    /**
     * Constructeur
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
        $this->syncOriginal();

        if (self::$eventDispatcher === null) {
            self::$eventDispatcher = new EventDispatcher();
        }
    }

    /**
     * Remplit les attributs du modèle
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    /**
     * Vérifie si un attribut est remplissable
     */
    public function isFillable(string $key): bool
    {
        if (in_array($key, $this->guarded)) {
            return false;
        }

        return empty($this->fillable) || in_array($key, $this->fillable);
    }

    /**
     * Synchronise les attributs originaux
     */
    public function syncOriginal(): self
    {
        $this->original = $this->attributes;
        $this->dirty = [];

        return $this;
    }

    /**
     * Récupère la valeur d'un attribut
     */
    public function getAttribute(string $key)
    {
        if (array_key_exists($key, $this->attributes)) {
            $value = $this->attributes[$key];

            // Appliquer les accesseurs si nécessaire
            $accessor = 'get' . ucfirst($key) . 'Attribute';
            if (method_exists($this, $accessor)) {
                return $this->$accessor($value);
            }

            return $value;
        }

        // Vérifier s'il s'agit d'une relation
        if (method_exists($this, $key)) {
            return $this->getRelationValue($key);
        }

        return null;
    }

    /**
     * Récupère une valeur de relation
     */
    protected function getRelationValue(string $key)
    {
        // Charger la relation si elle n'est pas déjà chargée (lazy loading)
        if (!array_key_exists($key, $this->relations)) {
            $this->relations[$key] = $this->$key();
        }

        return $this->relations[$key];
    }

    /**
     * Définit la valeur d'un attribut
     */
    public function setAttribute(string $key, $value): self
    {
        // Appliquer les mutateurs si nécessaire
        $mutator = 'set' . ucfirst($key) . 'Attribute';
        if (method_exists($this, $mutator)) {
            $value = $this->$mutator($value);
        }

        // Marquer l'attribut comme modifié
        if (!array_key_exists($key, $this->attributes) || $this->attributes[$key] !== $value) {
            $this->dirty[] = $key;
        }

        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Surcharge de __get pour accéder aux attributs
     */
    public function __get(string $key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Surcharge de __set pour définir les attributs
     */
    public function __set(string $key, $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Surcharge de __isset pour vérifier l'existence d'un attribut
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]) || isset($this->relations[$key]);
    }

    /**
     * Retourne le nom de la table
     */
    public static function getTable(): string
    {
        if (!empty(static::$table)) {
            return static::$table;
        }

        // Déduire le nom de la table à partir du nom de la classe
        $reflection = new ReflectionClass(static::class);
        $className = $reflection->getShortName();

        // Convertir CamelCase en snake_case et mettre au pluriel
        $table = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));

        // Pluralisation simple (à améliorer pour les cas spéciaux)
        if (!str_ends_with($table, 's')) {
            $table .= 's';
        }

        return $table;
    }

    /**
     * Retourne une instance de PDO
     */
    protected static function getConnection(): PDO
    {
        // Essayer d'utiliser l'instance injectée d'abord
        if (isset(static::$db) && static::$db instanceof Database) {
            return static::$db->getConnection();
        }
        
        // Fallback vers l'instance singleton pour la compatibilité
        return Database::getInstance()->getConnection();
    }

    /**
     * Définit l'instance de base de données à utiliser
     */
    public static function setDatabase(Database $database): void
    {
        static::$db = $database;
    }

    /**
     * Trouve un modèle par sa clé primaire
     */
    public static function find(int $id): ?static
    {
        $table = static::getTable();
        $primaryKey = static::$primaryKey;

        $sql = "SELECT * FROM {$table} WHERE {$primaryKey} = :id LIMIT 1";

        try {
            $statement = static::getConnection()->prepare($sql);
            $statement->execute([':id' => $id]);
            $data = $statement->fetch(PDO::FETCH_ASSOC);

            if ($data === false) {
                return null;
            }

            return new static($data);
        } catch (PDOException $e) {
            throw new ModelException("Erreur lors de la recherche de l'enregistrement: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Trouve le premier modèle correspondant aux critères
     */
    public static function findWhere(array $criteria): ?static
    {
        $table = static::getTable();

        $wheres = [];
        $params = [];

        foreach ($criteria as $column => $value) {
            $wheres[] = "{$column} = :{$column}";
            $params[":{$column}"] = $value;
        }

        $whereClause = implode(' AND ', $wheres);
        $sql = "SELECT * FROM {$table} WHERE {$whereClause} LIMIT 1";

        try {
            $statement = static::getConnection()->prepare($sql);
            $statement->execute($params);
            $data = $statement->fetch(PDO::FETCH_ASSOC);

            if ($data === false) {
                return null;
            }

            return new static($data);
        } catch (PDOException $e) {
            throw new ModelException("Erreur lors de la recherche de l'enregistrement: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Récupère tous les modèles correspondant aux critères
     *
     * @param array|string $criteria Critères de recherche ou nom de colonne
     * @param mixed|null $value Valeur à comparer ou null si $criteria est un tableau
     * @param string|null $orderBy Colonne pour le tri
     * @param string $direction Direction du tri (ASC/DESC)
     * @return array
     */
    public static function where($criteria, $value = null, ?string $orderBy = null, string $direction = 'ASC'): array
    {
        $argCount = func_num_args();

        // Si criteria n'est pas un tableau, c'est une colonne et value est sa valeur
        if (!is_array($criteria)) {
            // Si on a plus de 2 arguments et que value est une chaîne, 
            // alors c'est probablement la colonne de tri
            if ($argCount > 2 && is_string($value)) {
                // Dans ce cas, value devient orderBy, et le 3e argument devient direction
                $orderBy = $value;
                if ($argCount > 3) {
                    $direction = func_get_arg(2);
                }
                // Définir la valeur pour le critère (qui est null dans ce cas)
                $criteria = [$criteria => func_get_arg(3) ?? null];
            } else {
                // Cas standard: criteria est une colonne, value est sa valeur
                $criteria = [$criteria => $value];
            }
        }

        $table = static::getTable();

        $wheres = [];
        $params = [];

        foreach ($criteria as $column => $value) {
            $wheres[] = "{$column} = :{$column}";
            $params[":{$column}"] = $value;
        }

        $whereClause = !empty($wheres) ? 'WHERE ' . implode(' AND ', $wheres) : '';

        // Normalisation de direction pour s'assurer qu'elle est soit "ASC" soit "DESC"
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $orderClause = $orderBy ? "ORDER BY {$orderBy} {$direction}" : '';

        $sql = "SELECT * FROM {$table} {$whereClause} {$orderClause}";

        try {
            $statement = static::getConnection()->prepare($sql);
            $statement->execute($params);
            $data = $statement->fetchAll(PDO::FETCH_ASSOC);

            $models = [];
            foreach ($data as $item) {
                $models[] = new static($item);
            }

            return $models;
        } catch (PDOException $e) {
            throw new ModelException("Erreur lors de la recherche des enregistrements: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Récupère tous les modèles
     */
    public static function all(?string $orderBy = null, string $direction = 'ASC'): array
    {
        return static::where([], null, $orderBy, $direction);
    }

    /**
     * Chaîne de requête de style Eloquent
     *
     * @param string|array $column Le nom de la colonne ou un tableau de conditions
     * @param mixed|null $operator Opérateur ou valeur si l'opérateur est omis
     * @param mixed|null $value La valeur à comparer
     * @return Builder|static Une instance du builder ou statique
     */
    public static function query()
    {
        return new \TopoclimbCH\Core\Query\Builder(static::class);
    }
    /**
     * Sauvegarde le modèle (insert ou update)
     */
    public function save(): bool
    {
        // Valider les données avant de sauvegarder
        if (!$this->validate()) {
            return false;
        }

        // Vérifier s'il s'agit d'une création ou d'une mise à jour
        $primaryKey = static::$primaryKey;
        $isCreating = !isset($this->attributes[$primaryKey]) || empty($this->attributes[$primaryKey]);

        // Déclencher les événements appropriés
        if (!$this->fireEvent(self::EVENT_SAVING)) {
            return false;
        }

        if ($isCreating) {
            return $this->performInsert();
        } else {
            return $this->performUpdate();
        }
    }

    /**
     * Effectue une insertion
     */
    protected function performInsert(): bool
    {
        if (!$this->fireEvent(self::EVENT_CREATING)) {
            return false;
        }

        // Ajouter les timestamps si nécessaire
        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            $this->attributes[$this->createdAtColumn] = $now;
            $this->attributes[$this->updatedAtColumn] = $now;
        }

        $table = static::getTable();

        $columns = array_keys($this->attributes);
        $placeholders = array_map(fn($col) => ":{$col}", $columns);

        $columnsStr = implode(', ', $columns);
        $placeholdersStr = implode(', ', $placeholders);

        $sql = "INSERT INTO {$table} ({$columnsStr}) VALUES ({$placeholdersStr})";

        try {
            $statement = static::getConnection()->prepare($sql);

            $params = [];
            foreach ($this->attributes as $column => $value) {
                $params[":{$column}"] = $value;
            }

            $result = $statement->execute($params);

            if ($result) {
                // Récupérer l'ID généré
                $id = static::getConnection()->lastInsertId();
                $this->attributes[static::$primaryKey] = $id;

                // Synchroniser les attributs originaux
                $this->syncOriginal();

                // Déclencher l'événement post-création
                $this->fireEvent(self::EVENT_CREATED);
                $this->fireEvent(self::EVENT_SAVED);
            }

            return $result;
        } catch (PDOException $e) {
            throw new ModelException("Erreur lors de l'insertion de l'enregistrement: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Effectue une mise à jour
     */
    protected function performUpdate(): bool
    {
        if (empty($this->dirty)) {
            // Rien à mettre à jour
            return true;
        }

        if (!$this->fireEvent(self::EVENT_UPDATING)) {
            return false;
        }

        // Ajouter le timestamp de mise à jour si nécessaire
        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            $this->attributes[$this->updatedAtColumn] = $now;
            $this->dirty[] = $this->updatedAtColumn;
        }

        $table = static::getTable();
        $primaryKey = static::$primaryKey;

        $sets = [];
        $params = [];

        foreach ($this->dirty as $column) {
            if (isset($this->attributes[$column])) {
                $sets[] = "{$column} = :{$column}";
                $params[":{$column}"] = $this->attributes[$column];
            }
        }

        if (empty($sets)) {
            return true; // Rien à mettre à jour
        }

        $setClause = implode(', ', $sets);
        $params[":{$primaryKey}"] = $this->attributes[$primaryKey];

        $sql = "UPDATE {$table} SET {$setClause} WHERE {$primaryKey} = :{$primaryKey}";

        try {
            $statement = static::getConnection()->prepare($sql);
            $result = $statement->execute($params);

            if ($result) {
                // Synchroniser les attributs originaux
                $this->syncOriginal();

                // Déclencher l'événement post-mise à jour
                $this->fireEvent(self::EVENT_UPDATED);
                $this->fireEvent(self::EVENT_SAVED);
            }

            return $result;
        } catch (PDOException $e) {
            throw new ModelException("Erreur lors de la mise à jour de l'enregistrement: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Supprime le modèle
     */
    public function delete(): bool
    {
        if (!$this->fireEvent(self::EVENT_DELETING)) {
            return false;
        }

        $table = static::getTable();
        $primaryKey = static::$primaryKey;

        if (!isset($this->attributes[$primaryKey])) {
            return false;
        }

        $sql = "DELETE FROM {$table} WHERE {$primaryKey} = :id";

        try {
            $statement = static::getConnection()->prepare($sql);
            $result = $statement->execute([':id' => $this->attributes[$primaryKey]]);

            if ($result) {
                // Déclencher l'événement post-suppression
                $this->fireEvent(self::EVENT_DELETED);
            }

            return $result;
        } catch (PDOException $e) {
            throw new ModelException("Erreur lors de la suppression de l'enregistrement: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Supprime un modèle par sa clé primaire
     */
    public static function destroy(int $id): bool
    {
        $model = static::find($id);

        if ($model === null) {
            return false;
        }

        return $model->delete();
    }

    /**
     * Définit une relation hasMany
     */
    protected function hasMany(string $relatedClass, ?string $foreignKey = null, ?string $localKey = null): array
    {
        $localKey = $localKey ?? static::$primaryKey;
        $foreignKey = $foreignKey ?? strtolower((new ReflectionClass(static::class))->getShortName()) . '_id';

        if (!isset($this->attributes[$localKey])) {
            return [];
        }

        return $relatedClass::where([$foreignKey => $this->attributes[$localKey]]);
    }

    /**
     * Définit une relation belongsTo
     */
    protected function belongsTo(string $relatedClass, ?string $foreignKey = null, ?string $otherKey = null): ?object
    {
        $otherKey = $otherKey ?? (new ReflectionClass($relatedClass))->getShortName() . '_id';
        $foreignKey = $foreignKey ?? $relatedClass::$primaryKey;

        if (!isset($this->attributes[$otherKey])) {
            return null;
        }

        return $relatedClass::find($this->attributes[$otherKey]);
    }

    /**
     * Définit une relation belongsToMany (many-to-many)
     */
    protected function belongsToMany(string $relatedClass, ?string $pivotTable = null, ?string $foreignPivotKey = null, ?string $relatedPivotKey = null): array
    {
        $foreignPivotKey = $foreignPivotKey ?? strtolower((new ReflectionClass(static::class))->getShortName()) . '_id';
        $relatedPivotKey = $relatedPivotKey ?? strtolower((new ReflectionClass($relatedClass))->getShortName()) . '_id';

        if ($pivotTable === null) {
            // Générer le nom de la table pivot en utilisant les noms des deux modèles en ordre alphabétique
            $models = [
                strtolower((new ReflectionClass(static::class))->getShortName()),
                strtolower((new ReflectionClass($relatedClass))->getShortName())
            ];
            sort($models);
            $pivotTable = implode('_', $models);
        }

        if (!isset($this->attributes[static::$primaryKey])) {
            return [];
        }

        $pdo = static::getConnection();

        $sql = "SELECT r.* FROM {$relatedClass::getTable()} r
                INNER JOIN {$pivotTable} p 
                ON p.{$relatedPivotKey} = r." . $relatedClass::$primaryKey . "
                WHERE p.{$foreignPivotKey} = :id";

        try {
            $statement = $pdo->prepare($sql);
            $statement->execute([':id' => $this->attributes[static::$primaryKey]]);
            $data = $statement->fetchAll(PDO::FETCH_ASSOC);

            $models = [];
            foreach ($data as $item) {
                $models[] = new $relatedClass($item);
            }

            return $models;
        } catch (PDOException $e) {
            throw new ModelException("Erreur lors de la récupération de la relation belongsToMany: " . $e->getMessage(), 0, $e);
        }
    }


    /**
     * Déclenche un événement
     */
    protected function fireEvent(string $event): bool
    {
        // Vérifier si une méthode correspondant à l'événement existe dans le modèle
        $method = 'on' . ucfirst($event);
        if (method_exists($this, $method)) {
            // Si la méthode retourne false, annuler l'opération
            if ($this->$method() === false) {
                return false;
            }
        }

        // Déclencher l'événement via le dispatcher
        return self::$eventDispatcher->dispatch($event, $this) !== false;
    }

    /**
     * Enregistre un écouteur d'événement
     */
    public static function registerEventListener(string $event, callable $listener): void
    {
        if (self::$eventDispatcher === null) {
            self::$eventDispatcher = new EventDispatcher();
        }

        self::$eventDispatcher->addListener($event, $listener);
    }

    /**
     * Charge les relations spécifiées pour le modèle
     *
     * @param string|array $relations Nom de la relation ou tableau de noms de relations à charger
     * @return $this
     */
    public function load($relations): self
    {
        // Convertir une seule relation en tableau
        if (!is_array($relations)) {
            $relations = [$relations];
        }

        foreach ($relations as $relation) {
            if (method_exists($this, $relation)) {
                // Charger la relation et stocker son résultat
                $this->relations[$relation] = $this->$relation();
            }
        }

        return $this;
    }

    /**
     * Méthode helper pour permettre les styles d'appel where('column', 'value') et where(['column' => 'value'])
     *
     * @param string|array $column Nom de la colonne ou tableau de critères
     * @param mixed|null $value Valeur à comparer
     * @return array
     */
    public static function findBy($column, $value = null): array
    {
        if (is_array($column)) {
            return static::where($column);
        }

        return static::where([$column => $value]);
    }

    /**
     * Retrouve le premier modèle correspondant à la colonne et valeur
     * 
     * @param string|array $column Nom de la colonne ou tableau de critères
     * @param mixed|null $value Valeur à comparer
     * @return static|null
     */
    public static function findOneBy($column, $value = null): ?static
    {
        $result = static::findBy($column, $value);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Récupère le premier modèle correspondant aux critères
     *
     * @param array $criteria Critères de recherche
     * @return static|null
     */
    public static function findOne(array $criteria): ?static
    {
        $results = static::where($criteria);
        return !empty($results) ? $results[0] : null;
    }


    /**
     * Valide les données du modèle
     */
    public function validate(): bool
    {
        if (empty($this->rules)) {
            return true;
        }

        try {
            $validator = new Validator();
            return $validator->validate($this->attributes, $this->rules);
        } catch (\Exception $e) {
            // Logguer l'erreur mais retourner true pour ne pas bloquer l'opération
            error_log("Erreur de validation: " . $e->getMessage());
            return true;
        }
    }


    /**
     * Filtrer les résultats avec un objet Filter
     *
     * @param Filter $filter Filtre à appliquer
     * @param string|null $orderBy Colonne de tri
     * @param string $direction Direction de tri (ASC/DESC)
     * @return array Résultats filtrés
     */
    public static function filter(\TopoclimbCH\Core\Filtering\Filter $filter, ?string $orderBy = null, string $direction = 'ASC'): array
    {
        $conditions = $filter->apply([]);
        return static::where($conditions, null, $orderBy, $direction);
    }

    /**
     * Paginer les résultats filtrés
     *
     * @param Filter $filter Filtre à appliquer
     * @param int $page Page courante
     * @param int $perPage Nombre d'éléments par page
     * @param string|null $orderBy Colonne de tri
     * @param string $direction Direction de tri (ASC/DESC)
     * @return \TopoclimbCH\Core\Pagination\Paginator Résultats paginés
     */
    public static function filterAndPaginate(\TopoclimbCH\Core\Filtering\Filter $filter, int $page = 1, int $perPage = 15, ?string $orderBy = null, string $direction = 'ASC'): \TopoclimbCH\Core\Pagination\Paginator
    {
        $results = static::filter($filter, $orderBy, $direction);

        return \TopoclimbCH\Core\Pagination\Paginator::paginate(
            $results,
            $page,
            $perPage,
            $filter->getParams()
        );
    }


    /**
     * Récupère l'ID de façon sécurisée
     */
    public function getId(): int
    {
        return (int)($this->attributes[$this->primaryKey] ?? 0);
    }
}
