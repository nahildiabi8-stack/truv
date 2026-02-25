<?php
class Monster
{
    private string $name;
    private int $hp;
    private int $atk;

    public function __construct(string $name, int $hp, int $atk)
    {
        $this->name = $name;
        $this->hp = $hp;
        $this->atk = $atk;
    }

    public function getName(): string
    {
        return $this->name;
    
        }
    public function getHp(): int
    {
        return $this->hp;
    }

    public function getAtk(): int
    {
        return $this->atk;
    }

    public function takeDamage(int $damage): void
    {
        $this->hp -= $damage;
        if ($this->hp < 0) $this->hp = 0;
    }

    public function attack(Monster $target): void
    {
        $target->takeDamage($this->atk);
    }
}
