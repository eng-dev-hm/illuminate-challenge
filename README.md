# Illuminate Challenge

Bi-Tech Illuminate challenge, done as artisan commands.

## Setup

You need PHP 8.3+ with pdo_sqlite and pdo_pgsql, and ssh.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Put the token in `.env`:

```
ILLUMINATE_TOKEN=your-token
```

## Running

```bash
# stage 2 - find the flag in the remote db
php artisan illuminate:hunt

# stage 3 - import the data then read the flag from the donut
php artisan illuminate:import --fresh
php artisan illuminate:donut            # add --details to see distances

# submit
php artisan illuminate:submit-repo https://github.com/<user>/<repo> cv.pdf

# tests
php artisan test
```

Stage 0 was in the source of the illuminate CLI (a comment in DatabaseConnector).
Stage 1 was the md5 of `ForwardsCalls.php` from laravel/framework v10.14.0.

## Notes

**Remote db.** Postgres is only reachable from the server, so `RemoteDatabase` gets the ssh key from the API,
opens a tunnel (`ssh -L 15433:localhost:5432`) and registers it as a normal Laravel connection.
The tunnel and the key file are cleaned up in a `finally`.

**Import.** Reads the remote tables in chunks and saves them with `updateOrCreate` in a transaction.
Points and polygons come as text like `(33.33,44.35)`, so `PostgresGeometry` parses them.
The centroid is calculated once here and stored.

**Donut relation.** `Neighborhood::incidents()` returns `App\Relations\Donut`, which extends `Relation`.
There's no foreign key, incidents belong to a neighborhood by distance only.

- SQL first narrows it down to a box around the outer circle
- then PHP calculates the distance, keeps 0.5 < d <= 2 km and sorts by distance

`$n->incidents`, `$n->incidents()->get()` and `Neighborhood::with('incidents')` all work.
I had to override `get()` because the base `Relation::get()` goes straight to the query and skips the filter.

**Centroid.** This took the longest. The area centroid and the average of the corners both gave
extra letters before the flag. Looking at the data, incidents are tagged `alpha-N` (real) and `noise-N`,
and from the middle of the bounding box all the noise ones are at 0.45-0.50 km (inside the hole) and the
alpha ones are spread evenly from 0.54 to 1.96 km. So I used the bounding box center.

Distance is a flat approximation (1 degree = 111 km, lng scaled by cos(lat)), fine for a few km.
