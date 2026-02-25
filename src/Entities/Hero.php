<?php
class Hero
{
    private string $name;
    private int $hp;
    private int $atk;
    private int $lv;
    private int $xp;
    private array $skills;


    private const XP_TO_LEVEL = 500;
    private const HP_PER_LEVEL = 20;
    private const ATK_PER_LEVEL = 5;


    public function __construct(string $name, int $hp, int $atk, int $lv = 1, int $xp = 0, array $skills = [])
    {
        $this->name = $name;
        $this->hp = $hp;
        $this->atk = $atk;
        $this->lv = $lv;
        $this->xp = $xp;
        $this->skills = $skills;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSkills(): array
    {
        return $this->skills;
    }
    public function getHp(): int
    {
        return $this->hp;
    }

    public function getAtk(): int
    {
        return $this->atk;
    }

    public function getXp(): int
    {
        return $this->xp;
    }

    public function getLv(): int
    {
        return $this->lv;
    }



    public function LVUp(): void
    {
        // legacy wrapper - perform a check but prefers addXp for real changes
        $this->addXp(0);
    }

    // returns number of levels gained
    public function addXp(int $amount): int
    {
        if ($amount <= 0) {
            // still check if XP already overflowing
            $levels = 0;
            while ($this->xp >= self::XP_TO_LEVEL) {
                $this->xp -= self::XP_TO_LEVEL;
                $this->lv++;
                $this->hp += self::HP_PER_LEVEL;
                $this->atk += self::ATK_PER_LEVEL;
                $levels++;
            }
            return $levels;
        }

        $this->xp += $amount;
        $levels = 0;
        while ($this->xp >= self::XP_TO_LEVEL) {
            $this->xp -= self::XP_TO_LEVEL;
            $this->lv++;
            $this->hp += self::HP_PER_LEVEL;
            $this->atk += self::ATK_PER_LEVEL;
            $levels++;
        }

        return $levels;
    }

    

    public function takeDamage(int $damage): void
    {
        $this->hp -= $damage;
        if ($this->hp < 0) $this->hp = 0;
    }

    public function attack(Hero $target): void
    {
        $target->takeDamage($this->atk);
    }
}
