E20R_PLUGIN_NAME ?= 00-e20r-utilities
E20R_PLUGIN_BASE_FILE ?= %.php
WP_DEPENDENCIES ?= paid-memberships-pro
E20R_DEPENDENCIES ?=

DOCKER_USER ?= eighty20results
DOCKER_ENV ?= Docker.app
DOCKER_IS_RUNNING := $(shell ps -ef | grep $(DOCKER_ENV) | wc -l | xargs)
CONTAINER_ACCESS_TOKEN := $(shell [[ -f ../docker.hub.key ]] && cat ../docker.hub.key)
WP_IMAGE_VERSION ?= 1.0

COMPOSER_VERSION ?= 1.29.2
# COMPOSER_BIN := $(shell which composer)
COMPOSER_BIN := composer.phar
COMPOSER_DIR := inc

APACHE_RUN_USER ?= $(shell id -u)
# APACHE_RUN_GROUP ?= $(shell id -g)
APACHE_RUN_GROUP ?= $(shell id -u)

WP_VERSION ?= latest
DB_VERSION ?= latest
