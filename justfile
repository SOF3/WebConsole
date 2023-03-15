set shell := ["/usr/bin/env", "bash", "-e", "-c"]

help:
	just --list
fmt:
	cd plugin && vendor/bin/php-cs-fixer fix
	cd site && cargo fmt
phpstan:
	cd plugin && vendor/bin/phpstan analyze
phpstan-baseline:
	cd plugin && vendor/bin/phpstan analyze --generate-baseline
site:
	trunk serve --watch=site site/index.html
build:
	[[ -d local ]] || mkdir local ;\
	if [[ ! -f local/pharynx.phar ]] || [[ ! -f local/bootstrap-plugin-dev.php ]]; then \
		PHARYNX_TAG=$(curl -LH "Accept: application/json" https://github.com/SOF3/pharynx/releases/latest | jq -r .tag_name) ;\
		curl -Lolocal/pharynx.phar https://github.com/SOF3/pharynx/releases/download/${PHARYNX_TAG}/pharynx.phar ;\
		curl -Lolocal/bootstrap-plugin-dev.php https://github.com/SOF3/pharynx/releases/download/${PHARYNX_TAG}/bootstrap-plugin-dev.php ;\
		fi
	php -dphar.readonly=0 local/pharynx.phar -i plugin -p=local/WebConsole.phar
	cd local && PLUGIN_PATH=../plugin php -dphar.readonly=0 bootstrap-plugin-dev.php WebConsole.phar
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
