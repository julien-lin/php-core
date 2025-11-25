<?php

namespace JulienLinard\Core\Events;

/**
 * Gestionnaire d'événements simple (pattern Observer)
 */
class EventDispatcher
{
    /**
     * Liste des listeners par événement
     * Structure: ['event.name' => [callable, callable, ...]]
     */
    private array $listeners = [];

    /**
     * Écoute un événement
     *
     * @param string $event Nom de l'événement
     * @param callable $listener Fonction à exécuter lorsque l'événement est déclenché
     * @return void
     */
    public function listen(string $event, callable $listener): void
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
        
        $this->listeners[$event][] = $listener;
    }

    /**
     * Déclenche un événement
     *
     * @param string $event Nom de l'événement
     * @param array $data Données à passer aux listeners
     * @return void
     */
    public function dispatch(string $event, array $data = []): void
    {
        if (!isset($this->listeners[$event])) {
            return;
        }

        foreach ($this->listeners[$event] as $listener) {
            $listener($data);
        }
    }

    /**
     * Supprime tous les listeners d'un événement
     *
     * @param string $event Nom de l'événement
     * @return void
     */
    public function forget(string $event): void
    {
        unset($this->listeners[$event]);
    }

    /**
     * Supprime tous les listeners
     *
     * @return void
     */
    public function flush(): void
    {
        $this->listeners = [];
    }

    /**
     * Vérifie si un événement a des listeners
     *
     * @param string $event Nom de l'événement
     * @return bool True si l'événement a des listeners
     */
    public function hasListeners(string $event): bool
    {
        return isset($this->listeners[$event]) && !empty($this->listeners[$event]);
    }

    /**
     * Retourne tous les événements écoutés
     *
     * @return array Liste des noms d'événements
     */
    public function getEvents(): array
    {
        return array_keys($this->listeners);
    }
}

