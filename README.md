# local_loginsync

Moodle plugin that triggers cohort synchronization and course enrolment instantly at OIDC login, eliminating the cron job delay.

---

## Problem it solves

In a standard Moodle + Microsoft Entra ID integration using `local_o365`, cohort synchronization runs on a scheduled cron job. This means a user who authenticates via OpenID Connect may not see their enrolled courses until the next cron cycle runs — which can take several minutes.

**This plugin solves that by executing the sync immediately when the user logs in.**

---

## How it works

```
User logs in via OIDC (Entra ID)
        │
        ▼
Moodle fires event: \core\event\user_loggedin
        │
        ▼
local_loginsync observer checks: auth = 'oidc'?
        │
   YES  │   NO → do nothing
        ▼
Execute \local_o365\task\cohortsync
        │
        ▼
Execute \enrol_cohort\task\enrol_cohort_sync
        │
        ▼
User sees their enrolled courses immediately
```

The plugin registers an **event observer** that listens for `user_loggedin`. When triggered, it checks if the user authenticated via OIDC — if so, it synchronously runs two tasks:

1. **`local_o365\task\cohortsync`** — syncs Microsoft Entra ID group membership to Moodle cohorts
2. **`enrol_cohort\task\enrol_cohort_sync`** — enrols cohort members into their mapped courses

---

## Requirements

| Requirement | Version |
|---|---|
| Moodle | 4.0 or higher (tested on 5.0.2) |
| auth_oidc | Any version compatible with your Moodle |
| local_o365 | Any version compatible with your Moodle |
| PHP | 8.0 or higher |

> This plugin does **not** work standalone. It requires `auth_oidc` and `local_o365` to be installed and configured.

---

## Installation

### Option A — Via Moodle Admin UI

1. Download `local_loginsync_v1.zip`
2. Go to: Site administration → Plugins → **Install plugins**
3. Upload the ZIP file
4. Complete the installation wizard

### Option B — Via CLI

```bash
# Copy plugin to Moodle local plugins directory
unzip local_loginsync_v1.zip -d /var/www/moodle/local/

# Fix permissions
chown -R www-data:www-data /var/www/moodle/local/loginsync

# Run upgrade
sudo -u www-data php /var/www/moodle/admin/cli/upgrade.php
```

---

## Plugin structure

```
local_loginsync/
├── version.php                     ← plugin metadata and Moodle version requirement
├── db/
│   └── events.php                  ← registers the event observer
├── lang/
│   └── en/
│       └── local_loginsync.php     ← required language strings
└── classes/
    └── observer.php                ← core logic executed on login
```

### Key files

**`db/events.php`** — registers the observer:
```php
$observers = [
    [
        'eventname' => '\core\event\user_loggedin',
        'callback'  => '\local_loginsync\observer::on_user_loggedin',
        'priority'  => 200,
        'internal'  => false,
    ],
];
```

**`classes/observer.php`** — executes sync only for OIDC users:
```php
public static function on_user_loggedin(\core\event\user_loggedin $event) {
    $user = $DB->get_record('user', ['id' => $event->userid], 'id, auth');

    if ($user->auth !== 'oidc') {
        return; // Only process OIDC authenticated users
    }

    // Sync Entra ID group membership to Moodle cohorts
    (new \local_o365\task\cohortsync())->execute();

    // Enrol cohort members into mapped courses
    (new \enrol_cohort\task\enrol_cohort_sync())->execute();
}
```

---

## Configuration

No additional configuration is required after installation. The plugin works automatically for any user whose `auth` field is set to `oidc`.

**Prerequisites that must be configured separately:**

- Microsoft Entra ID groups mapped to Moodle cohorts via `local_o365` → Cohort sync
- Courses configured with **Cohort enrolment** method pointing to the relevant cohort

Refer to the full integration guide for setup instructions:  
→ [Moodle + Entra ID Integration Guide](../moodle-entra-integration/README.md)

---

## Behavior notes

- Execution is **synchronous** — the sync runs during the login request. On large tenants with many groups this may add a small delay to the login process.
- Only affects users authenticated via `auth_oidc`. Users logging in with manual accounts or other auth methods are not affected.
- Errors in the sync tasks are caught and logged as debug messages. They do not interrupt the login flow.

---

## Compatibility

| Moodle version | Status |
|---|---|
| 5.0.x | ✅ Tested |
| 4.x | ✅ Compatible (not tested) |
| 3.x | ❌ Not supported |

---

## License

GNU GPL v3 or later — https://www.gnu.org/licenses/gpl-3.0.html

---

*LABCLOUDSOLUTIONS — cloudlab.cl*
