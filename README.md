# Illuminate Challenge

My solution to the Bi-Tech Illuminate challenge. Everything runs as artisan commands.

## Requirements

- PHP 8.3+ with the `pdo_sqlite` and `pdo_pgsql` extensions
- Composer
- The `ssh` command

## How to run

**1. Install**

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

**2. Add your token** to `.env`

```env
BITECH_TOKEN=your-token
```

**3. Stage 2: find the flag in the remote database**

```bash
php artisan illuminate:hunt
```

**4. Stage 3: import the data, then get the flag from the donut**

```bash
php artisan illuminate:import --fresh
php artisan illuminate:donut
```

Add `--details` to see every incident with its distance:

```bash
php artisan illuminate:donut NB-7A2F --details
```

**5. Submit the repo and CV**

```bash
php artisan illuminate:submit-repo https://github.com/<user>/<repo> /path/to/cv.pdf
```

**Run the tests**

```bash
php artisan test
```

## Stages 0 and 1

No code was needed:

- **Stage 0:** the `illuminate` CLI showed "Something Went Wrong". The flag was in a comment in its source code.
- **Stage 1:** the trait Laravel uses to forward method calls is `ForwardsCalls`. The answer was the md5 of
  `src/Illuminate/Support/Traits/ForwardsCalls.php` at tag `v10.14.0`.

## How it works

### Connecting to the remote database (Stage 2)

The database only accepts connections from inside the server, so `RemoteDatabase`:

1. gets the SSH key and DB login from the API (`ChallengeApi`)
2. opens an SSH tunnel: local port `15433` goes to the server's `localhost:5432`
3. adds it as a normal Laravel connection, so the code can use `DB` and the query builder
4. closes the tunnel and deletes the key file when done, even if something fails

`illuminate:hunt` then searches every column of every table for `ILLUMINATE{`.

### Importing (Stage 3)

`illuminate:import` copies the remote tables into SQLite in chunks of 500 rows.

- Postgres sends `point` and `polygon` as text, e.g. `(33.33,44.35)`. `PostgresGeometry` turns that into numbers.
- The incident code is in `metadata.incident.code`.
- The centroid of each neighborhood is calculated once here and saved.
- It uses `updateOrCreate` inside a transaction, so running it again is safe.

### The `Donut` relation

```php
// app/Models/Neighborhood.php
public function incidents(): Donut
{
    return new Donut($this, innerKm: 0.5, outerKm: 2.0);
}
```

`Donut` extends Laravel's base `Relation` class, the same one `HasOne` and `BelongsToMany` extend.
The two tables have no foreign key. They are linked by distance, so a custom relation is needed.

It works in two steps:

1. **SQL:** only load incidents inside a square around the outer circle. This skips far-away rows.
2. **PHP:** calculate the real distance, keep `0.5 km < distance <= 2 km`, and sort nearest first.

All of these work and return the same result:

```php
$neighborhood->incidents;
$neighborhood->incidents()->get();
Neighborhood::with('incidents')->get(); // eager loading, one query for all neighborhoods
```

### The trap: which centroid?

My first try used the area centroid of the polygon, and the result had extra letters before the flag.
NB-7A2F is not a rectangle, so different ways of finding its "center" give different points.

Each incident has a `classification` that is either `alpha-N` or `noise-N`, so I compared the options:

| Center | Result |
|---|---|
| Average of the corners | `OWUVPALCMGTWVKINATE{...}` |
| Area centroid | `VLGREDOLAPVWUWTGCKVMINATE{...}` |
| **Middle of the bounding box** | `ILLUMINATE{centroid_of_chaos_33916059}` |

Measured from the middle of the bounding box, the `noise` incidents are all between 0.45 and 0.50 km,
inside the hole. The `alpha` incidents are evenly spaced from 0.54 to 1.96 km. So the data was built
around that point.

Distance uses a simple flat formula: 1° ≈ 111 km, with longitude scaled by `cos(latitude)`. For a few
km this is accurate enough.

## Project structure

```
app/
├── Console/Commands/
│   ├── HuntRemoteFlag.php     illuminate:hunt
│   ├── ImportGisData.php      illuminate:import
│   ├── RevealDonutFlag.php    illuminate:donut
│   └── SubmitRepo.php         illuminate:submit-repo
├── Models/
│   ├── Neighborhood.php
│   └── Incident.php
├── Relations/
│   └── Donut.php              the custom relation
├── Services/Illuminate/
│   ├── ChallengeApi.php       API calls
│   └── RemoteDatabase.php     SSH tunnel + DB connection
└── Support/
    ├── Geo.php                distance, centroid, bounding box
    └── PostgresGeometry.php   parse point / polygon text
```
