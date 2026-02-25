<?php
class Skills {

private const BOULE_DE_FEU_MIN_LV = 5;
private const COUTEAU_MIN_LV = 10;
private const M9_MIN_LV = 15;
private const REMINGTON_3700_MIN_LV = 19;


    public static function BouleDeFeu(Hero $hero, Monster $monster): void
    {
        if ($hero->getLv() < Skills::BOULE_DE_FEU_MIN_LV) {
            throw new Exception("Boule de Feu is only available at level " . Skills::BOULE_DE_FEU_MIN_LV . " or higher.");
        }
        $damage = $hero->getAtk() * 2; 
        $monster->takeDamage($damage);
    }

    public static function Couteau(Hero $hero, Monster $monster): void
    {
        if ($hero->getLv() < Skills::COUTEAU_MIN_LV) {
            throw new Exception("Couteau is only available at level " . Skills::COUTEAU_MIN_LV . " or higher.");
        }
        $damage = $hero->getAtk() * 3; 
        $monster->takeDamage($damage);
    }

       public static function M9(Hero $hero, Monster $monster): void
    {
        if ($hero->getLv() < Skills::M9_MIN_LV) {
            throw new Exception("M9 is only available at level " . Skills::M9_MIN_LV . " or higher.");
        }
        $damage = $hero->getAtk() * 4; 
        $monster->takeDamage($damage);
    }
    
       public static function Remington3700(Hero $hero, Monster $monster): void
    {
        if ($hero->getLv() < Skills::REMINGTON_3700_MIN_LV) {
            throw new Exception("Remington 3700 is only available at level " . Skills::REMINGTON_3700_MIN_LV . " or higher.");
        }
        $damage = $hero->getAtk() * 5; 
        $monster->takeDamage($damage);
    }

    
}