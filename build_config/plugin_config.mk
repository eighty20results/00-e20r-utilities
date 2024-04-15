E20R_PLUGIN_NAME ?= default-plugin-name # We need to/should use a shell environment variable to set this value!
E20R_PLUGIN_BASE_FILE ?= class-loader.php
E20R_DEPLOYMENT_SERVER ?= eighty20results.com
LOCAL_NETWORK_IF ?= en0

ifeq ($(E20R_DEPLOYMENT_SERVER), )
E20R_DEPLOYMENT_SERVER ?= eighty20results.com
endif

ifeq ($(COMPOSER_FILE), )
COMPOSER_FILE ?= composer-php8.json
endif

ifneq ($(LOCAL_NETWORK_IF), )
LOCAL_NETWORK_STATUS ?= $(shell ifconfig $(LOCAL_NETWORK_IF) | awk '/status:/ { print $$2 }')
$(info Setting local network interface status for $(LOCAL_NETWORK_IF): '$(LOCAL_NETWORK_STATUS)')
endif

ifeq ($(LOCAL_NETWORK_IF), )
LOCAL_NETWORK_STATUS ?=
endif

WP_DEPENDENCIES ?= paid-memberships-pro woocommerce
E20R_DEPENDENCIES ?=

DOCKER_HUB_USER ?= eighty20results
DOCKER_ENV ?= Docker.app
DOCKER_IS_RUNNING := $(shell ps -ef | grep $(DOCKER_ENV) | wc -l | xargs)

COMPOSER_VERSION ?= 1.29.2
# COMPOSER_BIN := $(shell which composer)
COMPOSER_BIN := composer.phar
COMPOSER_DIR := inc
E20R_COMPOSER_HASH := 'dac665fdc30fdd8ec78b38b9800061b4150413ff2e3b6f88543c636f7cd84f6db9189d43a81e5503cda447da73c7e5b6'

APACHE_RUN_USER ?= $(shell id -u)
# APACHE_RUN_GROUP ?= $(shell id -g)
APACHE_RUN_GROUP ?= $(shell id -u)

WP_VERSION ?= latest
DB_VERSION ?= latest
WP_IMAGE_VERSION ?= 1.0

PHP_CODE_PATHS := *.php src/E20R/*/*.php src/E20R/*/*/*.php
PHP_IGNORE_PATHS := $(COMPOSER_DIR)/*,node_modules/*,src/utilities/*
E20R_MAIN_BRANCH_NAME := main
