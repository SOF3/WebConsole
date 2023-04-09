set shell := ["/usr/bin/env", "bash", "-e", "-c"]

help:
	just --list
fmt:
	cd plugin && vendor/bin/php-cs-fixer fix
	cd site && cargo fmt -- -l
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

tls hostname ip_or_dns="DNS":
	openssl req -x509 -newkey rsa:4096 \
		-nodes \
		-sha512 \
		-keyout local/key.pem \
		-out local/crt.pem \
		-days 3650 \
		-subj "/CN={{hostname}}" \
		-addext "subjectAltName={{ip_or_dns}}:{{hostname}}"
	cat local/crt.pem local/key.pem >local/certs.pem

passwd:
	if [[ ! -f local/passwd.txt ]]; then \
		docker run -it --name webconsole_passwd httpd:2 htpasswd -c /webconsole_passwd.txt admin; \
		docker cp webconsole_passwd:/webconsole_passwd.txt local/passwd.txt; \
		docker rm webconsole_passwd; \
	fi

nginx: passwd
	docker rm -f webconsole_nginx 2>/dev/null || true
	docker create -it --rm \
		--name webconsole_nginx \
		--network host \
		nginx
	docker cp ./local webconsole_nginx:/etc/webconsole
	docker cp ./nginx.conf webconsole_nginx:/etc/nginx/conf.d/default.conf
	docker start webconsole_nginx
	docker exec -u root webconsole_nginx chown -R nginx /etc/webconsole /etc/nginx/conf.d
