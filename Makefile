SHELL := /bin/bash
BASE_PATH := $(PWD)
FIND := $(shell which find)
# PROJECT := $(shell basename ${PWD}) # This is the default as long as the plugin name matches
PROJECT := 00-e20r-utilities

# Settings for docker-compose
DC_CONFIG_FILE ?= $(PWD)/.circleci/docker/docker-compose.yml
DC_ENV_FILE ?= $(PWD)/.circleci/docker/.env


.PHONY: \
	clean \
	start-wordpress \
	stop-wordpress \
	restart-wordpress \
	shell \
	lint-test \
	phpcs-test \
	unit-test \
	acceptance-test \
	build-test

clean:
#	$(FIND) $(BASE_PATH)/inc -path composer -prune \
#		-path yahnis-elsts -prune \
#		-path 10quality -prune \
#		-type d -print
#		-exec rm -rf {} \;

start-wordpress:
	@docker-compose -p $(PROJECT) --env-file $(DC_ENV_FILE) --file $(DC_CONFIG_FILE) up --detach


stop-wordpress:
	@docker-compose -p $(PROJECT) --env-file $(DC_ENV_FILE) --file $(DC_CONFIG_FILE) down

restart_wordpress: stop_wordpress start_wordpress

shell:
	@docker-compose -p $(PROJECT) --env-file $(DC_ENV_FILE) --file $(DC_CONFIG_FILE) exec wordpress /bin/bash

lint-test:
	# TODO: Configure the linter test

phpcs-test:
	@docker-compose -p $(PROJECT) --env-file $(DC_ENV_FILE) --file $(DC_CONFIG_FILE) \
    	exec -T -w /var/www/html/wp-content/plugins/$(PROJECT)/ \
    	wordpress inc/bin/phpcs --report=full --colors -p --standard=WordPress-Extra --ignore=*/inc/*,*/node_modules/* --extensions=php *.php src/*/*.php
unit-test:
	@docker-compose -p $(PROJECT) --env-file $(DC_ENV_FILE) --file $(DC_CONFIG_FILE) \
	exec -T -w /var/www/html/wp-content/plugins/$(PROJECT)/ \
	wordpress inc/bin/codecept run wpunit

acceptance-test:
	@docker-compose $(PROJECT) --env-file $(DC_ENV_FILE) --file $(DC_CONFIG_FILE) \
	 exec -T -w /var/www/html/wp-content/plugins/${PROJECT}/ \
	 wordpress inc/bin/codecept run acceptance

build-test:
	@docker-compose $(PROJECT) --env-file $(DC_ENV_FILE) --file $(DC_CONFIG_FILE) \
	 exec -T -w /var/www/html/wp-content/plugins/${PROJECT}/ \
	 wordpress inc/bin/codecept build
