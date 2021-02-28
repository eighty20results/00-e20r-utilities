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
	start_wordpress \
	stop_wordpress \
	restart_wordpress \
	access

clean:
#	$(FIND) $(BASE_PATH)/inc -path composer -prune \
#		-path yahnis-elsts -prune \
#		-path 10quality -prune \
#		-type d -print
#		-exec rm -rf {} \;

start_wordpress:
	@docker-compose -p $(PROJECT) --env-file $(DC_ENV_FILE) --file $(DC_CONFIG_FILE) up --detach
#	@sleep 10


stop_wordpress:
	docker-compose -p $(PROJECT) --env-file $(DC_ENV_FILE) --file $(DC_CONFIG_FILE) down

restart_wordpress: stop_wordpress start_wordpress

access:
	docker-compose -p $(PROJECT) --env-file $(DC_ENV_FILE) --file $(DC_CONFIG_FILE) exec wordpress /bin/bash
