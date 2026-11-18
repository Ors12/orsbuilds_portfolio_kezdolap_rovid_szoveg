# orsbuilds – Vercel-es üzemeltetés

```
public/        statikus oldal (ez kerül nyilvánosan kiszolgálásra)
api/           Vercel Functions: submit (űrlapok + foglalás), availability, admin-auth
lib/           közös szerveroldali kód (nem érhető el kívülről)
legacy-php/    a korábbi PHP-s háttér, csak referenciaként; Vercelen nem fut és nem is publikus
```

## Szükséges környezeti változók (Vercel → Project → Settings → Environment Variables)

| Változó | Jelentés |
|---|---|
| `ORSBUILDS_SMTP_HOST` | pl. `smtp.gmail.com` |
| `ORSBUILDS_SMTP_USERNAME` | pl. `info.orsbuilds@gmail.com` |
| `ORSBUILDS_SMTP_PASSWORD` | Google alkalmazásjelszó (szóközök nélkül) |
| `ORSBUILDS_SMTP_FROM` | feladó (alapból a felhasználónév) |
| `ORSBUILDS_SMTP_PORT` / `ORSBUILDS_SMTP_ENCRYPTION` | alapból `587` / `tls` (`465` esetén `ssl`) |
| `ORSBUILDS_ADMIN_PASSWORD_HASH` | `npm run hash-password -- "jelszavad"` kimenete |
| `ORSBUILDS_SESSION_SECRET` | hosszú véletlen szöveg az admin munkamenethez (nem kötelező, de ajánlott) |
| `BLOB_READ_WRITE_TOKEN` | a Vercel Blob tár csatlakoztatásakor automatikusan létrejön |

## Foglalások tárolása
Privát Vercel Blob tárban: `bookings/records/<dátum>/<azonosító>.json` (a teljes foglalás) és
`bookings/slots/<dátum>/<HHMM>.json` (foglalt 30 perces cellák, ezek biztosítják, hogy két ember ne foglalhassa le ugyanazt).
Minden foglalásról e-mail is megy az `info.orsbuilds@gmail.com` címre.

## Tesztek
`npm test` (a foglalási logikát és az admin munkamenetet ellenőrzi).
