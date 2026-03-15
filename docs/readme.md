# ElkArte 2.0 — Install & Upgrade Guide

Welcome, and thanks for using [ElkArte](https://www.elkarte.net/)!
If you run into trouble at any point, the community is at **https://www.elkarte.net/community/index.php**.

> [!IMPORTANT]
> **Upgrading from 1.x?** ElkArte 2.0 is not an in-place upgrade — it must be installed in its own directory.
> Jump to [Upgrading from ElkArte 1.x to 2.0](#upgrading-from-elkarte-1x-to-20).

---

**New Installation**

- [Minimum requirements](#minimum-installation-requirements)
- [Performance recommendations](#recommendations-for-the-best-performance)
- [Upload files](#upload-files)
- [Set file permissions](#set-file-permissions)
- [Create a database and user](#create-a-database-and-a-database-user)
- [Run the installer](#starting-the-installer)
- [The installed.lock file](#the-installedlock-file)
- [Finish up](#finishing-everything-up)
- [Upgrading from 1.x to 2.0](#upgrading-from-elkarte-1x-to-20)

---

## Minimum installation requirements

Not sure if your server qualifies? Run the installer anyway, it catches most problems automatically.

- **Web server:** Apache 2.4+ or Nginx 1.18+ (or any server with PHP support)
- **PHP 8.1+** with these `php.ini` settings:
  - `engine` -> **On**
  - `session.save_path` -> a valid, writable directory
  - `file_uploads` -> **On**
  - `upload_tmp_dir` -> a valid directory
- **Required PHP extensions:** `json`, `gd`, `curl`, `mysqli` OR `pgsql`, `fileinfo`, `exif`, `libxml`, `dom`
- **Database** (one of):
  - MySQL 5.7+
  - MariaDB 10.2+
  - PostgreSQL 9.5+
- **Database user privileges:** SELECT, INSERT, UPDATE, DELETE, ALTER, CREATE, DROP, TRUNCATE, INDEX
- **Storage:** ~30 MB server space (more for attachments, avatars, and cache); 2 MB database minimum

---

## Recommendations for the best performance

- **OS:** Linux or another Unix-based system
- **Web server:** Apache 2.4+ with `AcceptPathInfo` enabled, or Nginx 1.18+ with a suitable `try_files` rewrite rule
- **PHP 8.2+** with:
  - `memory_limit` ≥ 128M
  - `max_execution_time` and `max_input_time` ≥ 30
  - `post_max_size` / `upload_max_filesize` sized to your attachment needs
  - `session.use_trans_sid` -> **Off**
  - OPcache enabled (`ext-zend-opcache`)
- **Recommended extensions:**

  | Extension | Purpose |
    |---|---|
  | `ext-imagick` (ImageMagick 3.x+) | Superior image resizing and thumbnails, preferred over GD |
  | `ext-gd` (GD 2.3+) | Baseline image support; install even if ImageMagick is present (used for CAPTCHA and avatars) |
  | `ext-intl` | Improved Unicode and locale handling |
  | `ext-iconv` | Character set conversion |
  | `ext-mbstring` | Multibyte string functions |
  | `ext-zlib` | Gzip output compression |
  | `ext-apcu` | In-process data caching |
  | `ext-imap` | Email-to-post and notification processing |

- **Database:** MySQL 8.0+, MariaDB 10.6+, or PostgreSQL 14+

---

## Upload files

Download the latest release from **https://github.com/elkarte/Elkarte/releases**.

Upload all files to a new, URL-accessible directory on your server (e.g. `/public_html/forum`).

After uploading, confirm these directories are present:

```
sources/ElkArte/
themes/default/
install/
Addons/
```

> [!TIP]
> Some FTP clients silently skip files. If the installer reports missing components, check your transfer logs and re-upload as needed.

### Additional languages

1. Download language packs from **https://translations.elkarte.net/**
2. Install them with the systems Addon Manager after you have installed ElkArte.

Files will be installed under:

```
sources/ElkArte/Languages/AREA/[language].php
```

---

## Set file permissions

Use **CHMOD** to set permissions after uploading.

- Files: `644`, `664`, or `666`
- Directories: `755`, `775`, or `777`

The following paths must be **writable by the web server:**

```
/Addons
/attachments
/avatars
/avatars_user
/cache
/install
/packages
/smileys
/sources
/themes
db_last_error.txt
Settings.php
Settings_bak.php
```

---

## Create a database and a database user

Before running the installer, create a database and a dedicated database user. Most shared hosting control panels include a **MySQL Databases** or **Database Wizard** tool for this.

Required privileges: SELECT, INSERT, UPDATE, DELETE, ALTER, CREATE, DROP, TRUNCATE, INDEX

---

## Starting the installer

Just visit your forum's URL in a browser:

```
https://www.yourdomain.com/forum/
```

ElkArte detects the installation state automatically:

| Condition | Result |
|---|---|
| No `installed.lock`, no `Settings.php`, `install/` present | Redirected to `install/install.php` |
| No `installed.lock`, valid `Settings.php`, `install/` present | Redirected to `install/upgrade.php` |

No need to navigate to these scripts directly.

### Basic forum settings

| Setting | Description |
|---|---|
| **Forum Name** | Your forum's display name (default: *My Community*). Easily changed later. |
| **Forum URL** | Full URL to the forum root, without a trailing slash. |
| **Database Sessions** | More reliable than file-based sessions, especially on shared hosting. |

### Database settings

| Setting | Description |
|---|---|
| **Database type** | `mysqli` (MySQL/MariaDB) or `postgresql` |
| **Server name** | Usually `localhost` |
| **Username** | Your database user |
| **Password** | Your database password |
| **Database name** | The database you created |
| **Table prefix** | Default `elk_`, useful if multiple apps share one database |

### Administrator account

Enter a username, password, and email for the primary admin account. This account controls the **Administration Center**.

---

## The installed.lock file

After a successful install or upgrade, ElkArte creates **`installed.lock`** in the forum root. This file:

- **Blocks `install.php` and `upgrade.php` from running again**, preventing accidental reinstallation or data loss
- Records the installed version and timestamp (plain text, safe to inspect)
- **Must be deleted** before the upgrader can run, only do this when you intentionally want to upgrade

> [!TIP]
> Removing the `install/` directory entirely after installation is a best practice, but the lock file alone is enough to keep the installer from running.

---

## Finishing everything up

Once installation completes, remove the installer files, either using the built-in cleanup option or manually:

```
install/install.php
install/upgrade.php
install/install_2-0.php
install/upgrade_2-0.php
```

Removing the entire `install/` directory is the safest option and is recommended.

**That's it,  good luck with your new site!**
*ElkArte Forum Contributors*

---

---

# Upgrading from ElkArte 1.x to 2.0

> [!WARNING]
> ElkArte 2.0 is **not** an in-place upgrade from 1.x. The directory and namespace structure changed entirely, do not overwrite your 1.x files with 2.0.

Help is available at **https://www.elkarte.net/community/index.php**.

---

## Overview

- Back up your database and files 
- Install ElkArte 2.0 in a **new, separate directory**
- Restore the database to a new database, e.g. elkarte20 (optional, but **HIGHLY** recommended)
- Copy `Settings.php` from your 1.x root into the 2.0 root 
- Confirm the 2.0 installation can access your existing 1.x avatars and attachments directory (or copy them if needed)
- Delete any `installed.lock` from the 2.0 root 
- Run the upgrader by visiting the new forum URL, the system redirects to the upgrader automatically 
- Visit the new forum URL, make sure everything works, then update your web server to point to the new directory if needed 
- Clean up

---

## Back up your data

> [!CAUTION]
> Don't skip this. A full backup is your safety net if anything goes wrong.

**Using phpMyAdmin:** Select your forum database -> click **Export** -> follow the wizard.

**Using a hosting control panel:** Use your host's **Backup** or **Backup Wizard** tool to export the database.

Back up your files as well, at minimum the `attachments/`, `avatars_user/`, and `smileys/` directories.

---

## Upload ElkArte 2.0 to a new directory

Upload the 2.0 release package to a **new directory**, for example:

```
/var/www/elkarte2/
```

Do **not** upload into your existing 1.x directory (e.g. `/var/www/elkarte1/`).

Set file permissions as described in [Set file permissions](#set-file-permissions).

---

## Create a new database table for 2.0

Restore the database to a new database, e.g. elkarte20 (optional, but **HIGHLY** recommended)
This step is optional but strongly recommended. It allows you to test the upgrade process without risking your live data.

---

## Copy Settings.php

Copy **only** `Settings.php` from your 1.x root into the 2.0 root:

```
/var/www/elkarte1/Settings.php  ->  /var/www/elkarte2/Settings.php
```

The upgrader uses this file to connect to your existing database. No manual edits needed, paths are updated automatically during the upgrade.

---

## Avatars and attachments

User-uploaded files don't need to be moved if the 2.0 installation can already read and write to their current location.

For example, if `/var/www/elkarte2` can access `/var/www/elkarte1/attachments`, no copying is required, the existing paths in `Settings.php` will keep working.

If the 2.0 directory **can't** reach the old paths (different server, restricted permissions, or a reorganized document root), copy these directories manually:

```
/var/www/elkarte1/avatars_user/  ->  /var/www/elkarte2/avatars_user/
/var/www/elkarte1/attachments/   ->  /var/www/elkarte2/attachments/
/var/www/elkarte1/smileys/       ->  /var/www/elkarte2/smileys/
```

> [!NOTE]
> If your admin panel defines a custom attachment path (`attachmentUploadDir`) that points outside the forum root and remains accessible, no change is needed.

### Custom add-ons

> [!WARNING]
> 1.x add-ons are not compatible with 2.0. Do not copy the 1.x `Addons/` directory, install 2.0-compatible versions instead.

---

## Delete installed.lock

The 2.0 package uses an `installed.lock` file. The upgrader won't run while it's present.

Delete it from the 2.0 root:

```
/var/www/elkarte2/installed.lock  <- delete this
```

---

## Step 7, Run the upgrader

Visit your new forum URL in a browser:

```
https://www.yourdomain.com/forum2/
```

ElkArte detects `Settings.php` without a lock file and redirects to the upgrader automatically.

### Upgrade options

| Option | Description |
|---|---|
| **Backup database with prefix `backup_`** | Copies all tables before modification, recommended |
| **Maintenance mode** | Locks the forum during the upgrade, recommended for live sites |
| **Extra debug output** | Verbose logging, useful if the upgrade stalls or fails |

---

## Update your web server if needed

Once the upgrade completes, point your web server at the 2.0 directory.

- **Apache:** Update `DocumentRoot` in your virtual host configuration or `.htaccess`
- **Nginx:** Update the `root` directive in your server block

If `$boardurl` in `Settings.php` already points to the correct domain, only the server-side path needs updating.

---

## Clean up

Remove the upgrade scripts from the server:

```
install/upgrade.php
install/upgrade_1-0.php
install/upgrade_1-1.php
install/upgrade_2-0.php
```

Removing the entire `install/` directory is the safest option.

Then confirm:

- ✅ `installed.lock` exists in the 2.0 forum root. If it's missing, the upgrade didn't complete, check the upgrade log.
- ✅ Your site loads correctly from the new directory.
- ✅ Once verified, archive or remove the old 1.x directory.

**Good luck!**
*ElkArte Forum Contributors*
