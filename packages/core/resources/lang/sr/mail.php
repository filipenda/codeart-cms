<?php

return [
    'button_help' => 'Ako dugme ne radi, kopirajte ovu vezu u pregledač:',

    'invitation' => [
        'subject' => 'Pozvani ste na :app',
        'heading' => 'Dobro došli na :app',
        'intro' => 'Zdravo :name, pozvani ste da se pridružite sistemu :app. Postavite lozinku da biste aktivirali nalog.',
        'note' => 'Poruka: :message',
        'expires' => 'Poziv se može iskoristiti samo jednom i važi :days dana.',
        'action' => 'Prihvati poziv',
    ],
    'reset_password' => [
        'subject' => 'Resetovanje lozinke',
        'heading' => 'Resetovanje lozinke',
        'intro' => 'Zdravo :name, primili smo zahtev za resetovanje lozinke vašeg naloga na :app.',
        'expires' => 'Ova veza važi :minutes minuta.',
        'ignore' => 'Ako niste vi tražili resetovanje, slobodno zanemarite ovu poruku; lozinka ostaje ista.',
        'action' => 'Izaberi novu lozinku',
    ],
    'verify_email' => [
        'subject' => 'Potvrdite adresu e-pošte',
        'heading' => 'Potvrdite adresu e-pošte',
        'intro' => 'Zdravo :name, potvrdite svoju adresu e-pošte za :app.',
        'ignore' => 'Ako niste vi napravili nalog, slobodno zanemarite ovu poruku.',
        'action' => 'Potvrdi adresu e-pošte',
    ],
    'registration_received' => [
        'subject' => 'Primili smo vašu registraciju',
        'heading' => 'Hvala na registraciji',
        'intro' => 'Zdravo :name, primili smo vašu registraciju na :app.',
        'approval' => 'Administrator će je pregledati. Dobićete poruku čim nalog bude odobren.',
    ],
    'registration_approved' => [
        'subject' => 'Vaš nalog je odobren',
        'heading' => 'Vaš nalog je odobren',
        'intro' => 'Zdravo :name, vaš nalog na :app je odobren. Sada se možete prijaviti.',
        'action' => 'Prijavi se',
    ],
    'registration_rejected' => [
        'subject' => 'Vaša registracija nije odobrena',
        'heading' => 'Registracija nije odobrena',
        'intro' => 'Zdravo :name, nažalost, vaša registracija na :app nije odobrena.',
        'reason' => 'Razlog: :reason',
    ],
    'registration_pending' => [
        'subject' => 'Nova registracija čeka odobrenje',
        'heading' => 'Registracija čeka odobrenje',
        'intro' => ':applicant (:email) se registrovao na :app i čeka odobrenje.',
        'action' => 'Pregledaj registracije',
    ],
    'test' => [
        'subject' => 'Probna poruka sa :app',
        'heading' => 'E-pošta radi',
        'intro' => 'Ovo je probna poruka sa :app. Ako je vidite, podešavanja e-pošte su ispravna.',
    ],
];
