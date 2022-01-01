# SMF Build Tools repository

This repository contains [continuous integration](https://codeship.com/continuous-integration-essentials) scripts for SMF.

All scripts in this repository are under BSD 3-clause license, unless specified otherwise.

## Installation

Requires PHP 7.1+, `git`, and `composer`.

### Typical Installation (via Git)

Clone the project into the `BuildTools/` directory inside your document root and install its dependencies:

```bash
cd /path/to/dir
git clone https://github.com/SimpleMachines/BuildTools.git BuildTools
cd BuildTools
composer install
```

### Installing as a composer dependency

Add the following to `composer.json`:

```json
{
  "repositories": [
    {
      "url": "https://github.com/SimpleMachines/BuildTools.git",
      "type": "vcs"
    }
  ],
  "minimum-stability": "dev",
  "require-dev": {
    "simplemachines/build-tools": "dev-master"
  }
}
```

Now you can install it:

```bash
composer install
```

## CI

### Travis CI

      script:
       - php check-signed-off.php
       - php check-version.php
       - php check-smf-langauges.php
       - php check-eof.php
       - php check-smf-license.php

### GitHub Action

```yaml
    - name: Checking for sign off (GPG also accepted)
      run: php ./other/check-signed-off.php

    - name: Checking file integrity
      run: |
        php check-eof.php
        php check-smf-license.php
        php check-smf-languages.php
        php check-version.php
```

## Lint PHP files

### Travis CI

      script:
       - vendor/bin/phplint . --exclude=vendor -w

### GitHub Action

```yaml
    - name: Lint PHP files
      run: vendor/bin/phplint . --exclude=vendor -w
```

## How to contribute:
* fork the repository. If you are not used to Github, please check out [fork a repository](https://help.github.com/fork-a-repo).
* branch your repository, to commit the desired changes.
* sign-off your commits, to acknowledge your submission under the license of the project.
  * Please see the [Developer's Certificate of Origin](https://github.com/SimpleMachines/buildTools/blob/master/DCO.txt) in the repository:
by signing off your contributions, you acknowledge that you can and do license your submissions under the license of the project.
  * It is enough to include in your commit comment "Signed-off by: " followed by your name and email address (for example: `Signed-off-by: Angelina Belle <angelinabelle1@hotmail.com>`)
  * an easy way to do so, is to define an alias for the git commit command, which includes -s switch (reference: [How to create Git aliases](https://git.wiki.kernel.org/index.php/Aliases))
* send a pull request to us.
