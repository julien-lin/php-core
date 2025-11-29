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
     * @param array $data Données à injecter
     * @return self Instance pour chaînage
     */
    public function fill(array $data): self
    {
        foreach ($data as $key => $value) {
            // Vérifier que la propriété existe
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
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
            $property->setAccessible(true);
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

