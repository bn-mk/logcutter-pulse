# LOGCUTTER PULSE  

Laravel focused log viewer.  Provides a nicer way to view, filter and follow your log files.  

Attempts to highlight the start of the stack trace and also find the route, file and method where the issue is hiding.

For personal use so expect issues, bugs, general weirdness.


<img width="954" height="861" alt="Screenshot 2026-04-08 at 14 07 04" src="https://github.com/user-attachments/assets/226df1c8-8b3a-474a-badb-503dcd792091" />


1. Add the GitHub repository as a Composer VCS source
```bash
composer config repositories.logcutter-logpulse vcs git@github.com:bn-mk/logcutter-pulse.git
```

2. Install the package
```bash
composer require --dev logcutter/logpulse:dev-main -W
```

3. Rebuild autoload and package discovery
```bash
composer dump-autoload
php artisan package:discover
```

4. Publish package assets/config (if your package supports publishing)
```bash
php artisan vendor:publish --tag=logpulse-config
php artisan vendor:publish --tag=logpulse-assets
```

5. Verify routes are registered
```bash
php artisan route:list | grep logpulse
```

6. Open LogPulse in browser and verify behavior `/logpulse`

