# GPS Travel Tracker (Laravel)

A Laravel REST API that receives GPS location updates from a mobile app (or a simulator), calculates the distance each user travelled in a day, and shows the result as a report with a map and CSV/Excel export.

> Laravel cannot read a user's live location by itself. A mobile app or device must send location updates to the API. This project includes a simulator (`php artisan gps:simulate`) that generates realistic GPS data, including bad data, so the whole flow can be tested without a phone.

## Features

- Token login (Laravel Sanctum) with admin and normal users
- GPS ingestion API (single point or batch of up to 500), with validation and duplicate protection
- Distance calculation with Haversine, with filters for inaccurate points, unrealistic jumps, GPS drift and long gaps
- Full-day user report: first/last location, total distance (km), travel duration, route and paginated location history
- All-users summary with filters (date, user, min/max distance) and pagination
- Map-based report (Leaflet + OpenStreetMap)
- CSV and Excel export
- Unit  tests, Postman collection with documentation

## Tech stack

| Part | Choice |
|---|---|
| Framework | Laravel |
| Database | MySQL (tests use in-memory SQLite) |
| Auth | Laravel Sanctum (Bearer tokens) |
| Export | maatwebsite/excel |
| Tests | PHPUnit via `php artisan test` |

## Requirements

- PHP 8.2 or higher with extensions: `pdo_mysql`, `pdo_sqlite`, `sqlite3`, `mbstring`, `zip`, `gd`
- Composer
- MySQL 8 (or MariaDB 10.6+)

`zip` and `gd` are needed by the Excel export. On Windows, enable them in `php.ini` by removing the `;` before `extension=zip` and `extension=gd`.

## Setup

```bash
# 1. Install dependencies
composer install

# 2. Environment file
cp .env.example .env
php artisan key:generate
```

Create an empty MySQL database (for example `gps_tracker`) and set it in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gps_tracker
DB_USERNAME=root
DB_PASSWORD=
```

```bash
# 3. Create tables and seed users
php artisan migrate --seed

# 4. Generate one day of simulated GPS data for all normal users (today, IST)
php artisan gps:simulate

# 5. Start the server
php artisan serve
```

The API is now available at `http://127.0.0.1:8000/api` and the map report at `http://127.0.0.1:8000/report`.

### Seeded users

All passwords are `password`.

