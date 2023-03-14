set shell := ["/usr/bin/env", "bash", "-e", "-c"]

help:
	just --list
fmt:
	cd plugin && vendor/bin/php-cs-fixer fix
	cd site && cargo fmt
site:
	trunk serve --watch=site site/index.html
build:
	[[ -d local ]] || mkdir local ;\
	if [[ ! -f local/pharynx.phar ]] || [[ ! -f local/bootstrap-plugin-dev.php ]]; then \
		PHARYNX_TAG=$(curl -LH "Accept: application/json" https://github.com/SOF3/pharynx/releases/latest | jq -r .tag_name) ;\
		curl -Lolocal/pharynx.phar https://github.com/SOF3/pharynx/releases/download/${PHARYNX_TAG}/pharynx.phar ;\
		curl -Lolocal/bootstrap-plugin-dev.php https://github.com/SOF3/pharynx/releases/download/${PHARYNX_TAG}/bootstrap-plugin-dev.php ;\
		fi
	php -dphar.readonly=0 local/pharynx.phar -i plugin -p=local/plugin.phar
	cd local && PLUGIN_PATH=../plugin php -dphar.readonly=0 bootstrap-plugin-dev.php plugin.phar
pm: build
	docker rm -f webconsole_pm 2>/dev/null || true
	docker create --name webconsole_pm \
		--rm -t \
		--network=host \
		--entrypoint=start-pocketmine \
		pmmp/pocketmine-mp:4
	docker cp local/plugin.phar webconsole_pm:/plugins/plugin.phar
	docker start webconsole_pm
