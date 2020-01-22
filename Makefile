
composer-install:
	@test ! -f vendor/autoload.php && composer install --no-dev || true

composer-install-dev:
	@test ! -d vendor/phpunit/phpunit && composer install || true

composer-update:
	composer update --no-dev

composer-update-dev:
	composer update

dev-doc-api: composer-install-dev
	@test -d doc/API && rm -Rf doc/API || true
	@mkdir doc/API
	@cp README.md doc/API/readme.md
	@php vendor/ceus-media/doc-creator/doc.php --config-file=doc.xml
	@cp doc/.theme/images/icon-database-black.png doc/API/images/logo/
	@cp doc/.theme/css/control.css doc/API/css/

dev-doc-coverage: composer-install-dev
	@rm -Rf doc/Coverage
	@phpunit --coverage-html doc/Coverage

dev-test-units: composer-install-dev
#	@phpunit --log-json doc/Test/logfile.json --log-tap doc/Test/logfile.tap --testdox-html doc/Test/testdox.html --testdox-text doc/Test/testdox.txt
	@./vendor/bin/phpunit

dev-test-syntax:
	@find src -type f -print0 | xargs -0 -n1 xargs php -l
