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
    'version' => 'CMS :version',
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
    'language' => 'Jezik',
    'user' => [
        'menu' => 'Korisnički meni',
        'security' => 'Dvostepena provera',
        'logout' => 'Odjava',
        'roles' => [
            'super-admin' => 'Super administrator',
            'admin' => 'Administrator',
            'editor' => 'Urednik',
            'member' => 'Član',
        ],
        'no_role' => 'Bez uloge',
    ],

    'activity' => [
        'auth' => [
            'login' => 'Prijava',
            'login_failed' => 'Neuspela prijava',
            'logout' => 'Odjava',
            'lockout' => 'Previše pokušaja prijave',
        ],
        'registration' => ['created' => 'Registracija'],
        'user' => [
            'created' => 'Korisnik kreiran',
            'deactivated' => 'Korisnik deaktiviran',
            'approved' => 'Registracija odobrena',
        ],
        'invitation' => ['sent' => 'Pozivnica poslata', 'accepted' => 'Pozivnica prihvaćena'],
        'password' => [
            'changed' => 'Lozinka promenjena',
            'reset_requested' => 'Zatraženo resetovanje lozinke',
            'reset_completed' => 'Lozinka resetovana',
        ],
        'email' => ['verified' => 'E-pošta potvrđena'],
        'session' => ['others_revoked' => 'Odjava sa drugih uređaja'],
        'two_factor' => [
            'setup_started' => 'Počelo podešavanje dvostepene provere',
            'enabled' => 'Dvostepena provera uključena',
            'disabled' => 'Dvostepena provera isključena',
            'failed' => 'Odbijen kod dvostepene provere',
            'recovery_code_used' => 'Iskorišćen rezervni kod',
            'recovery_codes_regenerated' => 'Novi rezervni kodovi',
            'reset' => 'Dvostepena provera resetovana',
        ],
        'role' => ['attached' => 'Uloga dodeljena', 'detached' => 'Uloga uklonjena'],
        'permission' => ['attached' => 'Dozvola dodeljena', 'detached' => 'Dozvola uklonjena'],
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
