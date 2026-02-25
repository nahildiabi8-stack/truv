<?php

declare(strict_types=1);
require '../src/Entities/Hero.php';
require '../src/Repositories/HeroesRepository.php';

// Connexion à la DB
$pdo = new PDO('mysql:host=localhost;dbname=d', 'root', '');
$repository = new HeroRepository($pdo);

// Récupérer les données du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $hp   = (int)$_POST['hp'];
    $atk  = (int)$_POST['atk'];


    // Créer l'entité Hero
    $hero = new Hero($name, $hp, $atk);

    // Sauvegarder en base de données
    $repository->save($hero);

    header("Location: ../public/creation.php");
}

