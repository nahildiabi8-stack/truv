<?php

class Skill
{
    private int $id;
    private string $name;
    private int $requiredLevel;
    private int $multiplier;

    public function __construct(int $id, string $name, int $requiredLevel, int $multiplier)
    {
        $this->id = $id;
        $this->name = $name;
        $this->requiredLevel = $requiredLevel;
        $this->multiplier = $multiplier;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRequiredLevel(): int
    {
        return $this->requiredLevel;
    }

    public function use(Hero $hero, Hero $monster): int
    {
        if ($hero->getLv() < $this->requiredLevel) {
            throw new Exception(
                "{$this->name} débloqué au niveau {$this->requiredLevel}"
            );
        }

        $damage = $hero->getAtk() * $this->multiplier;
        $monster->takeDamage($damage);

        return $damage;
    }
}