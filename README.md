# AI Infos File Manager

**A tiny, single-file PHP file manager. You get a full file manager by uploading one file.**

There's no database, no Composer, no npm, and no build step. Upload `fm.php` to your server, open it in a browser, and you can browse, upload, edit, rename, download and delete files.

![PHP](https://img.shields.io/badge/PHP-7.0%2B-777BB4?logo=php&logoColor=white)
![Single file](https://img.shields.io/badge/single%20file-~400%20lines-2f6fed)
![Dependencies](https://img.shields.io/badge/dependencies-none-1f9d55)
![License](https://img.shields.io/badge/license-MIT-blue)

---

## ✨ Features

- 📁 **Browse** folders, with breadcrumbs and a live filter box
- ⬆️ **Upload** multiple files at once, or **drag & drop** them anywhere on the page
- 📝 **Edit** text and code files in the browser (Tab indents, **Ctrl/Cmd + S** saves, warns about unsaved changes)
- ➕ **Create** new files and folders
- ✏️ **Rename** files and folders
- 🗑️ **Delete** a single item, or several at once with checkboxes (folders are deleted with everything inside them)
- ⬇️ **Download** any file
- 🖼️ **Preview** images and PDFs in a new tab
- ℹ️ Shows file size, last-modified date, permissions, free disk space and the max upload size
- 🌗 Switches between light and dark themes automatically, and works on mobile

## 🔒 Security

The tool has full access to your files, so it is locked down by default:

| Protection | What it does |
|---|---|
| Password login | The manager **won't run** until you change the default password |
| Hashed passwords | Accepts a `password_hash()` hash as well as a plain password |
| Brute-force delay | Each wrong password adds a 2-second delay |
| Root jail | Every path is resolved with `realpath()` and must stay inside the root folder. `../` tricks and symlinks that point outside are blocked |
| CSRF tokens | Every action that changes something needs a valid token |
| Secure session | Session cookie is `HttpOnly` and `SameSite=Lax`, and is `Secure` on HTTPS |
| Self-protection | It won't delete or rename itself |
| Sandboxed previews | Previewed files are served with `Content-Security-Policy: sandbox` and `nosniff`, so an uploaded HTML or SVG file can't run scripts against your session |
| No indexing | Sends `noindex` headers so search engines skip it, and blocks being embedded in frames |

## 🚀 Quick start

1. **Download** `fm.php` from this repository.
2. **Set a password.** Open the file and edit the config block at the top:

   ```php
   $FM_PASSWORD = 'change-me';   // ← change this
   ```

3. **Upload** it to your server, ideally under a name that's hard to guess:

   ```
   public_html/fm-x7k2q.php
   ```

4. **Open it** in your browser: `https://yoursite.com/fm-x7k2q.php`

## ⚙️ Configuration

All settings are in the block at the top of `fm.php`:

```php
$FM_PASSWORD = 'change-me';        // plain text, or a password_hash() string
$FM_ROOT     = __DIR__;            // folder to manage
$FM_TITLE    = 'AI Infos File Manager'; // name shown in the header
$FM_EDIT_MAX = 2 * 1024 * 1024;    // largest file (bytes) you can edit in the browser
```

### Use a hashed password (recommended)

```bash
php -r "echo password_hash('your-strong-password', PASSWORD_DEFAULT);"
```

Paste the output into the config:

```php
$FM_PASSWORD = '$2y$10$abc...';
```

### Manage the whole website

`$FM_ROOT` defaults to the folder that `fm.php` is in. To manage your entire web root:

```php
$FM_ROOT = $_SERVER['DOCUMENT_ROOT'];
```

Or point it at any absolute path that PHP can read:

```php
$FM_ROOT = '/home/user/htdocs/example.com';
```

## 📋 Requirements

- PHP **7.0+** (7.3+ recommended for full cookie security; tested on PHP 8.3)
- PHP sessions enabled (they are by default)
- Write permission on the folders you want to change

Works on Apache, Nginx + PHP-FPM, LiteSpeed, CloudPanel, cPanel, Plesk, shared hosting, and PHP's built-in server:

```bash
php -S localhost:8000
# then open http://localhost:8000/fm.php
```

## 📤 Upload limits

The maximum upload size is set by PHP, not by this tool. It's shown at the bottom of the file list. To raise it, edit `php.ini` (or use your hosting panel):

```ini
upload_max_filesize = 256M
post_max_size = 256M
```

## ⚠️ Best practices

- Use a **strong, unique password**, preferably stored as a hash.
- **Rename the file** to something random. Don't leave it at `fm.php`.
- **Use HTTPS**, so your password and session aren't sent in plain text.
- **Delete it when you're done**, or keep it outside public paths when you don't need it.
- Optionally restrict access by IP at the web-server level:

  ```apache
  # Apache (.htaccess)
  <Files "fm-x7k2q.php">
      Require ip 203.0.113.10
  </Files>
  ```

  ```nginx
  # Nginx
  location = /fm-x7k2q.php {
      allow 203.0.113.10;
      deny all;
      include fastcgi_params;
      fastcgi_pass unix:/run/php/php-fpm.sock;
  }
  ```

> **Note:** Deletions are permanent. There is no recycle bin.

## 🗺️ Roadmap

- [ ] Download a folder as a ZIP
- [ ] Extract ZIP files after upload
- [ ] Change permissions (chmod)
- [ ] Copy / move between folders
- [ ] Syntax highlighting in the editor

Pull requests are welcome.

## 🤝 Contributing

1. Fork the repo
2. Create a branch: `git checkout -b feature/zip-download`
3. Commit your changes: `git commit -m "Add ZIP download"`
4. Push and open a pull request

Please keep it **one file with zero dependencies**. That's the whole point.

## 👤 Author

**Md. Iqbal Mahmud**, AI Automation Engineer & Full Stack Developer
🌐 [iqbalmahmud.com](https://iqbalmahmud.com)

## 📄 License

[MIT](LICENSE). Free to use, modify and share.
