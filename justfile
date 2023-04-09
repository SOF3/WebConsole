set shell := ["/usr/bin/env", "bash", "-e", "-c"]

help:
	just --list
composer +args:
	cd plugin/api && composer {{args}}
	cd plugin/lib && composer {{args}}
	cd plugin/internal && composer {{args}}
fmt:
	cd plugin/api && vendor/bin/php-cs-fixer fix
	cd plugin/lib && vendor/bin/php-cs-fixer fix
	cd plugin/internal && vendor/bin/php-cs-fixer fix
	cd site && cargo fmt -- -l
phpstan:
	cd plugin/api && vendor/bin/phpstan analyze
	cd plugin/lib && vendor/bin/phpstan analyze
	cd plugin/internal && vendor/bin/phpstan analyze
phpstan-baseline:
	cd plugin/api && vendor/bin/phpstan analyze --generate-baseline
	cd plugin/lib && vendor/bin/phpstan analyze --generate-baseline
	cd plugin/internal && vendor/bin/phpstan analyze --generate-baseline
site:
	trunk serve --watch=site site/index.html
build:
	[[ -d local ]] || mkdir local
	cd plugin/internal && php -dphar.readonly=0 vendor/bin/pharynx \
		-f ../plugin.yml \
		-f ../resources/ \
		-s ../api/src/ \
		-s src/ \
		-c=. \
		-o ../../local/WebConsole \
		-p=../../local/WebConsole.phar
pm: build
	[[ -f local/FakePlayer.phar ]] || wget -O local/FakePlayer.phar https://poggit.pmmp.io/r/201617
	docker rm -f webconsole_pm 2>/dev/null || true
	docker create --name webconsole_pm \
		-t \
		--network=host \
		--entrypoint=start-pocketmine \
		pmmp/pocketmine-mp:4
	docker cp local/FakePlayer.phar webconsole_pm:/plugins/FakePlayer.phar
	docker cp local/WebConsole.phar webconsole_pm:/plugins/WebConsole.phar
	docker start webconsole_pm
