<?php
// src/Core/Events/EventDispatcher.php

namespace TopoclimbCH\Core\Events;

class EventDispatcher
{
    /**
     * Liste des écouteurs d'événements
     */
    protected array $listeners = [];
    
    /**
     * Ajoute un écouteur pour un événement
     */
    public function addListener(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }
    
    /**
     * Supprime un écouteur pour un événement
     */
    public function removeListener(string $event, callable $listener): void
    {
        if (!isset($this->listeners[$event])) {
            return;
        }
        
        $key = array_search($listener, $this->listeners[$event], true);
        
        if ($key !== false) {
            unset($this->listeners[$event][$key]);
        }
    }
    
    /**
     * Déclenche un événement
     */
    public function dispatch(string $event, object $subject)
    {
        if (!isset($this->listeners[$event])) {
            return true;
        }
        
        foreach ($this->listeners[$event] as $listener) {
            $result = call_user_func($listener, $subject);
            
            // Si un écouteur retourne explicitement false, arrêter la propagation
            if ($result === false) {
                return false;
            }
        }
        
        return true;
    }
}