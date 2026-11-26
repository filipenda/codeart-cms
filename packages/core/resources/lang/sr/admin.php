<?php

/* Tekstovi administracije (okvir, meni, tabla). sr-Cyrl se generiše: php artisan lang:transliterate */
return [
    'menu' => [
        'dashboard' => 'Kontrolna tabla',
        'main' => 'Glavni meni',
        'toggle' => 'Otvori ili zatvori meni',
        'close' => 'Zatvori meni',
        'expand_group' => 'Prikaži ili sakrij podstavke',
    ],
    'version' => 'CMS v:version',
    'breadcrumb' => 'Putanja',
    'home' => 'Početna',

    'search' => [
        'placeholder' => 'Pretraga…',
        'title' => 'Brza pretraga',
        'label' => 'Pretražite stranice',
        'hint' => 'Strelice za izbor, Enter za otvaranje, Esc za zatvaranje.',
        'empty' => 'Nema rezultata.',
        'results' => ':count rezultata',
    ],
    'theme' => [
        'label' => 'Tema',
        'light' => 'Svetla',
        'dark' => 'Tamna',
        'auto' => 'Automatska',
    ],
    'language' => 'Jezik panela',
    'user' => [
        'menu' => 'Korisnički meni',
        'security' => 'Dvostepena provera',
        'logout' => 'Odjavi se',
        'roles' => [
            'super-admin' => 'Super administrator',
            'admin' => 'Administrator',
            'editor' => 'Urednik',
            'member' => 'Član',
        ],
        'no_role' => 'Bez uloge',
    ],

    'dashboard' => [
        'title' => 'Kontrolna tabla',
        'welcome' => 'Dobro došli, :name.',
        'welcome_anonymous' => 'Dobro došli.',
        'empty_title' => 'Još nema šta da se prikaže',
        'empty_text' => 'Za vaš nalog trenutno nema dostupnih modula na kontrolnoj tabli.',
    ],
    'widgets' => [
        'recent_activity' => 'Nedavne aktivnosti',
        'system_status' => 'Status sistema',
        'quick_actions' => 'Brze akcije',
        'no_activity' => 'Još nema zabeleženih aktivnosti.',
        'by' => 'Izvršio: :name',
        'system' => 'Sistem',
        'check' => [
            'version' => 'Verzija CMS-a',
            'php' => 'PHP',
            'database' => 'Baza podataka',
            'storage' => 'Skladište (storage)',
        ],
        'ok' => 'U redu',
        'fail' => 'Greška',
        'error_title' => 'Ovaj prikaz trenutno nije dostupan',
        'error_text' => 'Došlo je do greške. Ostatak table radi normalno, a greška je zabeležena.',
    ],
];
