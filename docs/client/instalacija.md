# Instalacija sajta (vodič za klijenta)

Ovaj vodič je za instalaciju preko cPanel-a, bez komandne linije. Trebaće vam oko 15 minuta.

## Šta vam treba
- ZIP paket (dobijate od nas), npr. `codeart-cms-1.0.0.zip`
- cPanel pristup vašem hostingu
- PHP 8.4 ili noviji (ako nemate, vidite „Ako je nešto crveno“ ispod)

## 1. Napravite praznu bazu
1. U cPanel-u otvorite **MySQL® Databases**.
2. Napravite bazu (npr. `nalog_sajt`) i korisnika sa lozinkom.
3. Dodajte korisnika bazi i izaberite **All Privileges**.
4. Zapišite: ime baze, korisnika i lozinku. Imena obično počinju prefiksom vašeg naloga.

## 2. Otpremite i raspakujte paket
1. Otvorite **File Manager** i uđite u početni folder naloga (onaj u kome se nalazi folder `public_html`; nije unutar `public_html`).
2. Kliknite **Upload** i otpremite ZIP.
3. Desni klik na ZIP → **Extract**. Dobićete dva foldera:
   - `cms` – aplikacija (ostaje izvan `public_html`)
   - `public_html` – sadržaj koji se vidi na internetu (spaja se sa vašim `public_html`)
4. Obrišite ZIP.

Raspored mora da izgleda ovako:
```
/home/vasnalog/
    cms/            <- aplikacija
    public_html/    <- index.php, .htaccess ...
```
Ako vaš sajt koristi poddomen ili drugi folder (npr. `public_html/shop`), kopirajte sadržaj foldera `public_html` iz paketa tamo, otvorite `index.php` i u liniji `$applicationPath = __DIR__.'/../cms';` upišite pravu putanju do foldera `cms` (jedina linija koju menjate).

## 3. Pokrenite instalaciju
1. U pregledaču otvorite adresu vašeg sajta. Automatski ćete dobiti ekran za instalaciju.
2. **Instalacioni ključ:** nalazi se u fajlu `install.key`. U File Manager-u otvorite folder `cms`, otvorite `install.key` (desni klik → View/Edit) i prekopirajte tekst u polje.
3. **Provera servera:** zeleno je u redu. Ako je nešto crveno, pročitajte uputstvo ispod te stavke, ispravite i kliknite **Proveri ponovo**. Ne može se dalje dok je nešto crveno.
4. **Baza podataka:** upišite podatke iz koraka 1 (adresa je obično `localhost`) i kliknite **Testiraj vezu**. Poruka na ekranu kaže šta nije u redu (pogrešna lozinka, nepostojeća baza, nedovoljna prava...). Ako baza nije prazna, bićete upozoreni.
5. **Sajt:** naziv, adresa (već popunjena), vremenska zona, jezici i adresa administracije (npr. `/admin`).
6. **Administrator:** ime, prezime, e-pošta i lozinka. Ili kliknite **Generiši lozinku**: prikazuje se samo jednom, zapišite je odmah.
7. **Moduli:** označite koje module želite. Možete ih kasnije menjati.
8. **Instaliraj:** pratite spisak koraka. Ako neki ne uspe, piše šta da uradite; kliknite **Pokušaj ponovo**, instalacija nastavlja gde je stala.

## 4. Završetak
Na poslednjem ekranu su:
- veza za prijavu u administraciju (pri prvoj prijavi uključujete dvostepenu potvrdu),
- **cron linija** koju morate dodati.

Instalacioni ključ (`install.key`) se briše i čarobnjak se zaključava. Ako se ikada traži ponovna instalacija, to radi naša podrška.

### Cron (zakazani poslovi)
U cPanel-u otvorite **Cron Jobs**, izaberite **Once Per Minute** i u polje **Command** upišite liniju sa završnog ekrana. Izgleda ovako:
```
* * * * * php /home/vasnalog/cms/artisan schedule:run >> /dev/null 2>&1
```
(putanja je vaša stvarna putanja do foldera `cms`).

## Ako je nešto crveno
| Stavka | Šta da uradite |
|---|---|
| PHP verzija | cPanel → **MultiPHP Manager** → izaberite PHP 8.4 ili noviji za vaš domen |
| PHP dodatak (npr. intl, zip) | cPanel → **Select PHP Version** → **Extensions** → uključite navedeni dodatak |
| storage / bootstrap/cache nije upisiv | File Manager → desni klik na folder → **Change Permissions** → 775 (uključujući podfoldere) |
| Fajl .env se ne može upisati | folder `cms` mora biti upisiv (755 ili 775, vaš nalog kao vlasnik) |
| Raspored foldera | proverite da ste raspakovali ceo ZIP i da `index.php` pokazuje na folder `cms` |
| Memorija | cPanel → **MultiPHP INI Editor** → `memory_limit` = 256M |

Ako ni tada ne ide, pošaljite nam snimak ekrana sa spiskom.
