# LOGCUTTER PULSE  

Laravel focused log viewer.  Provides a nicer way to view, filter and follow your log files.  

Attempts to highlight the start of the stack trace and also find the route, file and method where the issue is hiding.

For personal use so expect issues, bugs, general weirdness.



5. Add the GitHub repository as a Composer VCS source
```bash
composer config repositories.logcutter-logpulse vcs git@github.com:bn-mk/logcutter-pulse.git
```

6. Install the package
```bash
composer require --dev logcutter/logpulse:dev-main -W
```

7. Rebuild autoload and package discovery
```bash
composer dump-autoload
php artisan package:discover
```

8. Publish package assets/config (if your package supports publishing)
```bash
php artisan vendor:publish --tag=logpulse-config
php artisan vendor:publish --tag=logpulse-assets
```

9. Verify routes are registered
```bash
php artisan route:list | grep logpulse
```

10. Open LogPulse in browser and verify behavior
- Page loads correctly
- Filters apply
- Live updates run
- Route insight, blame target, and stack trace panels render

11. If you use strict CSP, ensure the package uses external JS only
- No inline script blocks in package views
- JS served from published/public asset path
