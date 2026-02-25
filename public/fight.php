<?php

declare(strict_types=1);
session_start();
require_once __DIR__ . '/../src/Entities/Hero.php';
require_once __DIR__ . '/../src/Repositories/HeroesRepository.php';

$pdo = new PDO('mysql:host=localhost;dbname=d', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$repository = new HeroRepository($pdo);

$heroesStmt = $pdo->query("SELECT id, name, hp, atk FROM heroes ORDER BY id");
$heroesList = $heroesStmt->fetchAll(PDO::FETCH_ASSOC);

$skillsStmt = $pdo->query("SELECT id, required_lv, skill_name FROM skills ORDER BY id");
$skillsList = $skillsStmt->fetchAll(PDO::FETCH_ASSOC);




$heroId = null;
if (isset($_SESSION['hero_id'])) {
    $heroId = (int)$_SESSION['hero_id'];
} elseif (!empty($heroesList)) {
    $heroId = (int)$heroesList[0]['id'];
}

if ($heroId !== null) {
    $heroEntity = $repository->find($heroId);
}



if (!isset($heroEntity) || !$heroEntity) {
    exit("Héros introuvable !");
}

// full monster table (always defined)
$allMonsters = [
    1 => ['name' => 'Banane', 'hp' => 10, 'atk' => 2, 'img' => 'banane.png'],
    2 => ['name' => 'Pomme', 'hp' => 15, 'atk' => 1, 'img' => 'pomme.png'],
    3 => ['name' => 'Orange', 'hp' => 50, 'atk' => 5, 'img' => 'orange.png'],
    4 => ['name' => 'Pizza au nutella', 'hp' => 75, 'atk' => 8, 'img' => 'pizzaalabanane.png'],
    5 => ['name' => 'Tarte Au Pomme', 'hp' => 100, 'atk' => 25, 'img' => 'tartepomme.png'],
    6 => ['name' => 'Cerise', 'hp' => 125, 'atk' => 25, 'img' => 'cerise.png'],
    7 => ['name' => 'Napoléon Bonaparte', 'hp' => 15000, 'atk' => 500, 'img' => 'Napoleon.png'],
];

// Level gating: limit available monster indices based on hero level
$heroLv = (int) $heroEntity->getLv();
// default (safety) — ensure $maxIndex is always defined
$maxIndex = 2;
if ($heroLv <= 5) {
    $maxIndex = 2; // only weakest monsters
} elseif ($heroLv >= 6 && $heroLv < 10) {
    // early unlocks: allow a few stronger monsters for mid-early levels
    $maxIndex = 4;
} elseif ($heroLv >= 10 && $heroLv < 19) {
    $maxIndex = 6; // mid-tier unlocked
} elseif ($heroLv >= 19) {
    $maxIndex = count($allMonsters); // all monsters available
}

// slice allowed monsters and reindex to 1..N to keep session-compatible keys
$slice = array_slice($allMonsters, 0, $maxIndex, true);
$monsters = [];
$i = 1;
foreach ($slice as $m) {
    $monsters[$i++] = $m;
}

// ensure session monster_type is valid for current allowed set
if (!isset($_SESSION['monster_type']) || !isset($monsters[(int)$_SESSION['monster_type']])) {
    $_SESSION['monster_type'] = random_int(1, count($monsters));
}
$monsterType = (int)$_SESSION['monster_type'];
$monsterDef = $monsters[$monsterType];

if (!isset($_SESSION['monster_hp'])) {
    $_SESSION['monster_hp'] = $monsterDef['hp'];
}
if (!isset($_SESSION['hero_hp'])) {
    // initialise les PV du héros à sa valeur par défaut depuis la BDD
    $_SESSION['hero_hp'] = $heroEntity->getHp();
}

$monster = new Hero($monsterDef['name'], (int)$_SESSION['monster_hp'], $monsterDef['atk']);
$hero = new Hero($heroEntity->getName(), (int)$_SESSION['hero_hp'], $heroEntity->getAtk());


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    header('Content-Type: application/json');

    if ($action === 'select_hero') {
        $sel = isset($_POST['hero_id']) ? (int)$_POST['hero_id'] : 0;
        $selHero = $repository->find($sel);
        if ($selHero) {
            $_SESSION['hero_id'] = $sel;
            $_SESSION['hero_hp'] = $selHero->getHp();
            echo json_encode([
                'selected' => true,
                'hero_id' => $sel,
                'hero_name' => $selHero->getName(),
                'hero_hp' => $selHero->getHp(),
                'hero_lv' => $selHero->getLv(),
                'hero_xp' => $selHero->getXp(),
            ]);
            exit;
        }
        echo json_encode(['selected' => false]);
        exit;
    }

    if ($action === 'attack') {
        $log = [];


        $hero->attack($monster);
        $log[] = $hero->getName() . ' attaque et inflige ' . $hero->getAtk() . ' dégâts.';

        $_SESSION['monster_hp'] = $monster->getHp();

        // Award XP for attacking
        $xpForAttack = 10;
        $xpForKill = 100;
        $levelsGained = 0;

        if (isset($heroId) && $heroEntity) {
            $prevMax = $heroEntity->getHp();
            $levels = $heroEntity->addXp($xpForAttack);
            if ($levels > 0) {
                $levelsGained += $levels;
                $repository->update($heroId, $heroEntity);
                $delta = $heroEntity->getHp() - $prevMax;
                if ($delta > 0) {
                    // increase current HP in session by the gained max HP
                    $_SESSION['hero_hp'] = (int)$_SESSION['hero_hp'] + $delta;
                }
                // refresh in-combat hero object to new stats
                $hero = new Hero($heroEntity->getName(), (int)$_SESSION['hero_hp'], $heroEntity->getAtk());
            }
        }

        // If monster died, award additional XP
        $levelsFromKill = 0;
        if ($monster->getHp() <= 0) {
            if (isset($heroId) && $heroEntity) {
                $prevMax = $heroEntity->getHp();
                $levels = $heroEntity->addXp($xpForKill);
                if ($levels > 0) {
                    $levelsGained += $levels;
                }
                $repository->update($heroId, $heroEntity);
                $delta = $heroEntity->getHp() - $prevMax;
                if ($delta > 0) {
                    $_SESSION['hero_hp'] = (int)$_SESSION['hero_hp'] + $delta;
                }
                $hero = new Hero($heroEntity->getName(), (int)$_SESSION['hero_hp'], $heroEntity->getAtk());
            }
        }

        echo json_encode([
            'hero_hp' => $hero->getHp(),
            'hero_max' => $heroEntity->getHp(),
            'hero_lv' => $heroEntity->getLv(),
            'hero_xp' => $heroEntity->getXp(),
            'monster_hp' => $monster->getHp(),
            'monster_dead' => $monster->getHp() <= 0,
            'monster_name' => $monster->getName(),
            'monster_atk' => $monster->getAtk(),
            'log' => $log,
            'leveled' => $levelsGained > 0,
            'levels_gained' => $levelsGained,
        ]);
        exit;
    }

    if ($action === 'counter') {
        $log = [];


        if ($monster->getHp() > 0) {
            $monster->attack($hero);
            $log[] = $monster->getName() . ' contre-attaque et inflige ' . $monster->getAtk() . ' dégâts.';
        }

        $_SESSION['hero_hp'] = $hero->getHp();

        echo json_encode([
            'hero_hp' => $hero->getHp(),
            'hero_dead' => $hero->getHp() <= 0,
            'log' => $log,
        ]);
        exit;
    }

    if ($action === 'reset') {

        unset($_SESSION['monster_type'], $_SESSION['monster_hp'], $_SESSION['hero_hp']);
        echo json_encode(['reset' => true]);
        exit;
    }
}




