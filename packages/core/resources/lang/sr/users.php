<?php

/* Korisnici (docs/auth.md). sr-Cyrl se generiše: php artisan lang:transliterate */
return [
    'title' => 'Korisnici',
    'description' => 'Osobe koje mogu da uđu u administraciju.',
    'tab' => ['users' => 'Korisnici', 'invitations' => 'Pozivnice'],
    'pending_notice' => '{1} :count registracija čeka odobrenje.|[2,*] :count registracija čeka odobrenje.',
    'pending_show' => 'Prikaži',
    'col' => ['user' => 'Korisnik', 'role' => 'Uloga', 'status' => 'Status', 'two_factor' => 'Dvostepena provera', 'last_login' => 'Poslednja prijava', 'created' => 'Kreiran'],
    'status' => ['active' => 'Aktivan', 'pending' => 'Čeka odobrenje', 'blocked' => 'Blokiran', 'unverified' => 'E-pošta nepotvrđena', 'deleted' => 'Obrisan'],
    'roles' => ['super-admin' => 'Super administrator', 'admin' => 'Administrator', 'editor' => 'Urednik', 'member' => 'Član'],
    'filter' => ['deleted' => 'Obrisani', 'only_deleted' => 'Samo obrisani'],
    'action' => [
        'approve' => 'Odobri', 'reject' => 'Odbij', 'block' => 'Blokiraj', 'unblock' => 'Odblokiraj', 'delete' => 'Obriši',
        'restore' => 'Vrati', 'force_delete' => 'Obriši trajno', 'reset_2fa' => 'Resetuj dvostepenu proveru',
    ],
    'confirm' => [
        'approve' => 'Odobriti ovu registraciju? Osoba dobija poruku i može da se prijavi.',
        'approve_bulk' => 'Odobriti izabrane registracije?',
        'block' => 'Blokirati korisnika? Odmah se odjavljuje i ne može da se prijavi.',
        'block_bulk' => 'Blokirati izabrane korisnike? Redovi koje ne smete da menjate biće preskočeni.',
        'unblock' => 'Odblokirati korisnika?',
        'delete' => 'Obrisati korisnika? Nestaje sa liste i ne može da se prijavi, ali se može vratiti.',
        'delete_bulk' => 'Obrisati izabrane korisnike? Redovi koje ne smete da menjate biće preskočeni.',
        'restore' => 'Vratiti korisnika?',
        'force_delete' => 'Trajno obrisati korisnika? Ovo se ne može poništiti. Zapisi u dnevniku ostaju.',
        'reset_2fa' => 'Resetovati dvostepenu proveru? Korisnik je podešava ponovo pri sledećoj prijavi.',
    ],
    'edit' => [
        'details' => 'Podaci', 'save' => 'Sačuvaj', 'role' => 'Uloga', 'change_role' => 'Promeni ulogu', 'registration' => 'Registracija',
        'reject_reason' => 'Razlog odbijanja (opciono, ide u poruku osobi)', 'danger' => 'Opasne radnje', 'info' => 'Pregled',
        'email_hint' => 'Promena adrese poništava potvrdu e-pošte.',
        'danger_hint' => 'Ove radnje traže potvrdu i ponovni unos lozinke.',
    ],
    'msg' => [
        'updated' => 'Korisnik je sačuvan.', 'nothing_changed' => 'Ništa nije promenjeno.', 'role_changed' => 'Uloga je promenjena.',
        'blocked' => 'Korisnik je blokiran.', 'unblocked' => 'Korisnik je odblokiran.', 'deleted' => 'Korisnik je obrisan.',
        'restored' => 'Korisnik je vraćen.', 'force_deleted' => 'Korisnik je trajno obrisan.', 'two_factor_reset' => 'Dvostepena provera je resetovana.',
        'approved' => 'Registracija je odobrena.', 'already_approved' => 'Korisnik je već odobren.', 'rejected' => 'Registracija je odbijena.',
        'not_pending' => 'Ova registracija više ne čeka odobrenje.',
    ],
    'widget' => ['pending' => 'Čeka odobrenje', 'none' => 'Nema registracija na čekanju.', 'review' => 'Pregledaj'],
    'invite' => [
        'title' => 'Pozovi korisnika', 'description' => 'Osoba dobija poruku sa linkom da postavi lozinku. Ovo je jedini način da se doda nov korisnik.',
        'message' => 'Kratka poruka (opciono)', 'message_hint' => 'Prikazuje se u poruci pozivnice.', 'send' => 'Pošalji pozivnicu',
    ],
    'invitation' => [
        'description' => 'Pozivnice koje još nisu prihvaćene.', 'valid' => 'Važi', 'expired' => 'Isteklo', 'expires' => 'Ističe',
        'resend' => 'Pošalji ponovo', 'resend_confirm' => 'Poslati pozivnicu ponovo? Stari link prestaje da važi.',
        'revoke' => 'Opozovi', 'revoke_confirm' => 'Opozvati pozivnicu? Link prestaje da važi.',
        'sent' => 'Pozivnica je poslata.', 'resent' => 'Pozivnica je ponovo poslata.', 'revoked' => 'Pozivnica je opozvana.',
    ],
];
