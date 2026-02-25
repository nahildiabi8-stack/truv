<?php
class HeroRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function save(Hero $hero): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO heroes (name, hp, atk, LV, XP) VALUES (:name, :hp, :atk, :lv, :xp)"
        );
        $stmt->execute([
            ':name' => $hero->getName(),
            ':hp'   => $hero->getHp(),
            ':atk'  => $hero->getAtk(),
            ':lv'   => $hero->getLv(),
            ':xp'   => $hero->getXp(),
        ]);
    }

    public function find(int $id): ?Hero {
        $stmt = $this->pdo->prepare("SELECT * FROM heroes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $name = $row['name'] ?? $row['NAME'] ?? '';
            $hp = (int)($row['hp'] ?? $row['HP'] ?? 100);
            $atk = (int)($row['atk'] ?? $row['ATK'] ?? 10);
            $lv = (int)($row['lv'] ?? $row['LV'] ?? 1);
            $xp = (int)($row['xp'] ?? $row['XP'] ?? 0);

            return new Hero($name, $hp, $atk, $lv, $xp);
        }

        return null;
    }

    public function update(int $id, Hero $hero): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE heroes SET hp = :hp, atk = :atk, LV = :lv, XP = :xp WHERE id = :id"
        );

        $stmt->execute([
            ':hp' => $hero->getHp(),
            ':atk' => $hero->getAtk(),
            ':lv' => $hero->getLv(),
            ':xp' => $hero->getXp(),
            ':id' => $id,
        ]);
    }
}
