# The demo site

A throw-away HiveLog site for **App Review**, TestFlight and demonstrations of the Vinculum app
(task 0209). It runs on [DDEV](https://ddev.com): Drupal 11, `hivelog`, `hivelog_api` (with its
OAuth server), `nanoprobe` and `assimilate`, filled with invented records owned by a **reviewer**
account, and put back to that state by a reset. **It is not part of the HiveLog module** (it is
excluded from the package) and must never hold real data: `assimilate` refuses to install on a site
that has real AI-insight data, and this site's registration is closed.

## What a reviewer finds
One apiary on a heath near Copenhagen with three hives, each with an active queen (the first also
has a retired one); inspections over the last seven weeks, some with photos; queen observations;
seasonal jobs, two of them due now so the **Alerts** tab has something; and two mock sensors on the
first hive (a weight scale and a temperature and humidity probe) so a hive page's stat tiles
have values.

## On your own machine
Needs Docker (OrbStack works) and DDEV.

```sh
cd demo
ddev start                  # the first start asks for sudo once, to add hivelog-demo.ddev.site
ddev composer install
ddev demo-setup             # installs and seeds; prints where it put the passwords
ddev demo-info              # the address, the logins and the values for the review notes
```

`ddev demo-setup --fresh` rebuilds from nothing. `ddev demo-reset` puts the database and the
uploaded files back to the baseline `demo-setup` saved. The passwords are random and kept in
`.demo-secrets` (not committed); set `DEMO_REVIEWER_PASSWORD` and `DEMO_ADMIN_PASSWORD` before
`ddev demo-setup` to choose them.

Try it with the app: connect to `https://hivelog-demo.ddev.site` in a simulator (trust DDEV's
local certificate authority in the simulator first, as for any DDEV site), sign in as
`reviewer`, and go through `AppStore/review_notes.txt`.

## On a server
The reviewers need a public address with a certificate Apple's network trusts, so the site has to be
served by a real host name. DDEV can do this on a server (untested by me on a real server: check
`ddev config global --help` and DDEV's hosting documentation first):

1. A small Linux server with Docker and DDEV, and a DNS name (say `demo.example.org`) pointing at it.
2. Copy the `demo/` folder there. In `.ddev/config.yaml` set `additional_fqdns: [demo.example.org]`
   (or `ddev config --additional-fqdns=demo.example.org`).
3. Once: `ddev config global --use-letsencrypt --letsencrypt-email=you@example.org
   --router-bind-all-interfaces --use-hardened-images`, with ports 80 and 443 open.
4. `ddev start`, `ddev composer install`, `ddev demo-setup`.
5. Put `ddev demo-reset` in cron, for example nightly:
   `15 3 * * * cd /path/to/demo && /usr/local/bin/ddev demo-reset >> /var/log/demo-reset.log 2>&1`
   and `*/30 * * * * cd /path/to/demo && /usr/local/bin/ddev drush cron` so the mock sensors keep
   producing readings between resets.
6. `ddev demo-info` gives the values for `AppStore/en-GB/review_notes.txt`.

Keep it small and disposable. A reset signs everyone out (the OAuth tokens are in the database),
so sign in again afterwards.

## What is in here
| Path | |
|---|---|
| `composer.json` | The site's code: Drupal 11, Drush, `simple_oauth`, `geofield`, `leaflet` and the released `hivelog/hivelog` |
| `.ddev/config.yaml` | The DDEV project (PHP 8.4, MariaDB 11.8) |
| `.ddev/commands/web/` | `demo-setup`, `demo-reset`, `demo-info` |
| `scripts/seed.php` | The invented records, owned by the reviewer, validated as they are saved |
| generated, not committed | `web/`, `vendor/`, `private/` (the OAuth keys), `baseline/` (the reset's copy), `.demo-secrets` |

The seed runs as user 1 because creating a child record needs update access to its parent, and
assigns each record to the reviewer: the app only shows what its signed-in user owns, and
`assimilate`'s own records have no owner until this script gives them one.
