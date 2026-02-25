<!doctype html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../assets/output.css" rel="stylesheet">

    <style>
        body {
            font-family: "Courier New", Courier, monospace;
        }
    </style>
</head>

<body class="bg-[#030712] select-none">

    <form action="../process/create_hero.php" class="flex flex-col justify-center items-center" method="POST">

        <h1 class="text-3xl font-semibold underline pt-4 pb-4 text-[#6FCBF4]">
            Creation d'un héro
        </h1>

        <div class="flex flex-col justify-center items-center">

            <label for="name" class="pb-2 text-[#C1C8CD]">Nom de L'Héro</label>
            <input type="text" id="name" name="name" placeholder="Name" class="bg-[#1D202A] text-[#FB64B6] rounded w-70 h-10 pl-2 outline-none focus:outline-none focus:ring-0">

            <label for="hp" class="pb-2 pt-2 text-[#C1C8CD]">HP de L'Héro</label>
            <input type="text" id="hp" name="hp" placeholder="HP" class="bg-[#1D202A] text-[#FB64B6] rounded w-70 h-10 pl-2 outline-none focus:outline-none focus:ring-0">

            <label for="atk" class="pb-2 pt-2 text-[#C1C8CD]">Point de dégat de L'Héro</label>
            <input type="text" id="atk" name="atk" placeholder="ATK" class="bg-[#1D202A] text-[#FB64B6] rounded w-70 h-10 pl-2 outline-none focus:outline-none focus:ring-0">

            <button type="submit" class="mt-4 bg-[#1D202A] text-[#C3B26D] rounded w-70 h-10 hover:bg-[#262A36] cursor-pointer">
                Créer !
            </button>


        </div>

    </form>
    <form action="../process/explose_session_home.php" class="flex flex-col justify-center items-center pt-3">
        <button type="submit" class="mt-4 bg-[#1D202A] text-[#C3B26D] rounded w-80 h-10 hover:bg-[#262A36] cursor-pointer">
            Joue sans créer un héro
        </button>
    </form>




</body>

</html>