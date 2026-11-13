# Light PHP

Follow `AGENTS.md` in the project root before writing application code. These rules are the ones that are easy to get wrong:

- This is Core PHP. Use routes, controllers, and plain PHP views. Do not add Blade, Eloquent, or a migration builder unless asked.
- New pages reuse `views/layouts/app.php` and Tailwind classes. Register the route in `routes/web.php` yourself; `make:view` does not do that.
- Views call `DB::`, `request()`, and `esc()` with no import. Controllers and models call `\DB::`.
- Schema edits import `use App\Core\DBdelta`. It is not global.
- Escape untrusted HTML with `esc()`. Call `csrf_verify()` yourself on POST; the framework does not do it for you.
- Put new helper functions in `app/Helpers/helpers.php`. Do not redeclare core functions and do not edit `app/Core` for a normal feature.