| Email | Role |
|---|---|
| `admin@example.com` | Admin (can see every user's report) |
| `user1@example.com` to `user4@example.com` | Normal users (can see only their own data) |

### Simulator options

```bash
php artisan gps:simulate                       # today, all normal users
php artisan gps:simulate --date=2026-11-23     # a specific date (IST)
php artisan gps:simulate --user=2              # only one user id
```

The simulator creates a day with three moving legs and two stops (about 30 second interval) and **deliberately adds bad data** so the filters can be verified:

| Injected problem | Expected result in the report |
|---|---|
| One point about 55 km away (teleport) | Rejected as `unrealistic_speed` |
| One point with 250 m accuracy | Rejected as `low_accuracy` |
| About 40 minutes of missing points | Reported as a gap, distance not added |
| 10 to 20 m jitter while standing still | Ignored as drift |

Running it twice for the same date does not create duplicates, because of the unique key on `(user_id, recorded_at)`.

## Running the tests

```bash
php artisan test                       # everything
php artisan test --testsuite=Unit
```

Tests run on in-memory SQLite (set in `phpunit.xml`), so your MySQL data is not touched.

What is covered:

- **Distance calculation:** Haversine accuracy, same point, summing segments, low accuracy, unrealistic jump, long gap, drift, unsorted input, duplicate timestamps, config thresholds
- **Validation:** coordinate ranges, accuracy, future and too-old timestamps, batch limit
- **Ingestion:** storing, duplicates (inside a batch and across requests), UTC conversion
- **Reports:** IST day boundaries, empty days, user isolation, pagination, summary filters
- **API (feature tests):** login, 401 without token, 403 for another user's report, admin access, validation errors (422), export

## API overview

All endpoints except `/api/login` need these headers:

```
Authorization: Bearer <token>
Accept: application/json
```

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/login` | Returns a token |
| GET | `/api/me` | Current user |
| POST | `/api/logout` | Revokes the current token |
| POST | `/api/locations` | Store one point or a batch (max 500) |
| GET | `/api/reports/users/{user}?date=` | Full-day report for one user |
| GET | `/api/reports/summary` | All-users summary with filters |
| GET | `/api/reports/export` | CSV or Excel download |
| GET | `/api/reports/map-based-route/{user}` | Users the current account may select (used by the map page) |

### Store locations

`POST /api/locations`

```json
{
  "latitude": 21.2514,
  "longitude": 81.6296,
  "accuracy": 12.5,
  "recorded_at": "2026-11-23T09:00:00Z"
}
```

A batch uses `{"locations": [ {...}, {...} ]}`. The user is always taken from the token, never from the request body.

| Field | Rule |
|---|---|
| `latitude` | required, -90 to 90 |
| `longitude` | required, -180 to 180 |
| `accuracy` | required, metres, 0 or more |
| `recorded_at` | required, ISO 8601, not more than 5 minutes in the future, not older than 7 days |

Response (`201`):

```json
{ "received": 1, "stored": 1, "duplicates": 0 }
```

The endpoint is rate limited to 120 requests per minute.

### Reports

`GET /api/reports/users/{user}?date=2026-11-23&page=1&per_page=50`

Returns distance, first and last location, duration, rejected-point statistics, gaps, `route` (for the map, items are `[lat, lng, gap_before]`) and a paginated `history`.

`GET /api/reports/summary?date=2026-11-23&user_id=2&min_distance=10&max_distance=50&page=1&per_page=15`

```json
{
  "data": [
    { "user_id": 2, "user": "User A", "date": "23-Nov-2026",
      "distance_km": 22.4, "first_location": "09:00 AM", "last_location": "06:15 PM" }
  ],
  "current_page": 1, "last_page": 1, "per_page": 15, "total": 1
}
```

`GET /api/reports/export?date=2026-11-23&format=csv` (or `format=xlsx`) uses the same filters as the summary.

### Access rules

| Who | Can do |
|---|---|
| Admin | See any user's report, all-users summary and export |
| Normal user | See only their own report. Summary and export are limited to their own row |
| No token | `401 Unauthorized` |
| Normal user asking for another user | `403 Forbidden` |

Full request and response examples are in the Postman collection (see below).

## Postman documentation


1. Run **1. Auth > Login (admin)**. The token is saved automatically in the `token` collection variable.
2. Change the `date` variable to a day you generated data for.
3. Run the other requests, or use **Run collection** to execute all tests.

The collection contains descriptions, saved example responses (200, 401, 403, 422) and test scripts for each request. Use **Send and Download** for the export requests.



## How the distance is calculated

### Method

Distance is the sum of the **Haversine (great-circle) distance between consecutive valid GPS points**, in kilometres, using an Earth radius of 6371.0088 km.

```
a = sin²(Δlat / 2) + cos(lat1) · cos(lat2) · sin²(Δlon / 2)
d = 2 · R · atan2(√a, √(1 − a))
```

The points of the day are sorted by time and walked once. Each point is compared with the **last trusted point (the anchor)**, not with the previous raw point. This matters: one bad point in the middle does not damage the two segments around it.

### Filters (in order)

| # | Check | Rule (default) | Result |
|---|---|---|---|
| 1 | Inaccurate point | `accuracy > 50 m` | Rejected (`low_accuracy`) |
| 2 | Time did not move forward | `seconds <= 0` | Rejected (`duplicate_time`) |
| 3 | Long gap | `seconds > 900` (15 min) | Distance **not** added, the gap is recorded and the point becomes the new start |
| 4 | Unrealistic jump | `speed > 150 km/h` | Rejected (`unrealistic_speed`) |
| 5 | GPS drift | `movement < max(30 m, accuracy of both points)` | Ignored, anchor stays where it is |
| 6 | Everything else | | Distance added, anchor moves |

Speed is `(meters / seconds) × 3.6`. The factor 3.6 converts metres per second to kilometres per hour.

All thresholds are in `config/gps.php`:

```php
'max_accuracy_m'  => 50,
'max_speed_kmh'   => 150,
'min_movement_m'  => 30,
'max_gap_seconds' => 900,
'timezone'        => 'Asia/Kolkata',
```

### How the problems from the brief are handled

| Problem | How it is handled |
|---|---|
| **GPS noise / drift** | Points with poor accuracy are rejected. Small movements (below 30 m or below the reported accuracy) are treated as standing still. The anchor does not move during drift, so jitter is not added up, while slow real movement still accumulates once it passes the threshold |
| **Unrealistic jumps** | Speed between the anchor and the new point is checked. A teleport of tens of kilometres in 30 seconds is rejected and does not add distance |
| **Long gaps / missing updates** | If two valid points are more than 15 minutes apart, a straight line across the gap would invent distance. It is **not counted**. The gap is returned in the report (`gaps`) and drawn as a dashed line on the map |

| **Duplicate points** | A unique database key on `(user_id, recorded_at)` plus `insertOrIgnore`. The API reports how many points were duplicates |
| **Unauthorized access** | Sanctum token on all endpoints, user id taken from the token, and a rule that only admins or the user themself can read a report |

Raw points are always stored, even bad ones. Filtering happens when the report is calculated, so thresholds can be tuned later without losing data.


### Project structure

```
app/
  Console/Commands/SimulateGps.php        simulator
  Exports/TravelReportExport.php          CSV / Excel
  Http/Controllers/Api/                   thin controllers (Auth, Location, Report)
  Http/Requests/                          Form Requests (Login, StoreLocation, ReportFilter)
  Models/                                 User, GpsLocation
  Services/
    DistanceCalculator.php                Haversine and filters (pure logic)
    LocationIngestService.php             storing points
    TravelReportService.php               day range, reports, summary
config/gps.php                            thresholds and timezone

```

Controllers only validate, authorize and call a service.

### Database

`gps_locations`: `user_id`, `latitude` (decimal 10,7), `longitude` (decimal 10,7), `accuracy` (float, metres), `recorded_at` (UTC), `is_valid`, `reject_reason`, timestamps.

| Index | Purpose |
|---|---|
| unique `(user_id, recorded_at)` | blocks duplicate points |
| index `(user_id, recorded_at)` | main report query: one user, one day |
| index `recorded_at` | all-users queries by date |

### Time zones

All timestamps are stored in **UTC**. A report "day" is calculated in IST (`Asia/Kolkata`): 21 Oct 00:00 to 23:59 IST is converted to the matching UTC range before querying. Times shown in reports are in IST.

### Pagination

The location history and the all-users summary are paginated (`page`, `per_page`).


## Troubleshooting

| Problem | Fix |
|---|---|
| `403` on a report | Use an admin token, or request your own user id. Check `/api/me` to see whose token you have |
| `422` on a report | `date` must be `YYYY-MM-DD` and not in the future |
| Report shows 0 km | Generate data for that date: `php artisan gps:simulate --date=YYYY-MM-DD` |
| Excel export fails | Enable `zip` and `gd` PHP extensions |
| Tests fail with "could not find driver" | Enable `pdo_sqlite` and `sqlite3` in `php.ini` |
| Map is blank | Check the internet connection (CDN assets) and the browser console |

