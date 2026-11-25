<?php

/*
 * Fallback texts for Laravel's own keys (auth.*, passwords.*, validation.*), used only when the app
 * has no translation of its own. Laravel 13 ships no lang files, so without these users would see raw keys.
 */
return [
    'auth' => [
        'failed' => 'Uneti podaci se ne poklapaju sa našom evidencijom.',
        'throttle' => 'Previše pokušaja. Pokušajte ponovo za :seconds sekundi.',
    ],
    'passwords' => [
        'reset' => 'Lozinka je resetovana.',
        'sent' => 'Ako je adresa e-pošte registrovana, poslali smo vezu za resetovanje.',
        'throttled' => 'Ako je adresa e-pošte registrovana, poslali smo vezu za resetovanje.',
        'token' => 'Veza za resetovanje lozinke nije važeća ili je istekla.',
        'user' => 'Veza za resetovanje lozinke nije važeća ili je istekla.',
    ],
    'validation' => [
        'required' => 'Polje :attribute je obavezno.',
        'string' => 'Polje :attribute mora biti tekst.',
        'email' => 'Polje :attribute mora biti ispravna adresa e-pošte.',
        'unique' => 'Vrednost polja :attribute je već zauzeta.',
        'confirmed' => 'Potvrda polja :attribute se ne poklapa.',
        'current_password' => 'Lozinka nije ispravna.',
        'max' => [
            'string' => 'Polje :attribute ne sme imati više od :max znakova.',
        ],
        'min' => [
            'string' => 'Polje :attribute mora imati najmanje :min znakova.',
        ],
        'password' => [
            'letters' => 'Polje :attribute mora sadržati bar jedno slovo.',
            'mixed' => 'Polje :attribute mora sadržati bar jedno veliko i jedno malo slovo.',
            'numbers' => 'Polje :attribute mora sadržati bar jedan broj.',
            'symbols' => 'Polje :attribute mora sadržati bar jedan simbol.',
            'uncompromised' => 'Vrednost polja :attribute se pojavila u curenju podataka. Izaberite drugu.',
        ],
    ],
];
