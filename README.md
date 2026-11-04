# Illuminate Challenge

My solution to the Bi-Tech Illuminate challenge. Everything runs as artisan commands.

## Setup

Needs PHP 8.3+, the `pdo_pgsql` extension and the `ssh` client.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Then put your challenge token in `.env`:

```env
BITECH_TOKEN=your-token
```

## Commands

| Command | Stage | What it does |
|---|---|---|
| `php artisan illuminate:hunt` | 2 | Opens the SSH tunnel and searches every column of every table for `ILLUMINATE{...}` |
| `php artisan illuminate:import --fresh` | 3 | Copies neighborhoods and incidents from the remote PostgreSQL into local SQLite |
| `php artisan illuminate:donut [NB-7A2F] [--details]` | 3 | Prints the codes of the incidents in the donut, nearest first |
| `php artisan illuminate:submit-repo <repo-url> <cv.pdf>` | final | Sends the repo URL and CV |

Stages 0 and 1 did not need code. Stage 0 was in the source of the `bitech/illuminate` CLI. Stage 1 was the md5 of
`Illuminate/Support/Traits/ForwardsCalls.php` at v10.14.0.

## How it works

### Remote database (Stage 2)

The database is only reachable from the server itself, so `RemoteDatabase`:

1. gets the SSH key and DB credentials from the API (`ChallengeApi`)
2. starts `ssh -N -L 15433:localhost:5432 ...` in the background and waits until the port answers
3. registers it as a normal Laravel connection (`illuminate_remote`), so the commands use `DB` / the query builder
4. always stops the tunnel and deletes the key file at the end, even on errors (`try/finally`)

### Import (Stage 3)

- Postgres sends `point` and `polygon` as text like `(33.33,44.35)`. `PostgresGeometry` turns them into `[lat, lng]` arrays.
- The incident code comes from `metadata.incident.code`.
- The centroid is calculated once at import time and stored in the table.
- The import uses chunks, upserts and one transaction, so running it twice is safe.

### The `Donut` relation

`Neighborhood::incidents()` returns `App\Relations\Donut`, which extends Laravel's base `Relation` class.
There is no foreign key between the two tables. The link is distance, so none of the built-in relations fit.

- **SQL step:** only load incidents inside a square around the outer circle (`whereBetween` on lat/lng).
- **PHP step:** calculate the exact distance and keep `0.5 km < distance <= 2 km`, sorted nearest first.
  SQLite has no `cos`/`sqrt` by default, so this part is in PHP.
- Works with `$hood->incidents`, `$hood->incidents()->get()` and eager loading `Neighborhood::with('incidents')`.
  Eager loading runs one query for all neighborhoods, and `match()` gives each one only its own ring.

### The trap: which centroid?

My first try used the area centroid of the polygon. That gave extra letters before the flag. NB-7A2F is
a trapezoid, so different "center" definitions give points about 100 m apart. The incidents also have a
`classification` field, `alpha-N` or `noise-N`, which made it easy to check:

| Center used | Result |
|---|---|
| Average of the vertices | `OWUVPALCMGTWVKINATE{...}` |
| Area centroid | `VLGREDOLAPVWUWTGCKVMINATE{...}` |
| **Middle of the bounding box** | `ILLUMINATE{centroid_of_chaos_33916059}` |

Measured from the bounding-box center, the `noise` incidents sit at 0.45 to 0.50 km, inside the hole.
The `alpha` ones are evenly spaced from 0.54 to 1.96 km. So the data was clearly built around that point,
and I use it as the centroid.

Distance uses a flat approximation: 1° = 111 km, with longitude scaled by `cos(lat)`. At 2 km the
difference from Haversine is under 0.2%.

## Tests

```bash
php artisan test
```

They cover point/polygon parsing, the centroid, the ring filter and ordering, and eager loading.
