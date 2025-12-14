<?php

declare(strict_types=1);

namespace JulienLinard\Core\Model;

/**
 * Classe Model de base pour toutes les entités
 */
abstract class Model
{
    /**
     * Identifiant unique (généralement auto-incrémenté)
     */
    public ?int $id = null;

    /**
     * Propriétés qui peuvent être remplies via fill() (whitelist)
     * Si vide, toutes les propriétés sont autorisées sauf celles dans $guarded
     * 
     * @var array<string>
     */
    protected array $fillable = [];

    /**
     * Propriétés qui ne peuvent PAS être remplies via fill() (blacklist)
     * Par défaut, l'ID est protégé
     * 
     * @var array<string>
     */
    protected array $guarded = ['id'];

    /**
     * Constructeur avec hydratation automatique
     *
     * @param array $data Données à injecter dans l'objet
     */
    public function __construct(array $data = [])
    {
        $this->fill($data);
    }

    /**
     * Remplit l'objet avec des données
     * 
     * Protection contre le mass assignment:
     * - Si $fillable est défini, seules ces propriétés peuvent être remplies
     * - Si $guarded est défini, ces propriétés ne peuvent pas être remplies
     * - Par défaut, 'id' est protégé
     *
     * @param array $data Données à injecter
     * @return self Instance pour chaînage
     */
    public function fill(array $data): self
    {
        foreach ($data as $key => $value) {
            // Vérifier que la propriété existe
            if (!property_exists($this, $key)) {
                continue;
            }
            
            // Si fillable est défini et non vide, vérifier que la clé est autorisée
            if (!empty($this->fillable) && !in_array($key, $this->fillable, true)) {
                continue;
            }
            
            // Si guarded est défini et non vide, vérifier que la clé n'est pas protégée
            if (!empty($this->guarded) && in_array($key, $this->guarded, true)) {
                continue;
            }
            
            $this->$key = $value;
        }
        
        return $this;
    }

    /**
     * Convertit l'objet en tableau
     *
     * @return array Représentation tableau de l'objet
     */
    public function toArray(): array
    {
        $array = [];
        $reflection = new \ReflectionClass($this);
        
        foreach ($reflection->getProperties() as $property) {
            $array[$property->getName()] = $property->getValue($this);
        }
        
        return $array;
    }

    /**
     * Convertit l'objet en JSON
     *
     * @param int $flags Flags pour json_encode
     * @return string JSON encodé
     */
    public function toJson(int $flags = JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->toArray(), $flags);
    }

    /**
     * Vérifie si l'objet existe (a un ID)
     *
     * @return bool True si l'objet existe
     */
    public function exists(): bool
    {
        return $this->id !== null;
    }

    /**
     * Retourne une représentation string de l'objet
     *
     * @return string Nom de la classe et ID
     */
    public function __toString(): string
    {
        $className = (new \ReflectionClass($this))->getShortName();
        return $className . '#' . ($this->id ?? 'new');
    }
}

