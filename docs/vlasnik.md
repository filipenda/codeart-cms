# Vlasnik: kratak podsetnik

Sve komande se kucaju u terminalu, u glavnom folderu projekta (`codeart-cms`). Prvo pokrenite `ddev start`.

## 1. Provera koda
- Brza provera (dok se radi): `ddev composer check:fast`
  Proverava boje, stil koda, greske i samo testove koje je izmena dotakla. Rezultat se ispisuje u terminalu; "passed" / "No errors" znaci da je sve u redu.
- Puna provera (pre izdanja): `ddev composer check`
  Pokrece sve testove i provere. Rezultat u terminalu: na kraju mora pisati "passed", bez crvenih gresaka.

## 2. ZIP za klijenta
- `ddev exec "php tools/build-package.php --version=1.2.3"`
  Pravi instalacioni paket (promenite broj verzije). ZIP se pojavljuje u folderu `build/` (npr. `build/codeart-cms-1.2.3.zip`); u terminalu se ispisuju putanja i SHA-256. ZIP se nikad ne komituje.

## 3. Pregled instalatera u pregledacu
- Pokretanje: `ddev composer preview:install`
  Ispisuje adresu (`http://codeart-cms.ddev.site:8099/install`) i jednokratni kljuc za instalaciju. Vaša baza i `.env` ostaju netaknuti.
- Brisanje kad ste gotovi: `ddev composer preview:install:clean`
  Gasi pregled, brise privremeni folder i privremenu bazu.

## 4. Azuriranje paketa (dependencies)
- `ddev composer update` pa zatim `ddev composer check`
  Povlaci nove verzije paketa; menja se `composer.lock` (a potom proverite da nista nije puklo). Ako je sve zeleno, izmenu komitujte kroz granu i PR.

## 5. Izdanje (release)
- `main` nije produkcija. Izdanje je Git oznaka (tag) na `main`, tek kad je puna provera zelena:
  - proba na test sajtu: `git tag 1.2.0-rc.1 && git push origin 1.2.0-rc.1`
  - stabilno za klijente: `git tag 1.2.0 && git push origin 1.2.0`
  Oznaka se pojavljuje na GitHub-u (Tags). Zatim napravite ZIP (tacka 2) sa istim brojem verzije. Ove komande objavljuju, pa ih pokrecite samo kad ste sigurni.
