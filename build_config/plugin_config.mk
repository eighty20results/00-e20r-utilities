E20R_PLUGIN_NAME ?= 00-e20r-utilities
E20R_PLUGIN_BASE_FILE ?= class-loader.php

ifeq ($${E20R_DEPLOYMENT_SERVER},"")
E20R_DEPLOYMENT_SERVER ?= eighty20results.com
endif

WP_DEPENDENCIES ?= paid-memberships-pro woocommerce
E20R_DEPENDENCIES ?=

DOCKER_USER ?= eighty20results
DOCKER_ENV ?= Docker.app
DOCKER_IS_RUNNING := $(shell ps -ef | grep $(DOCKER_ENV) | wc -l | xargs)

COMPOSER_VERSION ?= 1.29.2
# COMPOSER_BIN := $(shell which composer)
COMPOSER_BIN := composer.phar
COMPOSER_DIR := inc

APACHE_RUN_USER ?= $(shell id -u)
# APACHE_RUN_GROUP ?= $(shell id -g)
APACHE_RUN_GROUP ?= $(shell id -u)

WP_VERSION ?= latest
DB_VERSION ?= latest
WP_IMAGE_VERSION ?= 1.0

PHP_CODE_PATHS := *.php src/*/*.php src/*/*/*.php
PHP_IGNORE_PATHS := $(COMPOSER_DIR)/*,node_modules/*,src/utilities/*
