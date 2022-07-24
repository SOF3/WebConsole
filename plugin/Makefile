PHP := php
PM_VERSION := 4
IMAGE := pmmp/pocketmine-mp:$(PM_VERSION)
CONTAINER_NAME := webconsole-pocketmine

default: run-server

target/pharynx.phar:
	wget -O $@ https://github.com/SOF3/pharynx/releases/download/0.2.1/pharynx.phar
target/await-generator.phar:
	wget -O $@ https://poggit.pmmp.io/v.dl/SOF3/await-generator/await-generator/^3.4.2
target/WebConsole.phar: target/pharynx.phar target/await-generator.phar $(shell find plugin.yml resources src -type f)
	test ! -f $@ || rm $@
	$(PHP) -dphar.readonly=0 target/pharynx.phar -i . -p=$@
	$(PHP) target/await-generator.phar $@
target/config.yml: resources/config.yml
	mkdir -p $$(dirname $@)
	cp resources/config.yml $@
	$(PHP) -r '$$data = yaml_parse_file("resources/config.yml"); $$data["api-server-address"] = "0.0.0.0"; yaml_emit_file($$argv[1], $$data);' $@
run-server: target/WebConsole.phar target/config.yml target/mkdir
	docker rm $(CONTAINER_NAME) || true
	docker create --name $(CONTAINER_NAME) \
		-u root \
		-p 127.0.0.1:14875:14875 \
		$(IMAGE) \
		start-pocketmine --debug.level=2
	docker cp target/WebConsole.phar $(CONTAINER_NAME):/plugins/WebConsole.phar
	docker cp target/mkdir $(CONTAINER_NAME):/data/plugin_data
	docker cp target/mkdir $(CONTAINER_NAME):/data/plugin_data/WebConsole
	docker cp target/config.yml $(CONTAINER_NAME):/data/plugin_data/WebConsole/config.yml
	test ! -f target/pocketmine.yml || docker cp target/pocketmine.yml $(CONTAINER_NAME):/data/pocketmine.yml
	docker start -ia $(CONTAINER_NAME)

# An empty directory used for mkdir in empty container
target/mkdir:
	mkdir $@

.PHONY: run-server