?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Combat</title>
    <link href="../assets/output.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Press Start 2P', 'Courier New', Courier, monospace;
            background: #000;

            /* pure black page background */
        }

        /* Pixel UI helpers */
        .pixelated-ui {
            image-rendering: pixelated;
            -ms-interpolation-mode: nearest-neighbor;
        }

        .pixel-font {
            font-family: 'Press Start 2P', monospace;
        }

        .pixel-img {
            image-rendering: pixelated;
            image-rendering: crisp-edges;
        }

        /* Battle grid and dialogue (Undertale-like) */
        .battle-area {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            padding-top: 40px;
            /* leave space under HUD so it doesn't touch dialogue box */
        }

        .battle-grid {
            width: calc(100% - 60px);
            max-width: 920px;
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            grid-auto-rows: 76px;
            gap: 12px;
        }

        .battle-cell {
            border: 2px solid #1fcf4a;
            background: rgba(0, 0, 0, 0.05);
            box-sizing: border-box;
        }

        .dialogue-box {
            width: calc(100% - 60px);
            max-width: 920px;
            background: #000;
            border: 4px solid #fff;
            padding: 10px;
            box-sizing: border-box;
        }

        .dialogue-text {
            color: #fff;
            font-size: 13px;
            min-height: 48px;
            font-family: 'Press Start 2P', monospace;
        }

        /* Controls area under dialogue */
        .controls {
            display: flex;
            align-items: center;
            gap: 12px;
            justify-content: flex-start;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .controls label {
            width: 110px;
            display: inline-block;
        }

        .controls select {
            min-width: 150px;
        }

        /* Select pixel wrapper to show white pixel border around dropdown */
        .select-pixel {
            display: inline-block;
            background: #0b1220;
            border: 3px solid #fff;
            padding: 6px 8px;
            border-radius: 4px;
            box-shadow: 4px 4px 0 rgba(0, 0, 0, 0.6);
        }

        .select-pixel select {
            background: transparent;
            color: #fff;
            border: none;
            outline: none;
            font-family: 'Press Start 2P', monospace;
            font-size: 11px;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            padding-right: 18px;
            min-width: 130px;
        }

        /* HUD XP positioned top-center, stacked column */
        #hud-top-right {
            position: absolute;
            top: 12px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            max-width: 90%;
            overflow: hidden;
            text-align: center;
            z-index: 50;
            /* ensure HUD sits above other elements */
            pointer-events: none;
            /* avoid interfering with clicks */
        }

        #hud-top-right .small-pixel-text {
            font-size: 11px;
            display: block;
            margin: 0;
        }

        #hud-top-right .hud-lv {
            color: #9fe2c0;
            font-weight: 700;
            font-size: 11px;
        }

        #hud-top-right .hud-xp {
            color: #a6d6ff;
            font-weight: 700;
            font-size: 11px;
            display: block;
        }

        /* hide duplicate XP label shown elsewhere */
        .xp-meta {
            display: none;
        }


        .pixel-border {
            border: 6px solid #ffffff;
            /* white pixel border around container */
            box-shadow: 8px 8px 0 rgba(0, 0, 0, 0.6);
            z-index: 2;
        }

        /* Pixel buttons: black background, white pixel border, smaller */
        .btn-pixel {
            background: #000 !important;
            color: #fff !important;
            border: 4px solid #fff !important;
            box-shadow: 6px 6px 0 rgba(0, 0, 0, 0.6);
            padding: 8px 12px !important;
            font-family: 'Press Start 2P', monospace !important;
            font-size: 11px !important;
            line-height: 1 !important;
            text-shadow: none !important;
            border-radius: 2px !important;
            cursor: pointer;
        }

        .btn-pixel-atk {
            background: #000 !important;
            color: orange !important;
            border: 4px solid #fff !important;
            box-shadow: 6px 6px 0 rgba(0, 0, 0, 0.6);
            padding: 8px 12px !important;
            font-family: 'Press Start 2P', monospace !important;
            font-size: 11px !important;
            line-height: 1 !important;
            text-shadow: none !important;
            border-radius: 2px !important;
            cursor: pointer;
        }

        /* Undertale-like big action buttons */
        .ut-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-family: 'Press Start 2P', monospace;
            font-size: 12px;
            padding: 0 18px;
            height: 44px;
            line-height: 1;
            text-transform: uppercase;
            border-width: 6px;
            border-style: solid;
            background: #000;
            box-shadow: none;
            margin: 6px 8px;
            cursor: pointer;
        }

        .ut-btn.yellow {
            color: #ffe95c;
            border-color: #ffe95c;
        }

        .ut-btn.orange {
            color: #ff9a33;
            border-color: #ff9a33;
        }

        .ut-btn.small {
            padding: 0 12px;
            font-size: 12px;
            height: 36px;
            border-width: 4px;
        }

        /* smaller variant for less emphasis */
        .btn-pixel.small {
            padding: 6px 10px !important;
            font-size: 10px !important;
        }

        /* Bars */
        .bar-outer {
            background: #07101a;
            border: 4px solid #0b1220;
            height: 12px;
            /* slimmer HP bars */
            border-radius: 2px;
            overflow: hidden;
            box-shadow: inset -3px -3px 0 rgba(0, 0, 0, 0.6);
        }

        .bar-fill {
            height: 100%;
            background-image: linear-gradient(90deg, rgba(255, 255, 255, 0.06) 0 10%, transparent 10% 20%);
            background-size: 12px 100%;
            image-rendering: pixelated;
            transition: width 220ms steps(4);
        }

        /* Hero-specific colors */
        .hp-fill {
            background-color: green;
        }

        .xp-fill {
            background-color: #3aa0ff;
        }

        .monster-fill {
            background-color: red;
        }

        /* thin xp bar */
        .thin-xp-outer {
            background: #081018;
            border: 2px solid rgba(0, 0, 0, 0.7);
            height: 6px;
            border-radius: 2px;
            overflow: hidden;
            box-shadow: inset -2px -2px 0 rgba(0, 0, 0, 0.6);
        }

        .thin-xp-fill {
            height: 100%;
            background: linear-gradient(90deg, #3aa0ff, #2b8be6);
            transition: width 220ms steps(4);
        }

        /* hide the textual XP beneath the thin bar — keep bar update logic */
        #hero-xp-text {
            display: none;
        }

        .xp-meta {
            font-size: 10px;
            color: #9fb9d6;
            margin-top: 6px;
        }

        /* Make text chunky and centered in small blocks */
        .small-pixel-text {
            font-size: 10px;
            color: #cfe8ff;
        }

        /* Tweak layout for hero/monster area */
        #hero-area .text-2xl,
        #monster-area .text-2xl {
            font-size: 18px;
        }

        #hero-area img,
        #monster-area img {
            width: 140px;
            height: 140px;
            image-rendering: pixelated;
        }

        /* Green grid background overlay */
        .green-grid {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            background-image:
                linear-gradient(#18ff3a 2px, transparent 2px),
                linear-gradient(90deg, #18ff3a 2px, transparent 2px);
            background-size: 120px 120px;
            background-position: 0 0;
            opacity: 1;
            animation: gridMove 8s linear infinite;
        }

        @keyframes gridMove {
            from {
                background-position: 0 0, 0 0;
            }

            to {
                background-position: -120px -120px, -120px -120px;
            }
        }

        /* Controls layout and select/button visuals */
        .controls {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 12px;
            width: calc(100% - 48px);
            margin-left: 24px;
            margin-right: 24px;
            box-sizing: border-box;
            padding: 0 6px;
        }

        .select-pixel {
            display: inline-flex;
            align-items: center;
            background: #0b1220;
            border: 6px solid #ff9a33;
            /* orange pixel border */
            padding: 4px 6px;
            border-radius: 4px;
            box-shadow: 4px 4px 0 rgba(0, 0, 0, 0.6);
            vertical-align: middle;
            flex: 1 1 0px;
            /* allow select wrappers to grow and fill space */
            max-width: none;
            box-sizing: border-box;
        }

        .select-pixel select {
            width: 100%;
            background: #000;
            color: #ff9a33;
            border: none;
            outline: none;
            font-family: 'Press Start 2P', monospace;
            font-size: 12px;
            padding: 0 12px;
            min-width: 80px;
            height: 44px;
            text-align: center;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            box-shadow: inset -4px -4px 0 rgba(0, 0, 0, 0.6);
            background-image: linear-gradient(45deg, #fff 25%, transparent 25%), linear-gradient(135deg, #fff 25%, transparent 25%);
            background-position: right 12px center, right 6px center;
            background-size: 6px 6px, 6px 6px;
            background-repeat: no-repeat;
            box-sizing: border-box;
        }

        .select-pixel select:focus {
            box-shadow: inset -4px -4px 0 rgba(0, 0, 0, 0.6), 0 0 0 6px rgba(255, 154, 51, 0.08);
        }

        /* make options text orange where browsers allow */
        .select-pixel select option {
            color: #ff9a33;
            background: #000;
        }

        /* Ensure buttons and selects share same height and alignment */
        .controls button,
        .controls .ut-btn,
        .controls .btn-pixel {
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: middle;
            flex: 0 0 auto;
            /* buttons keep intrinsic size */
            font-size: 12px;
            color: #ff9a33;
        }
    </style>
</head>

<body class="bg-black select-none min-h-screen flex items-center justify-center p-6 ">
    <div class="green-grid" aria-hidden="true"></div>
    <div class="bg-[#000000] p-6 rounded-md shadow-lg w-full max-w-3xl text-white pixelated-ui pixel-border relative">
        <section class="flex flex-col items-center gap-4">
            <div id="hud-top-right" class=" flex flex-col items-center gap-4">
                <div class="small-pixel-text hud-lv">Lv <?php echo $heroEntity->getLv(); ?></div>
                <div class="small-pixel-text hud-xp">XP <?php echo $heroEntity->getXp(); ?> / 500</div>
            </div>
            <div id="" class="small-pixel-text xp-meta items-center gap-4"><?php echo $heroEntity->getXp(); ?> / 500</div>
        </section>

        <!-- Battle area: grid + dialogue -->
        <div class="battle-area mb-6">
            <div class="battle-grid" aria-hidden="true">

            </div>

            <div class="dialogue-box" id="dialogue-box">
                <div class="dialogue-text" id="dialogue-text"><?php echo htmlspecialchars(''); ?></div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-6 items-start">

            <div id="monster-area" class="flex flex-col items-center" data-max="<?php echo $monsterDef['hp']; ?>">
                <div class="text-2xl font-bold mb-2"><?php echo htmlspecialchars($monster->getName()); ?></div>
                <img src="../assets/img/<?php echo $monsterDef['img']; ?>" alt="monster" class="w-48 h-48 object-contain">
                <div class="w-full mt-2">
                    <div class="bar-outer">
                        <div id="monster-bar" class="bar-fill monster-fill" style="width:<?php echo max(0, min(100, (int)($_SESSION['monster_hp'] / $monsterDef['hp'] * 100))); ?>%;"></div>
                    </div>
                    <div id="monster-hp" class="mt-2 small-pixel-text"><?php echo $monster->getHp(); ?> / <?php echo $monsterDef['hp']; ?> HP</div>
                </div>
            </div>


            <?php $heroMax = $heroEntity->getHp(); ?>
            <div id="hero-area" class="flex flex-col items-center" data-max="<?php echo $heroMax; ?>">
                <div class="text-2xl font-bold mb-2"><?php echo htmlspecialchars($hero->getName()); ?></div>
                <img src="../assets/img/hero.png" alt="hero" class="w-48 h-48 object-contain">
                <div class="w-full mt-2" style="max-width:260px;">
                    <div class="bar-outer">
                        <div id="hero-bar" class="bar-fill hp-fill" style="width:<?php echo max(0, min(100, (int)($_SESSION['hero_hp'] / $heroMax * 100))); ?>%;"></div>
                    </div>
                    <div id="hero-hp" class="mt-2 small-pixel-text"><?php echo $hero->getHp(); ?> / <?php echo $heroMax; ?> HP</div>

                    <!-- thin XP bar under HP -->

                </div>
            </div>
        </div>

        <div class="controls" style="display:grid; grid-template-columns: 1fr 1fr; gap:18px; align-items:start;">
            <div class="control-col" style="display:flex; flex-direction:column; gap:12px;">
                <div class="select-pixel">
                    <select id="hero-select" class="bg-[#0e1722] text-white p-2">
                        <?php foreach ($heroesList as $h): ?>
                            <option value="<?php echo $h['id']; ?>" <?php echo (isset($_SESSION['hero_id']) && (int)$_SESSION['hero_id'] === (int)$h['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($h['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button id="choose-hero-btn" class="ut-btn orange small">CHANGE</button>
            </div>

            <div class="control-col" style="display:flex; flex-direction:column; gap:12px;">
                <div class="select-pixel">
                    <select id="attack-select" class="bg-[#0e1722] text-white p-2">
                        <?php foreach ($skillsList as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo (isset($_SESSION['hero_id']) && (int)$_SESSION['hero_id'] === (int)$s['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['skill_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div  style="display:flex; gap:12px; align-items:center; justify-content:center;">
                    <button id="attack-btn" class="ut-btn orange small">FIGHT</button>
                    <button id="reset-btn" class="ut-btn orange small">KILL ANOTHER MONSTER</button>
                </div>
            </div>
        </div>

        <!-- legacy msg (hidden) -> use dialogue box above -->
        <div id="msg" class="hidden pt-14 bg-[#07101a] p-3 rounded h-12 items-center justify-center text-sm text-yellow-200"></div>
        <form action="../process/explose_session.php" class="flex flex-col justify-center ">
            <button type="submit" class="btn-pixel" style="border-color:#ff3b3b; color:#ff3b3b;">RETOURNER A LA PAGE DE CREATION D'HÉRO . . . </button>
        </form>

    </div>

    <script>
        const attackBtn = document.getElementById('attack-btn');
        const resetBtn = document.getElementById('reset-btn');
        const heroHpEl = document.getElementById('hero-hp');
        const monsterHpEl = document.getElementById('monster-hp');
        const heroBar = document.getElementById('hero-bar');
        const monsterBar = document.getElementById('monster-bar');
        const msgEl = document.getElementById('msg');
        const dialogueTextEl = document.getElementById('dialogue-text');
        const monsterArea = document.getElementById('monster-area');
        const heroArea = document.getElementById('hero-area');
        const monsterMax = parseInt(monsterArea.getAttribute('data-max'), 10) || 100;
        let heroMax = parseInt(heroArea.getAttribute('data-max'), 10) || 100;
        const sounds = {
            attack: new Audio('../assets/sounds/attack.mp3'),
            hit: new Audio('../assets/sounds/hit.mp3'),
            victory: new Audio('../assets/sounds/victory.mp3'),
            death: new Audio('../assets/sounds/death.mp3')
        };

        function playSound(sound) {
            sound.currentTime = 0; // permet de rejouer rapidement
            sound.play();
        }
        const heroName = <?php echo json_encode($hero->getName()); ?>;

        // Hero HUD state (initialized from server-side entity)
        let heroLv = <?php echo json_encode($heroEntity->getLv()); ?>;
        let heroXp = <?php echo json_encode($heroEntity->getXp()); ?>;
        const HERO_XP_MAX = 500;
        const hudTopRightEl = document.getElementById('hud-top-right');
        const heroThinXpEl = document.getElementById('hero-thin-xp');
        const heroXpTextEl = document.getElementById('hero-xp-text');

        function refreshHeroHudFromVars() {
            if (hudTopRightEl) {
                hudTopRightEl.innerHTML = '<div class="small-pixel-text hud-lv">Lv ' + heroLv + '</div>' +
                    '<div class="small-pixel-text hud-xp">XP ' + heroXp + ' / ' + HERO_XP_MAX + '</div>';
            }
            if (heroThinXpEl) {
                heroThinXpEl.style.width = Math.max(0, Math.min(100, Math.round(heroXp / HERO_XP_MAX * 100))) + '%';
            }
            if (heroXpTextEl) {
                heroXpTextEl.textContent = heroXp + ' / ' + HERO_XP_MAX;
            }
        }

        // populate initial HUD
        refreshHeroHudFromVars();

        function showMsg(text, duration = 3000) {
            // display in the dialogue box
            if (dialogueTextEl) {
                dialogueTextEl.textContent = text;
            }
            // also fallback to old msg element briefly
            if (msgEl) {
                msgEl.textContent = text;
            }
            setTimeout(() => {
                if (dialogueTextEl) dialogueTextEl.textContent = '';
                if (msgEl) msgEl.textContent = '';
            }, duration);
        }

        attackBtn.addEventListener('click', async () => {
            attackBtn.disabled = true;



            try {
                const form = new FormData();
                form.append('action', 'attack');

                const resp = await fetch('', {
                    method: 'POST',
                    body: form
                });
                if (!resp.ok) throw new Error('network');
                const data = await resp.json();

                monsterHpEl.textContent = data.monster_hp + ' / ' + monsterMax + ' HP';
                monsterBar.style.width = Math.max(0, Math.min(100, Math.round(data.monster_hp / monsterMax * 100))) + '%';

                // update hero displays if backend returned updated values
                if (typeof data.hero_hp !== 'undefined') {
                    const newMax = data.hero_max || heroMax;
                    // update hero max and current
                    heroMax = newMax;
                    document.getElementById('hero-area').setAttribute('data-max', heroMax);
                    heroHpEl.textContent = data.hero_hp + ' / ' + heroMax + ' HP';
                    heroBar.style.width = Math.max(0, Math.min(100, Math.round(data.hero_hp / heroMax * 100))) + '%';
                }

                if (typeof data.hero_lv !== 'undefined') {
                    heroLv = data.hero_lv;
                    heroXp = data.hero_xp || heroXp;
                    refreshHeroHudFromVars();
                }

                const monsterName = data.monster_name || 'Le monstre';
                showMsg(heroName + ' attaque !', 1500);
                playSound(sounds.attack);


                if (data.monster_dead) {
                    showMsg('Vous avez buter  ' + monsterName + ' !', 3000);
                    attackBtn.disabled = true;
                    playSound(sounds.victory);
                    showMsg('Victoire! ' + '+ ' + data.hero_xp + ' XP !', 3000);


                    return;

                }


                setTimeout(() => {
                    showMsg(monsterName + ' vous a contre-attaqué !', 1500);
                    playSound(sounds.hit);
                }, 1500);


                setTimeout(async () => {
                    try {
                        const form2 = new FormData();
                        form2.append('action', 'counter');
                        const resp2 = await fetch('', {
                            method: 'POST',
                            body: form2
                        });
                        if (!resp2.ok) throw new Error('network2');
                        const data2 = await resp2.json();

                        heroHpEl.textContent = data2.hero_hp + ' / ' + heroMax + ' HP';
                        heroBar.style.width = Math.max(0, Math.min(100, Math.round(data2.hero_hp / heroMax * 100))) + '%';

                        if (data2.hero_dead) {
                            playSound(sounds.death);
                            showMsg('Vous avez été vaincu..', 999999);
                            attackBtn.disabled = true;
                        } else {
                            attackBtn.disabled = false;
           
                        }
                    } catch (e) {
                        showMsg('ya un problem', 3000);
                        attackBtn.disabled = false;
                    }
                }, 3000);

            } catch (e) {
                showMsg('ya un problem', 3000);
                attackBtn.disabled = false;
                attackBtn.textContent = 'Attaquer';
            }
        });

        resetBtn.addEventListener('click', async () => {

            const form = new FormData();
            form.append('action', 'reset');
            await fetch('', {
                method: 'POST',
                body: form
            });

            location.reload();
        });

        const chooseHeroBtn = document.getElementById('choose-hero-btn');
        const heroSelect = document.getElementById('hero-select');
        chooseHeroBtn.addEventListener('click', async () => {
            chooseHeroBtn.disabled = true;
            const form = new FormData();
            form.append('action', 'select_hero');
            form.append('hero_id', heroSelect.value);
            const resp = await fetch('', {
                method: 'POST',
                body: form
            });
            const data = await resp.json();
            if (data.selected) {
                document.querySelector('#hero-area .text-2xl').textContent = data.hero_name;
                // Met à jour le max du héros (base HP) et recalcule la barre
                document.getElementById('hero-area').setAttribute('data-max', data.hero_hp);
                heroMax = parseInt(document.getElementById('hero-area').getAttribute('data-max'), 10) || heroMax;
                heroHpEl.textContent = data.hero_hp + ' / ' + heroMax + ' HP';
                heroBar.style.width = Math.max(0, Math.min(100, Math.round(data.hero_hp / heroMax * 100))) + '%';
                // update HUD with LV/XP returned from server
                if (typeof data.hero_lv !== 'undefined') {
                    heroLv = data.hero_lv;
                }
                if (typeof data.hero_xp !== 'undefined') {
                    heroXp = data.hero_xp;
                }
                refreshHeroHudFromVars();
            } else {
                showMsg('Sélection invalide', 2000);
            }
            chooseHeroBtn.disabled = false;
        });

        // Persist green-grid animation progress across reloads
        (function syncGridAnimation() {
            try {
                const el = document.querySelector('.green-grid');
                if (!el) return;
                const KEY = 'gridAnimStart_v1';
                const DURATION = 8000; // match CSS: gridMove 8s linear infinite
                const now = Date.now();
                let start = localStorage.getItem(KEY);
                if (!start) {
                    localStorage.setItem(KEY, String(now));
                    start = String(now);
                }
                const elapsed = now - Number(start);
                // Use negative animationDelay to offset the animation so it looks continuous
                const delay = -(elapsed % DURATION);
                el.style.animationDelay = delay + 'ms';
                // Force style flush
                el.getBoundingClientRect();
            } catch (e) {
                // silent fail - animation will just start normally
                console.warn('grid sync failed', e);
            }
        })();
    </script>

</body>

</html>